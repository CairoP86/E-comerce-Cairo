<?php

namespace Tests\Feature;

use App\Availability\Availability;
use App\Availability\AvailabilityState;
use App\Availability\PreferredOfferAvailability;
use App\Contracts\ProductAvailability;
use App\Models\Product;
use App\Models\ProductCommercialSetting;
use App\Models\StockHold;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AvailabilityResolutionTest extends TestCase
{
    use RefreshDatabase;

    private const TTL = 60;

    protected function setUp(): void
    {
        parent::setUp();
        config(['commerce.availability.ttl_minutes' => self::TTL]);
        $this->freezeSecond();
    }

    private function offer(array $attributes = [], bool $supplierActive = true, string $source = 'manual'): SupplierProduct
    {
        $product = Product::factory()->create(['status' => 'published']);
        $supplier = Supplier::create(['code' => 'qa-'.Str::lower(Str::random(10)), 'name' => 'QA', 'active' => $supplierActive]);
        $offer = new SupplierProduct(['supplier_id' => $supplier->id, 'supplier_sku' => 'QA-'.Str::random(8), 'currency' => 'CRC', 'stock' => 5, 'availability' => 'available', 'observed_at' => now()->subMinutes(10), 'active' => true, ...$attributes]);
        $offer->product_id = $product->id;
        $offer->source = $source;
        $offer->save();
        $setting = new ProductCommercialSetting;
        $setting->product_id = $product->id;
        $setting->preferred_offer_id = $offer->id;
        $setting->save();

        return $offer;
    }

    private function resolve(int $productId, ?string $holder = null): Availability
    {
        return app(ProductAvailability::class)->forProducts([$productId], $holder)[$productId];
    }

    private function visibleInSql(int $productId): bool
    {
        return Product::whereKey($productId)->whereExists(fn ($query) => PreferredOfferAvailability::constrainVisible($query, PreferredOfferAvailability::now(), self::TTL))->exists();
    }

    private function hold(SupplierProduct $offer, int $quantity, ?string $holder, int $minutesLeft = 30): void
    {
        (new StockHold)->forceFill(['product_id' => $offer->product_id, 'supplier_product_id' => $offer->id, 'holder' => $holder, 'quantity' => $quantity, 'expires_at' => now()->addMinutes($minutesLeft)])->save();
    }

    public function test_state_matrix_and_sql_visibility_agree(): void
    {
        $cases = [
            'available with stock' => [[], true, AvailabilityState::Available, 5],
            'available without stock (D1)' => [['stock' => null], true, AvailabilityState::Unknown, null],
            'available with zero stock' => [['stock' => 0], true, AvailabilityState::Unavailable, 0],
            'unavailable without stock (D2)' => [['availability' => 'unavailable', 'stock' => null], true, AvailabilityState::Unavailable, 0],
            'unavailable with zero stock' => [['availability' => 'unavailable', 'stock' => 0], true, AvailabilityState::Unavailable, 0],
            'unknown' => [['availability' => 'unknown', 'stock' => null], true, AvailabilityState::Unknown, null],
            'inactive offer (D4)' => [['active' => false], true, AvailabilityState::Unknown, null],
            'inactive supplier (D4)' => [[], false, AvailabilityState::Unknown, null],
            'future observation' => [['observed_at' => now()->addMinute()], true, AvailabilityState::Unknown, null],
            'stale' => [['observed_at' => now()->subMinutes(self::TTL + 1)], true, AvailabilityState::Stale, null],
        ];
        foreach ($cases as $name => [$attributes, $supplierActive, $state, $quantity]) {
            $offer = $this->offer($attributes, $supplierActive);
            $result = $this->resolve($offer->product_id);
            $this->assertSame($state, $result->state, $name);
            $this->assertSame($quantity, $result->quantity, $name);
            $this->assertSame($state->isPubliclyVisible(), $this->visibleInSql($offer->product_id), $name.' (SQL)');
        }
    }

    public function test_ttl_boundary_is_exclusive(): void
    {
        $fresh = $this->offer(['observed_at' => now()->subMinutes(self::TTL)->addSecond()]);
        $expired = $this->offer(['observed_at' => now()->subMinutes(self::TTL)]);
        $this->assertSame(AvailabilityState::Available, $this->resolve($fresh->product_id)->state);
        $this->assertTrue($this->visibleInSql($fresh->product_id));
        $this->assertSame(AvailabilityState::Stale, $this->resolve($expired->product_id)->state);
        $this->assertFalse($this->visibleInSql($expired->product_id));
    }

    public function test_ttl_applies_to_manual_and_supplier_data_alike(): void
    {
        foreach (['manual', 'eurocomp'] as $source) {
            $offer = $this->offer(['observed_at' => now()->subMinutes(self::TTL + 5)], true, $source);
            $result = $this->resolve($offer->product_id);
            $this->assertSame(AvailabilityState::Stale, $result->state, $source);
            $this->assertSame($source, $result->source);
        }
    }

    public function test_missing_or_foreign_preferred_offer_is_unknown(): void
    {
        $withoutOffer = Product::factory()->create(['status' => 'published']);
        $this->assertSame(AvailabilityState::Unknown, $this->resolve($withoutOffer->id)->state);
        $this->assertFalse($this->visibleInSql($withoutOffer->id));
        $foreign = $this->offer();
        $other = Product::factory()->create(['status' => 'published']);
        $setting = new ProductCommercialSetting;
        $setting->product_id = $other->id;
        $setting->preferred_offer_id = $foreign->id;
        $setting->save();
        $this->assertSame(AvailabilityState::Unknown, $this->resolve($other->id)->state);
        $this->assertFalse($this->visibleInSql($other->id));
    }

    public function test_active_holds_of_other_carts_reduce_the_quantity_seen(): void
    {
        $offer = $this->offer(['stock' => 5]);
        $this->hold($offer, 2, str_repeat('a', 64));
        $this->hold($offer, 1, null);
        $this->hold($offer, 4, str_repeat('c', 64), -1);
        $this->assertSame(2, $this->resolve($offer->product_id)->quantity);
        $this->assertSame(4, $this->resolve($offer->product_id, str_repeat('a', 64))->quantity);
        $this->hold($offer, 2, str_repeat('d', 64));
        $result = $this->resolve($offer->product_id);
        $this->assertSame(AvailabilityState::Unavailable, $result->state);
        $this->assertSame(0, $result->quantity);
        $this->assertTrue($this->visibleInSql($offer->product_id));
    }

    public function test_public_projection_exposes_only_state_and_quantity(): void
    {
        $this->assertSame(['state' => 'available', 'quantity' => 3], $this->resolve($this->offer(['stock' => 3])->product_id)->toPublic());
        $this->assertSame(['state' => 'unavailable', 'quantity' => 0], $this->resolve($this->offer(['availability' => 'unavailable', 'stock' => 0])->product_id)->toPublic());
    }

    public function test_sellable_factory_state_creates_a_fresh_preferred_offer(): void
    {
        $product = Product::factory()->sellable(7)->create(['status' => 'published']);
        $result = $this->resolve($product->id);
        $this->assertSame(AvailabilityState::Available, $result->state);
        $this->assertSame(7, $result->quantity);
        $this->assertSame('manual', $result->source);
    }
}
