<?php

declare(strict_types=1);

namespace Muh\Middleware;

use Muh\Core\Config;
use Muh\Core\Request;
use Muh\Core\Response;

/**
 * Durable per-IP rate limiter (best-effort).
 *
 * Not session-bound: state is written to a file under storage/rate so the
 * same IP is limited consistently across sessions/requests. Applied to guest,
 * authenticated and admin route groups.
 */
final class RateLimitMiddleware extends Middleware
{
    public function handle(Request $request): ?Response
    {
        if (!Config::get('app.security.rate_limit.enabled', true)) {
            return null;
        }
        $ip = $request->ip();
        $max = (int) Config::get('app.security.rate_limit.max', 120);
        $window = (int) Config::get('app.security.rate_limit.window', 60);

        $dir = dirname(__DIR__, 2) . '/storage/rate';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir . '/' . md5($ip) . '.json';

        $data = ['ts' => time(), 'count' => 0];
        if (is_file($file)) {
            $raw = @file_get_contents($file);
            $json = json_decode((string) $raw, true);
            if (is_array($json)) {
                $json['count'] = (int) ($json['count'] ?? 0);
                $json['ts'] = (int) ($json['ts'] ?? time());
                $data = $json;
            }
        }
        if (time() - $data['ts'] > $window) {
            $data = ['ts' => time(), 'count' => 0];
        }
        $data['count']++;

        // Atomic write.
        $fp = @fopen($file, 'c+');
        if ($fp) {
            flock($fp, LOCK_EX);
            ftruncate($fp, 0);
            fwrite($fp, json_encode($data));
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
        }

        if ($data['count'] > $max) {
            if ($request->wantsJson()) {
                return Response::json(['error' => 'too_many_requests'], 429);
            }
            return Response::html('Too many requests', 429);
        }
        return null;
    }
}
