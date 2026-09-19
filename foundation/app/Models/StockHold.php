<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Local soft hold (V1-B-SCOPE §5). Never a reservation in the supplier's system.
 * Active while expires_at is in the future. A null holder means the hold belongs to an order.
 */
class StockHold extends Model
{
    protected $fillable = [];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'expires_at' => 'datetime'];
    }

    public function offer()
    {
        return $this->belongsTo(SupplierProduct::class, 'supplier_product_id');
    }
}
