<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A versioned set of delivery rates. Orders keep the set they were quoted with (ADR-008). */
class DeliveryRateSet extends Model
{
    protected $fillable = ['version', 'effective_from', 'notes'];

    protected function casts(): array
    {
        return ['effective_from' => 'datetime'];
    }

    public function rates()
    {
        return $this->hasMany(DeliveryRate::class);
    }
}
