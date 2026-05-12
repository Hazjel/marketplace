<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Expand delivery_status to include 'cancelled' and 'failed' statuses.
     * Required for proper escrow refund flow (cancel after payment).
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // SQLite: recreate column as string
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropColumn('delivery_status');
            });
            Schema::table('transactions', function (Blueprint $table) {
                $table->string('delivery_status')->default('pending')->after('tracking_number');
            });
        } else {
            // MySQL: expand enum
            DB::statement("ALTER TABLE transactions MODIFY COLUMN `delivery_status` ENUM('pending', 'processing', 'delivering', 'completed', 'cancelled', 'failed') DEFAULT 'pending'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE transactions MODIFY COLUMN `delivery_status` ENUM('pending', 'processing', 'delivering', 'completed') DEFAULT 'pending'");
        }
    }
};
