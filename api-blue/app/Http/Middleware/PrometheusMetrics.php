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
            // report() itself must never be allowed to throw — a broken/
            // misconfigured log channel in this exact catch path (seen in
            // CI's isolated test containers) would otherwise turn a
            // deliberately-swallowed metrics failure into an uncaught 500
            // for the whole request, defeating the point of this try/catch.
            try {
                report($e);
            } catch (Throwable) {
                // Truly nothing more we can do — drop it.
            }
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

        // registerDefaultMetrics=false: the default (true) eagerly registers
        // process/PHP gauges INSIDE this constructor, which writes to Redis
        // immediately — outside the handle() method's try/catch scope by the
        // time it throws (this constructor call is itself inside that catch,
        // but the eager write made a Redis-down environment, e.g. CI's
        // isolated test containers with no Redis service, turn every single
        // request into an uncaught 500 instead of the graceful no-op this
        // middleware is supposed to degrade to). Lazy registration means
        // Redis is only touched by getOrRegisterCounter/Histogram below,
        // which already are inside the try/catch.
        return new CollectorRegistry($adapter, false);
    }
}
