<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Repositories\TransactionRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckTransactionExpiry extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transaction:check-expiry';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for pending transactions older than 15 minutes and mark them as failed, restoring stock.';

    /**
     * Execute the console command.
     */
    public function handle(TransactionRepository $transactionRepository)
    {
        $this->info('Checking for expired transactions...');
        Log::info('SCHEDULER: Checking for expired transactions...');

        $expiredTransactions = Transaction::whereIn('payment_status', ['pending', 'unpaid'])
            ->where('created_at', '<=', now()->subMinutes(15))
            ->get();

        if ($expiredTransactions->isEmpty()) {
            $this->info('No expired transactions found.');
            return;
        }

        foreach ($expiredTransactions as $transaction) {
            try {
                $this->info("Processing Expired Transaction: {$transaction->code}");
                Log::info("SCHEDULER: Expiring Transaction {$transaction->code}");

                // 1. Mark as Failed
                $transaction->payment_status = 'failed';
                $transaction->save();

                // 2. Restore Stock
                $transactionRepository->restoreStock($transaction);

                $this->info("Transaction {$transaction->code} expired and stock restored.");
            } catch (\Exception $e) {
                Log::error("SCHEDULER ERROR processing {$transaction->code}: " . $e->getMessage());
                $this->error("Error processing {$transaction->code}: " . $e->getMessage());
            }
        }

        $this->info('Expiry check completed.');
    }
}
