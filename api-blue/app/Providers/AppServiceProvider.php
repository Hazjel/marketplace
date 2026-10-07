<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // APP_URL https di belakang reverse proxy (Cloudflare Tunnel/nginx) yang
        // terminate SSL sebelum trafik sampai ke sini — tanpa ini asset()/url()
        // generate http:// dan browser block sebagai mixed content
        if (str_starts_with(config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        // These route keys are UUID columns: any other value reached Postgres
        // as an invalid uuid literal and turned "not found" into a 500.
        // Not `address`: its id is an integer.
        Route::patterns(array_fill_keys(
            ['id', 'buyer', 'product', 'product_category', 'store', 'store_balance', 'store_balance_history', 'transaction', 'user', 'withdrawal'],
            '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}'
        ));

        // Rate Limiters
        RateLimiter::for('api', function ($request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth', function ($request) {
            return Limit::perMinute(6)->by($request->ip());
        });

        RateLimiter::for('transaction', function ($request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });
    }
}
