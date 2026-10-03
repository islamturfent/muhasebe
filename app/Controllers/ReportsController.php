<?php

declare(strict_types=1);

namespace Muh\Controllers;

use Muh\Core\Auth;
use Muh\Core\DB;
use Muh\Core\Controller;
use Muh\Core\Request;
use Muh\Core\Response;
use Muh\Services\AccountingService;
use Muh\Services\ReportExportService;

/**
 * Reports with PDF/Excel/CSV export (Phase 9).
 */
final class ReportsController extends Controller
{
    public function index(Request $request): Response
    {
        Auth::requireCan('report.view');
        $tenantId = Auth::tenantId();
        $companies = DB::select('SELECT id, name FROM companies WHERE tenant_id = :t AND deleted_at IS NULL ORDER BY name', ['t' => $tenantId]);
        $companyId = (int) ($request->query('company_id') ?? ($companies[0]['id'] ?? 0));
        $periods = $companyId ? DB::select('SELECT * FROM fiscal_periods WHERE company_id = :c ORDER BY start_date DESC', ['c' => $companyId]) : [];
        $periodId = (int) ($request->query('period_id') ?? ($periods[0]['id'] ?? 0));

        return $this->view('app.reports.index', [
            'layout' => 'layouts.app',
            'companies' => $companies, 'companyId' => $companyId,
            'periods' => $periods, 'periodId' => $periodId,
        ]);
    }

    private function export(string $format, string $title, string $subtitle, array $headers, array $rows, string $baseName): Response
    {
        // Keep filename ASCII-safe for headers.
        $safe = str_slug($baseName);
        switch ($format) {
            case 'excel':
                return ReportExportService::excel($title, $headers, $rows, $safe . '.xls');
            case 'pdf':
                return ReportExportService::pdf($title, $subtitle, $headers, $rows, $safe . '.pdf');
            case 'csv':
            default:
                return ReportExportService::csv($headers, $rows, $safe . '.csv');
        }
    }

    public function mizan(Request $request): Response
    {
        $format = $request->query('format', 'csv');
        [$companyId, $periodId] = $this->ctx($request);
        $data = AccountingService::trialBalance($companyId, $periodId);
        $headers = [__('accounting.chart_of_accounts'), __('common.name'), __('accounting.debit'), __('accounting.credit'), __('accounting.balance')];
        $rows = array_map(fn ($r) => [
            $r['code'], $r['name'],
            number_format((float) $r['debit'], 2, ',', '.'),
            number_format((float) $r['credit'], 2, ',', '.'),
            number_format((float) $r['debit'] - (float) $r['credit'], 2, ',', '.'),
        ], $data);
        return $this->export($format, __('accounting.trial_balance'), "C: {$companyId} P: {$periodId}", $headers, $rows, 'mizan');
    }

    public function yevmiye(Request $request): Response
    {
        $format = $request->query('format', 'csv');
        [$companyId, $periodId] = $this->ctx($request);
        $data = AccountingService::journal($companyId, $periodId);
        $headers = [__('accounting.number'), __('accounting.date'), __('accounting.voucher_type'), __('accounting.description'), __('accounting.debit'), __('accounting.credit')];
        $rows = array_map(fn ($e) => [
            $e['number'], format_date($e['date']), $e['voucher_type'], $e['description'] ?? '',
            number_format((float) $e['debit_total'], 2, ',', '.'),
            number_format((float) $e['credit_total'], 2, ',', '.'),
        ], $data);
        return $this->export($format, __('accounting.journal'), '', $headers, $rows, 'yevmiye');
    }

    public function bilanco(Request $request): Response
    {
        $format = $request->query('format', 'csv');
        [$companyId, $periodId] = $this->ctx($request);
        $data = AccountingService::balanceSheet($companyId, $periodId);
        $headers = ['Hesap', __('common.name'), __('accounting.debit'), __('accounting.credit')];
        $rows = [];
        $rows[] = ['AKTIF', '', '', ''];
        foreach ($data['asset'] as $r) {
            $rows[] = [$r['code'], $r['name'], number_format((float) $r['debit'], 2, ',', '.'), number_format((float) $r['credit'], 2, ',', '.')];
        }
        $rows[] = ['PASIF + OZKAYNAK', '', '', ''];
        foreach (array_merge($data['liability'], $data['equity']) as $r) {
            $rows[] = [$r['code'], $r['name'], number_format((float) $r['debit'], 2, ',', '.'), number_format((float) $r['credit'], 2, ',', '.')];
        }
        return $this->export($format, __('accounting.balance_sheet'), '', $headers, $rows, 'bilanco');
    }

