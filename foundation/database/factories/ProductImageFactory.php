<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductImageFactory extends Factory
{
    public function definition(): array
    {
        return ['product_id' => Product::factory(), 'path' => 'tests/'.Str::uuid().'.webp', 'alt' => 'Ilustración de prueba', 'position' => 0, 'is_primary' => true];
    }
}
