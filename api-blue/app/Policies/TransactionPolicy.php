<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;

/**
 * Satu-satunya sumber kebenaran untuk "siapa boleh menyentuh transaksi ini".
 *
 * Sebelumnya aturan ini disalin ke dalam beberapa aksi controller dengan
 * bentuk yang sedikit berbeda-beda, dan tiga aksi tidak memilikinya sama
 * sekali — sehingga transaksi milik toko lain bisa dibaca, diubah, dan
 * dihapus hanya dengan menebak id-nya.
 */
class TransactionPolicy
{
    /**
     * Pembeli dan penjual dari transaksi yang sama bisa jadi orang yang sama
     * (akun dual-role ala Shopee), jadi kepemilikan diperiksa secara OR, bukan
     * saling meniadakan.
     */
    public function view(User $user, Transaction $transaction): bool
    {
        return $this->ownsAsBuyer($user, $transaction)
            || $this->ownsAsStore($user, $transaction)
            || $this->isAdmin($user);
    }

    /**
     * Update di sini berarti aksi pengiriman milik penjual: nomor resi, bukti
     * kirim, dan status pengiriman. Pembeli tidak ikut, karena pembeli punya
     * jalurnya sendiri lewat complete().
     */
    public function update(User $user, Transaction $transaction): bool
    {
        return $this->ownsAsStore($user, $transaction) || $this->isAdmin($user);
    }

    /**
     * Menyelesaikan pesanan melepas dana escrow ke penjual, jadi hanya pembeli
     * pemilik transaksi yang boleh — bukan penjual, dan bukan admin.
     */
    public function complete(User $user, Transaction $transaction): bool
    {
        return $this->ownsAsBuyer($user, $transaction);
    }

    public function checkPaymentStatus(User $user, Transaction $transaction): bool
    {
        return $this->view($user, $transaction);
    }

    public function delete(User $user, Transaction $transaction): bool
    {
        return $this->ownsAsStore($user, $transaction) || $this->isAdmin($user);
    }

    /**
     * Perbandingan id dijaga terhadap null di kedua sisi: user tanpa profil
     * buyer dan transaksi tanpa buyer_id sama-sama menghasilkan null, dan
     * null === null akan meloloskan orang yang salah.
     */
    private function ownsAsBuyer(User $user, Transaction $transaction): bool
    {
        $buyerId = $user->buyer?->id;

        return $buyerId !== null && $transaction->buyer_id === $buyerId;
    }

    private function ownsAsStore(User $user, Transaction $transaction): bool
    {
        $storeId = $user->store?->id;

        return $storeId !== null && $transaction->store_id === $storeId;
    }

    private function isAdmin(User $user): bool
    {
        return $user->hasRole('admin');
    }
}
