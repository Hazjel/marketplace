<?php

namespace App\Models;

use App\Traits\UUID;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductReviewAttachment extends Model
{
    use UUID, HasFactory;

    protected $fillable = [
        'product_review_id',
        'file_path',
        'file_type'
    ];

    public function productReview()
    {
        return $this->belongsTo(ProductReview::class);
    }
}