    public function gelir(Request $request): Response
    {
        $format = $request->query('format', 'csv');
        [$companyId, $periodId] = $this->ctx($request);
        $data = AccountingService::incomeStatement($companyId, $periodId);
        $headers = ['Tür', __('common.name'), __('accounting.debit'), __('accounting.credit'), __('accounting.balance')];
        $rows = [];
        foreach ($data['income'] as $r) {
            $rows[] = ['GELIR', $r['code'] . ' ' . $r['name'], number_format((float) $r['debit'], 2, ',', '.'), number_format((float) $r['credit'], 2, ',', '.'), number_format((float) $r['credit'] - (float) $r['debit'], 2, ',', '.')];
        }
        foreach ($data['expense'] as $r) {
            $rows[] = ['GIDER', $r['code'] . ' ' . $r['name'], number_format((float) $r['debit'], 2, ',', '.'), number_format((float) $r['credit'], 2, ',', '.'), number_format((float) $r['debit'] - (float) $r['credit'], 2, ',', '.')];
        }
        return $this->export($format, __('accounting.income_statement'), '', $headers, $rows, 'gelir-tablosu');
    }

    public function kdv(Request $request): Response
    {
        $format = $request->query('format', 'csv');
        [$companyId, $periodId] = $this->ctx($request);
        // line_total = tax base (net), tax = VAT, total = VAT-inclusive amount.
        $rows = DB::select(
            "SELECT i.type, ii.tax_rate AS rate,
                    SUM(ii.line_total) AS net,
                    SUM(ii.tax) AS tax,
                    SUM(ii.total) AS total
               FROM invoice_items ii
               JOIN invoices i ON i.id = ii.invoice_id
              WHERE i.company_id = :c AND i.fiscal_period_id = :p
                AND i.deleted_at IS NULL AND i.status = 'posted'
              GROUP BY i.type, ii.tax_rate
              ORDER BY i.type, ii.tax_rate",
            ['c' => $companyId, 'p' => $periodId]
        );
        $headers = [__('report.vat_type'), __('report.vat_rate'), __('report.tax_base'), __('report.vat'), __('report.total_incl')];
        $out = [];
        $tBase = 0.0; $tVat = 0.0; $tTotal = 0.0;
        foreach ($rows as $r) {
            $net = (float) $r['net']; $vat = (float) $r['tax']; $tot = (float) $r['total'];
            $tBase += $net; $tVat += $vat; $tTotal += $tot;
            $out[] = [
                $r['type'] === 'sales' ? __('report.vat_sales_out') : __('report.vat_purchase_in'),
                number_format((float) $r['rate'], 0, ',', '.') . '%',
                number_format($net, 2, ',', '.'),
                number_format($vat, 2, ',', '.'),
                number_format($tot, 2, ',', '.'),
            ];
        }
        $out[] = [__('common.total'), '', number_format($tBase, 2, ',', '.'), number_format($tVat, 2, ',', '.'), number_format($tTotal, 2, ',', '.')];
        return $this->export($format, __('report.vat_summary'), 'C:' . $companyId . ' P:' . $periodId, $headers, $out, 'kdv');
    }

    /**
     * KDV detay raporu: her fatura satırı için matrah/KDV/dahil tutar.
     * Item 3 — KDV raporu detayları.
     */
    public function kdvDetay(Request $request): Response
    {
        $format = $request->query('format', 'csv');
        [$companyId, $periodId] = $this->ctx($request);
        $companyId = $companyId ?: $this->firstCompanyId();
        $company = DB::first('SELECT id, name FROM companies WHERE id = :id AND tenant_id = :t AND deleted_at IS NULL', ['id' => $companyId, 't' => Auth::tenantId()]);
        $rows = DB::select(
            "SELECT i.number, i.date, i.type, ca.name AS cari,
                    ii.tax_rate AS rate, ii.line_total AS net, ii.tax AS vat, ii.total AS incl
               FROM invoice_items ii
               JOIN invoices i ON i.id = ii.invoice_id
               LEFT JOIN current_accounts ca ON ca.id = i.current_account_id
              WHERE i.company_id = :c AND i.fiscal_period_id = :p
                AND i.deleted_at IS NULL AND i.status = 'posted'
              ORDER BY i.date, i.number, ii.id",
            ['c' => $companyId, 'p' => $periodId]
        );
        $headers = [
            __('accounting.number'), __('common.date'), __('report.vat_type'), __('current_account.name'),
            __('report.vat_rate'), __('report.tax_base'), __('report.vat'), __('report.total_incl'),
        ];
        $out = [];
        $tBase = 0.0; $tVat = 0.0; $tTotal = 0.0;
        foreach ($rows as $r) {
            $net = (float) $r['net']; $vat = (float) $r['vat']; $tot = (float) $r['incl'];
            $tBase += $net; $tVat += $vat; $tTotal += $tot;
            $out[] = [
                $r['number'], format_date($r['date']),
                $r['type'] === 'sales' ? __('report.vat_sales_out') : __('report.vat_purchase_in'),
                $r['cari'] ?? '',
                number_format((float) $r['rate'], 0, ',', '.') . '%',
                number_format($net, 2, ',', '.'),
                number_format($vat, 2, ',', '.'),
                number_format($tot, 2, ',', '.'),
            ];
        }
        $out[] = [__('common.total'), '', '', '', '', number_format($tBase, 2, ',', '.'), number_format($tVat, 2, ',', '.'), number_format($tTotal, 2, ',', '.')];
        return $this->export($format, __('report.vat_detail'), ($company['name'] ?? 'C:' . $companyId) . ' — P:' . $periodId, $headers, $out, 'kdv-detay');
    }

