<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Illuminate\Auth\AuthenticationException;

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
    $middleware->append(\Illuminate\Http\Middleware\HandleCors::class);
    $middleware->append(\App\Http\Middleware\UpdateLastSeen::class);
    $middleware->alias([
        'idempotent' => \App\Http\Middleware\IdempotencyMiddleware::class,
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
        $exceptions->render(function (\Throwable $e, $request) {
            if ($request->expectsJson() && !app()->hasDebugModeEnabled()) {
                $code = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
                return response()->json([
                    'success' => false,
                    'message' => 'Terjadi kesalahan pada server',
                ], $code >= 400 && $code < 600 ? $code : 500);
            }
        });
    })->create();
