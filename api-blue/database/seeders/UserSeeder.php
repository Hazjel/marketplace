<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Database\Seeder;

use function Symfony\Component\Clock\now;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Admin
        $admin = User::create([
            'name' => 'admin',
            'username' => 'admin',
            'email' => 'admin@gmail.com',
            'email_verified_at' => now(),
            'password' => bcrypt('password')
        ])->assignRole('admin');

        // 2. Buyer
        $buyer = User::factory()->create([
            'name' => 'Buyer Account',
            'username' => 'buyer',
            'email' => 'buyer@gmail.com',
            'password' => bcrypt('password')
        ]);
        $buyer->assignRole('buyer');
        $buyer->buyer()->create(['phone_number' => '08123456789']);

        // 3. Seller (Store)
        $seller = User::factory()->create([
            'name' => 'Seller Account',
            'username' => 'seller',
            'email' => 'seller@gmail.com',
            'password' => bcrypt('password')
        ]);
        $seller->assignRole('store');
        
        $store = \App\Models\Store::factory()->create([
            'user_id' => $seller->id,
            'name' => 'Seller Official Store',
            'username' => 'seller-official'
        ]);

        // Create Wallet for Seller
        \App\Models\StoreBalance::create(['store_id' => $store->id, 'balance' => 0]);


        // Random Users
        UserFactory::new()->count(15)->create();
    }
}
