<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When an order was paid and by when the seller must ship it (set by
 * TransactionRepository::markPaid()). No backfill here: older paid orders get
 * their deadline from transaction:cancel-unshipped's catch-up, which also
 * covers orders the old code marks paid while a deploy is still rolling out.
 */
return new class extends Migration
{
    public function up(): void
    {
        // transactions is hot (checkout, webhooks): give up instead of
        // queueing every request behind the ALTER's lock.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SET LOCAL lock_timeout = '3s'");
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('ship_deadline_at')->nullable();
            $table->index(['delivery_status', 'ship_deadline_at']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['delivery_status', 'ship_deadline_at']);
            $table->dropColumn(['paid_at', 'ship_deadline_at']);
        });
    }
};
