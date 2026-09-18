<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PricingRule extends Model
{
    protected $fillable = ['name', 'multiplier_units'];

    protected function casts(): array
    {
        return ['multiplier_units' => 'integer'];
    }
}
