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
            // Kalau restoreStock() di bawah mengubah Mongo lalu sesuatu
            // SETELAHNYA di closure ini gagal (atau closure itu sendiri
            // gagal setelah restoreStock() sukses), DB::transaction() di
            // bawah rollback SQL tapi TIDAK menyentuh Mongo -- restoreStock()
            // sendiri tidak lagi jadi compensation boundary mandiri, lihat
            // docblock-nya. Kompensasi jadi tanggung jawab try/catch ini.
            $mongoAdjustments = [];

            try {
                $this->info("Processing Expired Transaction: {$transaction->code}");
                Log::info("SCHEDULER: Expiring Transaction {$transaction->code}");

                // Kandidat di atas diambil tanpa lock. Sebelum benar-benar
                // menandai failed + mengembalikan stok, kunci baris ini dan
                // baca ulang -- kalau webhook Midtrans atau checkPaymentStatus
                // manual sudah mengubahnya jadi paid di antara query di atas
                // dan baris ini, jangan sampai scheduler menimpanya balik
                // jadi failed dan mengembalikan stok barang yang sudah terjual.
                DB::transaction(function () use ($transaction, $transactionRepository, &$mongoAdjustments) {
                    $locked = Transaction::where('id', $transaction->id)->lockForUpdate()->first();

                    if (! $locked || ! in_array($locked->payment_status, ['pending', 'unpaid'])) {
                        return;
                    }

                    $locked->payment_status = 'failed';
                    $locked->save();

                    $transactionRepository->restoreStock($locked, $mongoAdjustments);
                });

                $this->info("Transaction {$transaction->code} expired and stock restored.");
            } catch (\Throwable $e) {
                $transactionRepository->compensateStockRestoreRollback($mongoAdjustments);
                Log::error("SCHEDULER ERROR processing {$transaction->code}: ".$e->getMessage());
                $this->error("Error processing {$transaction->code}: ".$e->getMessage());
            }
        }

        $this->info('Expiry check completed.');
    }
}
