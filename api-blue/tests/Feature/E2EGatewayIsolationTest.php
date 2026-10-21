<?php

namespace Tests\Feature;

use App\Interfaces\PaymentGatewayInterface;
use App\Models\Transaction;
use App\Providers\E2EServiceProvider;
use App\Services\MidtransPaymentGateway;
use Database\Seeders\E2ESeeder;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use LogicException;
use Tests\TestCase;

class E2EGatewayIsolationTest extends TestCase
{
    public function test_e2e_payments_and_google_auth_stay_offline(): void
    {
        $this->configureEnvironment('e2e');
        $transaction = new Transaction;
        $transaction->id = 'e2e-order';
        $gateway = $this->app->make(PaymentGatewayInterface::class);

        $this->assertSame('e2e-snap-e2e-order', $gateway->getSnapToken($transaction));
        $this->assertSame(PaymentGatewayInterface::REFUND_DONE, $gateway->refund($transaction, 'E2E'));
        $google = Socialite::driver('google');
        $redirect = $google->stateless()->with(['state' => 'e2e-state'])->redirect()->getTargetUrl();
        $this->assertSame('/api/auth/google/callback', parse_url($redirect, PHP_URL_PATH));
        $this->assertSame('state=e2e-state', parse_url($redirect, PHP_URL_QUERY));
        $this->assertSame('buyer@e2e.test', $google->user()->getEmail());
    }

    public function test_production_keeps_the_real_payment_and_google_providers(): void
    {
        $this->configureEnvironment('production');

        $this->assertSame(MidtransPaymentGateway::class, $this->app->make(PaymentGatewayInterface::class)::class);
        $this->assertSame(MidtransPaymentGateway::class, $this->app->make(MidtransPaymentGateway::class)::class);
        $this->assertSame(GoogleProvider::class, Socialite::driver('google')::class);
    }

    public function test_synthetic_seeder_refuses_production(): void
    {
        $this->app['env'] = 'production';
        $this->expectException(LogicException::class);

        (new E2ESeeder)->run();
    }

    private function configureEnvironment(string $environment): void
    {
        $this->app['env'] = $environment;
        config(['services.google' => [
            'client_id' => 'e2e',
            'client_secret' => 'e2e',
            'redirect' => 'http://localhost/api/auth/google/callback',
        ]]);
        $provider = new E2EServiceProvider($this->app);
        $provider->register();
        $provider->boot();
    }
}
