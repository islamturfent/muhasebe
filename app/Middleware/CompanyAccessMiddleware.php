<?php

declare(strict_types=1);

namespace Muh\Middleware;

use Muh\Core\Auth;
use Muh\Core\Request;
use Muh\Core\Response;
use Muh\Services\CurrentContextService;
use Muh\Services\SessionContext;

/**
 * Enforces company-level access on the authenticated office (/app) area.
 *
 * Guards two vectors centrally:
 *  - the active session company (SessionContext),
 *  - any ?company_id= query parameter.
 * Owners/system-admins may access all tenant companies; other users are limited
 * to companies assigned to them via `user_company`.
 */
final class CompanyAccessMiddleware extends Middleware
{
    public function handle(Request $request): ?Response
    {
        if (Auth::check()) {
            $user = Auth::user();

            // Active company context must be one the user may access.
            $active = SessionContext::companyId();
            if ($active && !CurrentContextService::canAccessCompany($active)) {
                SessionContext::forgetCompany();
            }

            // Any explicit ?company_id= in the request must be accessible.
            $cid = (int) ($request->query('company_id') ?? 0);
            if ($cid > 0 && !CurrentContextService::canAccessCompany($cid)) {
                return Response::json(['error' => 'forbidden'], 403);
            }
        }
        return null;
    }
}
