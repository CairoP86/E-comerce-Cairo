<?php

namespace App\Models;

use App\Delivery\DeliveryZone;
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
        return ['status' => OrderStatus::class, 'shipping_zone' => DeliveryZone::class, 'total_minor' => 'integer', 'subtotal_minor' => 'integer', 'shipping_minor' => 'integer', 'cart_revision' => 'integer'];
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
            'currency' => $this->currency, 'subtotal_minor' => $this->subtotal_minor,
            // Null for orders placed before V1-C: they were never quoted for delivery.
            'shipping' => $this->shipping_minor === null ? null : [
                'amount_minor' => $this->shipping_minor,
                'zone' => $this->shipping_zone?->value,
                'zone_label' => $this->shipping_zone?->label(),
                'free' => $this->shipping_minor === 0,
            ],
            'total_minor' => $this->total_minor,
            'items' => $this->items->map(fn ($item) => $item->only(['name', 'sku', 'quantity', 'unit_price_minor', 'subtotal_minor', 'currency', 'is_demo']))->all(),
            'address' => $this->address->only(['country_code', 'province', 'canton', 'district', 'exact_address', 'additional']),
        ];
    }
}
