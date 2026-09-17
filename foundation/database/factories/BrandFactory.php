<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BrandFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => 'Marca demo '.fake()->unique()->word(), 'slug' => 'brand-'.Str::lower(Str::random(12)), 'status' => 'published'];
    }
}
