<?php

declare(strict_types=1);

namespace Muh\Controllers;

use Muh\Core\Auth;
use Muh\Core\DB;
use Muh\Core\Controller;
use Muh\Core\Request;
use Muh\Core\Response;
use Muh\Core\Session;
use Muh\Core\ValidationException;
use Muh\Services\AccountingService;

/**
 * Accounting / double-entry views (Phase 8): journal, trial balance,
 * balance sheet, income statement.
 */
final class AccountingController extends Controller
{
    private function resolveContext(Request $request): array
    {
        $tenantId = Auth::tenantId();
        $companies = DB::select('SELECT id, name FROM companies WHERE tenant_id = :t AND deleted_at IS NULL ORDER BY name', ['t' => $tenantId]);

        $companyId = (int) ($request->query('company_id') ?? ($companies[0]['id'] ?? 0));
        $periods = [];
        if ($companyId) {
            $periods = DB::select('SELECT * FROM fiscal_periods WHERE company_id = :c AND deleted_at IS NULL ORDER BY start_date DESC', ['c' => $companyId]);
        }
        $periodId = (int) ($request->query('period_id') ?? ($periods[0]['id'] ?? 0));

        return [$companies, $companyId, $periods, $periodId];
    }

    public function index(Request $request): Response
    {
        Auth::requireCan('accounting.read');
        [$companies, $companyId, $periods, $periodId] = $this->resolveContext($request);

        return $this->view('app.accounting.index', [
            'layout' => 'layouts.app',
            'companies' => $companies, 'companyId' => $companyId,
            'periods' => $periods, 'periodId' => $periodId,
        ]);
    }

    public function journal(Request $request): Response
    {
        Auth::requireCan('accounting.read');
        [$companies, $companyId, $periods, $periodId] = $this->resolveContext($request);
        $entries = $companyId ? AccountingService::journal($companyId, $periodId) : [];

        return $this->view('app.accounting.journal', [
            'layout' => 'layouts.app',
            'companies' => $companies, 'companyId' => $companyId,
            'periods' => $periods, 'periodId' => $periodId,
            'entries' => $entries,
        ]);
    }

    public function trialBalance(Request $request): Response
    {
        Auth::requireCan('accounting.read');
        [$companies, $companyId, $periods, $periodId] = $this->resolveContext($request);
        $rows = $companyId ? AccountingService::trialBalance($companyId, $periodId) : [];

        return $this->view('app.accounting.trial-balance', [
            'layout' => 'layouts.app',
            'companies' => $companies, 'companyId' => $companyId,
            'periods' => $periods, 'periodId' => $periodId,
            'rows' => $rows,
        ]);
    }

    public function balanceSheet(Request $request): Response
    {
        Auth::requireCan('report.view');
        [$companies, $companyId, $periods, $periodId] = $this->resolveContext($request);
        $data = $companyId ? AccountingService::balanceSheet($companyId, $periodId) : ['asset' => [], 'liability' => [], 'equity' => []];

        return $this->view('app.accounting.balance-sheet', [
            'layout' => 'layouts.app',
            'companies' => $companies, 'companyId' => $companyId,
            'periods' => $periods, 'periodId' => $periodId,
            'data' => $data,
        ]);
    }

    public function incomeStatement(Request $request): Response
    {
        Auth::requireCan('report.view');
        [$companies, $companyId, $periods, $periodId] = $this->resolveContext($request);
        $data = $companyId ? AccountingService::incomeStatement($companyId, $periodId) : ['income' => [], 'expense' => []];

        return $this->view('app.accounting.income-statement', [
            'layout' => 'layouts.app',
            'companies' => $companies, 'companyId' => $companyId,
            'periods' => $periods, 'periodId' => $periodId,
            'data' => $data,
        ]);
    }
}