    // ---- Report: Banka e-mutabakat (bank reconciliation) ----
    public function bankaMutabakat(Request $request): Response
    {
        $format = $request->query('format', 'csv');
        [$companyId, $periodId] = $this->ctx($request);
        $companyId = $companyId ?: $this->firstCompanyId();
        $depositTypes = "('deposit','interest','transfer')";
        $rows = DB::select(
            "SELECT a.id, a.bank_name, a.account_name, a.iban, a.balance,
                    (SELECT COALESCE(SUM(t.amount),0) FROM bank_transactions t WHERE t.bank_account_id = a.id AND t.type IN " . $depositTypes . ") AS deposits,
                    (SELECT COALESCE(SUM(t.amount),0) FROM bank_transactions t WHERE t.bank_account_id = a.id AND t.type IN ('withdrawal','fee')) AS withdrawals,
                    (SELECT COUNT(*) FROM bank_transactions t WHERE t.bank_account_id = a.id) AS tx_count
               FROM bank_accounts a
              WHERE a.company_id = :c AND a.deleted_at IS NULL
              ORDER BY a.bank_name, a.id",
            ['c' => $companyId]
        );
        $headers = [
            __('bank.bank_name'), __('bank.account_name'), __('bank.iban'),
            __('report.rc_bank_balance'), __('report.rc_deposits'), __('report.rc_withdrawals'),
            __('report.rc_net'), __('report.rc_computed'), __('report.rc_difference'), __('report.rc_status'),
        ];
        $out = [];
        foreach ($rows as $r) {
            $dep = (float) $r['deposits'];
            $wd = (float) $r['withdrawals'];
            $net = $dep - $wd;
            $bankBalance = (float) $r['balance'];
            $diff = $bankBalance - $net;
            $out[] = [
                $r['bank_name'], $r['account_name'] ?? '', $r['iban'] ?? '',
                number_format($bankBalance, 2, ',', '.'),
                number_format($dep, 2, ',', '.'),
                number_format($wd, 2, ',', '.'),
                number_format($net, 2, ',', '.'),
                number_format($net, 2, ',', '.'),
                number_format($diff, 2, ',', '.'),
                abs($diff) < 0.005 ? __('report.rc_ok') : __('report.rc_mismatch'),
            ];
        }
        return $this->export($format, __('report.bank_reconciliation'), 'C:' . $companyId, $headers, $out, 'banka-mutabakat');
    }

    public function cari(Request $request): Response
    {
        $format = $request->query('format', 'csv');
        $companyId = (int) $request->query('company_id', 0);

        $sql = 'SELECT ca.code, ca.name, ca.type, ca.balance, c.name AS company_name
                 FROM current_accounts ca JOIN companies c ON c.id = ca.company_id
                WHERE ca.tenant_id = :t AND ca.deleted_at IS NULL';
        $params = ['t' => Auth::tenantId()];
        if ($companyId) {
            $sql .= ' AND ca.company_id = :c';
            $params['c'] = $companyId;
        }
        $data = DB::select($sql . ' ORDER BY ca.name', $params);

        $headers = [__('current_account.code'), __('current_account.name'), __('current_account.type'), __('current_account.balance'), __('current_account.company')];
        $rows = array_map(fn ($r) => [
            $r['code'], $r['name'], $r['type'],
            number_format((float) $r['balance'], 2, ',', '.'), $r['company_name'],
        ], $data);
        return $this->export($format, __('current_account.title'), '', $headers, $rows, 'cari');
    }

