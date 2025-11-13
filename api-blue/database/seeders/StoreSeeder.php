<?php

namespace Database\Seeders;

use App\Models\Store;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\StoreBalance;
use App\Models\StoreBalanceHistory;

class StoreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Store::factory()->count(10)->create()->each(function ($store) {
            $storeBalance = StoreBalance::factory()->create(['store_id' => $store->id,]);
            StoreBalanceHistory::factory()->create([
                'store_balance_id' => $storeBalance->id,
                'amount' => $storeBalance->balance
            ]);
        });
    }
}
