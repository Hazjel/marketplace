<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Helpers\ResponseHelper;

/**
 * Idempotency Middleware — Antigravity Standard.
 *
 * Prevents duplicate transaction submissions caused by double-clicks,
 * unstable networks, or mobile app retries. Every non-GET mutating request
 * should include an `X-Idempotency-Key` header with a unique UUID.
 *
 * The key is stored in Redis with a TTL of 24 hours. If the same key
 * arrives again within that window, the previous cached response is
 * returned immediately — no DB hit, no double-charge.
 */
class IdempotencyMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        $idempotencyKey = $request->header('X-Idempotency-Key');

        if (!$idempotencyKey) {
            return ResponseHelper::jsonResponse(
                false,
                'Header X-Idempotency-Key wajib disertakan untuk operasi ini.',
                null,
                422
            );
        }

        $cacheKey = 'idempotency:' . auth()->id() . ':' . $idempotencyKey;

        // If this key already exists in Redis, return the cached response
        if (Cache::has($cacheKey)) {
            $cachedResponse = Cache::get($cacheKey);
            return response()->json($cachedResponse, $cachedResponse['code'] ?? 200)
                ->header('X-Idempotency-Replayed', 'true');
        }

        $response = $next($request);

        // Only cache successful responses (2xx) so failed requests can be retried
        $statusCode = $response->getStatusCode();
        if ($statusCode >= 200 && $statusCode < 300) {
            $responseData = json_decode($response->getContent(), true);
            if ($responseData) {
                $responseData['code'] = $statusCode;
                // Store for 24 hours
                Cache::put($cacheKey, $responseData, now()->addHours(24));
            }
        }

        return $response;
    }
}