    // ---- Report: Stok (stock) ----
    public function stok(Request $request): Response
    {
        $format = $request->query('format', 'csv');
        [$companyId, $periodId] = $this->ctx($request);
        $companyId = $companyId ?: $this->firstCompanyId();
        $rows = DB::select(
            'SELECT code, name, type, stock_quantity, critical_stock, purchase_price, sale_price
               FROM products WHERE company_id = :c AND deleted_at IS NULL ORDER BY code',
            ['c' => $companyId]
        );
        $headers = [__('inventory.code'), __('inventory.name'), __('inventory.type'), __('report.stock_qty'), __('inventory.critical_stock'), __('inventory.purchase_price'), __('inventory.sale_price'), __('report.stock_value')];
        $out = [];
        $sum = 0.0;
        foreach ($rows as $r) {
            $out[] = [
                $r['code'], $r['name'], $r['type'],
                number_format((float) $r['stock_quantity'], 2, ',', '.'),
                number_format((float) $r['critical_stock'], 2, ',', '.'),
                number_format((float) $r['purchase_price'], 2, ',', '.'),
                number_format((float) $r['sale_price'], 2, ',', '.'),
                number_format((float) ($r['stock_quantity'] * $r['purchase_price']), 2, ',', '.'),
            ];
            $sum += (float) $r['stock_quantity'] * (float) $r['purchase_price'];
        }
        $out[] = ['', __('common.total'), '', '', '', '', '', number_format($sum, 2, ',', '.')];
        return $this->export($format, __('report.stock'), 'C:' . $companyId, $headers, $out, 'stok');
    }

    // ---- Report: Satış (sales) ----
    public function satis(Request $request): Response
    {
        $format = $request->query('format', 'csv');
        [$companyId, $periodId] = $this->ctx($request);
        $companyId = $companyId ?: $this->firstCompanyId();
        $rows = DB::select(
            "SELECT i.number, i.date, ca.name AS cari, i.subtotal, i.tax, i.total
               FROM invoices i LEFT JOIN current_accounts ca ON ca.id = i.current_account_id
              WHERE i.company_id = :c AND i.fiscal_period_id = :p AND i.type = 'sales' AND i.status = 'posted' AND i.deleted_at IS NULL
              ORDER BY i.date",
            ['c' => $companyId, 'p' => $periodId]
        );
        return $this->export($format, __('report.sales'), 'C:' . $companyId, [__('accounting.number'), __('common.date'), __('current_account.name'), __('common.subtotal'), __('common.tax'), __('common.total')], array_map(fn ($r) => [$r['number'], format_date($r['date']), $r['cari'], number_format((float) $r['subtotal'], 2, ',', '.'), number_format((float) $r['tax'], 2, ',', '.'), number_format((float) $r['total'], 2, ',', '.')], $rows), 'satis');
    }

    // ---- Report: Alış (purchases) ----
    public function alis(Request $request): Response
    {
        $format = $request->query('format', 'csv');
        [$companyId, $periodId] = $this->ctx($request);
        $companyId = $companyId ?: $this->firstCompanyId();
        $rows = DB::select(
            "SELECT i.number, i.date, ca.name AS cari, i.subtotal, i.tax, i.total
               FROM invoices i LEFT JOIN current_accounts ca ON ca.id = i.current_account_id
              WHERE i.company_id = :c AND i.fiscal_period_id = :p AND i.type = 'purchase' AND i.status = 'posted' AND i.deleted_at IS NULL
              ORDER BY i.date",
            ['c' => $companyId, 'p' => $periodId]
        );
        return $this->export($format, __('report.purchases'), 'C:' . $companyId, [__('accounting.number'), __('common.date'), __('current_account.name'), __('common.subtotal'), __('common.tax'), __('common.total')], array_map(fn ($r) => [$r['number'], format_date($r['date']), $r['cari'], number_format((float) $r['subtotal'], 2, ',', '.'), number_format((float) $r['tax'], 2, ',', '.'), number_format((float) $r['total'], 2, ',', '.')], $rows), 'alis');
    }

