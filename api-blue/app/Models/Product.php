<?php

namespace App\Models;

use App\Traits\UUID;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use MongoDB\Laravel\Eloquent\HybridRelations;

class Product extends Model
{
    use UUID, HasFactory, HybridRelations;

    protected $fillable = [
        'store_id',
        'product_category_id',
        'name',
        'slug',
        'description',
        'condition',
        'price',
        'weight',
        'stock',
        'has_variants'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'weight' => 'decimal:2',
        'stock' => 'integer',
        'has_variants' => 'boolean',
    ];

    public function scopeSearch($query, $search)
    {
        return $query->where('name', 'like', '%' . $search . '%');
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function productCategory()
    {
        return $this->belongsTo(ProductCategory::class);
    }

    public function productImages()
    {
        return $this->hasMany(ProductImage::class);
    }

    public function transactionDetails()
    {
        return $this->hasMany(TransactionDetail::class);
    }

    public function productReviews()
    {
        return $this->hasMany(ProductReview::class);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariantMongo::class, 'product_id', 'id');
    }

    public function getTotalSoldAttribute()
    {
        return $this->transactionDetails()
            ->whereHas('transaction', function ($q) {
                $q->where('payment_status', 'paid');
            })
            ->sum('qty');
    }
}
