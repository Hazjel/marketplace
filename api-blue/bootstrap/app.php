<?php

use App\Http\Middleware\IdempotencyMiddleware;
use App\Http\Middleware\PrometheusMetrics;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\UpdateLastSeen;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
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
        $middleware->append(HandleCors::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->append(UpdateLastSeen::class);
        $middleware->append(PrometheusMetrics::class);
        $middleware->alias([
            'idempotent' => IdempotencyMiddleware::class,
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

        // Generic fallback for production — hide internal errors
        $exceptions->render(function (Throwable $e, $request) {
            if ($request->expectsJson() && ! app()->hasDebugModeEnabled()) {
                $code = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;

                return response()->json([
                    'success' => false,
                    'message' => 'Terjadi kesalahan pada server',
                ], $code >= 400 && $code < 600 ? $code : 500);
            }
        });
    })->create();
