<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'description', 'parent_id'];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public static function publicIds(): array
    {
        $all = static::query()->get(['id', 'parent_id', 'status'])->keyBy('id');

        return $all->filter(function ($category) use ($all) {
            $seen = [];
            while ($category) {
                if ($category->status !== 'published' || isset($seen[$category->id])) {
                    return false;
                }
                $seen[$category->id] = true;
                if ($category->parent_id && ! $all->has($category->parent_id)) {
                    return false;
                }
                $category = $all->get($category->parent_id);
            }

            return true;
        })->keys()->all();
    }
}
