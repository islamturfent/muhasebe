<?php

declare(strict_types=1);

namespace Muh\Middleware;

use Muh\Core\Request;
use Muh\Core\Response;
use Muh\Core\Session;

/**
 * Simple rate limiter per IP (best-effort, session-scoped for the web UI).
 */
final class RateLimitMiddleware extends Middleware
{
    public function handle(Request $request): ?Response
    {
        if (!config('app.security.rate_limit.enabled', true)) {
            return null;
        }
        $ip = $request->ip();
        $max = (int) config('app.security.rate_limit.max', 120);
        $window = (int) config('app.security.rate_limit.window', 60);

        $key = 'rate.' . md5($ip);
        $windowData = Session::get($key, ['ts' => time(), 'count' => 0]);
        if (time() - $windowData['ts'] > $window) {
            $windowData = ['ts' => time(), 'count' => 0];
        }
        $windowData['count']++;
        Session::set($key, $windowData);

        if ($windowData['count'] > $max) {
            if ($request->wantsJson()) {
                return Response::json(['error' => 'too_many_requests'], 429);
            }
            return Response::html('Too many requests', 429);
        }
        return null;
    }
}
