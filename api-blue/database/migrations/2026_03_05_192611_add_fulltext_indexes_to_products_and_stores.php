<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // FULLTEXT indexes are MySQL-only; skip for SQLite (testing)
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // FULLTEXT index on products.name + products.description
        DB::statement('ALTER TABLE products ADD FULLTEXT INDEX ft_products_search (name, description)');

        // FULLTEXT index on stores.name
        DB::statement('ALTER TABLE stores ADD FULLTEXT INDEX ft_stores_search (name)');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE products DROP INDEX ft_products_search');
        DB::statement('ALTER TABLE stores DROP INDEX ft_stores_search');
    }
};
