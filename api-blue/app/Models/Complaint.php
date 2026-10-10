<?php

namespace App\Models;

use App\Traits\UUID;
use Illuminate\Database\Eloquent\Model;

/**
 * Buyer's complaint about a delivering order. open -> seller accepts
 * (approved, full refund) or rejects / lets the 2-day deadline pass
 * (escalated) -> admin approves or rejects. The buyer can withdraw while
 * open or escalated.
 */
class Complaint extends Model
{
    use UUID;

    const REASONS = ['not_received', 'damaged', 'wrong_item', 'other'];

    /** Statuses that block completing the order. */
    const ACTIVE = ['open', 'escalated'];

    protected $fillable = [
        'transaction_id',
        'reason',
        'description',
        'photos',
        'status',
        'seller_response',
        'admin_note',
        'deadline_at',
        'escalated_at',
        'resolved_at',
        'resolved_by',
    ];

    protected $casts = [
        'photos' => 'array',
        'deadline_at' => 'datetime',
        'escalated_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    /** Read under the order's row lock: every complaint change takes it first. */
    public static function activeFor(string $transactionId): bool
    {
        return self::where('transaction_id', $transactionId)->whereIn('status', self::ACTIVE)->exists();
    }
}
