<?php

namespace App\Models;

use App\Traits\UUID;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Payout extends Model
{
    use UUID;

    protected $fillable = [
        'payable_type',
        'payable_id',
        'reference_no',
        'amount',
        'bank_code',
        'account_number',
        'account_name',
        'status',
        'failure_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'account_number' => 'encrypted',
    ];

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }
}
