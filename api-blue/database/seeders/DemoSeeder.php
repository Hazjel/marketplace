<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Isi contoh untuk pengembangan: akun demo, toko, katalog produk.
 *
 * UserSeeder membuat akun dengan password yang tertulis di repositori ini,
 * salah satunya ber-role admin. Seeder ini tidak boleh menyentuh environment
 * yang bisa dijangkau publik.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            StoreSeeder::class,
            BuyerSeeder::class,
            ProductCategorySeeder::class,
            ProductSeeder::class,
            // TransactionSeeder disabled sementara — TransactionDetailFactory bikin
            // Product::factory() baru pakai nama Latin generic, ngerusak katalog demo
            // TransactionSeeder::class,
        ]);
    }
}
