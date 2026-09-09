<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Traits\UUID;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionDetail extends Model
{
    use HasFactory, UUID;

    protected $fillable = [
        'transaction_id',
        'product_id',
        'variant_id',
        'qty',
        'subtotal',
    ];

    protected $casts = [
        // B3.1 pilot: subtotal is a Money value object. The column stays
        // decimal(26,2); MoneyCast bridges it. docs/money-contract.md.
        'subtotal' => MoneyCast::class,
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
