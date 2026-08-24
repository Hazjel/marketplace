<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Data yang memang dibutuhkan aplikasi untuk berjalan: definisi permission
 * dan role. Aman dijalankan berulang dan tidak membuat akun siapa pun.
 *
 * Ini yang boleh dipanggil deployment. DemoSeeder tidak.
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
        ]);
    }
}
