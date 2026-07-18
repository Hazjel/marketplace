<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\Redis as RedisAdapter;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PrometheusMetrics
{
    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);
        $response = $next($request);

        if ($request->path() === 'metrics') {
            return $response;
        }

        // metrics gak boleh bikin request gagal kalau Redis lagi down
        try {
            $registry = $this->registry();
            $route = $request->route()?->uri() ?? $request->path();
            $status = (string) $response->getStatusCode();

            $registry->getOrRegisterCounter(
                'api',
                'http_requests_total',
                'Total request masuk ke Laravel API',
                ['route', 'method', 'status']
            )->inc([$route, $request->method(), $status]);

            $registry->getOrRegisterHistogram(
                'api',
                'http_request_duration_seconds',
                'Durasi request Laravel API',
                ['route', 'method'],
                [0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10]
            )->observe(microtime(true) - $start, [$route, $request->method()]);
        } catch (Throwable $e) {
            report($e);
        }

        return $response;
    }

    public function registry(): CollectorRegistry
    {
        $adapter = new RedisAdapter([
            'host' => config('database.redis.default.host'),
            'port' => (int) config('database.redis.default.port'),
            'password' => config('database.redis.default.password') ?: null,
            'timeout' => 0.1,
            'read_timeout' => 10,
            'persistent_connections' => false,
        ]);

        return new CollectorRegistry($adapter);
    }
}
