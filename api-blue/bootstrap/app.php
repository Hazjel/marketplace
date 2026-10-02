<?php

use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\IdempotencyMiddleware;
use App\Http\Middleware\InternalServiceAuth;
use App\Http\Middleware\PrometheusMetrics;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrackServerErrors;
use App\Http\Middleware\UpdateLastSeen;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->validateCsrfTokens(except: [
            'midtrans-callback',
            'logistics/webhook',
        ]);
        // TLS ends at Cloudflare, so PHP sees http:// and every signed URL
        // (email verification) failed with 403. Only the scheme is trusted:
        // trusting X-Forwarded-For too would let clients spoof the IP used
        // by the per-IP rate limiters. nginx is reachable only through the
        // tunnel, and Cloudflare always overwrites X-Forwarded-Proto.
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_PROTO);
        // First, so everything after it (including error responses) logs and
        // returns the same id.
        $middleware->prepend(AssignRequestId::class);
        $middleware->append(HandleCors::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->append(UpdateLastSeen::class);
        $middleware->append(PrometheusMetrics::class);
        $middleware->append(TrackServerErrors::class);
        $middleware->alias([
            'idempotent' => IdempotencyMiddleware::class,
            'internal' => InternalServiceAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Endpoint tidak ditemukan',
                ], 404);
            }
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Method HTTP tidak diizinkan',
                ], 405);
            }
        });

        $exceptions->render(function (AuthenticationException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sesi telah berakhir, silakan login kembali',
                ], 401);
            }
        });

        // Generic fallback for production — hide internal errors. 4xx get a
        // message that matches the status: a permission denial used to read
        // as a server error.
        $exceptions->render(function (Throwable $e, $request) {
            if ($request->expectsJson() && ! app()->hasDebugModeEnabled()) {
                $code = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
                $code = $code >= 400 && $code < 600 ? $code : 500;

                $message = match (true) {
                    $code === 403 => 'Anda tidak memiliki izin untuk melakukan aksi ini',
                    $code === 429 => 'Terlalu banyak permintaan, coba lagi sebentar lagi',
                    $code < 500 => 'Permintaan tidak dapat diproses',
                    default => 'Terjadi kesalahan pada server',
                };

                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], $code);
            }
        });
    })->create();
