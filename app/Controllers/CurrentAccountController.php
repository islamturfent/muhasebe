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
use Muh\Services\CurrentAccountService;

/**
 * Current accounts (cari hesap) management — Phase 4.
 */
final class CurrentAccountController extends Controller
{
    public function index(Request $request): Response
    {
        Auth::requireCan('current_account.read');

        $tenantId = Auth::tenantId();
        $companyId = (int) ($request->query('company_id') ?? 0);
        $type = $request->query('type') ?: null;
        $search = $request->query('search') ?: null;

        $service = new CurrentAccountService();
        $accounts = $service->listForCompany($tenantId, $companyId ?: null, $type, $search);

        // Companies for the create form / filter.
        $companies = DB::select(
            'SELECT id, name, currency FROM companies WHERE tenant_id = :t AND deleted_at IS NULL ORDER BY name',
            ['t' => $tenantId]
        );

        $defaultCompanyId = $companyId ?: ($companies[0]['id'] ?? null);

        return $this->view('app.current-accounts.index', [
            'layout' => 'layouts.app',
            'accounts' => $accounts,
            'companies' => $companies,
            'companyId' => $companyId,
            'defaultCompanyId' => $defaultCompanyId,
            'type' => $type,
            'search' => $search,
        ]);
    }

    public function create(Request $request): Response
    {
        Auth::requireCan('current_account.create');

        $tenantId = Auth::tenantId();
        $companies = DB::select(
            'SELECT id, name FROM companies WHERE tenant_id = :t AND deleted_at IS NULL ORDER BY name',
            ['t' => $tenantId]
        );

        $selectedCompany = (int) ($request->query('company_id') ?? ($companies[0]['id'] ?? 0));
        $account = $request->all(); // preserve submitted values on validation error

        return $this->view('app.current-accounts.create', [
            'layout' => 'layouts.app',
            'companies' => $companies,
            'selectedCompany' => $selectedCompany,
            'account' => $account,
        ]);
    }

    public function store(Request $request): Response
    {
        Auth::requireCan('current_account.create');

        $service = new CurrentAccountService();
        try {
            $id = $service->create($request->all(), $request);
        } catch (ValidationException $e) {
            Session::set('_form_errors', $e->errors);
            return Response::redirect('/app/current-accounts/create?company_id=' . (int) $request->input('company_id'));
        }

        Session::flash('success', __('current_account.created'));
        return Response::redirect('/app/current-accounts/' . $id);
    }

    public function show(Request $request, $id): Response
    {
        $id = (int) $id;
        Auth::requireCan('current_account.read');

        $service = new CurrentAccountService();
        $account = $service->findForTenant(Auth::tenantId(), $id);
        if (!$account) {
            return Response::redirect('/app/current-accounts');
        }

        $company = DB::first(
            'SELECT id, name, currency FROM companies WHERE id = :id AND tenant_id = :t',
            ['id' => $account['company_id'], 't' => Auth::tenantId()]
        );
        $transactions = $service->transactions($id);

        return $this->view('app.current-accounts.show', [
            'layout' => 'layouts.app',
            'account' => $account,
            'company' => $company,
            'transactions' => $transactions,
        ]);
    }

    public function edit(Request $request, $id): Response
    {
        $id = (int) $id;
        Auth::requireCan('current_account.update');

        $service = new CurrentAccountService();
        $account = $service->findForTenant(Auth::tenantId(), $id);
        if (!$account) {
            return Response::redirect('/app/current-accounts');
        }

        return $this->view('app.current-accounts.edit', [
            'layout' => 'layouts.app',
            'account' => $account,
        ]);
    }

    public function update(Request $request, $id): Response
    {
        $id = (int) $id;
        Auth::requireCan('current_account.update');

        $service = new CurrentAccountService();
        try {
            $service->update($id, $request->all());
        } catch (ValidationException $e) {
            Session::set('_form_errors', $e->errors);
            return Response::redirect('/app/current-accounts/' . $id . '/edit');
        }
        Session::flash('success', __('current_account.updated'));
        return Response::redirect('/app/current-accounts/' . $id);
    }

    public function destroy(Request $request, $id): Response
    {
        $id = (int) $id;
        Auth::requireCan('current_account.delete');

        $service = new CurrentAccountService();
        $service->delete($id);
        Session::flash('success', __('current_account.deleted'));
        return Response::redirect('/app/current-accounts');
    }
}
