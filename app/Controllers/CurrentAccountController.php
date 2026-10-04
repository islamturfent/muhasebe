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

        $sql = 'SELECT ca.*, c.name AS company_name FROM current_accounts ca
                 JOIN companies c ON c.id = ca.company_id
                WHERE ca.tenant_id = :t AND ca.deleted_at IS NULL';
        $params = ['t' => $tenantId];
        if ($companyId) {
            $sql .= ' AND ca.company_id = :c';
            $params['c'] = $companyId;
        }
        if ($type && in_array($type, ['customer', 'supplier', 'both'], true)) {
            $sql .= ' AND ca.type = :type';
            $params['type'] = $type;
        }
        if ($search) {
            $sql .= ' AND (ca.name LIKE :s OR ca.code LIKE :s OR ca.tax_number LIKE :s)';
            $params['s'] = '%' . $search . '%';
        }
        $sql .= ' ORDER BY ca.name ASC';

        $page = paginate($sql, $params, 25);

        // Companies for the create form / filter.
        $companies = DB::select(
            'SELECT id, name, currency FROM companies WHERE tenant_id = :t AND deleted_at IS NULL ORDER BY name',
            ['t' => $tenantId]
        );

        $defaultCompanyId = $companyId ?: ($companies[0]['id'] ?? null);

        return $this->view('app.current-accounts.index', [
            'layout' => 'layouts.app',
            'accounts' => $page['items'],
            'companies' => $companies,
            'companyId' => $companyId,
            'defaultCompanyId' => $defaultCompanyId,
            'type' => $type,
            'search' => $search,
            'page' => $page['page'],
            'lastPage' => $page['lastPage'],
            'total' => $page['total'],
        ]);
    }

    /** Cari listesini CSV/Excel olarak dışa aktar (master-data export). */
    public function export(Request $request): Response
    {
        Auth::requireCan('current_account.read');
        $tenantId = Auth::tenantId();
        $format = $request->query('format', 'csv');
        $companyId = (int) ($request->query('company_id') ?? 0);
        $type = $request->query('type') ?: null;

        $sql = 'SELECT ca.*, c.name AS company_name FROM current_accounts ca
                 JOIN companies c ON c.id = ca.company_id
                WHERE ca.tenant_id = :t AND ca.deleted_at IS NULL';
        $params = ['t' => $tenantId];
        if ($companyId) {
            $sql .= ' AND ca.company_id = :c';
            $params['c'] = $companyId;
        }
        if ($type && in_array($type, ['customer', 'supplier', 'both'], true)) {
            $sql .= ' AND ca.type = :type';
            $params['type'] = $type;
        }
        $sql .= ' ORDER BY ca.name ASC';
        $rows = DB::select($sql, $params);

        $headers = [
            __('current_account.code'), __('current_account.name'), __('current_account.company'),
            __('current_account.type'), __('current_account.tax_number'), __('current_account.phone'),
            __('current_account.email'), __('current_account.iban'), __('current_account.balance'),
        ];
        $data = array_map(fn ($r) => [
            $r['code'], $r['name'], $r['company_name'],
            __('current_account.type_' . $r['type']), $r['tax_number'] ?? '', $r['phone'] ?? '',
            $r['email'] ?? '', $r['iban'] ?? '', number_format((float) ($r['balance'] ?? 0), 2, ',', '.'),
        ], $rows);

        return $format === 'excel'
            ? \Muh\Services\ReportExportService::excel(__('current_account.title'), $headers, $data, 'cari.xls')
            : \Muh\Services\ReportExportService::csv($headers, $data, 'cari.csv');
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

        \Muh\Services\CurrentContextService::guardRecord((int) Auth::tenantId(), 'current_accounts', $id);

        $service = new CurrentAccountService();
        $account = $service->findForTenant(Auth::tenantId(), $id);
        if (!$account) {
            return Response::redirect('/app/current-accounts');
        }

        $company = DB::first(
            'SELECT id, name, currency FROM companies WHERE id = :id AND tenant_id = :t',
            ['id' => $account['company_id'], 't' => Auth::tenantId()]
        );
        $from = $request->query('from') ?: null;
        $to = $request->query('to') ?: null;
        $type = $request->query('type') ?: null;
        $trx = $service->transactions($id, $from, $to, $type);
        // Statement (ekstre) summary for the selected range (Item 2).
        $statement = CurrentAccountService::statement($id, $from, $to);

        // Cash & bank accounts for the collection / payment (tahsil / tediye) form.
        $tenantId = (int) Auth::tenantId();
        $cashAccounts = DB::select('SELECT id, name, code FROM cash_accounts WHERE company_id = :c AND tenant_id = :t AND deleted_at IS NULL ORDER BY name', ['c' => (int) $account['company_id'], 't' => $tenantId]);
        $bankAccounts = DB::select('SELECT id, bank_name, account_name FROM bank_accounts WHERE company_id = :c AND tenant_id = :t AND deleted_at IS NULL ORDER BY bank_name', ['c' => (int) $account['company_id'], 't' => $tenantId]);
        $unpaidInvoices = $account['type'] === 'customer'
            ? DB::select("SELECT id, number, total, paid FROM invoices WHERE company_id = :c AND current_account_id = :a AND type = 'sales' AND status = 'posted' AND paid < total AND deleted_at IS NULL ORDER BY due_date LIMIT 20", ['c' => (int) $account['company_id'], 'a' => $id])
            : [];
        $documents = (new \Muh\Services\DocumentService())->forCurrentAccount(Auth::tenantId(), $id);

        return $this->view('app.current-accounts.show', [
            'statement' => $statement,
            'layout' => 'layouts.app',
            'account' => $account,
            'company' => $company,
            'transactions' => $trx['items'],
            'cashAccounts' => $cashAccounts,
            'bankAccounts' => $bankAccounts,
            'unpaidInvoices' => $unpaidInvoices,
            'documents' => $documents,
            'page' => $trx['page'],
            'lastPage' => $trx['lastPage'],
            'total' => $trx['total'],
            'from' => $from,
            'to' => $to,
            'type' => $type,
        ]);
    }

    /** Tahsilat / Ödeme kaydı (manual collection / payment). */
    public function transaction(Request $request, $id): Response
    {
        $id = (int) $id;
        Auth::requireCan('current_account.update');
        $tenantId = (int) Auth::tenantId();
        $service = new CurrentAccountService();
        $account = $service->findForTenant($tenantId, $id);
        if (!$account) {
            return Response::redirect('/app/current-accounts');
        }
        $type = $request->input('type');
        $targetType = $request->input('target_type') ?: 'cash';
        $targetId = (int) ($request->input('target_id') ?? 0);
        $amount = (float) ($request->input('amount') ?? 0);
        $date = $request->input('date') ?: date('Y-m-d');
        $invoiceId = (int) ($request->input('invoice_id') ?? 0);

        try {
            if ($amount <= 0) {
                throw new \Muh\Core\ValidationException(['amount' => __('validation.min')]);
            }
            CurrentAccountService::postCollectionPayment(
                $tenantId, (int) $account['company_id'], $id,
                $type === 'payment' ? 'payment' : 'collection',
                $date, $amount, $targetType, $targetId,
                $request->input('description') ?: null,
                $invoiceId ?: null
            );
            Session::flash('success', $type === 'payment' ? __('current_account.payment_done') : __('current_account.collection_done'));
        } catch (\Muh\Core\ValidationException $e) {
            Session::set('_form_errors', $e->errors);
        }
        return Response::redirect('/app/current-accounts/' . $id);
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
