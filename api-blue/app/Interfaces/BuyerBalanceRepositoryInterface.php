<?php

namespace App\Interfaces;

use Illuminate\Database\Eloquent\Model;

/**
 * Saldo Blukios ledger.
 *
 * Inside a caller's DB transaction, credit/debit run as a savepoint of it.
 * Callers must let their exceptions propagate: catching one and committing
 * leaves a half-done checkout or refund. Lock order: callers lock their
 * transactions/products first and the buyer row (taken here) last.
 *
 * A repeated $uniqueRef for the same buyer, type and amount returns false
 * (already applied); reusing it for a different mutation throws LogicException.
 */
interface BuyerBalanceRepositoryInterface
{
    /**
     * Add to Saldo Blukios. False when $uniqueRef was already applied.
     */
    public function credit(string $buyerId, string $amount, string $type, string $uniqueRef, ?Model $reference = null, ?string $remarks = null): bool;

    /**
     * Take from Saldo Blukios. False when $uniqueRef was already applied;
     * throws (422) when the balance is short.
     */
    public function debit(string $buyerId, string $amount, string $type, string $uniqueRef, ?Model $reference = null, ?string $remarks = null): bool;
}
