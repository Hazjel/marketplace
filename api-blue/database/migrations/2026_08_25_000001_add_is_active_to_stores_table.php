<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * is_verified sudah dipakai untuk arti "diverifikasi admin" -- memakainya
 * ulang untuk "pemiliknya menghapus akun" akan mencampur dua konsep dan
 * memaksa toko lewat verifikasi ulang kalau suatu saat dipulihkan.
 *
 * Soft-delete User tidak menyentuh baris Store sama sekali (lihat migrasi
 * sebelumnya), jadi tanpa flag ini toko tetap tampil dan bisa dibeli
 * meskipun pemiliknya sudah tidak bisa login untuk mengurusnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('is_verified');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
