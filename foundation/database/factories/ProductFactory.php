<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Equipo de demostración '.fake()->word(), 'sku' => 'TEST-'.strtoupper(Str::random(12)),
            'slug' => 'test-'.Str::lower(Str::random(16)), 'category_id' => Category::factory(), 'brand_id' => Brand::factory(),
            'short_description' => 'Producto de prueba; no representa disponibilidad real.', 'description' => 'Datos ficticios para desarrollo.',
            'warranty' => 'Garantía ilustrativa.', 'specifications' => [['key' => 'ram_gb', 'label' => 'RAM', 'value' => '16', 'unit' => 'GB', 'group' => 'Memoria']],
            'currency' => 'CRC', 'price_minor' => 12500000, 'status' => 'draft', 'featured' => false, 'is_demo' => true,
        ];
    }
}
