<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The part of an order paid with Saldo Blukios. grand_total stays the full
 * order value (escrow, admin fee and seller amount are based on it); Midtrans
 * collects and refunds only grand_total - balance_used.
 */
return new class extends Migration
{
    public function up(): void
    {
        $pgsql = DB::getDriverName() === 'pgsql';

        // transactions is hot (checkout, webhooks): give up instead of
        // queueing every request behind the ALTER's lock. SET LOCAL lasts
        // until the migration's own transaction ends.
        if ($pgsql) {
            DB::statement("SET LOCAL lock_timeout = '3s'");
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('balance_used', 26, 2)->default(0);
        });

        // SQLite cannot add a CHECK through ALTER TABLE; checkout guards there.
        if ($pgsql) {
            DB::statement('ALTER TABLE transactions ADD CONSTRAINT transactions_balance_used_range CHECK (balance_used >= 0 AND balance_used <= grand_total)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE transactions DROP CONSTRAINT IF EXISTS transactions_balance_used_range');
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('balance_used');
        });
    }
};
