<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierProduct extends Model
{
    protected $fillable = ['supplier_id', 'supplier_sku', 'reference', 'cost_minor', 'currency', 'stock', 'availability', 'observed_at', 'active', 'notes'];

    protected function casts(): array
    {
        return ['cost_minor' => 'integer', 'stock' => 'integer', 'active' => 'boolean', 'observed_at' => 'datetime'];
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
