<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Touching "3 vencidas" on the product list shows exactly those three. */
class ProductFreshnessFilterTest extends TestCase
{
    use RefreshDatabase;

    private array $p = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        $ttl = config('commerce.availability.ttl_minutes');
        $published = ['status' => 'published', 'is_demo' => false];
        $expired = ['observed_at' => now()->subMinutes($ttl + 5)];
        $this->p['vencida-1'] = Product::factory()->sellable(1, $expired)->create(['name' => 'Vencida uno', ...$published]);
        $this->p['vencida-2'] = Product::factory()->sellable(1, $expired)->create(['name' => 'Vencida dos', ...$published]);
        $this->p['por-vencer'] = Product::factory()->sellable(1, ['observed_at' => now()->subMinutes((int) ($ttl * 0.9))])->create(['name' => 'Por vencer', ...$published]);
        $this->p['sin-oferta'] = Product::factory()->create(['name' => 'Sin oferta', ...$published]);
        $this->p['vigente'] = Product::factory()->sellable()->create(['name' => 'Vigente', ...$published]);
        // Expired too, but not published or only a demonstration: the summary does not count them either.
        $this->p['borrador'] = Product::factory()->sellable(1, $expired)->create(['name' => 'Borrador vencido', 'status' => 'draft', 'is_demo' => false]);
        $this->p['demo'] = Product::factory()->sellable(1, $expired)->create(['name' => 'Demo vencida', 'status' => 'published', 'is_demo' => true]);
    }

    private function names(string $query): array
    {
        return collect($this->get('/admin/catalog/products?'.$query)->assertOk()->viewData('page')['props']['products']['data'])->pluck('name')->sort()->values()->all();
    }

    public function test_each_level_lists_exactly_the_products_the_summary_counts(): void
    {
        $this->assertSame(['Vencida dos', 'Vencida uno'], $this->names('vigencia=vencidas'));
        $this->assertSame(['Por vencer'], $this->names('vigencia=por-vencer'));
        $this->assertSame(['Sin oferta'], $this->names('vigencia=sin-dato'));

        $summary = $this->get('/admin/catalog/products')->viewData('page')['props']['freshnessSummary'];
        $this->assertSame(['expired' => 2, 'expiring' => 1, 'invalid' => 1], $summary);
    }

    public function test_the_filter_combines_with_search_and_is_echoed_back(): void
    {
        $this->assertSame(['Vencida dos'], $this->names('vigencia=vencidas&q=dos'));
        $filters = $this->get('/admin/catalog/products?vigencia=vencidas')->viewData('page')['props']['filters'];
        $this->assertSame('vencidas', $filters['vigencia']);
    }

    public function test_without_the_filter_the_list_is_unchanged(): void
    {
        $this->assertSame(['Borrador vencido', 'Por vencer', 'Sin oferta', 'Vencida dos', 'Vencida uno', 'Vigente'], $this->names(''));
    }

    public function test_an_unknown_level_is_rejected(): void
    {
        $this->get('/admin/catalog/products?vigencia=cualquiera')->assertSessionHasErrors('vigencia');
    }
}
