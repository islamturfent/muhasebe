<?php

declare(strict_types=1);

namespace Muh\Controllers;

use Muh\Core\Auth;
use Muh\Core\Controller;
use Muh\Core\DB;
use Muh\Core\Request;
use Muh\Core\Response;
use Muh\Core\Session;
use Muh\Core\ValidationException;
use Muh\Services\PeriodService;

/**
 * Fiscal period lifecycle (closing & carry-forward).
 */
final class PeriodController extends Controller
{
    public function close(Request $request): Response
    {
        Auth::requireCan('accounting.create');
        $tenantId = Auth::tenantId();
        $companyId = (int) $request->input('company_id');
        $periodId = (int) $request->input('period_id');
        $targetId = (int) ($request->input('target_period_id') ?? 0);

        // Ensure the company belongs to this tenant.
        $company = DB::first(
            'SELECT id FROM companies WHERE id = :id AND tenant_id = :t AND deleted_at IS NULL',
            ['id' => $companyId, 't' => $tenantId]
        );
        if (!$company) {
            Session::flash('error', __('app.not_authorized'));
            return Response::redirect('/app/companies');
        }

        try {
            PeriodService::close($tenantId, $companyId, $periodId, $targetId ?: null);
            Session::flash('success', __('period.closed'));
        } catch (ValidationException $e) {
            Session::flash('error', implode(' ', $e->errors));
        }

        return Response::redirect('/app/companies/' . $companyId);
    }
}
