<?php

namespace App\Repositories;

use App\Interfaces\EscrowRepositoryInterface;
use App\Interfaces\StoreBalanceRepositoryInterface;
use App\Models\Store;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;

class EscrowRepository implements EscrowRepositoryInterface
{
    public function __construct(
        private StoreBalanceRepositoryInterface $storeBalanceRepository
    ) {}

    public function credit(Transaction $transaction): void
    {
        $store = Store::find($transaction->store_id);

        if (! $store || ! $store->storeBalance) {
            Log::error('EscrowRepository::credit: Store or StoreBalance not found', [
                'store_id' => $transaction->store_id,
            ]);

            return;
        }

        $sellerAmount = $this->applyAdminFee($transaction);

        $this->storeBalanceRepository->creditPending($store->storeBalance->id, $sellerAmount);

        $store->storeBalance->storeBalanceHistories()->create([
            'type' => 'pending_income',
            'reference_id' => $transaction->id,
            'reference_type' => Transaction::class,
            'amount' => $sellerAmount,
            'remarks' => 'Pembayaran diterima (ditahan) dari transaksi '.$transaction->code.' — akan dirilis setelah pesanan selesai',
        ]);

        Log::info('Pending balance credited for store: '.$store->id, [
            'seller_amount' => $sellerAmount,
            'transaction_code' => $transaction->code,
        ]);
    }

    public function release(Transaction $transaction): void
    {
        $store = Store::find($transaction->store_id);

        if (! $store || ! $store->storeBalance) {
            Log::error('EscrowRepository::release: Store or StoreBalance not found', [
                'store_id' => $transaction->store_id,
            ]);

            return;
        }

        $sellerAmount = $this->sellerAmount($transaction);

        $this->storeBalanceRepository->releasePending($store->storeBalance->id, $sellerAmount);

        $store->storeBalance->storeBalanceHistories()->create([
            'type' => 'released',
            'reference_id' => $transaction->id,
            'reference_type' => Transaction::class,
            'amount' => $sellerAmount,
            'remarks' => 'Dana dirilis ke saldo tersedia — pesanan '.$transaction->code.' selesai',
        ]);

        Log::info('Escrow released for transaction: '.$transaction->code, [
            'store_id' => $store->id,
            'seller_amount' => $sellerAmount,
        ]);
    }

    public function refund(Transaction $transaction): void
    {
        $store = Store::find($transaction->store_id);

        if (! $store || ! $store->storeBalance) {
            Log::error('EscrowRepository::refund: Store or StoreBalance not found', [
                'store_id' => $transaction->store_id,
            ]);

            return;
        }

        $sellerAmount = $this->sellerAmount($transaction);

        $this->storeBalanceRepository->refundPending($store->storeBalance->id, $sellerAmount);

        $store->storeBalance->storeBalanceHistories()->create([
            'type' => 'refunded',
            'reference_id' => $transaction->id,
            'reference_type' => Transaction::class,
            'amount' => -$sellerAmount,
            'remarks' => 'Escrow dibatalkan (refund) — pesanan '.$transaction->code.' dibatalkan',
        ]);

        Log::info('Escrow refunded for transaction: '.$transaction->code, [
            'store_id' => $store->id,
            'refunded_amount' => $sellerAmount,
        ]);
    }

    /**
     * Hitung admin_fee, simpan ke transaksi, return sellerAmount.
     * Dipanggil sekali saat credit — admin_fee dikunci di kolom transaksi.
     */
    private function applyAdminFee(Transaction $transaction): float
    {
        $netSales = $transaction->grand_total - $transaction->shipping_cost;
        $adminFee = $netSales * config('marketplace.admin_fee_percentage');
        $sellerAmount = $netSales - $adminFee;

        $transaction->admin_fee = $adminFee;
        $transaction->save();

        return $sellerAmount;
    }

    /**
     * sellerAmount dari admin_fee yang sudah dikunci saat credit (release/refund
     * tidak boleh recompute — admin_fee bisa saja beda kalau config berubah).
     */
    private function sellerAmount(Transaction $transaction): float
    {
        $netSales = $transaction->grand_total - $transaction->shipping_cost;

        return $netSales - $transaction->admin_fee;
    }
}
