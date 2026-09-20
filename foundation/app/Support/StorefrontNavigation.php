<?php

namespace App\Support;

use App\Models\Category;

class StorefrontNavigation
{
    /**
     * Top level public categories for the header trigger. Subcategories stay in the catalog
     * filters: the header lists entry points, not the whole taxonomy.
     */
    public static function categories(): array
    {
        $public = Category::publicIds();

        return Category::whereIn('id', $public)
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get(['name', 'slug'])
            ->map(fn (Category $category) => ['name' => $category->name, 'slug' => $category->slug])
            ->all();
    }
}
