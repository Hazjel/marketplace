<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UpdateLastSeen
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check()) {
            $user = auth()->user();
            // Optimization: Only update if last update was > 5 minutes ago
            if (!$user->last_seen_at || $user->last_seen_at->diffInMinutes(now()) >= 5) {
                $user->last_seen_at = now();
                $user->saveQuietly();
            }
        }

        return $next($request);
    }
}
