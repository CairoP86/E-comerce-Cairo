<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Support\Audit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Explicit initial taxonomy correction, never run automatically on deployment. */
class CommercialTaxonomySeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->call(CommercialSetupSeeder::class);

            // Keep historical identifiers and parent relationships, including unmarked
            // descendants of a DEMO branch. Do not infer DEMO from a product relation.
            $categories = Category::orderBy('id')->lockForUpdate()->get();
            $demo = $categories->filter(fn ($category) => preg_match('/(?:^|[^\p{L}\p{N}])demo(?:$|[^\p{L}\p{N}])/iu', $category->name.' '.$category->slug))->keyBy('id');
            do {
                $count = $demo->count();
                foreach ($categories as $category) {
                    if ($category->parent_id && $demo->has($category->parent_id)) {
                        $demo->put($category->id, $category);
                    }
                }
            } while ($count !== $demo->count());

            foreach ($demo as $category) {
                if ($category->status === 'archived') {
                    continue;
                }
                $old = $category->status;
                $category->status = 'archived';
                $category->save();
                Audit::record('category.demo_archived', metadata: ['entity_type' => 'categories', 'entity_id' => $category->id, 'from_status' => $old, 'to_status' => 'archived'], source: 'cli');
            }

            $tree = [
                'Cómputo' => [
                    'Laptops' => [], 'Computadoras de escritorio' => [], 'Monitores' => [],
                    'Componentes' => ['Procesadores' => [], 'Memoria RAM' => [], 'Almacenamiento' => [], 'Tarjetas gráficas' => []],
                    'UPS y energía' => [],
                ],
                'Redes' => ['Routers' => [], 'Switches' => [], 'Access Points' => [], 'Wi-Fi Mesh' => [], 'Adaptadores de red' => [], 'Cableado y accesorios' => []],
                'Periféricos' => ['Teclados' => [], 'Mouse' => [], 'Combos teclado y mouse' => [], 'Audífonos' => [], 'Webcams' => [], 'Docking stations y hubs' => []],
            ];
            $this->createBranch($tree);
        });
    }

    private function createBranch(array $tree, ?Category $parent = null): void
    {
        foreach ($tree as $name => $children) {
            // Path-based slugs distinguish commercial categories from historical DEMO.
            $slug = ($parent ? $parent->slug.'-' : '').Str::slug($name);
            $category = Category::firstOrNew(['slug' => $slug]);
            $category->fill(['name' => $name, 'parent_id' => $parent?->id]);
            $category->status = 'draft';
            if ($category->isDirty()) {
                $fields = array_keys($category->getDirty());
                $event = $category->exists ? 'category.commercial_initialized' : 'category.created';
                $category->save();
                Audit::record($event, metadata: ['entity_type' => 'categories', 'entity_id' => $category->id, 'changed_fields' => $fields], source: 'cli');
            }
            // No per-child pricing assignments: CommercialPricing traverses ancestors.
            $this->createBranch($children, $category);
        }
    }
}
