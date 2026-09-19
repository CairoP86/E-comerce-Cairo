<?php

namespace App\Http\Controllers;

use App\Availability\Availability;
use App\Contracts\ProductAvailability;
use App\Http\Resources\PublicProductResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Support\CartHolder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PublicCatalogController extends Controller
{
    public function __construct(private ProductAvailability $availability) {}

    private function products()
    {
        return Product::storefrontVisible()->with(['category', 'brand', 'images']);
    }

    private function cards(Collection $products): array
    {
        return $products->map(fn ($product) => (new PublicProductResource($product))->resolve())->values()->all();
    }

    /** Public availability keyed by slug: state and exact quantity only (V1-B-SCOPE §4). */
    private function availabilityFor(Collection $products): array
    {
        if ($products->isEmpty()) {
            return [];
        }
        $resolved = $this->availability->forProducts($products->pluck('id')->all(), CartHolder::current());

        return $products->mapWithKeys(fn ($product) => [$product->slug => ($resolved[$product->id] ?? Availability::unknown())->toPublic()])->all();
    }

    private function categories()
    {
        return Category::whereIn('id', Category::publicIds())->orderBy('name')->get(['id', 'parent_id', 'name', 'slug', 'description']);
    }

    private function taxonomy($categories): array
    {
        return $categories->map(fn ($item) => [
            'name' => $item->name, 'slug' => $item->slug, 'description' => $item->description,
            'parent_slug' => $categories->firstWhere('id', $item->parent_id)?->slug,
        ])->values()->all();
    }

    private function render(string $page, array $props, string $title, string $description, string $url, ?string $image = null)
    {
        $seo = [
            'title' => $title.' · '.config('storefront.name'), 'description' => $description,
            'url' => $url, 'image' => $image, 'robots' => 'noindex, nofollow',
            'site_name' => config('storefront.name'), 'locale' => config('storefront.locale'),
        ];

        // Preview remains non-indexable until commercial launch is authorized.
        return Inertia::render($page, [...$props, 'seo' => $seo])->withViewData(['seo' => $seo]);
    }

    public function home()
    {
        $featured = $this->products()->where('featured', true)->orderBy('id')->limit(4)->get();
        $recent = $this->products()->orderByDesc('published_at')->orderByDesc('id')->limit(4)->get();
        $offers = $this->products()->whereColumn('previous_price_minor', '>', 'price_minor')->orderBy('id')->limit(4)->get();

        return $this->render('Home', [
            'featured' => $this->cards($featured), 'recent' => $this->cards($recent), 'offers' => $this->cards($offers),
            'availability' => $this->availabilityFor($featured->concat($recent)->concat($offers)->unique('id')),
            'categories' => $this->taxonomy($this->categories()),
            'brands' => Brand::where('status', 'published')->orderBy('name')->get(['name', 'slug'])->toArray(),
        ], config('storefront.tagline'), config('storefront.description'), route('home'));
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:160'], 'brand' => ['nullable', 'string', 'max:160'],
            'currency' => ['nullable', Rule::in(['CRC', 'USD'])],
            'min' => ['nullable', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
            'max' => ['nullable', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
            'sort' => ['nullable', Rule::in(['newest', 'price_asc', 'price_desc', 'name_asc', 'name_desc', 'featured'])],
            'editorial' => ['nullable', Rule::in(['demo', 'standard'])],
            'featured' => ['nullable', 'boolean'], 'offers' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);
        $filters = array_filter($filters, fn ($value) => $value !== null && $value !== '');
        $filters['currency'] ??= 'CRC';
        $filters['sort'] ??= 'newest';
        $toMinor = function (string $amount): int {
            [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

            return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
        };
        if (isset($filters['min'], $filters['max']) && $toMinor($filters['min']) > $toMinor($filters['max'])) {
            throw ValidationException::withMessages(['max' => 'El precio máximo debe ser mayor o igual al mínimo.']);
        }
        $categories = $this->categories();
        $category = null;
        $query = $this->products()->where('currency', $filters['currency']);
        if (! empty($filters['category'])) {
            $category = $categories->firstWhere('slug', $filters['category']);
            abort_unless($category, 404);
            $ids = [$category->id];
            do {
                $previous = count($ids);
                $ids = array_values(array_unique([...$ids, ...$categories->whereIn('parent_id', $ids)->pluck('id')->all()]));
            } while (count($ids) > $previous);
            $query->whereIn('category_id', $ids);
        }
        if (! empty($filters['brand'])) {
            $brand = Brand::where('status', 'published')->where('slug', $filters['brand'])->firstOrFail();
            $query->where('brand_id', $brand->id);
        }
        if (isset($filters['q']) && trim($filters['q']) !== '') {
            $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($filters['q'])).'%';
            $query->where(fn ($q) => $q->whereRaw("name LIKE ? ESCAPE '!'", [$term])->orWhereRaw("sku LIKE ? ESCAPE '!'", [$term]));
        }
        foreach (['min' => '>=', 'max' => '<='] as $key => $operator) {
            if (isset($filters[$key])) {
                $query->where('price_minor', $operator, $toMinor($filters[$key]));
            }
        }
        if (! empty($filters['editorial'])) {
            $query->where('is_demo', $filters['editorial'] === 'demo');
        }
        if (! empty($filters['featured'])) {
            $query->where('featured', true);
        }
        if (! empty($filters['offers'])) {
            $query->whereColumn('previous_price_minor', '>', 'price_minor');
        }
        [$column, $direction] = match ($filters['sort']) {
            'price_asc' => ['price_minor', 'asc'], 'price_desc' => ['price_minor', 'desc'],
            'name_asc' => ['name', 'asc'], 'name_desc' => ['name', 'desc'],
            'featured' => ['featured', 'desc'], default => ['published_at', 'desc'],
        };
        $page = $query->orderBy($column, $direction)->orderByDesc('id')->paginate(12)->appends($filters);
        $availability = $this->availabilityFor($page->getCollection());
        $products = $page->through(fn ($product) => (new PublicProductResource($product))->resolve());
        $canonicalFilters = array_intersect_key($filters, array_flip(['category', 'currency', 'page']));
        if ($canonicalFilters['currency'] === 'CRC') {
            unset($canonicalFilters['currency']);
        }
        if (($canonicalFilters['page'] ?? 1) == 1) {
            unset($canonicalFilters['page']);
        }

        return $this->render('catalog/Index', [
            'products' => $products, 'availability' => $availability, 'filters' => $filters, 'categories' => $this->taxonomy($categories),
            'brands' => Brand::where('status', 'published')->orderBy('name')->get(['name', 'slug'])->toArray(),
            'activeCategory' => $category ? ['name' => $category->name, 'description' => $category->description] : null,
        ], $category?->name ?? 'Catálogo de tecnología', $category?->description ?: config('storefront.description'), route('catalog.index', $canonicalFilters));
    }

    public function show(string $slug)
    {
        $product = $this->products()->where('slug', $slug)->firstOrFail();
        $related = $this->products()->where('category_id', $product->category_id)->whereKeyNot($product->id)->orderByDesc('featured')->orderBy('id')->limit(4)->get();
        $data = (new PublicProductResource($product))->resolve();

        return $this->render('catalog/Show', [
            'product' => $data,
            'related' => $this->cards($related),
            'availability' => $this->availabilityFor(collect([$product])->concat($related)),
        ], $product->meta_title ?: $product->name, $product->meta_description ?: mb_substr($product->short_description, 0, 170), route('catalog.show', $product->slug), collect($data['images'])->firstWhere('is_primary', true)['url'] ?? null);
    }
}
