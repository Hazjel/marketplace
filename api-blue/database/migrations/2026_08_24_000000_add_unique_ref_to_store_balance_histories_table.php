<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menjamin exactly-once untuk mutasi saldo di level database.
 *
 * Logika aplikasi sudah menjaganya lewat transaksi + row lock, tapi itu
 * bergantung pada kode yang selalu benar. Constraint ini membuat mutasi ganda
 * mustahil tersimpan, apa pun yang terjadi di atasnya.
 *
 * Kolomnya nullable dan hanya diisi oleh mutasi yang memang harus sekali
 * saja. MySQL mengecualikan NULL dari unique index, jadi baris lama -- dan
 * tipe yang wajar berulang -- tidak terganggu. Ini juga sebabnya constraint
 * tidak dipasang langsung pada (reference_type, reference_id, type):
 * WithdrawalFactory menulis dua baris 'withdraw' per penarikan untuk data
 * demo, dan index menyeluruh akan gagal diterapkan pada database yang sudah
 * berjalan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_balance_histories', function (Blueprint $table) {
            $table->string('unique_ref')->nullable()->after('reference_type');
            $table->unique('unique_ref');
        });
    }

    public function down(): void
    {
        Schema::table('store_balance_histories', function (Blueprint $table) {
            $table->dropUnique(['unique_ref']);
            $table->dropColumn('unique_ref');
        });
    }
};
