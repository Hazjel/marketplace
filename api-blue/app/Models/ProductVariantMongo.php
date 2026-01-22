<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class ProductVariantMongo extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'product_variants';

    protected $fillable = [
        'product_id',
        'name',
        'variant_attributes', // JSON/Array field: e.g. { "Color": "Red", "Size": "M" }
        'price',
        'stock',
        'sku',
        'image'
    ];

    protected $casts = [
        'variant_attributes' => 'array',
        'product_id' => 'string',
        'price' => 'decimal:2',
        'stock' => 'integer',
    ];
}
