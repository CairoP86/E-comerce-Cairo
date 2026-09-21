<?php

namespace App\Models;

use App\Delivery\DeliveryZone;
use Illuminate\Database\Eloquent\Model;

/** Which delivery zone a canton belongs to. Every canton of the official catalogue has a row. */
class DeliveryZoneCanton extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $primaryKey = 'canton_code';

    protected $fillable = ['canton_code', 'zone'];

    protected function casts(): array
    {
        return ['zone' => DeliveryZone::class];
    }
}
