<?php

namespace Tests\Feature;

use App\Delivery\DeliveryQuoter;
use App\Delivery\DeliveryUnavailable;
use App\Delivery\DeliveryZone;
use App\Models\DeliveryRate;
use App\Models\DeliveryRateSet;
use Database\Seeders\DeliveryZonesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryQuoterTest extends TestCase
{
    use RefreshDatabase;

    private function quoter(): DeliveryQuoter
    {
        return app(DeliveryQuoter::class);
    }

    private function seedRates(): void
    {
        $this->seed(DeliveryZonesSeeder::class);
    }

    public function test_gam_charges_the_flat_fee_below_the_threshold(): void
    {
        $this->seedRates();
        $quote = $this->quoter()->quote('101', 8999999, 'CRC');
        $this->assertSame(DeliveryZone::Gam, $quote->zone);
        $this->assertSame(350000, $quote->amountMinor);
        $this->assertFalse($quote->free);
        $this->assertSame(9000000, $quote->freeFromMinor);
        $this->assertSame(1, $quote->missingForFreeMinor);
        $this->assertSame('CRC', $quote->currency);
    }

    public function test_gam_is_free_exactly_at_the_threshold_and_above(): void
    {
        $this->seedRates();
        foreach ([9000000, 9000001, 50000000] as $subtotal) {
            $quote = $this->quoter()->quote('102', $subtotal, 'CRC');
            $this->assertSame(0, $quote->amountMinor, 'subtotal '.$subtotal);
            $this->assertTrue($quote->free);
            $this->assertNull($quote->missingForFreeMinor);
        }
    }

    public function test_guanacaste_norte_is_always_free_even_for_a_tiny_order(): void
    {
        $this->seedRates();
        foreach (['501', '504', '506', '510'] as $canton) {
            $quote = $this->quoter()->quote($canton, 1, 'CRC');
            $this->assertSame(DeliveryZone::GuanacasteNorte, $quote->zone, $canton);
            $this->assertSame(0, $quote->amountMinor);
            $this->assertTrue($quote->free);
            $this->assertNull($quote->freeFromMinor);
            $this->assertNull($quote->missingForFreeMinor);
        }
    }

    public function test_the_rest_of_the_country_uses_its_own_fee_and_threshold(): void
    {
        $this->seedRates();
        $below = $this->quoter()->quote('706', 10999999, 'CRC');
        $this->assertSame(DeliveryZone::Rest, $below->zone);
        $this->assertSame(450000, $below->amountMinor);
        $this->assertSame(1, $below->missingForFreeMinor);
        $this->assertSame(0, $this->quoter()->quote('706', 11000000, 'CRC')->amountMinor);
        // Guanacaste outside the four approved cantons is not free.
        $this->assertSame(450000, $this->quoter()->quote('502', 100, 'CRC')->amountMinor);
    }

    public function test_an_unknown_canton_blocks_instead_of_guessing(): void
    {
        $this->seedRates();
        $this->expectException(DeliveryUnavailable::class);
        $this->quoter()->quote('999', 100000, 'CRC');
    }

    public function test_only_colones_are_quoted(): void
    {
        $this->seedRates();
        $this->expectException(DeliveryUnavailable::class);
        $this->expectExceptionMessage('colones');
        $this->quoter()->quote('101', 100000, 'USD');
    }

    public function test_without_configured_rates_it_blocks(): void
    {
        $this->expectException(DeliveryUnavailable::class);
        $this->quoter()->quote('101', 100000, 'CRC');
    }

    public function test_the_newest_effective_rate_set_wins(): void
    {
        $this->seedRates();
        $newer = DeliveryRateSet::create(['version' => 'v2-test', 'effective_from' => now()->subMinute(), 'notes' => 'QA']);
        DeliveryRate::create(['delivery_rate_set_id' => $newer->id, 'zone' => DeliveryZone::Gam, 'flat_minor' => 500000, 'free_from_minor' => null, 'currency' => 'CRC']);
        $quote = $this->quoter()->quote('101', 100000, 'CRC');
        $this->assertSame(500000, $quote->amountMinor);
        $this->assertSame($newer->id, $quote->rateSetId);
        $this->assertSame('v2-test', $quote->rateSetVersion);
    }

    public function test_a_future_rate_set_is_ignored_until_it_starts(): void
    {
        $this->seedRates();
        $future = DeliveryRateSet::create(['version' => 'v3-test', 'effective_from' => now()->addDay(), 'notes' => 'QA']);
        DeliveryRate::create(['delivery_rate_set_id' => $future->id, 'zone' => DeliveryZone::Gam, 'flat_minor' => 999999, 'free_from_minor' => null, 'currency' => 'CRC']);
        $this->assertSame(350000, $this->quoter()->quote('101', 100000, 'CRC')->amountMinor);
        $this->assertSame(DeliveryZonesSeeder::VERSION, $this->quoter()->activeSet()->version);
    }

    public function test_the_public_projection_exposes_only_buyer_facing_fields(): void
    {
        $this->seedRates();
        $public = $this->quoter()->quote('101', 100000, 'CRC')->toPublic();
        $this->assertSame(['zone', 'zone_label', 'amount_minor', 'free', 'free_from_minor', 'missing_for_free_minor', 'currency'], array_keys($public));
        $this->assertSame('Gran Área Metropolitana', $public['zone_label']);
    }
}
