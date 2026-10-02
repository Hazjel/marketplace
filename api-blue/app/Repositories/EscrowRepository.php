<?php

namespace App\Repositories;

use App\Interfaces\EscrowRepositoryInterface;
use App\Interfaces\StoreBalanceRepositoryInterface;
use App\Models\Store;
use App\Models\Transaction;
use App\ValueObjects\Money;
use Illuminate\Support\Facades\DB;
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

        // Dulu creditPending() (mutasi saldo) dan storeBalanceHistories()->create()
        // (yang membawa unique_ref) berjalan sebagai dua statement lepas.
        // Kalau caller tidak membungkusnya sendiri dalam DB::transaction --
        // dan checkPaymentStatus() manual dulu tidak -- kegagalan unique_ref
        // pada insert kedua TIDAK membatalkan mutasi saldo yang sudah
        // ter-commit di statement pertama. Dibungkus di sini supaya method
        // ini aman terlepas dari kedisiplinan caller.
        $sellerAmount = DB::transaction(function () use ($transaction, $store) {
            $sellerAmount = $this->applyAdminFee($transaction);

            $this->storeBalanceRepository->creditPending($store->storeBalance->id, $sellerAmount);

            $store->storeBalance->storeBalanceHistories()->create([
                'type' => 'pending_income',
                'reference_id' => $transaction->id,
                'reference_type' => Transaction::class,
                'unique_ref' => 'pending_income:'.$transaction->id,
                'amount' => $sellerAmount,
                'remarks' => 'Pembayaran diterima (ditahan) dari transaksi '.$transaction->code.' — akan dirilis setelah pesanan selesai',
            ]);

            return $sellerAmount;
        });

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

        $sellerAmount = DB::transaction(function () use ($transaction, $store) {
            $sellerAmount = $this->sellerAmount($transaction);

            $this->storeBalanceRepository->releasePending($store->storeBalance->id, $sellerAmount);

            $store->storeBalance->storeBalanceHistories()->create([
                'type' => 'released',
                'reference_id' => $transaction->id,
                'reference_type' => Transaction::class,
                'unique_ref' => 'released:'.$transaction->id,
                'amount' => $sellerAmount,
                'remarks' => 'Dana dirilis ke saldo tersedia — pesanan '.$transaction->code.' selesai',
            ]);

            return $sellerAmount;
        });

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

        $sellerAmount = DB::transaction(function () use ($transaction, $store) {
            $sellerAmount = $this->sellerAmount($transaction);

            $this->storeBalanceRepository->refundPending($store->storeBalance->id, $sellerAmount);

            $store->storeBalance->storeBalanceHistories()->create([
                'type' => 'refunded',
                'reference_id' => $transaction->id,
                'reference_type' => Transaction::class,
                'unique_ref' => 'refunded:'.$transaction->id,
                'amount' => -$sellerAmount,
                'remarks' => 'Escrow dibatalkan (refund) — pesanan '.$transaction->code.' dibatalkan',
            ]);

            return $sellerAmount;
        });

        Log::info('Escrow refunded for transaction: '.$transaction->code, [
            'store_id' => $store->id,
            'refunded_amount' => $sellerAmount,
        ]);
    }

    /**
     * Hitung admin_fee dan sellerAmount, kunci keduanya di transaksi, return
     * sellerAmount (rupiah bulat). Dipanggil sekali saat credit.
     *
     * goods  = grand_total - ongkir - pajak  (harga barang setelah diskon;
     *          voucher ditanggung seller, pajak hanya ada di transaksi lama)
     * fee    = goods x admin_fee_basis_points (HALF_UP), via Money
     * seller = goods - fee + ongkir  (seller yang membayar kurir)
     */
    private function applyAdminFee(Transaction $transaction): int
    {
        $shipping = Money::fromDecimalString((string) $transaction->shipping_cost);
        $goods = Money::fromDecimalString((string) $transaction->grand_total)
            ->subtract($shipping)
            ->subtract(Money::fromDecimalString((string) $transaction->tax))
            ->subtract(Money::fromDecimalString((string) ($transaction->service_fee ?? 0)));

        $adminFee = $goods->percentage((int) config('marketplace.admin_fee_basis_points'));
        $sellerAmount = $goods->subtract($adminFee)->add($shipping);

        $transaction->admin_fee = $adminFee->minor();
        $transaction->seller_amount = $sellerAmount->minor();
        $transaction->save();

        return $sellerAmount->minor();
    }

    /**
     * sellerAmount dari admin_fee yang sudah DIKUNCI saat credit — release/refund
     * tidak boleh recompute (fee bisa berbeda kalau config berubah). Membaca
     * nilai tersimpan apa adanya; tidak lewat Money supaya transaksi lama yang
     * admin_fee-nya masih pecahan (pra-B3.2d) tetap bisa dirilis.
     */
    private function sellerAmount(Transaction $transaction): float
    {
        if ($transaction->seller_amount !== null) {
            return (float) $transaction->seller_amount;
        }

        // Credited by code older than the seller_amount column, during the
        // seconds between the deploy's git reset and its migration.
        $netSales = $transaction->grand_total - $transaction->shipping_cost;

        return $netSales - $transaction->admin_fee;
    }
}
