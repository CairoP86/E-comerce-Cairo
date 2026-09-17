<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'unit_price_minor' => 'integer', 'subtotal_minor' => 'integer', 'is_demo' => 'boolean'];
    }
}
