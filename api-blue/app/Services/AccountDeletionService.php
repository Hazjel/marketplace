<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
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
                // ProductController::index() cache listing tanpa filter
                // 600 detik -- tanpa flush ini, katalog warm bisa tetap
                // menampilkan produk toko yang baru nonaktif sampai ±10
                // menit. Gap ini ada di SEMUA jalur hapus akun (self-service
                // maupun admin), bukan cuma StoreController::destroy().
                Cache::tags(['stores'])->flush();
                Cache::tags(['products'])->flush();
            }

            // users.email punya unique index di level DATABASE, dan MySQL
            // tidak punya partial/filtered unique index (tidak bisa
            // "unique kecuali baris yang sudah dihapus"). Soft-delete saja
            // tidak membebaskan email itu -- dikonfirmasi langsung: baris
            // ter-soft-delete tetap membuat pendaftaran kedua dengan email
            // yang sama gagal dengan UniqueConstraintViolationException,
            // bukan pesan validasi yang ramah. Tulis ulang jadi placeholder
            // yang mustahil bentrok supaya user bisa daftar ulang dengan
            // email yang sama kalau mereka mau.
            $user->update(['email' => "deleted+{$user->id}@deleted.invalid"]);

            // SoftDeletes: tidak mengeluarkan SQL DELETE, jadi FK cascade
            // dari stores/buyers ke products/transactions/store_balance_
            // histories/withdrawals tidak pernah terpicu. Lihat migrasi
            // 2026_08_25_000000_add_deleted_at_to_users_table.
            $user->delete();

            return $user;
        });
    }
}
