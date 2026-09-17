<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Support\Audit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CatalogDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Demo seeding is limited to local/testing.');
        }
        $root = Category::firstOrCreate(['slug' => 'demo-tecnologia'], ['name' => 'Tecnología DEMO', 'status' => 'published']);
        $categories = [];
        foreach (['Laptops', 'Computadoras', 'Monitores', 'Procesadores', 'Tarjetas gráficas', 'Memoria RAM', 'Almacenamiento', 'Routers', 'Switches', 'Periféricos', 'Accesorios'] as $name) {
            $categories[$name] = Category::firstOrCreate(['slug' => 'demo-'.Str::slug($name)], ['name' => $name.' DEMO', 'parent_id' => $root->id, 'status' => 'published']);
        }
        $brands = collect(['Aurora DEMO', 'Vector DEMO', 'Núcleo DEMO', 'Órbita DEMO'])->map(fn ($name) => Brand::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'description' => 'Marca ficticia de demostración.', 'status' => 'published']));
        $items = [
            ['Laptop Studio 14', 'Laptops', 'ram_gb', 'Memoria RAM', '16', 'GB'],
            ['Laptop Creator 16', 'Laptops', 'storage_gb', 'Almacenamiento', '1024', 'GB'],
            ['Computadora Compact', 'Computadoras', 'cpu_cores', 'Núcleos', '8', ''],
            ['Estación Work Pro', 'Computadoras', 'ram_gb', 'Memoria RAM', '32', 'GB'],
            ['Monitor View 24', 'Monitores', 'screen_inches', 'Diagonal', '24', 'pulgadas'],
            ['Monitor Wide 34', 'Monitores', 'refresh_hz', 'Frecuencia', '144', 'Hz'],
            ['Procesador Core 6', 'Procesadores', 'cpu_cores', 'Núcleos', '6', ''],
            ['Procesador Core 12', 'Procesadores', 'socket', 'Socket', 'Demo A', ''],
            ['Gráfica Render 8', 'Tarjetas gráficas', 'vram_gb', 'Memoria de video', '8', 'GB'],
            ['Gráfica Render 16', 'Tarjetas gráficas', 'vram_gb', 'Memoria de video', '16', 'GB'],
            ['Memoria DDR5 16', 'Memoria RAM', 'memory_type', 'Tipo', 'DDR5', ''],
            ['Kit RAM 32', 'Memoria RAM', 'speed_mts', 'Velocidad', '5600', 'MT/s'],
            ['SSD Flash 1TB', 'Almacenamiento', 'interface', 'Interfaz', 'NVMe', ''],
            ['Disco Archive 4TB', 'Almacenamiento', 'capacity_tb', 'Capacidad', '4', 'TB'],
            ['Router Mesh AX', 'Routers', 'wifi_standard', 'Estándar Wi-Fi', 'Wi-Fi 6', ''],
            ['Switch Connect 8', 'Switches', 'ports', 'Puertos', '8', ''],
            ['Teclado Mechanical', 'Periféricos', 'layout', 'Distribución', 'Español', ''],
            ['Mouse Precision', 'Periféricos', 'sensor_dpi', 'Resolución', '8000', 'DPI'],
            ['Hub USB-C', 'Accesorios', 'ports', 'Conexiones', '6', ''],
            ['Soporte Desk', 'Accesorios', 'material', 'Material', 'Aluminio', ''],
        ];
        foreach ($items as $index => [$name, $category, $key, $label, $value, $unit]) {
            $sku = 'DEMO-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
            if (Product::where('sku', $sku)->exists()) {
                continue;
            }
            DB::transaction(function () use ($index, $name, $category, $key, $label, $value, $unit, $sku, $categories, $brands) {
                $product = new Product([
                    'sku' => $sku, 'slug' => 'demo-'.Str::slug($name), 'name' => $name.' · DEMO',
                    'category_id' => $categories[$category]->id, 'brand_id' => $brands[$index % 4]->id,
                    'short_description' => 'Ejemplo de '.$category.'. Datos y precio de demostración.',
                    'description' => 'Producto ficticio para revisar el catálogo. No representa un equipo comercial, stock ni relación con proveedores.',
                    'warranty' => 'Ejemplo de texto de garantía; no constituye una oferta real.',
                    'currency' => 'CRC', 'price_minor' => (15000 + $index * 12500) * 100,
                    'previous_price_minor' => $index % 4 === 0 ? (19000 + $index * 12500) * 100 : null,
                    'featured' => $index % 3 === 0, 'meta_title' => $name.' — Demostración', 'meta_description' => 'Ficha ilustrativa para probar el catálogo tecnológico.',
                    'specifications' => [['key' => $key, 'label' => $label, 'value' => $value, 'unit' => $unit, 'group' => 'Características'], ['key' => 'demo_only', 'label' => 'Uso', 'value' => 'Demostración', 'unit' => '', 'group' => 'Información']],
                ]);
                $product->forceFill(['is_demo' => true, 'status' => 'draft'])->save();
                for ($view = 0; $view < ($index === 0 ? 2 : 1); $view++) {
                    $image = imagecreatetruecolor(800, 600);
                    $background = imagecolorallocate($image, 227, 232, 236);
                    $ink = imagecolorallocate($image, 37, 48, 65);
                    $accent = imagecolorallocate($image, 170 + $index % 3 * 15, 159, 139);
                    imagefill($image, 0, 0, $background);
                    imagefilledrectangle($image, 160, 120, 640, 400, $ink);
                    imagefilledrectangle($image, 180, 140, 620, 380, $accent);
                    imagefilledrectangle($image, 350, 400, 450, 445, $ink);
                    imagefilledrectangle($image, 290, 445, 510, 455, $ink);
                    imagestring($image, 5, 240, 260, 'CATALOGO DE DEMOSTRACION', $ink);
                    imagestring($image, 4, 300, 490, $sku.' / VISTA '.($view + 1), $ink);
                    ob_start();
                    imagewebp($image, null, 85);
                    $bytes = ob_get_clean();
                    $path = 'demo/'.$sku.'-'.$view.'.webp';
                    Storage::disk('catalog')->put($path, $bytes);
                    $product->images()->create(['path' => $path, 'alt' => 'Ilustración genérica de demostración para '.$name.', vista '.($view + 1), 'position' => $view, 'is_primary' => $view === 0]);
                    unset($image);
                }
                $product->forceFill(['status' => 'published', 'published_at' => now()])->save();
                Audit::record('catalog.demo_created', null, null, ['entity_type' => 'products', 'entity_id' => $product->id], 'console');
            });
        }
    }
}