    // ---- Report: Kasa (cash) ----
    public function kasa(Request $request): Response
    {
        $format = $request->query('format', 'csv');
        [$companyId, $periodId] = $this->ctx($request);
        $companyId = $companyId ?: $this->firstCompanyId();
        $rows = DB::select(
            "SELECT a.code, a.name, t.date, t.description, t.type, t.amount, a.balance
               FROM cash_transactions t JOIN cash_accounts a ON a.id = t.cash_account_id
              WHERE a.company_id = :c
              ORDER BY t.date",
            ['c' => $companyId]
        );
        $headers = [__('cash.code'), __('cash.name'), __('common.date'), __('common.description'), __('report.flow'), __('report.amount'), __('report.balance')];
        return $this->export($format, __('report.cash'), 'C:' . $companyId, $headers, array_map(fn ($r) => [$r['code'], $r['name'], format_date($r['date']), $r['description'] ?? '', $r['type'] === 'income' ? __('report.income') : __('report.expense'), number_format((float) $r['amount'], 2, ',', '.'), number_format((float) $r['balance'], 2, ',', '.')], $rows), 'kasa');
    }

    // ---- Report: Banka (bank) ----
    public function banka(Request $request): Response
    {
        $format = $request->query('format', 'csv');
        [$companyId, $periodId] = $this->ctx($request);
        $companyId = $companyId ?: $this->firstCompanyId();
        $rows = DB::select(
            "SELECT a.bank_name, a.iban, t.date, t.description, t.type, t.amount
               FROM bank_transactions t JOIN bank_accounts a ON a.id = t.bank_account_id
              WHERE a.company_id = :c
              ORDER BY t.date",
            ['c' => $companyId]
        );
        $headers = [__('bank.bank_name'), __('bank.iban'), __('common.date'), __('common.description'), __('report.flow'), __('report.amount')];
        return $this->export($format, __('report.bank'), 'C:' . $companyId, $headers, array_map(fn ($r) => [$r['bank_name'], $r['iban'] ?? '', format_date($r['date']), $r['description'] ?? '', $r['type'] === 'income' ? __('report.income') : __('report.expense'), number_format((float) $r['amount'], 2, ',', '.')], $rows), 'banka');
    }

    // ---- Report: Kârlılık (profitability) ----
    public function karlilik(Request $request): Response
    {
        $format = $request->query('format', 'csv');
        [$companyId, $periodId] = $this->ctx($request);
        $companyId = $companyId ?: $this->firstCompanyId();
        $data = AccountingService::incomeStatement($companyId, $periodId);
        $headers = ['Hesap', __('common.name'), __('report.amount')];
        $out = [];
        $revenue = 0.0; $expense = 0.0;
        $out[] = [__('report.revenue'), '', ''];
        foreach ($data['income'] as $r) {
            $bal = (float) $r['credit'] - (float) $r['debit'];
            $revenue += $bal;
            $out[] = [$r['code'], $r['name'], number_format($bal, 2, ',', '.')];
        }
        $out[] = [__('report.expenses'), '', ''];
        foreach ($data['expense'] as $r) {
            $bal = (float) $r['debit'] - (float) $r['credit'];
            $expense += $bal;
            $out[] = [$r['code'], $r['name'], number_format($bal, 2, ',', '.')];
        }
        $out[] = [__('report.net_profit'), '', number_format($revenue - $expense, 2, ',', '.')];
        return $this->export($format, __('report.profitability'), 'C:' . $companyId, $headers, $out, 'karlilik');
    }

    // ---- Report: Borç / Alacak (receivables & payables) ----
    public function borcAlacak(Request $request): Response
    {
        $format = $request->query('format', 'csv');
        [$companyId, $periodId] = $this->ctx($request);
        $companyId = $companyId ?: $this->firstCompanyId();
        $rows = DB::select(
            'SELECT code, name, type, balance FROM current_accounts
              WHERE company_id = :c AND deleted_at IS NULL ORDER BY type, name',
            ['c' => $companyId]
        );
        $headers = [__('current_account.code'), __('current_account.name'), __('current_account.type'), __('current_account.balance')];
        return $this->export($format, __('report.receivables_payables'), 'C:' . $companyId, $headers, array_map(fn ($r) => [$r['code'], $r['name'], __('current_account.type_' . $r['type']), number_format((float) $r['balance'], 2, ',', '.')], $rows), 'borc-alacak');
    }

    private function firstCompanyId(): int
    {
        return (int) DB::scalar('SELECT id FROM companies WHERE tenant_id = :t AND deleted_at IS NULL ORDER BY id LIMIT 1', ['t' => Auth::tenantId()]);
    }

    private function ctx(Request $request): array
    {
        $companyId = (int) $request->query('company_id', 0);
        $periodId = (int) $request->query('period_id', 0);
        return [$companyId, $periodId];
    }
}
