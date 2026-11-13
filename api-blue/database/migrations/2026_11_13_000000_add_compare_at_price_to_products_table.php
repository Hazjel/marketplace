<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Harga sebelum diskon", display only: checkout keeps charging products.price.
 * Nullable, no default, no backfill, so Postgres only touches the catalog.
 */
return new class extends Migration
{
    public function up(): void
    {
        // products is read on every page: give up instead of queueing reads
        // behind the ALTER's lock.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SET LOCAL lock_timeout = '3s'");
        }

        Schema::table('products', function (Blueprint $table) {
            $table->decimal('compare_at_price', 26, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('compare_at_price');
        });
    }
};
