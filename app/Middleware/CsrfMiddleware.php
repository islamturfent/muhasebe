<?php

declare(strict_types=1);

namespace Muh\Middleware;

use Muh\Core\Hash;
use Muh\Core\Request;
use Muh\Core\Response;
use Muh\Core\Session;

/**
 * CSRF protection for unsafe (state-changing) HTTP methods.
 */
final class CsrfMiddleware extends Middleware
{
    public function handle(Request $request): ?Response
    {
        $method = $request->method();
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return null;
        }

        $token = $request->input('_token', '');
        if (!$token) {
            $token = $request->header('X-CSRF-TOKEN') ?? '';
        }

        if (Session::has('_csrf') && Hash::constantEquals(Session::get('_csrf'), (string) $token)) {
            return null;
        }

        if ($request->wantsJson()) {
            return Response::json(['error' => 'csrf_mismatch'], 403);
        }
        return Response::html('CSRF token mismatch', 403);
    }
}
