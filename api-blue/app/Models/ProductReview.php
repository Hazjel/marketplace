<?php

namespace App\Models;

use App\Traits\UUID;
use Illuminate\Database\Eloquent\Model;

class ProductReview extends Model
{
    use UUID;

    protected $fillable = [
        'transaction_id',
        'product_id',
        'user_id',
        'rating',
        'review',
        'is_anonymous'
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function attachments()
    {
        return $this->hasMany(ProductReviewAttachment::class);
    }
}
