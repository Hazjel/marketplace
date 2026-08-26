<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // Gated di APP_ENV, bukan $request->secure() -- nginx tidak set
        // X-Forwarded-Proto dan TrustProxies belum dikonfigurasi, jadi
        // Laravel tidak punya cara diandalkan mendeteksi HTTPS asli di
        // belakang reverse proxy/tunnel. blukios.store production selalu
        // diakses lewat HTTPS di edge; dev/local tidak pernah APP_ENV=production.
        if (app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
