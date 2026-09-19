<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductCommercialSetting;
use App\Models\Supplier;
use App\Models\SupplierProduct;
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

    /** Fresh preferred manual offer, so the product stays publicly sellable under V1-B rules. */
    public function sellable(int $stock = 1000, array $offer = []): static
    {
        return $this->afterCreating(function (Product $product) use ($stock, $offer) {
            $supplier = Supplier::create(['code' => 'test-'.Str::lower(Str::random(12)), 'name' => 'Proveedor de prueba', 'active' => true]);
            $row = new SupplierProduct(['supplier_id' => $supplier->id, 'supplier_sku' => 'TEST-'.Str::upper(Str::random(10)), 'currency' => $product->currency, 'stock' => $stock, 'availability' => 'available', 'observed_at' => now(), 'active' => true, ...$offer]);
            $row->product_id = $product->id;
            $row->source = 'manual';
            $row->save();
            $setting = new ProductCommercialSetting;
            $setting->product_id = $product->id;
            $setting->preferred_offer_id = $row->id;
            $setting->save();
        });
    }
}
