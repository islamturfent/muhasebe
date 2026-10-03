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
use Muh\Services\BankService;

final class BankController extends Controller
{
    public function index(Request $request): Response
    {
        Auth::requireCan('bank.read');
        return $this->view('app.bank.index', [
            'layout' => 'layouts.app',
            'accounts' => (new BankService())->accounts(),
        ]);
    }

    public function create(Request $request): Response
    {
        Auth::requireCan('bank.create');
        $companies = DB::select('SELECT id, name FROM companies WHERE tenant_id = :t AND deleted_at IS NULL ORDER BY name', ['t' => Auth::tenantId()]);
        return $this->view('app.bank.create', [
            'layout' => 'layouts.app',
            'companies' => $companies,
            'selectedCompany' => (int) ($request->query('company_id') ?? ($companies[0]['id'] ?? 0)),
        ]);
    }

    public function store(Request $request): Response
    {
        Auth::requireCan('bank.create');
        try {
            (new BankService())->createAccount($request->all());
        } catch (ValidationException $e) {
            Session::set('_form_errors', $e->errors);
            return Response::redirect('/app/bank/create');
        }
        Session::flash('success', __('bank.created_account'));
        return Response::redirect('/app/bank');
    }

    public function show(Request $request, $id): Response
    {
        $id = (int) $id;
        Auth::requireCan('bank.read');
        $service = new BankService();
        $account = $service->account(Auth::tenantId(), $id);
        if (!$account) {
            return Response::redirect('/app/bank');
        }
        $trx = $service->transactions($id);
        return $this->view('app.bank.show', [
            'layout' => 'layouts.app',
            'account' => $account,
            'transactions' => $trx['items'],
            'page' => $trx['page'],
            'lastPage' => $trx['lastPage'],
            'total' => $trx['total'],
        ]);
    }

    public function transaction(Request $request, $id): Response
    {
        $id = (int) $id;
        Auth::requireCan('bank.create');
        $service = new BankService();
        $account = $service->account(Auth::tenantId(), $id);
        if (!$account) {
            return Response::redirect('/app/bank');
        }
        BankService::addTransaction(
            (int) $account['tenant_id'], (int) $account['company_id'], $id,
            $request->input('type'), $request->input('date') ?: date('Y-m-d'),
            (float) $request->input('amount'), $request->input('description')
        );
        Session::flash('success', __('bank.created_transaction'));
        return Response::redirect('/app/bank/' . $id);
    }
}
