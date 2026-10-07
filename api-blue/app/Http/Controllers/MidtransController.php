<?php

namespace App\Http\Controllers;

use App\Events\TransactionStatusUpdated;
use App\Interfaces\EscrowRepositoryInterface;
use App\Interfaces\TransactionRepositoryInterface;
use App\Models\Transaction;
use App\Services\MidtransPaymentStatusInterpreter;
use App\Support\BusinessMetrics;
use App\Support\OpsSignals;
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

        if (! hash_equals($hashedKey, (string) ($request->signature_key ?? ''))) {
            // Never log $hashedKey: it is the valid signature for the order_id,
            // status_code and gross_amount the caller chose, so anyone who can
            // read the logs could replay it to mark that order paid.
            Log::warning('Midtrans signature mismatch', [
                'order_id' => $request->order_id ?? null,
            ]);

            OpsSignals::record(OpsSignals::MIDTRANS_REJECTED);
            BusinessMetrics::record('webhook_rejected', 'signature');

            return response()->json(['message' => 'Invalid signature key'], 403);
        }

        $transactionCode = $request->order_id;

        // Semua pembacaan dan penulisan berada dalam SATU transaksi database,
        // dengan lock baris di dalamnya.
        //
        // Sebelumnya lockForUpdate() dipanggil di luar transaksi mana pun. Di
        // Postgres, SELECT ... FOR UPDATE pada mode autocommit melepas lock-nya
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
        // [event, detail] for BusinessMetrics, recorded only after commit.
        $metric = null;
        DB::beginTransaction();

        try {
            // Every transaction paid under this order_id: one, or all orders
            // of a multi-store checkout. Locked together in id order.
            $group = Transaction::inPayment($transactionCode)->orderBy('id')->lockForUpdate()->get();

            if ($group->isEmpty()) {
                $outcome = 'not_found';
            } else {
                // Pertahanan berlapis: signature sudah mencakup nominal, tapi
                // cocokkan lagi dengan yang tersimpan (the sum, for a shared payment).
                $expectedAmount = (int) round((float) $group->sum('grand_total'));
                $receivedAmount = (int) round((float) ($request->gross_amount ?? 0));

                if ($expectedAmount !== $receivedAmount) {
                    Log::error('Midtrans amount mismatch', [
                        'expected' => $expectedAmount,
                        'received' => $receivedAmount,
                        'transaction' => $transactionCode,
                    ]);

                    $outcome = 'amount_mismatch';
                } else {
                    $outcomes = [];
                    foreach ($group as $transaction) {
                        $outcomes[] = $this->applyNotification($transaction, $request, $group->count() > 1, $events, $metric, $mongoAdjustments);
                    }
                    $outcome = in_array('updated', $outcomes, true) ? 'updated' : 'ignored';
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

        if ($metric !== null) {
            BusinessMetrics::record(...$metric);
        }

        if ($outcome === 'not_found') {
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        if ($outcome === 'amount_mismatch') {
            OpsSignals::record(OpsSignals::MIDTRANS_REJECTED);
            BusinessMetrics::record('webhook_rejected', 'amount');

            return response()->json(['message' => 'Amount mismatch'], 403);
        }

        // always return 200 after processing so Midtrans considers callback successful
        return response()->json(['message' => 'Payment Status updated successfully'], 200);
    }

    /**
     * The per-transaction part of a notification. A multi-store checkout is one
     * Midtrans payment for several transactions, each with its own escrow and
     * status, so the same notification is applied to every one of them.
     *
     * @param  list<object>  $events
     * @param  array<int, string>|null  $metric
     * @param  list<array{variant_id: string, qty: int}>  $mongoAdjustments
     */
    private function applyNotification(Transaction $transaction, Request $request, bool $sharedPayment, array &$events, ?array &$metric, array &$mongoAdjustments): string
    {
        $transactionCode = $transaction->code;
        $outcome = 'ignored';

        // A refund notification on a shared payment does not say which
        // order it was for; RefundCancelledTransactionJob records each
        // order's refund itself once Midtrans accepts it.
        if ($sharedPayment && $transaction->refund_status !== null) {
            return 'ignored';
        }

        if ($transaction->refund_status !== null) {
            // Dibatalkan penjual setelah dibayar. Satu-satunya kabar
            // yang berarti adalah refund-nya selesai; "settlement"
            // ulang tidak boleh mengkredit escrow lagi.
            // $transaction is locked by callback(), so this state is current.
            $refundDone = in_array($request->transaction_status, ['refund', 'partial_refund', 'cancel'], true);
            if ($refundDone && in_array($transaction->refund_status, ['processing', 'manual_required'], true)) {
                $transaction->update([
                    'refund_status' => 'refunded',
                    'refund_method' => 'midtrans',
                    'refunded_at' => now(),
                ]);
                $events[] = new TransactionStatusUpdated($transaction->fresh());
                $metric = ['refund_done', 'midtrans_webhook'];
                $outcome = 'updated';
            } else {
                if ($refundDone && $transaction->refund_method === 'balance') {
                    // The buyer was paid twice: by Midtrans and to Saldo Blukios.
                    Log::error('Refund Midtrans masuk untuk pesanan yang sudah dikembalikan ke Saldo Blukios', [
                        'transaction' => $transactionCode,
                    ]);
                    OpsSignals::record(OpsSignals::REFUND_CONFLICT, $transactionCode);
                }
                $outcome = 'ignored';
            }
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
                    $metric = ['payment_paid', (string) $request->payment_type];
                    $this->escrowRepository->credit($transaction);
                } elseif ($newStatus === 'unpaid') {
                    $transaction->update(['payment_status' => 'unpaid']);
                } elseif ($newStatus === 'failed') {
                    $transaction->update(['payment_status' => 'failed']);
                    $metric = ['payment_failed', (string) $request->transaction_status];
                    $this->transactionRepository->restoreStock($transaction, $mongoAdjustments);
                }

                // Event ditahan sampai commit. Dipancarkan di dalam
                // transaksi, pendengarnya bisa menyiarkan status yang
                // ternyata di-rollback.
                $events[] = new TransactionStatusUpdated($transaction->fresh());

                $outcome = 'updated';
            }
        }

        return $outcome;
    }
}
