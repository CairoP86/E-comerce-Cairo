<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $fillable = ['code', 'name', 'active', 'notes'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function offers()
    {
        return $this->hasMany(SupplierProduct::class);
    }
}
