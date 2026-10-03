<?php

declare(strict_types=1);

namespace Muh\Middleware;

use Muh\Core\Auth;
use Muh\Core\Request;
use Muh\Core\Response;
use Muh\Core\Session;

/**
 * Requires an active tenant (accounting office) for tenant-scoped routes.
 */
final class TenantMiddleware extends Middleware
{
    public function handle(Request $request): ?Response
    {
        if (!Auth::check()) {
            return (new AuthMiddleware())->handle($request);
        }
        $user = Auth::user();

        // Super admins never use the office (/app) area — they have their own
        // /admin panel with a distinct menu. Unless impersonating a tenant
        // owner, force them back to /admin so the two menus never mix.
        if (!empty($user['is_system_admin']) && !Session::get('_impersonator')) {
            return Response::redirect('/admin');
        }

        if (!$user['tenant_id']) {
            return Response::redirect('/onboarding');
        }
        return null;
    }
}
