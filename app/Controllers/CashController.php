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

    /**
     * Virman: transfer funds from this cash account to another.
     */
    public function virman(Request $request, $id): Response
    {
        $id = (int) $id;
        Auth::requireCan('cash.create');
        // Destination is sent as "type:id" (cash:12 or bank:34).
        [$toType, $toId] = array_pad(explode(':', (string) $request->input('to')), 2, '');
        try {
            (new TransferService())->transfer(
                'cash',
                $id,
                $toType,
                (int) $toId,
                (float) $request->input('amount'),
                $request->input('date') ?: null,
                $request->input('description') ?: null
            );
            Session::flash('success', __('cash.virman_done'));
        } catch (ValidationException $e) {
            Session::set('_form_errors', $e->errors);
        }
        return Response::redirect('/app/cash/' . $id);
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
        $from = $request->query('from') ?: null;
        $to = $request->query('to') ?: null;
        $type = $request->query('type') ?: null;
        $trx = $service->transactions($id, $from, $to, $type);
        // Cash + bank accounts available as virman destinations.
        $transferDests = (new TransferService())->destinations('cash', $id);
        return $this->view('app.cash.show', [
            'layout' => 'layouts.app',
            'account' => $account,
            'company' => $company,
            'transactions' => $trx['items'],
            'transferDests' => $transferDests,
            'page' => $trx['page'],
            'lastPage' => $trx['lastPage'],
            'total' => $trx['total'],
            'from' => $from,
            'to' => $to,
            'type' => $type,
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
