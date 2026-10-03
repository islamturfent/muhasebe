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

    /** Yeni yevmiye/mahsup fişi formu. */
    public function createEntry(Request $request): Response
    {
        Auth::requireCan('accounting.create');
        [$companies, $companyId, $periods, $periodId] = $this->resolveContext($request);
        $accounts = $companyId ? DB::select(
            'SELECT code, name FROM accounting_accounts WHERE company_id = :c AND fiscal_period_id = :p ORDER BY code',
            ['c' => $companyId, 'p' => $periodId]
        ) : [];
        $type = in_array($request->query('type', 'journal'), ['journal', 'transfer', 'opening', 'closing', 'carry_forward'], true)
            ? $request->query('type', 'journal')
            : 'journal';
        return $this->view('app.accounting.entry-create', [
            'layout' => 'layouts.app',
            'companies' => $companies, 'companyId' => $companyId,
            'periods' => $periods, 'periodId' => $periodId,
            'accounts' => $accounts,
            'voucherType' => $type,
        ]);
    }

    /** Yevmiye fişi kaydet (dengeli olmalı). */
    public function storeEntry(Request $request): Response
    {
        Auth::requireCan('accounting.create');
        $companyId = (int) $request->input('company_id');
        $periodId = (int) ($request->input('period_id') ?? 0);
        $tenantId = Auth::tenantId();

        $lines = [];
        foreach ((array) $request->input('lines', []) as $l) {
            $code = trim((string) ($l['account_code'] ?? ''));
            $debit = (float) ($l['debit'] ?? 0);
            $credit = (float) ($l['credit'] ?? 0);
            if ($code === '' || ($debit <= 0 && $credit <= 0)) {
                continue;
            }
            $lines[] = ['account_code' => $code, 'debit' => $debit, 'credit' => $credit];
        }

        $voucherType = in_array($request->input('voucher_type', 'journal'), ['journal', 'transfer', 'opening', 'closing', 'carry_forward'], true)
            ? $request->input('voucher_type', 'journal')
            : 'journal';

        try {
            $id = AccountingService::postEntry(
                $tenantId, $companyId, $periodId,
                $voucherType, $request->input('date') ?: date('Y-m-d'),
                $request->input('description') ?: __('accounting.manual_entry'),
                $lines, $request
            );
            Session::flash('success', __('accounting.entry_created'));
            return Response::redirect('/app/accounting/journal?company_id=' . $companyId . '&period_id=' . $periodId);
        } catch (ValidationException $e) {
            Session::set('_form_errors', $e->errors);
            return Response::redirect('/app/accounting/entry/create?company_id=' . $companyId . '&period_id=' . $periodId);
        }
    }

    /** Yevmiye fişi sil. */
    public function destroyEntry(Request $request, $id): Response
    {
        Auth::requireCan('accounting.delete');
        $id = (int) $id;
        $entry = DB::first('SELECT * FROM accounting_entries WHERE id = :id AND tenant_id = :t', ['id' => $id, 't' => Auth::tenantId()]);
        if ($entry) {
            DB::execute('DELETE FROM accounting_entry_lines WHERE entry_id = :id', ['id' => $id]);
            DB::execute('DELETE FROM accounting_entries WHERE id = :id', ['id' => $id]);
            Session::flash('success', __('accounting.entry_deleted'));
        }
        return Response::redirect('/app/accounting/journal?company_id=' . (int) $entry['company_id'] . '&period_id=' . (int) $entry['fiscal_period_id']);
    }

    // ---- Büyük Defter (General Ledger) ----

    public function ledger(Request $request): Response
    {
        Auth::requireCan('accounting.read');
        [$companies, $companyId, $periods, $periodId] = $this->resolveContext($request);
        $accountId = (int) ($request->query('account_id') ?? 0);
        $accountOptions = $companyId ? DB::select(
            'SELECT id, code, name FROM accounting_accounts WHERE company_id = :c AND fiscal_period_id = :p ORDER BY code',
            ['c' => $companyId, 'p' => $periodId]
        ) : [];
        $lines = $companyId ? AccountingService::ledger($companyId, $periodId, $accountId ?: null) : [];

        return $this->view('app.accounting.ledger', [
            'layout' => 'layouts.app',
            'companies' => $companies, 'companyId' => $companyId,
            'periods' => $periods, 'periodId' => $periodId,
            'accountOptions' => $accountOptions, 'accountId' => $accountId,
            'lines' => $lines,
        ]);
    }

    public function ledgerExport(Request $request): Response
    {
        Auth::requireCan('report.export');
        $format = $request->query('format', 'csv');
        [$companyId, $periodId] = $this->ledgerCtx($request);
        $accountId = (int) ($request->query('account_id') ?? 0);
        $lines = $companyId ? AccountingService::ledger($companyId, $periodId, $accountId ?: null) : [];

        $headers = [
            __('accounting.account_code'), __('common.name'), __('accounting.number'), __('accounting.date'),
            __('accounting.description'), __('common.debit'), __('common.credit'), __('accounting.balance'),
        ];
        $running = [];
        $out = [];
        foreach ($lines as $ln) {
            $k = $ln['account_code'];
            if (!isset($running[$k])) {
                $running[$k] = (float) $ln['opening_debit'] - (float) $ln['opening_credit'];
            }
            $running[$k] += (float) $ln['debit'] - (float) $ln['credit'];
            $out[] = [
                $ln['account_code'], $ln['account_name'], $ln['number'], format_date($ln['date']),
                $ln['description'] ?? '',
                number_format((float) $ln['debit'], 2, ',', '.'),
                number_format((float) $ln['credit'], 2, ',', '.'),
                number_format($running[$k], 2, ',', '.'),
            ];
        }
        $safe = str_slug('buyuk-defter');
        switch ($format) {
            case 'excel':
                return \Muh\Services\ReportExportService::excel(__('accounting.ledger'), $headers, $out, $safe . '.xls');
            case 'pdf':
                return \Muh\Services\ReportExportService::pdf(__('accounting.ledger'), 'C:' . $companyId . ' P:' . $periodId, $headers, $out, $safe . '.pdf');
            default:
                return \Muh\Services\ReportExportService::csv($headers, $out, $safe . '.csv');
        }
    }

    private function ledgerCtx(Request $request): array
    {
        $tenantId = Auth::tenantId();
        $companies = DB::select('SELECT id FROM companies WHERE tenant_id = :t AND deleted_at IS NULL ORDER BY name', ['t' => $tenantId]);
        $companyId = (int) ($request->query('company_id') ?? ($companies[0]['id'] ?? 0));
        $periods = $companyId ? DB::select('SELECT id FROM fiscal_periods WHERE company_id = :c ORDER BY start_date DESC', ['c' => $companyId]) : [];
        $periodId = (int) ($request->query('period_id') ?? ($periods[0]['id'] ?? 0));
        return [$companyId, $periodId];
    }

    // ---- Hesap Planı (Chart of Accounts) CRUD ----

    public function chart(Request $request): Response
    {
        Auth::requireCan('accounting.read');
        [$companies, $companyId, $periods, $periodId] = $this->resolveContext($request);
        $accounts = $companyId ? DB::select(
            'SELECT * FROM accounting_accounts WHERE company_id = :c AND fiscal_period_id = :p ORDER BY code ASC',
            ['c' => $companyId, 'p' => $periodId]
        ) : [];

        return $this->view('app.accounting.chart', [
            'layout' => 'layouts.app',
            'companies' => $companies, 'companyId' => $companyId,
            'periods' => $periods, 'periodId' => $periodId,
            'accounts' => $accounts,
        ]);
    }

    private function chartFormData(Request $request): array
    {
        [$companies, $companyId, $periods, $periodId] = $this->resolveContext($request);
        $periodOptions = DB::select('SELECT id, name FROM fiscal_periods WHERE company_id = :c AND deleted_at IS NULL ORDER BY start_date DESC', ['c' => $companyId]);
        foreach ($periods as &$p) {
            $p['is_current'] = (int) $p['is_current'];
        }
        return [$companies, $companyId, $periodOptions, $periodId];
    }

    public function createChartAccount(Request $request): Response
    {
        Auth::requireCan('accounting.create');
        [$companies, $companyId, $periodOptions, $periodId] = $this->chartFormData($request);
        return $this->view('app.accounting.chart-create', [
            'layout' => 'layouts.app',
            'companies' => $companies, 'companyId' => $companyId,
            'periods' => $periodOptions, 'periodId' => $periodId,
            'account' => null,
        ]);
    }

    public function storeChartAccount(Request $request): Response
    {
        Auth::requireCan('accounting.create');
        $tenantId = Auth::tenantId();
        $companyId = (int) $request->input('company_id');
        $periodId = (int) ($request->input('period_id') ?? 0);

        $code = trim((string) $request->input('code'));
        $name = trim((string) $request->input('name'));
        $type = (string) $request->input('type');
        $isHeader = $request->input('is_header') ? 1 : 0;
        $openingDebit = (float) ($request->input('opening_debit') ?? 0);
        $openingCredit = (float) ($request->input('opening_credit') ?? 0);
        $currency = $request->input('currency') ?: 'TRY';

        $validTypes = ['asset', 'liability', 'equity', 'income', 'expense'];
        if ($code === '' || $name === '' || !in_array($type, $validTypes, true) || !$companyId || !$periodId) {
            Session::set('_form_errors', [__('accounting.account_required')]);
            return Response::redirect('/app/accounting/chart/create?company_id=' . $companyId . '&period_id=' . $periodId);
        }

        $exists = DB::first(
            'SELECT id FROM accounting_accounts WHERE company_id = :c AND fiscal_period_id = :p AND code = :code',
            ['c' => $companyId, 'p' => $periodId, 'code' => $code]
        );
        if ($exists) {
            Session::set('_form_errors', [__('accounting.code_exists')]);
            return Response::redirect('/app/accounting/chart/create?company_id=' . $companyId . '&period_id=' . $periodId);
        }

        DB::insert('accounting_accounts', [
            'tenant_id'     => $tenantId,
            'company_id'    => $companyId,
            'fiscal_period_id' => $periodId,
            'code'          => $code,
            'name'          => $name,
            'type'          => $type,
            'subtype'       => $request->input('subtype') ?: null,
            'group'         => substr($code, 0, 1),
            'is_header'     => $isHeader,
            'currency'      => $currency,
            'opening_debit' => $openingDebit,
            'opening_credit'=> $openingCredit,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
        Session::flash('success', __('accounting.account_created'));
        return Response::redirect('/app/accounting/chart?company_id=' . $companyId . '&period_id=' . $periodId);
    }

    public function editChartAccount(Request $request, $id): Response
    {
        Auth::requireCan('accounting.update');
        $id = (int) $id;
        $account = DB::first(
            'SELECT * FROM accounting_accounts WHERE id = :id AND tenant_id = :t',
            ['id' => $id, 't' => Auth::tenantId()]
        );
        if (!$account) {
            return Response::redirect('/app/accounting/chart');
        }
        $companyId = (int) $account['company_id'];
        $periodId = (int) $account['fiscal_period_id'];
        $companies = DB::select('SELECT id, name FROM companies WHERE tenant_id = :t AND deleted_at IS NULL ORDER BY name', ['t' => Auth::tenantId()]);
        $periodOptions = DB::select('SELECT id, name FROM fiscal_periods WHERE company_id = :c ORDER BY start_date DESC', ['c' => $companyId]);

        return $this->view('app.accounting.chart-create', [
            'layout' => 'layouts.app',
            'companies' => $companies, 'companyId' => $companyId,
            'periods' => $periodOptions, 'periodId' => $periodId,
            'account' => $account,
        ]);
    }

    public function updateChartAccount(Request $request, $id): Response
    {
        Auth::requireCan('accounting.update');
        $id = (int) $id;
        $account = DB::first('SELECT * FROM accounting_accounts WHERE id = :id AND tenant_id = :t', ['id' => $id, 't' => Auth::tenantId()]);
        if (!$account) {
            return Response::redirect('/app/accounting/chart');
        }

        $code = trim((string) $request->input('code'));
        $name = trim((string) $request->input('name'));
        $type = (string) $request->input('type');
        $validTypes = ['asset', 'liability', 'equity', 'income', 'expense'];
        if ($code === '' || $name === '' || !in_array($type, $validTypes, true)) {
            Session::set('_form_errors', [__('accounting.account_required')]);
            return Response::redirect('/app/accounting/chart/' . $id . '/edit');
        }

        $dupe = DB::first(
            'SELECT id FROM accounting_accounts WHERE company_id = :c AND fiscal_period_id = :p AND code = :code AND id != :id',
            ['c' => $account['company_id'], 'p' => $account['fiscal_period_id'], 'code' => $code, 'id' => $id]
        );
        if ($dupe) {
            Session::set('_form_errors', [__('accounting.code_exists')]);
            return Response::redirect('/app/accounting/chart/' . $id . '/edit');
        }

        DB::execute(
            'UPDATE accounting_accounts SET code = :code, name = :name, type = :t, subtype = :st,
                    is_header = :h, currency = :cu, opening_debit = :od, opening_credit = :oc, updated_at = NOW()
              WHERE id = :id',
            [
                'code' => $code, 'name' => $name, 't' => $type,
                'st' => $request->input('subtype') ?: null, 'h' => $request->input('is_header') ? 1 : 0,
                'cu' => $request->input('currency') ?: 'TRY',
                'od' => (float) ($request->input('opening_debit') ?? 0),
                'oc' => (float) ($request->input('opening_credit') ?? 0),
                'id' => $id,
            ]
        );
        Session::flash('success', __('accounting.account_updated'));
        return Response::redirect('/app/accounting/chart?company_id=' . (int) $account['company_id'] . '&period_id=' . (int) $account['fiscal_period_id']);
    }

    public function destroyChartAccount(Request $request, $id): Response
    {
        Auth::requireCan('accounting.delete');
        $id = (int) $id;
        $account = DB::first('SELECT * FROM accounting_accounts WHERE id = :id AND tenant_id = :t', ['id' => $id, 't' => Auth::tenantId()]);
        $refs = $account ? (int) DB::scalar('SELECT COUNT(*) FROM accounting_entry_lines WHERE account_id = :id', ['id' => $id]) : 0;
        if ($account && $refs === 0) {
            DB::execute('DELETE FROM accounting_accounts WHERE id = :id', ['id' => $id]);
            Session::flash('success', __('accounting.account_deleted'));
        } else {
            Session::flash('error', __('accounting.account_in_use'));
        }
        return Response::redirect('/app/accounting/chart?company_id=' . (int) ($account['company_id'] ?? 0) . '&period_id=' . (int) ($account['fiscal_period_id'] ?? 0));
    }
}

    
