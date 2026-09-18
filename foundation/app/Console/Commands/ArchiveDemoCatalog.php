<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Support\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ArchiveDemoCatalog extends Command
{
    protected $signature = 'catalog:archive-demo';

    protected $description = 'Archiva productos marcados DEMO sin borrar imágenes, fixtures ni pedidos.';

    public function handle(): int
    {
        $count = DB::transaction(function () {
            $products = Product::where('is_demo', true)->where('status', '!=', 'archived')->lockForUpdate()->get();
            foreach ($products as $product) {
                $old = $product->status;
                $product->status = 'archived';
                $product->save();
                Audit::record('catalog.demo_archived', metadata: ['entity_type' => 'products', 'entity_id' => $product->id, 'from_status' => $old, 'to_status' => 'archived'], source: 'cli');
            }

            return $products->count();
        });
        $this->info('Productos DEMO archivados: '.$count);

        return self::SUCCESS;
    }
}
