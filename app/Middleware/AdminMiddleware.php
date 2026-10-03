<?php

declare(strict_types=1);

namespace Muh\Middleware;

use Muh\Core\Auth;
use Muh\Core\Request;
use Muh\Core\Response;

/**
 * Requires an authenticated SUPER ADMIN (system administrator). Only the
 * software owner / platform admins assigned by them can pass.
 */
final class AdminMiddleware extends Middleware
{
    public function handle(Request $request): ?Response
    {
        if (!Auth::check()) {
            return (new AuthMiddleware())->handle($request);
        }
        $user = Auth::user();
        if (empty($user['is_system_admin'])) {
            if ($request->wantsJson()) {
                return Response::json(['error' => 'forbidden'], 403);
            }
            return Response::redirect('/app/dashboard');
        }
        return null;
    }
}
