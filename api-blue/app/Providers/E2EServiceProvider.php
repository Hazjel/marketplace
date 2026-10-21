<?php

namespace App\Providers;

use App\Interfaces\PaymentGatewayInterface;
use App\Services\E2E\FakeGoogleProvider;
use App\Services\E2E\FakePaymentGateway;
use App\Services\MidtransPaymentGateway;
use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Facades\Socialite;

class E2EServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if ($this->app->environment('e2e')) {
            $this->app->bind(PaymentGatewayInterface::class, FakePaymentGateway::class);
            $this->app->bind(MidtransPaymentGateway::class, FakePaymentGateway::class);
        }
    }

    public function boot(): void
    {
        if ($this->app->environment('e2e')) {
            Socialite::extend('google', fn ($app) => new FakeGoogleProvider(
                $app['request'], 'e2e', 'e2e', url('/api/auth/google/callback')
            ));
        }
    }
}
