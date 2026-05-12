<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Models\Store;
use App\Models\StoreBalance;
use App\Repositories\StoreBalanceRepository;
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
    public function handle()
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

        $storeBalanceRepository = new StoreBalanceRepository;

        foreach ($transactions as $transaction) {
            try {
                $this->info("Auto-completing transaction: {$transaction->code}");
                Log::info("SCHEDULER: Auto-completing transaction {$transaction->code}");

                // 1. Update delivery status ke completed
                $transaction->delivery_status = 'completed';
                $transaction->save();

                // 2. Release escrow: pindahkan pending_balance ke available balance
                $store = Store::find($transaction->store_id);

                if ($store && $store->storeBalance) {
                    $netSales = $transaction->grand_total - $transaction->shipping_cost;
                    $adminFee = $netSales * 0.10;
                    $sellerAmount = $netSales - $adminFee;

                    $storeBalanceRepository->releasePending($store->storeBalance->id, $sellerAmount);

                    // 3. Catat history
                    $store->storeBalance->storeBalanceHistories()->create([
                        'type' => 'released',
                        'reference_id' => $transaction->id,
                        'reference_type' => Transaction::class,
                        'amount' => $sellerAmount,
                        'remarks' => 'Dana dirilis otomatis (auto-complete 7 hari) — pesanan ' . $transaction->code,
                    ]);

                    $this->info("Escrow released for {$transaction->code}: Rp " . number_format($sellerAmount));
                    Log::info("SCHEDULER: Escrow released for {$transaction->code}", [
                        'seller_amount' => $sellerAmount,
                        'store_id' => $store->id,
                    ]);
                } else {
                    Log::error("SCHEDULER: Store or StoreBalance not found for transaction {$transaction->code}");
                    $this->error("Store balance not found for transaction {$transaction->code}");
                }
            } catch (\Exception $e) {
                Log::error("SCHEDULER ERROR auto-completing {$transaction->code}: " . $e->getMessage());
                $this->error("Error processing {$transaction->code}: " . $e->getMessage());
            }
        }

        $this->info('Auto-complete check finished.');
    }
}
