<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
            // Postgres: $table->enum() di migrasi asal menghasilkan VARCHAR
            // plus CHECK constraint, bukan tipe enum tersendiri. Jadi yang
            // perlu dilepas adalah constraint-nya dulu, baru tipe kolomnya
            // dilebarkan -- kalau urutannya dibalik, ALTER TYPE ditolak
            // karena nilai baru melanggar CHECK yang masih terpasang.
            DB::statement('ALTER TABLE store_balance_histories DROP CONSTRAINT IF EXISTS store_balance_histories_type_check');
            DB::statement('ALTER TABLE store_balance_histories ALTER COLUMN type TYPE VARCHAR(50)');
            DB::statement('ALTER TABLE store_balance_histories ALTER COLUMN type SET NOT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE store_balance_histories ALTER COLUMN type TYPE VARCHAR(255)');
            DB::statement("ALTER TABLE store_balance_histories ADD CONSTRAINT store_balance_histories_type_check CHECK (type IN ('income', 'withdraw', 'initial'))");
        }
    }
};
