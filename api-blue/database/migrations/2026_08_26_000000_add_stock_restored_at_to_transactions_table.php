<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * restoreStock() mengunci baris Product yang dikembalikan stoknya, yang
 * benar untuk aritmatika +=, tapi lock itu tidak sama dengan idempotency:
 * tidak ada apa pun yang mencegah restoreStock() dipanggil dua kali untuk
 * transaksi yang sama dan menambah stok dua kali.
 *
 * Ada empat jalur yang bisa memicunya: webhook Midtrans (payment_status
 * failed/expired), checkPaymentStatus() manual, updateStatus() saat
 * seller membatalkan pesanan, dan scheduler transaction:check-expiry
 * tiap menit. Tanpa penanda, dua di antaranya yang kebetulan tumpang
 * tindih pada transaksi yang sama bisa mengembalikan stok dua kali.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->timestamp('stock_restored_at')->nullable()->after('payment_status');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('stock_restored_at');
        });
    }
};
