<?php

namespace App\Models;

use App\Traits\UUID;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreBalanceHistory extends Model
{
    use UUID, HasFactory;

    /**
     * History types for escrow-based payment flow:
     * 
     * - income          : (LEGACY) Dana langsung masuk ke available balance
     * - expense         : (LEGACY) Potongan dari available balance (admin fee)
     * - pending_income  : Dana masuk ke pending_balance (escrow hold)
     * - released        : Dana dirilis dari pending ke available (pesanan selesai)
     * - refunded        : Dana dikembalikan dari pending (pembatalan/refund)
     * - withdrawal      : Pencairan saldo ke rekening seller
     */
    const TYPE_INCOME = 'income';
    const TYPE_EXPENSE = 'expense';
    const TYPE_PENDING_INCOME = 'pending_income';
    const TYPE_RELEASED = 'released';
    const TYPE_REFUNDED = 'refunded';
    const TYPE_WITHDRAWAL = 'withdrawal';

    protected $fillable = [
        'store_balance_id',
        'type',
        'reference_id',
        'reference_type',
        'amount',
        'remarks',
    ];

    protected $casts = [
        'amount' => 'decimal:2'
    ];

    /**
     * Check if this is an escrow-related history entry.
     */
    public function isEscrowRelated(): bool
    {
        return in_array($this->type, [
            self::TYPE_PENDING_INCOME,
            self::TYPE_RELEASED,
            self::TYPE_REFUNDED,
        ]);
    }

    /**
     * Scope: filter only pending (escrow held) entries.
     */
    public function scopePending($query)
    {
        return $query->where('type', self::TYPE_PENDING_INCOME);
    }

    /**
     * Scope: filter only released entries.
     */
    public function scopeReleased($query)
    {
        return $query->where('type', self::TYPE_RELEASED);
    }

    /**
     * Scope: filter only refunded entries.
     */
    public function scopeRefunded($query)
    {
        return $query->where('type', self::TYPE_REFUNDED);
    }

    public function storeBalance()
    {
        return $this->belongsTo(StoreBalance::class);
    }
}
