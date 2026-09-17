<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductImage extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['path', 'alt', 'position', 'is_primary'];

    protected function casts(): array
    {
        return ['position' => 'integer', 'is_primary' => 'boolean'];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
