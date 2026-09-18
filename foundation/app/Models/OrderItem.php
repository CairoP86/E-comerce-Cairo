<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['product_id', 'name', 'sku', 'is_demo', 'quantity', 'unit_price_minor', 'subtotal_minor', 'currency'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'unit_price_minor' => 'integer', 'subtotal_minor' => 'integer', 'is_demo' => 'boolean'];
    }
}
