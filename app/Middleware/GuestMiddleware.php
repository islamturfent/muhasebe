<?php

declare(strict_types=1);

namespace Muh\Middleware;

use Muh\Core\Auth;
use Muh\Core\Request;
use Muh\Core\Response;

/**
 * Redirects authenticated users away from guest-only pages.
 */
final class GuestMiddleware extends Middleware
{
    public function handle(Request $request): ?Response
    {
        if (Auth::check()) {
            return Response::redirect('/app/dashboard');
        }
        return null;
    }
}
