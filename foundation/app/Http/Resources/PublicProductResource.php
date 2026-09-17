<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Public allowlist. Never serialize a full model or supplier data.
        return [
            'name' => $this->name, 'sku' => $this->sku, 'slug' => $this->slug,
            'short_description' => $this->short_description, 'description' => $this->description,
            'warranty' => $this->warranty, 'specifications' => $this->specifications,
            'price_minor' => $this->price_minor, 'previous_price_minor' => $this->previous_price_minor,
            'currency' => $this->currency, 'featured' => $this->featured, 'is_demo' => $this->is_demo,
            'meta_title' => $this->meta_title, 'meta_description' => $this->meta_description,
            'category' => $this->category->only(['name', 'slug']), 'brand' => $this->brand->only(['name', 'slug']),
            'images' => $this->images->map(fn ($image) => ['id' => $image->id, 'url' => route('catalog.image', $image), 'alt' => $image->alt, 'position' => $image->position, 'is_primary' => $image->is_primary])->all(),
        ];
    }
}
