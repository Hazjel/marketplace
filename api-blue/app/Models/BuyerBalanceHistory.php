<?php

namespace App\Models;

use App\Traits\UUID;
use Illuminate\Database\Eloquent\Model;

/**
 * One Saldo Blukios mutation. `amount` is signed: + credit, - debit.
 */
class BuyerBalanceHistory extends Model
{
    use UUID;

    const TYPE_REFUND = 'refund';

    const TYPE_PAYMENT = 'payment';

    const TYPE_PAYMENT_RETURNED = 'payment_returned';

    const TYPES = [self::TYPE_REFUND, self::TYPE_PAYMENT, self::TYPE_PAYMENT_RETURNED];

    protected $fillable = [
        'buyer_id',
        'type',
        'amount',
        'reference_type',
        'reference_id',
        'unique_ref',
        'remarks',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function buyer()
    {
        return $this->belongsTo(Buyer::class);
    }
}
