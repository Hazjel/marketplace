<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Saldo Blukios: closed-loop buyer balance. `buyers.balance` is the cached
 * total; buyer_balance_histories is the ledger it must always equal
 * (ops:check alerts on drift). unique_ref makes each mutation exactly-once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buyers', function (Blueprint $table) {
            $table->decimal('balance', 26, 2)->default(0);
        });

        // SQLite cannot add a CHECK through ALTER TABLE; BuyerBalanceRepository guards there.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE buyers ADD CONSTRAINT buyers_balance_non_negative CHECK (balance >= 0)');
        }

        Schema::create('buyer_balance_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('buyer_id')->constrained('buyers')->cascadeOnDelete();
            $table->string('type');
            $table->decimal('amount', 26, 2);
            $table->string('reference_type')->nullable();
            $table->uuid('reference_id')->nullable();
            $table->string('unique_ref')->unique();
            $table->string('remarks')->nullable();
            $table->timestamps();

            $table->index(['buyer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buyer_balance_histories');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE buyers DROP CONSTRAINT IF EXISTS buyers_balance_non_negative');
        }

        Schema::table('buyers', function (Blueprint $table) {
            $table->dropColumn('balance');
        });
    }
};
