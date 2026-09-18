<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\PricingRule;
use App\Models\Supplier;
use App\Support\Audit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CommercialSetupSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            foreach (['eurocomp' => 'Eurocomp', 'dataformas' => 'Dataformas', 'cq-international' => 'CQ International'] as $code => $name) {
                $supplier = Supplier::firstOrCreate(['code' => $code], ['name' => $name, 'active' => true]);
                if ($supplier->wasRecentlyCreated) {
                    Audit::record('supplier.created', metadata: ['entity_type' => 'suppliers', 'entity_id' => $supplier->id], source: 'cli');
                }
            }
            foreach ([['computo', 'Cómputo', 14000], ['redes', 'Redes', 15000], ['perifericos', 'Periféricos', 17000]] as [$slug, $name, $units]) {
                $rule = PricingRule::firstOrCreate(['name' => $name], ['multiplier_units' => $units]);
                $category = Category::firstOrCreate(['slug' => $slug], ['name' => $name]);
                if ($rule->wasRecentlyCreated) {
                    Audit::record('pricing_rule.created', metadata: ['entity_type' => 'pricing_rules', 'entity_id' => $rule->id], source: 'cli');
                }
                if ($category->wasRecentlyCreated) {
                    DB::table('category_pricing_rules')->insert(['category_id' => $category->id, 'pricing_rule_id' => $rule->id]);
                    Audit::record('category.pricing_assigned', metadata: ['entity_type' => 'categories', 'entity_id' => $category->id], source: 'cli');
                }
            }
        });
    }
}
