<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Proteksi endpoint yang cuma boleh diakses service internal (recommendation-service
 * dkk), bukan lewat auth token user -- bandingin header X-Internal-Key dengan
 * INTERNAL_SERVICE_KEY di .env. Endpoint ini gak return data personal per-user,
 * cuma data agregat buat training model, jadi cukup shared secret sederhana.
 */
class InternalServiceAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.internal.key');
        $given = $request->header('X-Internal-Key');

        if (! $expected || ! $given || ! hash_equals($expected, $given)) {
            abort(403, 'Akses ditolak');
        }

        return $next($request);
    }
}
