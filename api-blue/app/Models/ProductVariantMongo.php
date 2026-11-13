<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * @property string $product_id
 * @property numeric-string $price
 * @property numeric-string|null $compare_at_price
 * @property int $stock
 */
class ProductVariantMongo extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'product_variants';

    protected $fillable = [
        'product_id',
        'name',
        'variant_attributes', // JSON/Array field: e.g. { "Color": "Red", "Size": "M" }
        'price',
        'compare_at_price',
        'stock',
        'sku',
        'image',
    ];

    protected $casts = [
        'variant_attributes' => 'array',
        'product_id' => 'string',
        'price' => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'stock' => 'integer',
    ];
}
