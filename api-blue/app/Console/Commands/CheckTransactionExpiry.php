<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Repositories\TransactionRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
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
            // DB::transaction(closure) TIDAK dipakai di sini dengan sengaja --
            // ia rollback SQL (melepas Product/Transaction lock) SEBELUM
            // exception sampai ke catch di luar, jadi kompensasi Mongo di
            // catch itu selalu terlambat: caller lain sudah bisa mengunci
            // Product yang sama dan membaca stok Mongo yang belum
            // dikompensasi. beginTransaction()/commit()/rollBack() manual di
            // sini menjamin kompensasi jalan SAAT lock masih dipegang --
            // lihat docblock restoreStock().
            $mongoAdjustments = [];
            DB::beginTransaction();

            try {
                $this->info("Processing Expired Transaction: {$transaction->code}");
                Log::info("SCHEDULER: Expiring Transaction {$transaction->code}");

                // Kandidat di atas diambil tanpa lock. Sebelum benar-benar
                // menandai failed + mengembalikan stok, kunci baris ini dan
                // baca ulang -- kalau webhook Midtrans atau checkPaymentStatus
                // manual sudah mengubahnya jadi paid di antara query di atas
                // dan baris ini, jangan sampai scheduler menimpanya balik
                // jadi failed dan mengembalikan stok barang yang sudah terjual.
                $locked = Transaction::where('id', $transaction->id)->lockForUpdate()->first();

                if ($locked && in_array($locked->payment_status, ['pending', 'unpaid'])) {
                    $locked->payment_status = 'failed';
                    $locked->save();

                    $transactionRepository->restoreStock($locked, $mongoAdjustments);
                }

                DB::commit();

                $this->info("Transaction {$transaction->code} expired and stock restored.");
            } catch (\Throwable $e) {
                $transactionRepository->compensateStockRestoreRollback($mongoAdjustments);
                DB::rollBack();
                Log::error("SCHEDULER ERROR processing {$transaction->code}: ".$e->getMessage());
                $this->error("Error processing {$transaction->code}: ".$e->getMessage());
            }
        }

        $this->info('Expiry check completed.');
    }
}
