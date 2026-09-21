<?php

namespace App\Models;

use App\Delivery\DeliveryZone;
use Illuminate\Database\Eloquent\Model;

class DeliveryRate extends Model
{
    protected $fillable = ['delivery_rate_set_id', 'zone', 'flat_minor', 'free_from_minor', 'currency'];

    protected function casts(): array
    {
        return ['zone' => DeliveryZone::class, 'flat_minor' => 'integer', 'free_from_minor' => 'integer'];
    }
}
