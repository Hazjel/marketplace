<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Dipanggil manual saat pengembangan, jadi tetap mengisi data contoh.
        // Deployment memanggil ProductionSeeder langsung -- lihat
        // docker-entrypoint.sh -- supaya akun demo tidak pernah ikut terbuat.
        $this->call([
            ProductionSeeder::class,
            DemoSeeder::class,
        ]);
    }
}
