<?php

namespace App\Http\Controllers;

use App\Availability\CatalogFreshness;
use App\Http\Requests\SaveProductRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CatalogProductController extends Controller
{
    public function index(Request $request, CatalogFreshness $freshness)
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['draft', 'published', 'archived'])], 'demo' => ['nullable', 'boolean']]);
        $showDemo = (bool) ($filters['demo'] ?? false);
        $query = Product::query()->with(['category:id,name', 'brand:id,name'])->latest('id');
        // Demonstration products stay in the database; they are only out of the default view.
        if (! $showDemo) {
            $query->where('is_demo', false);
        }
        if ($filters['q'] ?? null) {
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$filters['q'].'%')->orWhere('sku', 'like', '%'.$filters['q'].'%'));
        }
        if ($filters['status'] ?? null) {
            $query->where('status', $filters['status']);
        }

        $products = $query->paginate(20)->withQueryString();

        return Inertia::render('admin/catalog/Products', [
            'products' => $products, 'filters' => $filters, 'canManage' => $request->user()->can('manage-catalog'),
            'freshness' => $freshness->forProducts($products->pluck('id')->all()),
            'freshnessSummary' => $freshness->publishedSummary(),
            'hiddenDemo' => $showDemo ? 0 : Product::where('is_demo', true)->count(),
        ]);
    }

    public function form(Request $request, CatalogFreshness $freshness, ?Product $product = null)
    {
        return Inertia::render('admin/catalog/ProductForm', [
            'product' => $product?->load('images'),
            'freshness' => $product ? $freshness->forProducts([$product->id])[$product->id] : null,
            'categories' => Category::orderBy('name')->get(['id', 'name', 'status']),
            'brands' => Brand::orderBy('name')->get(['id', 'name', 'status']),
            'canManage' => $request->user()->can('manage-catalog'),
        ]);
    }

    public function store(SaveProductRequest $request)
    {
        $product = DB::transaction(function () use ($request) {
            $product = Product::create($request->validated());
            Audit::record('catalog.created', $request->user()->id, null, ['entity_type' => 'products', 'entity_id' => $product->id, 'to_status' => 'draft']);

            return $product;
        });

        return redirect()->route('admin.catalog.products.edit', $product)->with('status', 'Borrador creado. Agrega imágenes antes de publicar.');
    }

    public function update(SaveProductRequest $request, Product $product)
    {
        DB::transaction(function () use ($request, $product) {
            $product = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $product->fill($request->validated());
            if ($product->status === 'published') {
                $this->validatePublication($product);
            }
            $fields = array_keys($product->getDirty());
            $product->save();
            Audit::record('catalog.updated', $request->user()->id, null, ['entity_type' => 'products', 'entity_id' => $product->id, 'changed_fields' => $fields]);
        });

        return back()->with('status', 'Producto actualizado.');
    }

    private function validatePublication(Product $product): void
    {
        if (! in_array($product->category_id, Category::publicIds()) || ! Brand::whereKey($product->brand_id)->where('status', 'published')->exists()) {
            throw ValidationException::withMessages(['publication' => 'Publica primero la marca y toda la jerarquía de categorías.']);
        }
        if (! $product->images()->exists()) {
            throw ValidationException::withMessages(['publication' => 'Agrega al menos una imagen antes de publicar.']);
        }
    }

    public function status(Request $request, Product $product)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['draft', 'published', 'archived'])]]);
        DB::transaction(function () use ($request, $product, $data) {
            $product = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $old = $product->status;
            if ($old === 'archived' && $data['status'] === 'published') {
                throw ValidationException::withMessages(['publication' => 'Restaura como borrador antes de publicar.']);
            }
            if ($data['status'] === 'published') {
                $this->validatePublication($product);
            }
            $product->status = $data['status'];
            if ($product->status === 'published' && ! $product->published_at) {
                $product->published_at = now();
            }
            $product->save();
            Audit::record('catalog.publication_changed', $request->user()->id, null, ['entity_type' => 'products', 'entity_id' => $product->id, 'from_status' => $old, 'to_status' => $product->status]);
        });

        return back()->with('status', 'Estado de publicación actualizado.');
    }
}
