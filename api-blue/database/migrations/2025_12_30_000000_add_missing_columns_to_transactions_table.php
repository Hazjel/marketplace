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
            if (!Schema::hasColumn('transactions', 'snap_token')) {
                $table->string('snap_token')->nullable()->after('payment_status');
            }
            if (!Schema::hasColumn('transactions', 'receiving_proof')) {
                $table->string('receiving_proof')->nullable()->after('delivery_proof');
            }
            if (!Schema::hasColumn('transactions', 'admin_fee')) {
                $table->decimal('admin_fee', 26, 2)->default(0)->after('shipping_cost');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
             if (Schema::hasColumn('transactions', 'snap_token')) {
                $table->dropColumn('snap_token');
            }
            if (Schema::hasColumn('transactions', 'receiving_proof')) {
                $table->dropColumn('receiving_proof');
            }
            if (Schema::hasColumn('transactions', 'admin_fee')) {
                $table->dropColumn('admin_fee');
            }
        });
    }
};
