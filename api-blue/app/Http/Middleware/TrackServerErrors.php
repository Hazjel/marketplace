<?php

namespace App\Http\Middleware;

use App\Support\OpsSignals;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Counts every 5xx the API returns, for `ops:check` to email about. The web
 * app and the mobile app call the same API, so this covers both.
 *
 * Uncaught exceptions are already rendered into a response by the time it
 * gets here, so they are counted too, not only controllers that return a
 * 500 themselves.
 */
class TrackServerErrors
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() >= 500) {
            // The route pattern, not the URL: ids would split one bug into
            // a sample per order.
            $route = $request->route()?->uri() ?? $request->path();
            OpsSignals::record(
                OpsSignals::SERVER_ERROR,
                $request->method().' '.$route.' '.$response->getStatusCode()
            );
        }

        return $response;
    }
}
