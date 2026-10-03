<?php

namespace App\Models;

use App\Support\PostgresSearch;
use App\Traits\UUID;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory, UUID;

    protected $fillable = [
        'code',
        'payment_code',
        'buyer_id',
        'store_id',
        'address',
        'address_id',
        'city',
        'postal_code',
        'dest_latitude',
        'dest_longitude',
        'shipping',
        'shipping_type',
        'shipping_cost',
        'tracking_number',
        'delivery_proof',
        'delivery_status',
        'tax',
        'service_fee',
        'grand_total',
        'payment_status',
        'refund_status',
        'refund_method',
        'refund_amount',
        'refund_reason',
        'refund_note',
        'refund_bank_name',
        'refund_account_number',
        'refund_account_name',
        'refunded_at',
        'receiving_proof',
        'admin_fee',
        'seller_amount',
        'voucher_id',
        'discount_amount',
    ];

    protected $casts = [
        'shipping_cost' => 'decimal:2',
        'tax' => 'decimal:2',
        'service_fee' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'admin_fee' => 'decimal:2',
        'seller_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'stock_restored_at' => 'datetime',
        'refund_amount' => 'decimal:2',
        'refund_account_number' => 'encrypted',
        'refunded_at' => 'datetime',
    ];

    public function scopeSearch($query, $search)
    {
        return $query->where('code', PostgresSearch::likeOperator(), '%'.$search.'%');
    }

    /** The Midtrans order_id this transaction was paid under. */
    public function paymentCode(): string
    {
        return $this->payment_code ?? $this->code;
    }

    /**
     * Every transaction paid under one Midtrans order_id: the orders of a
     * multi-store checkout, or a single-store transaction by its own code.
     */
    public function scopeInPayment($query, string $paymentCode)
    {
        return $query->where(fn ($q) => $q->where('payment_code', $paymentCode)
            ->orWhere(fn ($q) => $q->whereNull('payment_code')->where('code', $paymentCode)));
    }

    public function buyer()
    {
        return $this->belongsTo(Buyer::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function transactionDetails()
    {
        return $this->hasMany(TransactionDetail::class);
    }

    public function productReviews()
    {
        return $this->hasMany(ProductReview::class);
    }

    public function voucher()
    {
        return $this->belongsTo(Voucher::class);
    }
}
