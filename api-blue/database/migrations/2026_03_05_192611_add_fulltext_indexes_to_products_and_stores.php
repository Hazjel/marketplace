<?php

use App\Support\PostgresSearch;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Index full-text hanya dibuat di Postgres; sqlite (test suite) tidak
        // punya padanannya dan scope pencarian di model sudah jatuh ke LIKE
        // di sana. Ekspresi tsvector-nya diambil dari PostgresSearch supaya
        // persis sama dengan yang dipakai query -- kalau berbeda sedikit saja
        // (konfigurasi teks, urutan kolom, coalesce), planner tidak akan
        // memakai index ini dan pencarian diam-diam jadi sequential scan.
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(
            'CREATE INDEX ft_products_search ON products USING GIN ('
            .PostgresSearch::tsVector(['name', 'description']).')'
        );

        DB::statement(
            'CREATE INDEX ft_stores_search ON stores USING GIN ('
            .PostgresSearch::tsVector(['name']).')'
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS ft_products_search');
        DB::statement('DROP INDEX IF EXISTS ft_stores_search');
    }
};
