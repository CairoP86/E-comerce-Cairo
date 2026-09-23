<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Services\CategoryTree;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CatalogTaxonomyController extends Controller
{
    private function model(string $kind): string
    {
        return $kind === 'categories' ? Category::class : Brand::class;
    }

    public function index(Request $request, string $kind, CategoryTree $tree)
    {
        return Inertia::render('admin/catalog/Taxonomies', [
            'kind' => $kind,
            // Categories come in tree order with counts and their pricing rule. Historical demonstration
            // categories carry path slugs under demo- (see CommercialTaxonomySeeder): flagged, not removed.
            'entries' => $kind === 'categories'
                ? $tree->entries()
                : $this->model($kind)::query()->withCount('products')->orderBy('name')->get()
                    // Every state counts, the same rule the categories tree follows.
                    ->map(fn ($entry) => [...$entry->toArray(), 'is_demo' => false, 'products_total' => $entry->products_count])->values(),
            'canManage' => $request->user()->can('manage-catalog'),
        ]);
    }

    public function save(Request $request, string $kind, ?int $id = null)
    {
        $model = $this->model($kind);
        $entry = $id ? $model::findOrFail($id) : new $model;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique($kind)->ignore($entry->id)],
            'description' => ['nullable', 'string', 'max:3000'],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'parent_id' => $kind === 'categories' ? ['nullable', 'integer', 'exists:categories,id'] : ['prohibited'],
        ]);
        DB::transaction(function () use ($kind, $entry, $data, $request, $id, $model) {
            if ($kind === 'categories') {
                $all = Category::query()->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $parent = $data['parent_id'] ?? null;
                $seen = [];
                while ($parent) {
                    if ((int) $parent === (int) $entry->id || isset($seen[$parent])) {
                        throw ValidationException::withMessages(['parent_id' => 'La categoría no puede ser su propia descendiente.']);
                    }
                    $seen[$parent] = true;
                    $parent = $all->get($parent)?->parent_id;
                }
            }
            if ($id) {
                $entry = $model::whereKey($id)->lockForUpdate()->firstOrFail();
            }
            $old = $entry->status;
            $entry->fill(collect($data)->except('status')->all());
            $entry->status = $data['status'];
            $changed = array_keys($entry->getDirty());
            $entry->save();
            Audit::record('catalog.'.($id ? 'updated' : 'created'), $request->user()->id, null, [
                'entity_type' => $kind, 'entity_id' => $entry->id, 'changed_fields' => $changed,
                'from_status' => $old, 'to_status' => $entry->status,
            ]);
        });

        return back()->with('status', 'Información guardada. Archivar o despublicar una categoría o marca oculta sus productos del catálogo público.');
    }
}
