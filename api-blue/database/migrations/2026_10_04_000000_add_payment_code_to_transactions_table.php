<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One Midtrans payment for orders from several stores: every transaction of a
 * multi-store checkout shares this order_id. Null for a single-store checkout,
 * which keeps paying under its own code exactly as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('payment_code')->nullable()->index()->after('code');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['payment_code']);
            $table->dropColumn('payment_code');
        });
    }
};
