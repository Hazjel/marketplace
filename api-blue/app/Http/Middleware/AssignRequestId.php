<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives every request one id, so a user's "it failed" can be matched to the
 * exact log lines: it is in every log entry written while handling the
 * request (and by jobs it queues; Laravel carries Context into them) and is
 * returned in the X-Request-Id header.
 */
class AssignRequestId
{
    public const HEADER = 'X-Request-Id';

    public function handle(Request $request, Closure $next): Response
    {
        $id = $this->incomingId($request) ?? (string) Str::uuid();
        Context::add('request_id', $id);

        $response = $next($request);
        $response->headers->set(self::HEADER, $id);

        return $response;
    }

    // A caller may send its own id (a proxy, a client correlating retries).
    // Anyone can set this header, so keep it only when it looks like an id:
    // it ends up verbatim in log files.
    private function incomingId(Request $request): ?string
    {
        $id = $request->headers->get(self::HEADER);

        return is_string($id) && preg_match('/^[A-Za-z0-9-]{8,64}$/', $id) === 1 ? $id : null;
    }
}
