<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductCommercialSetting extends Model
{
    protected $fillable = ['preferred_offer_id', 'multiplier_units'];

    protected function casts(): array
    {
        return ['multiplier_units' => 'integer'];
    }
}
