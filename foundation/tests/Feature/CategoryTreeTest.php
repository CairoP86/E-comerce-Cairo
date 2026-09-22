<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Category;
use App\Models\PricingRule;
use App\Models\Product;
use App\Models\User;
use App\Services\CommercialPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CategoryTreeTest extends TestCase
{
    use RefreshDatabase;

    private array $c = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => Role::Admin]));
        // Names chosen so that alphabetical order and tree order differ.
        $this->c['Zeta'] = Category::factory()->create(['name' => 'Zeta', 'slug' => 'zeta']);
        $this->c['Alfa'] = Category::factory()->create(['name' => 'Alfa', 'slug' => 'alfa']);
        $this->c['Beta'] = Category::factory()->create(['name' => 'Beta', 'slug' => 'zeta-beta', 'parent_id' => $this->c['Zeta']->id]);
        $this->c['Gama'] = Category::factory()->create(['name' => 'Gama', 'slug' => 'zeta-gama', 'parent_id' => $this->c['Zeta']->id]);
        $this->c['Delta'] = Category::factory()->create(['name' => 'Delta', 'slug' => 'zeta-gama-delta', 'parent_id' => $this->c['Gama']->id]);
    }

    private function entries(): array
    {
        return $this->get('/admin/catalog/categories')->assertOk()->viewData('page')['props']['entries'];
    }

    private function rule(string $category, int $units, string $name): void
    {
        $rule = PricingRule::firstOrCreate(['name' => $name], ['multiplier_units' => $units]);
        DB::table('category_pricing_rules')->updateOrInsert(['category_id' => $this->c[$category]->id], ['pricing_rule_id' => $rule->id]);
    }

    public function test_categories_are_listed_as_a_tree_with_their_depth(): void
    {
        $rows = collect($this->entries())->map(fn ($e) => str_repeat('  ', $e['depth']).$e['name'])->all();
        $this->assertSame(['Alfa', 'Zeta', '  Beta', '  Gama', '    Delta'], $rows);
    }

    public function test_each_category_counts_its_own_products_and_its_whole_branch(): void
    {
        Product::factory()->count(2)->create(['category_id' => $this->c['Beta']->id]);
        Product::factory()->create(['category_id' => $this->c['Delta']->id]);
        Product::factory()->create(['category_id' => $this->c['Zeta']->id]);

        $by = collect($this->entries())->keyBy('name');
        $this->assertSame([1, 4], [$by['Zeta']['products_direct'], $by['Zeta']['products_total']]);
        $this->assertSame([0, 1], [$by['Gama']['products_direct'], $by['Gama']['products_total']]);
        $this->assertSame([2, 2], [$by['Beta']['products_direct'], $by['Beta']['products_total']]);
        $this->assertSame([0, 0], [$by['Alfa']['products_direct'], $by['Alfa']['products_total']]);
    }

    public function test_the_multiplier_says_whether_it_is_the_categorys_own_or_inherited(): void
    {
        $this->rule('Zeta', 14000, 'Estándar ×1,40');
        $this->rule('Gama', 17000, 'Alto ×1,70');

        $by = collect($this->entries())->keyBy('name');
        $this->assertSame(['units' => 14000, 'rule' => 'Estándar ×1,40', 'inherited' => false, 'from' => null], $by['Zeta']['pricing']);
        $this->assertSame(['units' => 14000, 'rule' => 'Estándar ×1,40', 'inherited' => true, 'from' => 'Zeta'], $by['Beta']['pricing']);
        // The nearest ancestor wins: Delta takes Gama's rule, not Zeta's.
        $this->assertSame(['units' => 17000, 'rule' => 'Alto ×1,70', 'inherited' => true, 'from' => 'Gama'], $by['Delta']['pricing']);
        $this->assertNull($by['Alfa']['pricing']);
    }

    public function test_the_table_never_disagrees_with_the_price_suggestion(): void
    {
        $this->rule('Zeta', 14000, 'Estándar ×1,40');
        $this->rule('Gama', 17000, 'Alto ×1,70');
        $pricing = app(CommercialPricing::class);

        foreach ($this->entries() as $entry) {
            $product = Product::factory()->sellable()->create(['category_id' => $entry['id']]);
            $quote = $pricing->quote($product);
            $expected = $entry['pricing'] ? number_format($entry['pricing']['units'] / 10000, 4, '.', '') : null;
            $this->assertSame($expected, $quote['multiplier'], $entry['name']);
            if ($entry['pricing']) {
                // CommercialPricing names the category the rule was found on.
                $this->assertSame($entry['pricing']['rule'].' · '.($entry['pricing']['from'] ?? $entry['name']), $quote['rule'], $entry['name']);
            }
        }
    }

    public function test_a_parent_cycle_does_not_hang_the_page_and_keeps_every_category(): void
    {
        // Not reachable through the form, but a broken row must not take the page down.
        DB::table('categories')->where('id', $this->c['Zeta']->id)->update(['parent_id' => $this->c['Delta']->id]);
        $names = collect($this->entries())->pluck('name')->sort()->values()->all();
        $this->assertSame(['Alfa', 'Beta', 'Delta', 'Gama', 'Zeta'], $names);
    }

    public function test_brands_keep_their_plain_list(): void
    {
        $entries = $this->get('/admin/catalog/brands')->assertOk()->viewData('page')['props']['entries'];
        foreach ($entries as $entry) {
            $this->assertArrayNotHasKey('pricing', $entry);
            $this->assertArrayNotHasKey('depth', $entry);
        }
    }
}
