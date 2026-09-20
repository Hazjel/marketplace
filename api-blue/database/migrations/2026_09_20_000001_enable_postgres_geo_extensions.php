<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * earth_distance() dipakai StoreRepository untuk mengurutkan toko
     * berdasarkan jarak, menggantikan ST_Distance_Sphere() milik MySQL.
     * Fungsi itu datang dari extension earthdistance, yang sendirinya
     * bergantung pada cube -- jadi urutan pembuatannya tidak boleh dibalik.
     *
     * Catatan operasional: earthdistance bukan extension "trusted" di
     * Postgres, jadi CREATE EXTENSION di sini hanya berhasil kalau role yang
     * menjalankan migrasi punya hak superuser. Pada shared-postgres, kedua
     * extension dibuat sekali oleh role postgres saat provisioning database,
     * sehingga IF NOT EXISTS di bawah tinggal jadi no-op untuk role aplikasi.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS cube');
        DB::statement('CREATE EXTENSION IF NOT EXISTS earthdistance');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Urutan dibalik: earthdistance bergantung pada cube.
        DB::statement('DROP EXTENSION IF EXISTS earthdistance');
        DB::statement('DROP EXTENSION IF EXISTS cube');
    }
};
