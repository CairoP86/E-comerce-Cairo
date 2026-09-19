<?php

namespace App\Models;

use App\Availability\AvailabilityConfig;
use App\Availability\PreferredOfferAvailability;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'sku', 'slug', 'category_id', 'brand_id', 'short_description', 'description', 'warranty', 'specifications', 'price_minor', 'previous_price_minor', 'currency', 'featured', 'meta_title', 'meta_description'];

    protected function casts(): array
    {
        return ['specifications' => 'array', 'price_minor' => 'integer', 'previous_price_minor' => 'integer', 'featured' => 'boolean', 'is_demo' => 'boolean', 'published_at' => 'datetime'];
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('position')->orderBy('id');
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where('status', 'published')->whereIn('category_id', Category::publicIds())->whereHas('brand', fn ($q) => $q->where('status', 'published'));
    }

    /** Editorially public and with a fresh, publicly visible preferred offer (V1-B-SCOPE §2, D9). */
    public function scopeStorefrontVisible(Builder $query): Builder
    {
        return $query->publiclyVisible()->whereExists(fn ($offers) => PreferredOfferAvailability::constrainVisible($offers, PreferredOfferAvailability::now(), AvailabilityConfig::ttlMinutes()));
    }
}
