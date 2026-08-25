<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya tempat "hapus akun" didefinisikan.
 *
 * Sebelum ini, AuthRepository::deleteAccount() (self-service) dan
 * UserRepository::delete() (admin) sama-sama berujung ke $user->delete(),
 * tapi hanya jalur self-service yang ikut menonaktifkan toko. Admin yang
 * menghapus seller lewat UserController::destroy() akan meninggalkan
 * stores.is_active tetap true -- soft-delete-nya sudah aman dari cascade
 * finansial (lihat migrasi deleted_at di users), tapi tokonya tetap
 * tampil dan bisa dibeli meski pemiliknya sudah tidak punya akun.
 *
 * Dua semantics untuk satu operasi seperti itu tinggal menunggu satu
 * jalur lagi ditambahkan (mis. bulk admin action) yang lupa meniru
 * langkah penonaktifan toko. Disatukan di sini supaya cuma ada satu
 * tempat yang perlu diingat.
 */
class AccountDeletionService
{
    public function delete(User $user): User
    {
        return DB::transaction(function () use ($user) {
            $user->tokens()->delete();

            if ($user->store) {
                $user->store->update(['is_active' => false]);
            }

            // SoftDeletes: tidak mengeluarkan SQL DELETE, jadi FK cascade
            // dari stores/buyers ke products/transactions/store_balance_
            // histories/withdrawals tidak pernah terpicu. Lihat migrasi
            // 2026_08_25_000000_add_deleted_at_to_users_table.
            $user->delete();

            return $user;
        });
    }
}
