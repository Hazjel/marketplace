<?php

namespace Tests\Feature;

use App\Http\Middleware\PrometheusMetrics;
use App\Models\Buyer;
use App\Models\Transaction;
use App\Providers\E2EServiceProvider;
use Database\Seeders\E2ESeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class E2ESeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_synthetic_accounts_have_orders_balances_and_a_store_voucher(): void
    {
        $this->app['env'] = 'e2e';
        $this->withoutMiddleware(PrometheusMetrics::class);
        $this->seed(E2ESeeder::class);

        $this->assertDatabaseCount('users', 5);
        $this->assertDatabaseCount('buyers', 2);
        $this->assertDatabaseCount('stores', 2);
        $this->assertDatabaseCount('products', 2);
        foreach (Buyer::all() as $buyer) {
            $this->assertSame(1, Transaction::where('buyer_id', $buyer->id)->where('payment_status', 'unpaid')->count());
            $this->assertSame(1, Transaction::where('buyer_id', $buyer->id)->where('payment_status', 'paid')->count());
        }
        $this->assertSame(1, Buyer::where('balance', 100000)->count());
        $this->assertDatabaseCount('buyer_balance_histories', 1);
        $this->assertDatabaseCount('transaction_details', 4);
        $this->assertDatabaseCount('store_balance_histories', 2);
        $this->assertDatabaseHas('vouchers', ['code' => 'E2EHEMAT', 'is_active' => true]);

        (new E2EServiceProvider($this->app))->register();
        foreach (Buyer::all() as $buyer) {
            $this->actingAs($buyer->user, 'sanctum');
            foreach (Transaction::where('buyer_id', $buyer->id)->get() as $transaction) {
                $this->postJson('/api/transaction/'.$transaction->id.'/check-status')
                    ->assertOk()->assertJsonPath('data.payment_status', $transaction->payment_status);
            }
        }
        $this->assertDatabaseCount('store_balance_histories', 2);
    }
}
