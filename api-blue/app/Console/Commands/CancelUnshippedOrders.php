<?php

namespace App\Console\Commands;

use App\Events\TransactionStatusUpdated;
use App\Interfaces\TransactionRepositoryInterface;
use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class CancelUnshippedOrders extends Command
{
    protected $signature = 'transaction:cancel-unshipped';

    protected $description = 'Cancel and refund paid orders the seller did not ship before ship_deadline_at.';

    public const REASON = 'Penjual tidak mengirim pesanan sebelum batas waktu';

    public function handle(TransactionRepositoryInterface $transactionRepository): void
    {
        // Catch-up: orders paid by a path that set no deadline (old code still
        // serving during a deploy, orders paid before this feature) get a full one now.
        self::unshipped()->whereNull('ship_deadline_at')
            ->update(['ship_deadline_at' => now()->addDays(config('marketplace.ship_deadline_days'))]);

        // Keyset by id over the whole due set: an order that keeps failing is
        // tried once per run and cannot block the ones after it.
        $due = self::unshipped()->where('ship_deadline_at', '<=', now())->select('id');

        foreach ($due->lazyById(200) as $row) {
            try {
                // Re-checked under the lock: a seller who shipped meanwhile wins.
                $transaction = $transactionRepository->cancelPaidOrder($row->id, self::REASON);
            } catch (\Throwable $e) {
                // Only a state change (seller shipped or cancelled first) is a
                // skip. A 422 from EscrowRepository::refund() (missing wallet or
                // ledger) on a still-eligible order is a real failure.
                $stillDue = self::unshipped()->whereKey($row->id)->exists();
                Log::log($stillDue ? 'error' : 'info', 'transaction:cancel-unshipped '.($stillDue ? 'failed' : 'skipped'),
                    ['transaction' => $row->id, 'error' => $e->getMessage()]);

                continue;
            }

            // Committed: a broadcast/push failure must not look like a skip, and
            // must not retry the refund.
            try {
                event(new TransactionStatusUpdated($transaction));
            } catch (\Throwable $e) {
                Log::error('transaction:cancel-unshipped notify failed', ['transaction' => $transaction->code, 'error' => $e->getMessage()]);
            }

            $this->info("Cancelled unshipped order {$transaction->code}");
        }
    }

    /** @return Builder<Transaction> */
    private static function unshipped(): Builder
    {
        return Transaction::where('payment_status', 'paid')->whereIn('delivery_status', ['pending', 'processing']);
    }
}
