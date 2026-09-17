<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImage;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CatalogImageController extends Controller
{
    public function show(Request $request, ProductImage $image)
    {
        abort_unless($request->user()?->hasVerifiedEmail() && $request->user()?->can('view-catalog-admin') || Product::publiclyVisible()->whereKey($image->product_id)->exists(), 404);
        abort_unless(Storage::disk('catalog')->exists($image->path), 404);

        return Storage::disk('catalog')->response($image->path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function store(Request $request, Product $product)
    {
        $data = $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:12'],
            'files.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=4096,max_height=4096'],
            'alt' => ['required', 'string', 'max:180'],
        ]);
        $stored = [];
        try {
            DB::transaction(function () use ($request, $product, $data, &$stored) {
                $product = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
                $count = $product->images()->count();
                $position = $count ? $product->images()->max('position') + 1 : 0;
                if ($count + count($data['files']) > 12) {
                    throw ValidationException::withMessages(['files' => 'Máximo 12 imágenes activas por producto.']);
                }
                foreach ($data['files'] as $file) {
                    $source = imagecreatefromstring($file->getContent());
                    if (! $source) {
                        throw ValidationException::withMessages(['files' => 'La imagen no se pudo procesar.']);
                    }
                    ob_start();
                    imagewebp($source, null, 85);
                    $bytes = ob_get_clean();
                    $path = 'products/'.$product->id.'/'.Str::uuid().'.webp';
                    $stored[] = $path;
                    Storage::disk('catalog')->put($path, $bytes);
                    $image = $product->images()->create(['path' => $path, 'alt' => $data['alt'], 'position' => $position++, 'is_primary' => $count === 0]);
                    $count++;
                    Audit::record('catalog.image_added', $request->user()->id, null, ['entity_type' => 'products', 'entity_id' => $product->id, 'image_id' => $image->id]);
                    unset($source);
                }
            });
        } catch (\Throwable $error) {
            foreach ($stored as $path) {
                Storage::disk('catalog')->delete($path);
            }
            throw $error;
        }

        return back()->with('status', 'Imágenes guardadas.');
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:12'],
            'images.*' => ['array:id,alt'], 'images.*.id' => ['required', 'integer', 'distinct'],
            'images.*.alt' => ['required', 'string', 'max:180'], 'primary_id' => ['required', 'integer'],
        ]);
        DB::transaction(function () use ($request, $product, $data) {
            Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $ids = $product->images()->pluck('id')->sort()->values()->all();
            $provided = collect($data['images'])->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            if ($ids !== $provided || ! in_array((int) $data['primary_id'], $ids, true)) {
                throw ValidationException::withMessages(['images' => 'La lista de imágenes cambió o contiene imágenes ajenas. Recarga la página.']);
            }
            foreach ($data['images'] as $index => $row) {
                $product->images()->whereKey($row['id'])->update(['alt' => $row['alt'], 'position' => $index, 'is_primary' => (int) $row['id'] === (int) $data['primary_id']]);
            }
            Audit::record('catalog.images_updated', $request->user()->id, null, ['entity_type' => 'products', 'entity_id' => $product->id, 'changed_fields' => ['alt', 'position', 'is_primary']]);
        });

        return back()->with('status', 'Orden, textos e imagen principal actualizados.');
    }

    public function archive(Request $request, Product $product, ProductImage $image)
    {
        abort_unless($image->product_id === $product->id, 404);
        DB::transaction(function () use ($request, $product, $image) {
            $product = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            if ($product->status === 'published' && $product->images()->count() <= 1) {
                throw ValidationException::withMessages(['images' => 'Despublica antes de retirar la última imagen.']);
            }
            $image->delete(); // Soft delete; original file and record retained.
            if ($image->is_primary && ($next = $product->images()->first())) {
                $next->update(['is_primary' => true]);
            }
            Audit::record('catalog.image_archived', $request->user()->id, null, ['entity_type' => 'products', 'entity_id' => $product->id, 'image_id' => $image->id]);
        });

        return back()->with('status', 'Imagen retirada sin destruir su archivo ni su historial.');
    }
}
