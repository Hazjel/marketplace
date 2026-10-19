<?php

namespace App\Models;

use App\Traits\UUID;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductReview extends Model
{
    use UUID;

    protected $fillable = [
        'transaction_id',
        'product_id',
        'user_id',
        'rating',
        'review',
        'is_anonymous',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasMany<ProductReviewAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(ProductReviewAttachment::class);
    }
}
