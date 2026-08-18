<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('transactions', 'voucher_id')) {
                $table->uuid('voucher_id')->nullable()->after('grand_total');
                $table->foreign('voucher_id')->references('id')->on('vouchers')->onDelete('set null');
            }
            if (! Schema::hasColumn('transactions', 'discount_amount')) {
                $table->decimal('discount_amount', 26, 2)->default(0)->after('voucher_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'voucher_id')) {
                $table->dropForeign(['voucher_id']);
                $table->dropColumn('voucher_id');
            }
            if (Schema::hasColumn('transactions', 'discount_amount')) {
                $table->dropColumn('discount_amount');
            }
        });
    }
};
