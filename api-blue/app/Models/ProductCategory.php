<?php

namespace App\Models;

use App\Traits\UUID;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Testing\Fluent\Concerns\Has;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductCategory extends Model
{
    use UUID, HasFactory;
    protected $fillable = [
        'parent_id',
        'image',
        'name',
        'slug',
        'tagline',
        'description'
    ];

    protected $appends = [];

    // ✅ TAMBAHKAN INI - Cast withCount sebagai integer
    protected function casts(): array
    {
        return [
            'product_count' => 'integer',
            'children_count' => 'integer',
        ];
    }

    public function scopeSearch($query, $search)
    {
        return $query->where('name', 'like', '%' . $search . '%');
    }

    public function parent()
    {
        return $this->belongsTo(ProductCategory::class, 'parent_id', 'id');
    }

    public function childrens()
    {
        return $this->hasMany(ProductCategory::class, 'parent_id', 'id');
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
