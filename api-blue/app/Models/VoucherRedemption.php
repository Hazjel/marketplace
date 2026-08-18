<?php

namespace App\Models;

use App\Traits\UUID;
use Illuminate\Database\Eloquent\Model;

class VoucherRedemption extends Model
{
    use UUID;

    protected $fillable = [
        'voucher_id',
        'buyer_id',
        'transaction_id',
        'redeemed_at',
    ];

    protected $casts = [
        'redeemed_at' => 'datetime',
    ];

    public function voucher()
    {
        return $this->belongsTo(Voucher::class);
    }

    public function buyer()
    {
        return $this->belongsTo(Buyer::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
