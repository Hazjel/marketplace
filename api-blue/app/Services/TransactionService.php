<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\Store;
use App\Repositories\StoreBalanceRepository;
use Illuminate\Support\Facades\Log;

class TransactionService
{
    protected StoreBalanceRepository $storeBalanceRepository;

    public function __construct(StoreBalanceRepository $storeBalanceRepository)
    {
        $this->storeBalanceRepository = $storeBalanceRepository;
    }

    /**
     * Release escrow: move funds from pending_balance to available balance.
     * Called when buyer confirms receipt or auto-complete triggers.
     */
    public function releaseEscrow(Transaction $transaction): void
    {
        try {
            $store = Store::find($transaction->store_id);

            if (!$store || !$store->storeBalance) {
                Log::error('releaseEscrow: Store or StoreBalance not found', [
                    'store_id' => $transaction->store_id,
                ]);
                return;
            }

            $amounts = $this->calculateSellerAmount($transaction);
            $sellerAmount = $amounts['seller_amount'];

            // Pindahkan dari pending ke available
            $this->storeBalanceRepository->releasePending($store->storeBalance->id, $sellerAmount);

            // Catat history: dana dirilis
            $store->storeBalance->storeBalanceHistories()->create([
                'type' => 'released',
                'reference_id' => $transaction->id,
                'reference_type' => Transaction::class,
                'amount' => $sellerAmount,
                'remarks' => 'Dana dirilis ke saldo tersedia — pesanan ' . $transaction->code . ' selesai',
            ]);

            Log::info('Escrow released for transaction: ' . $transaction->code, [
                'store_id' => $store->id,
                'seller_amount' => $sellerAmount,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error releasing escrow balance: ' . $e->getMessage(), [
                'transaction_id' => $transaction->id,
            ]);
        }
    }

    /**
     * Credit escrow: add funds to pending_balance when payment is confirmed.
     */
    public function creditEscrow(Transaction $transaction): void
    {
        try {
            $store = Store::find($transaction->store_id);

            if (!$store || !$store->storeBalance) {
                Log::error('creditEscrow: Store or StoreBalance not found', [
                    'store_id' => $transaction->store_id,
                ]);
                return;
            }

            $amounts = $this->calculateSellerAmount($transaction);
            $sellerAmount = $amounts['seller_amount'];
            $adminFee = $amounts['admin_fee'];

            // Update admin_fee on transaction
            $transaction->admin_fee = $adminFee;
            $transaction->save();

            // Credit ke pending_balance (escrow)
            $this->storeBalanceRepository->creditPending($store->storeBalance->id, $sellerAmount);

            // Catat history: dana ditahan
            $store->storeBalance->storeBalanceHistories()->create([
                'type' => 'pending_income',
                'reference_id' => $transaction->id,
                'reference_type' => Transaction::class,
                'amount' => $sellerAmount,
                'remarks' => 'Pembayaran diterima (ditahan) dari transaksi ' . $transaction->code . ' — akan dirilis setelah pesanan selesai',
            ]);

            Log::info('Escrow credited for transaction: ' . $transaction->code, [
                'store_id' => $store->id,
                'seller_amount' => $sellerAmount,
                'admin_fee' => $adminFee,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error crediting escrow balance: ' . $e->getMessage(), [
                'transaction_id' => $transaction->id,
            ]);
        }
    }

    /**
     * Calculate seller amount after admin fee deduction.
     */
    public function calculateSellerAmount(Transaction $transaction): array
    {
        $netSales = $transaction->grand_total - $transaction->shipping_cost;
        $adminFee = $netSales * 0.10;
        $sellerAmount = $netSales - $adminFee;

        return [
            'net_sales' => $netSales,
            'admin_fee' => $adminFee,
            'seller_amount' => $sellerAmount,
        ];
    }

    /**
     * Resolve Midtrans transaction status to internal payment status.
     */
    public function resolveMidtransStatus(object $midtransStatus): string
    {
        $transactionStatus = $midtransStatus->transaction_status;
        $paymentType = $midtransStatus->payment_type;
        $fraudStatus = $midtransStatus->fraud_status ?? null;

        if ($transactionStatus == 'capture') {
            if ($paymentType == 'credit_card') {
                return $fraudStatus == 'challenge' ? 'unpaid' : 'paid';
            }
        }

        return match ($transactionStatus) {
            'settlement' => 'paid',
            'pending' => 'unpaid',
            'deny', 'expire', 'cancel' => 'failed',
            default => 'unpaid',
        };
    }
}
