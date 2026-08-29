<?php

namespace App\Http\Controllers;

use App\Events\TransactionStatusUpdated;
use App\Interfaces\EscrowRepositoryInterface;
use App\Interfaces\TransactionRepositoryInterface;
use App\Models\Transaction;
use App\Services\MidtransPaymentStatusInterpreter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MidtransController extends Controller
{
    protected $transactionRepository;

    protected EscrowRepositoryInterface $escrowRepository;

    public function __construct(
        TransactionRepositoryInterface $transactionRepository,
        EscrowRepositoryInterface $escrowRepository
    ) {
        $this->transactionRepository = $transactionRepository;
        $this->escrowRepository = $escrowRepository;
    }

    public function callback(Request $request)
    {
        // load server key from config (matches config/midtrans.php)
        $serverKey = config('midtrans.serverKey');

        // compute signature using Midtrans formula: order_id + status_code + gross_amount + server_key
        $hashedKey = hash('sha512', ($request->order_id ?? '').($request->status_code ?? '').($request->gross_amount ?? '').($serverKey ?? ''));

        Log::info('Midtrans callback received', [
            'order_id' => $request->order_id ?? null,
            'status_code' => $request->status_code ?? null,
            'transaction_status' => $request->transaction_status ?? null,
        ]);

        if ($hashedKey !== ($request->signature_key ?? '')) {
            Log::warning('Midtrans signature mismatch', [
                'computed' => $hashedKey,
                'received' => $request->signature_key ?? null,
            ]);

            return response()->json(['message' => 'Invalid signature key'], 403);
        }

        $transactionCode = $request->order_id;

        // Semua pembacaan dan penulisan berada dalam SATU transaksi database,
        // dengan lock baris di dalamnya.
        //
        // Sebelumnya lockForUpdate() dipanggil di luar transaksi mana pun. Di
        // MySQL, SELECT ... FOR UPDATE pada mode autocommit melepas lock-nya
        // begitu statement selesai, jadi lock itu tidak menahan apa pun. Dua
        // webhook yang datang berdekatan sama-sama membaca payment_status
        // "unpaid", sama-sama lolos guard duplikat, lalu sama-sama mengkredit
        // saldo penjual.
        $events = [];
        // DB::transaction(closure) TIDAK dipakai di sini dengan sengaja --
        // ia rollback SQL (melepas row lock Transaction) SEBELUM exception
        // sampai ke catch di luar, jadi kompensasi Mongo yang menunggu di
        // situ selalu terlambat: request lain sudah bisa mengunci baris
        // yang sama dan membaca stok Mongo yang belum dikompensasi.
        // beginTransaction()/commit()/rollBack() manual di sini menjamin
        // kompensasi jalan SAAT lock masih dipegang -- lihat docblock
        // TransactionRepository::restoreStock().
        $mongoAdjustments = [];
        $outcome = null;
        DB::beginTransaction();

        try {
            $transaction = Transaction::where('code', $transactionCode)->lockForUpdate()->first();

            if (! $transaction) {
                $outcome = 'not_found';
            } else {
                // Pertahanan berlapis: signature sudah mencakup nominal, tapi
                // cocokkan lagi dengan yang tersimpan.
                $expectedAmount = (int) round((float) $transaction->grand_total);
                $receivedAmount = (int) round((float) ($request->gross_amount ?? 0));

                if ($expectedAmount !== $receivedAmount) {
                    Log::error('Midtrans amount mismatch', [
                        'expected' => $expectedAmount,
                        'received' => $receivedAmount,
                        'transaction' => $transactionCode,
                    ]);

                    $outcome = 'amount_mismatch';
                } else {
                    $newStatus = MidtransPaymentStatusInterpreter::interpret(
                        $request->transaction_status,
                        $request->payment_type,
                        $request->fraud_status
                    );

                    // Webhook tidak selalu datang berurutan. Transaksi yang
                    // sudah dibayar tidak boleh mundur: webhook "failed" yang
                    // telat dulu bisa menimpanya menjadi failed lalu
                    // mengembalikan stok, padahal saldo penjual sudah
                    // terlanjur dikredit.
                    if ($newStatus === null) {
                        $outcome = 'ignored';
                    } elseif ($transaction->payment_status === 'paid' && $newStatus !== 'paid') {
                        Log::warning('Webhook telat diabaikan: transaksi sudah dibayar', [
                            'transaction' => $transactionCode,
                            'status_diminta' => $newStatus,
                        ]);

                        $outcome = 'ignored';
                    } elseif ($newStatus === 'paid' && $transaction->payment_status === 'paid') {
                        Log::info('Duplicate webhook ignored for: '.$transactionCode);

                        $outcome = 'ignored';
                    } else {
                        if ($newStatus === 'paid') {
                            $transaction->update(['payment_status' => 'paid']);
                            $this->escrowRepository->credit($transaction);
                        } elseif ($newStatus === 'unpaid') {
                            $transaction->update(['payment_status' => 'unpaid']);
                        } elseif ($newStatus === 'failed') {
                            $transaction->update(['payment_status' => 'failed']);
                            $this->transactionRepository->restoreStock($transaction, $mongoAdjustments);
                        }

                        // Event ditahan sampai commit. Dipancarkan di dalam
                        // transaksi, pendengarnya bisa menyiarkan status yang
                        // ternyata di-rollback.
                        $events[] = new TransactionStatusUpdated($transaction->fresh());

                        $outcome = 'updated';
                    }
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            // Kompensasi SEBELUM rollback -- lihat docblock restoreStock().
            $this->transactionRepository->compensateStockRestoreRollback($mongoAdjustments);
            DB::rollBack();
            Log::error('Midtrans callback gagal setelah restoreStock() -- Mongo dikompensasi', [
                'transaction' => $transactionCode,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        foreach ($events as $event) {
            event($event);
        }

        if ($outcome === 'not_found') {
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        if ($outcome === 'amount_mismatch') {
            return response()->json(['message' => 'Amount mismatch'], 403);
        }

        // always return 200 after processing so Midtrans considers callback successful
        return response()->json(['message' => 'Payment Status updated successfully'], 200);
    }
}
