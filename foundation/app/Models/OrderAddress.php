<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderAddress extends Model
{
    public $timestamps = false;

    protected $fillable = ['country_code', 'province_code', 'canton_code', 'district_code', 'province', 'canton', 'district', 'territory_version', 'exact_address', 'additional'];
}
