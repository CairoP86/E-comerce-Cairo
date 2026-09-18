<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = ['*'];

    protected $hidden = ['checkout_key', 'owner_hash', 'request_hash'];

    protected function casts(): array
    {
        return ['status' => OrderStatus::class, 'total_minor' => 'integer', 'subtotal_minor' => 'integer', 'cart_revision' => 'integer'];
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function address()
    {
        return $this->hasOne(OrderAddress::class);
    }

    public function publicSummary(): array
    {
        return [
            'number' => $this->number, 'created_at' => $this->created_at->toIso8601String(),
            'buyer' => $this->only(['first_name', 'last_name', 'email', 'phone']),
            'status' => $this->status->value, 'status_label' => $this->status->label(),
            'currency' => $this->currency, 'subtotal_minor' => $this->subtotal_minor, 'total_minor' => $this->total_minor,
            'items' => $this->items->map(fn ($item) => $item->only(['name', 'sku', 'quantity', 'unit_price_minor', 'subtotal_minor', 'currency', 'is_demo']))->all(),
            'address' => $this->address->only(['country_code', 'province', 'canton', 'district', 'exact_address', 'additional']),
        ];
    }
}
