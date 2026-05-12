<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Change 'type' from enum to string to support new escrow types:
     * pending_income, released, refunded, withdrawal, expense
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // SQLite doesn't support ALTER COLUMN, recreate is handled by RefreshDatabase
            // For fresh SQLite migrations, we'll modify the original migration approach
            // by using string type in our test-compatible migration
            Schema::table('store_balance_histories', function (Blueprint $table) {
                // SQLite: drop the enum column and add a string column
                $table->dropColumn('type');
            });
            Schema::table('store_balance_histories', function (Blueprint $table) {
                $table->string('type')->after('store_balance_id');
            });
        } else {
            // MySQL: ALTER COLUMN to expand enum values
            DB::statement("ALTER TABLE store_balance_histories MODIFY COLUMN `type` VARCHAR(50) NOT NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE store_balance_histories MODIFY COLUMN `type` ENUM('income', 'withdraw', 'initial') NOT NULL");
        }
    }
};
