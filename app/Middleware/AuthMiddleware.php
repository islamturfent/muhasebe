<?php

declare(strict_types=1);

namespace Muh\Middleware;

use Muh\Core\Auth;
use Muh\Core\Request;
use Muh\Core\Response;

/**
 * Requires an authenticated session user.
 */
final class AuthMiddleware extends Middleware
{
    public function handle(Request $request): ?Response
    {
        if (!Auth::check()) {
            if ($request->wantsJson()) {
                return Response::json(['error' => 'unauthorized'], 401);
            }
            return Response::redirect('/login');
        }
        return null;
    }
}
