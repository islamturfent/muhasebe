<?php

declare(strict_types=1);

namespace Muh\Controllers;

use Muh\Core\Auth;
use Muh\Core\Controller;
use Muh\Core\DB;
use Muh\Core\Request;
use Muh\Core\Response;
use Muh\Core\Session;
use Muh\Models\Company;

/**
 * Client company management (Phase 2).
 */
final class CompanyController extends Controller
{
    public function index(Request $request): Response
    {
        Auth::requireCan('company.read');
        $paged = paginate(
            'SELECT * FROM companies WHERE tenant_id = :t AND deleted_at IS NULL ORDER BY id DESC',
            ['t' => Auth::tenantId()],
            25
        );
        $companies = $paged['items'];

        $stats = [];
        foreach ($companies as $c) {
            $stats[(int) $c['id']] = [
                'periods' => (int) DB::scalar(
                    'SELECT COUNT(*) FROM fiscal_periods WHERE company_id = :c AND deleted_at IS NULL',
                    ['c' => $c['id']]
                ),
                'invoices' => (int) DB::scalar(
                    'SELECT COUNT(*) FROM invoices WHERE company_id = :c AND deleted_at IS NULL',
                    ['c' => $c['id']]
                ),
                'balance' => (float) (DB::scalar(
                    'SELECT COALESCE(SUM(balance),0) FROM current_accounts WHERE company_id = :c AND deleted_at IS NULL',
                    ['c' => $c['id']]
                ) ?? 0),
            ];
        }

        return $this->view('app.companies.index', [
            'layout' => 'layouts.app',
            'companies' => $companies,
            'stats' => $stats,
            'page' => $paged['page'],
            'lastPage' => $paged['lastPage'],
            'total' => $paged['total'],
        ]);
    }

    public function show(Request $request, $id): Response
    {
        $id = (int) $id;
        Auth::requireCan('company.read');

        $company = DB::first(
            'SELECT * FROM companies WHERE id = :id AND tenant_id = :t AND deleted_at IS NULL',
            ['id' => $id, 't' => Auth::tenantId()]
        );
        if (!$company) {
            return Response::redirect('/app/companies');
        }

        $periods = DB::select(
            'SELECT * FROM fiscal_periods WHERE company_id = :c AND deleted_at IS NULL ORDER BY start_date DESC',
            ['c' => $id]
        );

        // Latest chart of accounts (for the current period, fallback to any).
        $periodId = $periods[0]['id'] ?? null;
        $accounts = [];
        if ($periodId) {
            $accounts = DB::select(
                'SELECT code, name, type, opening_debit, opening_credit FROM accounting_accounts
                  WHERE company_id = :c AND fiscal_period_id = :p
                  ORDER BY code ASC',
                ['c' => $id, 'p' => $periodId]
            );
        }

        $currentAccounts = DB::select(
            'SELECT * FROM current_accounts WHERE company_id = :c AND deleted_at IS NULL ORDER BY name LIMIT 50',
            ['c' => $id]
        );

        $branches = DB::select(
            'SELECT * FROM branches WHERE company_id = :c AND deleted_at IS NULL ORDER BY name',
            ['c' => $id]
        );

        return $this->view('app.companies.show', [
            'layout' => 'layouts.app',
            'company' => $company,
            'periods' => $periods,
            'accounts' => $accounts,
            'currentAccounts' => $currentAccounts,
            'branches' => $branches,
        ]);
    }

    public function create(Request $request): Response
    {
        Auth::requireCan('company.create');
        return $this->view('app.companies.create', ['layout' => 'layouts.app']);
    }

    public function store(Request $request): Response
    {
        Auth::requireCan('company.create');
        try {
            $id = (new \Muh\Services\CompanyService())->create($request->all());
        } catch (\Muh\Core\ValidationException $e) {
            Session::set('_form_errors', $e->errors);
            return Response::redirect('/app/companies/create');
        }
        Session::flash('success', __('app.company_created'));
        return Response::redirect('/app/companies/' . $id);
    }
}
