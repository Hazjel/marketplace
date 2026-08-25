<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prasyarat untuk hapus akun yang tidak menghancurkan data finansial.
 *
 * stores.user_id dan buyers.user_id di-cascade ke users, dan dari situ
 * cascade lagi ke products, transactions, store_balances,
 * store_balance_histories, withdrawals -- hard-delete satu baris user bisa
 * memusnahkan seluruh toko, katalog, riwayat transaksi, dan ledger saldo
 * penjual sekaligus.
 *
 * SoftDeletes tidak pernah mengeluarkan SQL DELETE, jadi tidak satu pun FK
 * cascade di atas pernah terpicu -- baris user hanya ditandai deleted_at,
 * dan Eloquent secara default sudah menyembunyikannya dari query biasa
 * (termasuk yang dipakai login dan resolusi token Sanctum).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
