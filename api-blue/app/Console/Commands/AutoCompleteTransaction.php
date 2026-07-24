<?php

namespace App\Console\Commands;

use App\Interfaces\EscrowRepositoryInterface;
use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoCompleteTransaction extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transaction:auto-complete';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-complete transactions that have been in "delivering" status for more than 7 days. Releases escrow to seller balance.';

    /**
     * Execute the console command.
     */
    public function handle(EscrowRepositoryInterface $escrowRepository)
    {
        $this->info('Checking for auto-completable transactions...');
        Log::info('SCHEDULER: Checking for auto-completable transactions...');

        // Cari transaksi yang sudah delivering lebih dari 7 hari
        $transactions = Transaction::where('payment_status', 'paid')
            ->where('delivery_status', 'delivering')
            ->where('updated_at', '<=', now()->subDays(7))
            ->get();

        if ($transactions->isEmpty()) {
            $this->info('No transactions to auto-complete.');

            return;
        }

        $this->info("Found {$transactions->count()} transaction(s) to auto-complete.");

        foreach ($transactions as $transaction) {
            try {
                $this->info("Auto-completing transaction: {$transaction->code}");
                Log::info("SCHEDULER: Auto-completing transaction {$transaction->code}");

                // 1. Update delivery status ke completed
                $transaction->delivery_status = 'completed';
                $transaction->save();

                // 2. Release escrow: pindahkan pending_balance ke available balance
                $escrowRepository->release($transaction);

                $this->info("Escrow released for {$transaction->code}");
                Log::info("SCHEDULER: Escrow released for {$transaction->code}");
            } catch (\Exception $e) {
                Log::error("SCHEDULER ERROR auto-completing {$transaction->code}: ".$e->getMessage());
                $this->error("Error processing {$transaction->code}: ".$e->getMessage());
            }
        }

        $this->info('Auto-complete check finished.');
    }
}
