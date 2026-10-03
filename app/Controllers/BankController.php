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
        $from = $request->query('from') ?: null;
        $to = $request->query('to') ?: null;
        $type = $request->query('type') ?: null;
        $trx = $service->transactions($id, $from, $to, $type);
        $transferDests = (new \Muh\Services\TransferService())->destinations('bank', $id);
        $recon = DB::first(
            "SELECT
                COALESCE(SUM(CASE WHEN type IN ('deposit','interest','transfer') THEN amount ELSE 0 END), 0) AS deposits,
                COALESCE(SUM(CASE WHEN type IN ('withdrawal','fee') THEN amount ELSE 0 END), 0) AS withdrawals,
                COUNT(*) AS tx_count
               FROM bank_transactions WHERE bank_account_id = :i",
            ['i' => $id]
        );
        $deposits = (float) ($recon['deposits'] ?? 0);
        $withdrawals = (float) ($recon['withdrawals'] ?? 0);
        $net = $deposits - $withdrawals;
        $bankBalance = (float) $account['balance'];
        $reconData = [
            'deposits' => $deposits,
            'withdrawals' => $withdrawals,
            'net' => $net,
            'balance' => $bankBalance,
            'difference' => $bankBalance - $net,
            'ok' => abs($bankBalance - $net) < 0.005,
        ];
        return $this->view('app.bank.show', [
            'recon' => $reconData,
            'layout' => 'layouts.app',
            'account' => $account,
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

    /**
     * Virman from a bank account to any cash/bank account.
     */
    public function virman(Request $request, $id): Response
    {
        $id = (int) $id;
        Auth::requireCan('bank.create');
        [$toType, $toId] = array_pad(explode(':', (string) $request->input('to')), 2, '');
        try {
            (new \Muh\Services\TransferService())->transfer(
                'bank',
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
        return Response::redirect('/app/bank/' . $id);
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
