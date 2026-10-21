<?php

namespace Database\Seeders;

use App\Interfaces\EscrowRepositoryInterface;
use App\Models\Buyer;
use App\Models\BuyerBalanceHistory;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductImage;
use App\Models\Store;
use App\Models\StoreBalance;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use App\Models\Voucher;
use App\ValueObjects\Money;
use Illuminate\Database\Seeder;
use LogicException;

class E2ESeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('e2e')) {
            throw new LogicException('E2ESeeder only runs with APP_ENV=e2e.');
        }

        $this->call([PermissionSeeder::class, RoleSeeder::class]);

        $category = ProductCategory::create([
            'name' => 'E2E Stationery',
            'slug' => 'e2e-stationery',
            'description' => 'Synthetic browser test category',
        ]);
        $buyers = [];
        $products = [];

        foreach (['buyer', 'buyer-two', 'seller', 'seller-two', 'admin'] as $account) {
            $user = User::create([
                'name' => 'E2E '.ucfirst($account),
                'username' => 'e2e-'.$account,
                'email' => $account.'@e2e.test',
                'password' => 'password',
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $role = str_starts_with($account, 'seller') ? 'store' : (str_starts_with($account, 'buyer') ? 'buyer' : 'admin');
            $user->assignRole($role);

            if ($role === 'buyer') {
                $buyers[] = Buyer::create(['user_id' => $user->id, 'phone_number' => '0800000000']);
            }

            if ($role === 'store') {
                $second = $account === 'seller-two';
                $store = Store::create([
                    'user_id' => $user->id,
                    'name' => $second ? 'E2E Second Store' : 'E2E Test Store',
                    'username' => $second ? 'e2e-store-two' : 'e2e-store',
                    'logo' => 'e2e-product.svg',
                    'about' => 'Synthetic browser test store',
                    'phone' => '0800000001',
                    'address_id' => 1,
                    'address' => 'E2E Test Street',
                    'city' => 'Jakarta',
                    'postal_code' => '12345',
                    'is_verified' => true,
                    'is_active' => true,
                    'ai_assistant_enabled' => false,
                ]);
                StoreBalance::create(['store_id' => $store->id, 'balance' => 0, 'pending_balance' => 0]);
                $product = Product::create([
                    'store_id' => $store->id,
                    'product_category_id' => $category->id,
                    'name' => $second ? 'E2E Pen' : 'E2E Notebook',
                    'slug' => $second ? 'e2e-pen' : 'e2e-notebook',
                    'description' => 'Synthetic notebook for isolated browser tests',
                    'condition' => 'new',
                    'price' => $second ? 15000 : 25000,
                    'weight' => 0.5,
                    'stock' => 20,
                    'has_variants' => false,
                ]);
                ProductImage::create([
                    'product_id' => $product->id,
                    'image' => 'e2e-product.svg',
                    'is_thumbnail' => true,
                ]);
                $products[] = $product;
            }
        }

        $buyers[0]->forceFill(['balance' => 100000])->save();
        BuyerBalanceHistory::create([
            'buyer_id' => $buyers[0]->id,
            'type' => BuyerBalanceHistory::TYPE_REFUND,
            'amount' => 100000,
            'unique_ref' => 'e2e-opening-balance',
            'remarks' => 'Synthetic opening Saldo for E2E',
        ]);

        foreach ($buyers as $index => $buyer) {
            $product = $products[$index];
            $price = (int) $product->price;
            foreach (['unpaid', 'paid'] as $status) {
                $transaction = Transaction::create([
                    'code' => 'E2E-B'.($index + 1).'-'.strtoupper($status),
                    'buyer_id' => $buyer->id,
                    'store_id' => $product->store_id,
                    'address_id' => 1,
                    'address' => 'E2E Buyer Street',
                    'city' => 'Jakarta',
                    'postal_code' => '12345',
                    'shipping' => 'JNE',
                    'shipping_type' => 'regular',
                    'shipping_cost' => 10000,
                    'tax' => 0,
                    'grand_total' => $price + 10000,
                    'payment_status' => $status,
                ]);
                TransactionDetail::create([
                    'transaction_id' => $transaction->id,
                    'product_id' => $product->id,
                    'qty' => 1,
                    'subtotal' => Money::rupiah($price),
                ]);
                $transaction->forceFill(['snap_token' => 'e2e-snap-'.$transaction->id])->save();
                if ($status === 'paid') {
                    app(EscrowRepositoryInterface::class)->credit($transaction);
                }
            }
        }

        Voucher::create([
            'store_id' => $products[0]->store_id,
            'code' => 'E2EHEMAT',
            'type' => 'fixed',
            'value' => 5000,
            'min_purchase' => 10000,
            'usage_limit' => 100,
            'usage_limit_per_buyer' => 5,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDays(30),
            'is_active' => true,
        ]);
    }
}
