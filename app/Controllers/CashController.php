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
use Muh\Services\CashService;

final class CashController extends Controller
{
    public function index(Request $request): Response
    {
        Auth::requireCan('cash.read');
        $service = new CashService();
        $companyId = (int) ($request->query('company_id') ?? 0);
        return $this->view('app.cash.index', [
            'layout' => 'layouts.app',
            'accounts' => $service->accounts($companyId ?: null),
            'companyId' => $companyId,
        ]);
    }

    public function create(Request $request): Response
    {
        Auth::requireCan('cash.create');
        $companies = DB::select('SELECT id, name FROM companies WHERE tenant_id = :t AND deleted_at IS NULL ORDER BY name', ['t' => Auth::tenantId()]);
        return $this->view('app.cash.create', [
            'layout' => 'layouts.app',
            'companies' => $companies,
            'selectedCompany' => (int) ($request->query('company_id') ?? ($companies[0]['id'] ?? 0)),
        ]);
    }

    public function store(Request $request): Response
    {
        Auth::requireCan('cash.create');
        try {
            (new CashService())->createAccount($request->all());
        } catch (ValidationException $e) {
            Session::set('_form_errors', $e->errors);
            return Response::redirect('/app/cash/create');
        }
        Session::flash('success', __('cash.created_account'));
        return Response::redirect('/app/cash');
    }

    public function show(Request $request, $id): Response
    {
        $id = (int) $id;
        Auth::requireCan('cash.read');
        $service = new CashService();
        $account = $service->account(Auth::tenantId(), $id);
        if (!$account) {
            return Response::redirect('/app/cash');
        }
        $company = DB::first('SELECT id, name FROM companies WHERE id = :id', ['id' => $account['company_id']]);
        return $this->view('app.cash.show', [
            'layout' => 'layouts.app',
            'account' => $account,
            'company' => $company,
            'transactions' => $service->transactions($id),
        ]);
    }

    public function transaction(Request $request, $id): Response
    {
        $id = (int) $id;
        Auth::requireCan('cash.create');
        $service = new CashService();
        $account = $service->account(Auth::tenantId(), $id);
        if (!$account) {
            return Response::redirect('/app/cash');
        }
        CashService::addTransaction(
            (int) $account['tenant_id'], (int) $account['company_id'], $id,
            $request->input('type'), $request->input('date') ?: date('Y-m-d'),
            (float) $request->input('amount'), $request->input('description')
        );
        Session::flash('success', __('cash.created_transaction'));
        return Response::redirect('/app/cash/' . $id);
    }
}
