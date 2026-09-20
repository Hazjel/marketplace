<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
            // Postgres: enum Laravel = VARCHAR + CHECK constraint. Memperluas
            // daftar nilai berarti mengganti constraint-nya, bukan mengubah
            // tipe kolom. DEFAULT 'pending' dari migrasi asal tidak disentuh
            // supaya tidak ikut hilang.
            DB::statement('ALTER TABLE transactions DROP CONSTRAINT IF EXISTS transactions_delivery_status_check');
            DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_delivery_status_check CHECK (delivery_status IN ('pending', 'processing', 'delivering', 'completed', 'cancelled', 'failed'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE transactions DROP CONSTRAINT IF EXISTS transactions_delivery_status_check');
            DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_delivery_status_check CHECK (delivery_status IN ('pending', 'processing', 'delivering', 'completed'))");
        }
    }
};
