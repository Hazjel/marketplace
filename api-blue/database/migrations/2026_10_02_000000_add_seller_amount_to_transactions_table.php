<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * release()/refund() recomputed the seller's share from grand_total,
 * shipping_cost and admin_fee. Changing that formula would release a
 * different amount than was credited to pending_balance. The amount is now
 * locked at credit time, like admin_fee already is.
 *
 * Every paid transaction has already been credited with the formula in
 * force so far, so the backfill reproduces exactly what sits in escrow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('seller_amount', 26, 2)->nullable()->after('admin_fee');
        });

        DB::table('transactions')
            ->where('payment_status', 'paid')
            ->update(['seller_amount' => DB::raw('grand_total - shipping_cost - admin_fee')]);
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('seller_amount');
        });
    }
};
