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
        // line_total = tax base (net), tax = VAT, withholding = tevkifat, total = VAT-inclusive.
        $rows = DB::select(
            "SELECT i.type, ii.tax_rate AS rate,
                    SUM(ii.line_total) AS net,
                    SUM(ii.tax) AS tax,
                    SUM(COALESCE(ii.withholding,0)) AS withholding,
                    SUM(ii.total) AS total
               FROM invoice_items ii
               JOIN invoices i ON i.id = ii.invoice_id
              WHERE i.company_id = :c AND i.fiscal_period_id = :p
                AND i.deleted_at IS NULL AND i.status = 'posted'
              GROUP BY i.type, ii.tax_rate
              ORDER BY i.type, ii.tax_rate",
            ['c' => $companyId, 'p' => $periodId]
        );
        $headers = [__('report.vat_type'), __('report.vat_rate'), __('report.tax_base'), __('report.vat'), __('report.vat_withholding'), __('report.total_incl')];
        $out = [];
        $tBase = 0.0; $tVat = 0.0; $tW = 0.0; $tTotal = 0.0;
        foreach ($rows as $r) {
            $net = (float) $r['net']; $vat = (float) $r['tax']; $w = (float) $r['withholding']; $tot = (float) $r['total'];
            $tBase += $net; $tVat += $vat; $tW += $w; $tTotal += $tot;
            $out[] = [
                $r['type'] === 'sales' ? __('report.vat_sales_out') : __('report.vat_purchase_in'),
                number_format((float) $r['rate'], 0, ',', '.') . '%',
                number_format($net, 2, ',', '.'),
                number_format($vat, 2, ',', '.'),
                number_format($w, 2, ',', '.'),
                number_format($tot, 2, ',', '.'),
            ];
        }
        $out[] = [__('common.total'), '', number_format($tBase, 2, ',', '.'), number_format($tVat, 2, ',', '.'), number_format($tW, 2, ',', '.'), number_format($tTotal, 2, ',', '.')];
        return $this->export($format, __('report.vat_summary'), 'C:' . $companyId . ' P:' . $periodId, $headers, $out, 'kdv');
    }

    /**
     * KDV Beyanname raporu (dönem bazlı kümülatif özet).
     * Item 3 — indirilecek/hesaplanan KDV + tevkifat ve net ödenecek/iade.
     */
    public function kdvBeyanname(Request $request): Response
    {
        $format = $request->query('format', 'csv');
        [$companyId, $periodId] = $this->ctx($request);
        $company = DB::first('SELECT id, name FROM companies WHERE id = :id AND tenant_id = :t AND deleted_at IS NULL', ['id' => $companyId, 't' => Auth::tenantId()]);

        $rows = DB::select(
            "SELECT i.type, SUM(ii.line_total) AS net, SUM(ii.tax) AS vat,
                    SUM(COALESCE(ii.withholding,0)) AS withholding
               FROM invoice_items ii
               JOIN invoices i ON i.id = ii.invoice_id
              WHERE i.company_id = :c AND i.fiscal_period_id = :p
                AND i.deleted_at IS NULL AND i.status = 'posted'
              GROUP BY i.type",
            ['c' => $companyId, 'p' => $periodId]
        );
        $sales = null; $purch = null;
        foreach ($rows as $r) {
            if ($r['type'] === 'sales') {
                $sales = $r;
            } elseif ($r['type'] === 'purchase') {
                $purch = $r;
            }
        }

        $outBase = (float) ($sales['net'] ?? 0);
        $outVat  = (float) ($sales['vat'] ?? 0);
        $outW    = (float) ($sales['withholding'] ?? 0);
        $inBase  = (float) ($purch['net'] ?? 0);
        $inVat   = (float) ($purch['vat'] ?? 0);
        $inW     = (float) ($purch['withholding'] ?? 0);

        $payable = $outVat - $inVat;
        $refund  = $payable < 0 ? abs($payable) : 0.0;
        if ($payable < 0) {
            $payable = 0.0;
        }

        $fmt = fn ($x) => number_format((float) $x, 2, ',', '.');
        $headers = [__('report.vat_declaration'), __('report.vat_base'), __('report.vat_amount'), __('report.vat_withholding')];
        $out = [
            [__('report.vat_out_base'), $fmt($outBase), $fmt($outVat), $fmt($outW)],
            [__('report.vat_in_base'), $fmt($inBase), $fmt($inVat), $fmt($inW)],
            ['', '', '', ''],
            [__('report.vat_payable'), '', $fmt($payable), ''],
            [__('report.vat_refund'), '', $fmt($refund), ''],
        ];

        return $this->export(
            $format,
            __('report.vat_declaration'),
            ($company['name'] ?? 'C:' . $companyId) . ' — ' . __('report.vat_declaration_subtitle'),
            $headers,
            $out,
            'kdv-beyanname'
        );
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
                    ii.tax_rate AS rate, ii.line_total AS net, ii.tax AS vat,
                    COALESCE(ii.withholding,0) AS withholding, ii.total AS incl
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
            __('report.vat_rate'), __('report.tax_base'), __('report.vat'), __('report.vat_withholding'), __('report.total_incl'),
        ];
        $out = [];
        $tBase = 0.0; $tVat = 0.0; $tW = 0.0; $tTotal = 0.0;
        foreach ($rows as $r) {
            $net = (float) $r['net']; $vat = (float) $r['vat']; $w = (float) $r['withholding']; $tot = (float) $r['incl'];
            $tBase += $net; $tVat += $vat; $tW += $w; $tTotal += $tot;
            $out[] = [
                $r['number'], format_date($r['date']),
                $r['type'] === 'sales' ? __('report.vat_sales_out') : __('report.vat_purchase_in'),
                $r['cari'] ?? '',
                number_format((float) $r['rate'], 0, ',', '.') . '%',
                number_format($net, 2, ',', '.'),
                number_format($vat, 2, ',', '.'),
                number_format($w, 2, ',', '.'),
                number_format($tot, 2, ',', '.'),
            ];
        }
        $out[] = [__('common.total'), '', '', '', '', number_format($tBase, 2, ',', '.'), number_format($tVat, 2, ',', '.'), number_format($tW, 2, ',', '.'), number_format($tTotal, 2, ',', '.')];
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

    // ---- Report: Cari Yaşlandırma (receivables aging) ----
    public function yaslandirma(Request $request): Response
    {
        $format = $request->query('format', 'csv');
        [$companyId, $periodId] = $this->ctx($request);
        $companyId = $companyId ?: $this->firstCompanyId();
        $today = date('Y-m-d');

        // Open (unpaid) invoiced amounts per current account.
        $invoices = DB::select(
            "SELECT current_account_id, due_date, (total - paid) AS outstanding
               FROM invoices
              WHERE company_id = :c AND status = 'posted' AND deleted_at IS NULL AND total > paid",
            ['c' => $companyId]
        );
        $accounts = DB::select(
            'SELECT id, code, name, type FROM current_accounts WHERE company_id = :c AND deleted_at IS NULL ORDER BY type, name',
            ['c' => $companyId]
        );

        $bucketLabels = [
            'current' => __('report.aging_current'),
            'd30'     => __('report.aging_30'),
            'd60'     => __('report.aging_60'),
            'd90'     => __('report.aging_90'),
            'd90p'    => __('report.aging_90p'),
        ];
        $buckets = array_keys($bucketLabels);

        $map = [];
        foreach ($accounts as $a) {
            $map[(int) $a['id']] = array_fill_keys($buckets, 0.0) + ['total' => 0.0];
        }
        foreach ($invoices as $inv) {
            $aid = (int) $inv['current_account_id'];
            if (!isset($map[$aid])) {
                continue;
            }
            $amt = (float) $inv['outstanding'];
            if ($amt <= 0) {
                continue;
            }
            $bucket = 'current';
            if (!empty($inv['due_date'])) {
                $days = (int) floor((strtotime($today) - strtotime($inv['due_date'])) / 86400);
                if ($days > 90) {
                    $bucket = 'd90p';
                } elseif ($days > 60) {
                    $bucket = 'd90';
                } elseif ($days > 30) {
                    $bucket = 'd60';
                } elseif ($days > 0) {
                    $bucket = 'd30';
                }
            }
            $map[$aid][$bucket] += $amt;
            $map[$aid]['total'] += $amt;
        }

        $headers = array_merge(
            [__('current_account.code'), __('current_account.name'), __('current_account.type')],
            array_values($bucketLabels),
            [__('report.aging_total')]
        );
        $out = [];
        $totals = array_fill_keys($buckets, 0.0) + ['total' => 0.0];
        foreach ($accounts as $a) {
            $m = $map[(int) $a['id']];
            foreach ($totals as $k => $v) {
                $totals[$k] += $m[$k];
            }
            $row = [$a['code'], $a['name'], __('current_account.type_' . $a['type'])];
            foreach ($buckets as $b) {
                $row[] = number_format($m[$b], 2, ',', '.');
            }
            $row[] = number_format($m['total'], 2, ',', '.');
            $out[] = $row;
        }
        $row = [__('common.total'), '', ''];
        foreach ($buckets as $b) {
            $row[] = number_format($totals[$b], 2, ',', '.');
        }
        $row[] = number_format($totals['total'], 2, ',', '.');
        $out[] = $row;

        return $this->export($format, __('report.aging'), 'C:' . $companyId, $headers, $out, 'cari-yaslandirma');
    }

    // ---- Report: Cari Ekstre (current-account statement) ----
    public function cariEkstre(Request $request): Response
    {
        $format = $request->query('format', 'csv');
        $accountId = (int) $request->query('account_id', 0);
        $from = $request->query('from') ?: null;
        $to = $request->query('to') ?: null;
        if (!$accountId) {
            return Response::redirect('/app/current-accounts');
        }
        $account = DB::first('SELECT * FROM current_accounts WHERE id = :id AND tenant_id = :t AND deleted_at IS NULL', ['id' => $accountId, 't' => Auth::tenantId()]);
        if (!$account) {
            return Response::redirect('/app/current-accounts');
        }
        $st = \Muh\Services\CurrentAccountService::statement($accountId, $from, $to);
        $headers = [
            __('common.date'), __('current_account.type'), __('common.description'),
            __('current_account.debit'), __('current_account.credit'), __('report.balance'),
        ];
        $out = [];
        $out[] = [__('report.ekstre_opening'), '', '', '', '', number_format($st['opening'], 2, ',', '.')];
        foreach ($st['rows'] as $r) {
            $debit = $r['sign'] > 0 ? $r['amount'] : 0;
            $credit = $r['sign'] < 0 ? $r['amount'] : 0;
            $out[] = [
                format_date($r['date']),
                __('current_account.type_' . $r['type']),
                $r['description'] ?: '',
                $debit ? number_format((float) $debit, 2, ',', '.') : '',
                $credit ? number_format((float) $credit, 2, ',', '.') : '',
                number_format((float) $r['running'], 2, ',', '.'),
            ];
        }
        $out[] = [__('report.ekstre_closing'), '', '', '', '', number_format($st['closing'], 2, ',', '.')];
        $sub = $account['name'] . ($from ? ' — ' . $from . ($to ? ' / ' . $to : '') : '');
        return $this->export($format, __('report.cari_ekstre'), $sub, $headers, $out, 'cari-ekstre');
    }

    // ---- Report: e-Fatura durum (e-Fatura status across the office) ----
    public function efatura(Request $request): Response
    {
        $format = $request->query('format', 'csv');
        $tenantId = (int) Auth::tenantId();
        $companyId = (int) ($request->query('company_id') ?? 0);
        $status = $request->query('status') ?: null;

        $sql = "SELECT i.number, i.date, i.type, i.total, i.efatura_status, i.efatura_doc_type, i.efatura_envelope_id,
                       c.name AS company_name, ca.name AS cari
                  FROM invoices i
                  JOIN companies c ON c.id = i.company_id
                  LEFT JOIN current_accounts ca ON ca.id = i.current_account_id
                 WHERE i.tenant_id = :t AND i.deleted_at IS NULL";
        $params = ['t' => $tenantId];
        if ($companyId) {
            $sql .= ' AND i.company_id = :c';
            $params['c'] = $companyId;
        }
        if ($status && in_array($status, ['draft', 'sending', 'sent', 'accepted', 'rejected', 'error'], true)) {
            $sql .= ' AND i.efatura_status = :st';
            $params['st'] = $status;
        }
        $sql .= ' ORDER BY i.date DESC, i.id DESC';
        $rows = DB::select($sql, $params);

        $headers = [
            __('accounting.number'), __('common.date'), __('invoice.type'), __('current_account.name'), __('invoice.company'),
            __('report.amount'), __('efatura.status'), __('efatura.doc_type'), __('efatura.envelope_id'),
        ];
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                $r['number'], format_date($r['date']), __('invoice.type_' . $r['type']), $r['cari'] ?? '', $r['company_name'],
                number_format((float) $r['total'], 2, ',', '.'),
                __('efatura.st_' . $r['efatura_status']),
                $r['efatura_doc_type'] ? __('efatura.type_' . $r['efatura_doc_type']) : '—',
                $r['efatura_envelope_id'] ?? '',
            ];
        }
        return $this->export($format, __('report.efatura_status'), '', $headers, $out, 'efatura-durum');
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

    private function screenContext(Request $request): array
    {
        $companies = DB::select('SELECT id, name FROM companies WHERE tenant_id = :t AND deleted_at IS NULL ORDER BY name', ['t' => Auth::tenantId()]);
        $companyId = (int) ($request->query('company_id') ?? ($companies[0]['id'] ?? 0));
        $periods = $companyId ? DB::select('SELECT * FROM fiscal_periods WHERE company_id = :c ORDER BY start_date DESC', ['c' => $companyId]) : [];
        $periodId = (int) ($request->query('period_id') ?? ($periods[0]['id'] ?? 0));
        return [$companies, $companyId, $periods, $periodId];
    }

    // ---- Ekran görünümleri (on-screen reports) ----

    /** Cari Yaşlandırma ekranı. */
    public function agingScreen(Request $request): Response
    {
        Auth::requireCan('report.view');
        [$companies, $companyId, $periods, $periodId] = $this->screenContext($request);
        $companyId = $companyId ?: $this->firstCompanyId();
        $today = date('Y-m-d');
        $invoices = DB::select(
            "SELECT current_account_id, due_date, (total - paid) AS outstanding FROM invoices
              WHERE company_id = :c AND status = 'posted' AND deleted_at IS NULL AND total > paid",
            ['c' => $companyId]
        );
        $accounts = DB::select('SELECT id, code, name, type FROM current_accounts WHERE company_id = :c AND deleted_at IS NULL ORDER BY type, name', ['c' => $companyId]);
        $bucketLabels = ['current' => __('report.aging_current'), 'd30' => __('report.aging_30'), 'd60' => __('report.aging_60'), 'd90' => __('report.aging_90'), 'd90p' => __('report.aging_90p')];
        $buckets = array_keys($bucketLabels);
        $map = [];
        foreach ($accounts as $a) {
            $map[(int) $a['id']] = array_fill_keys($buckets, 0.0) + ['total' => 0.0];
        }
        foreach ($invoices as $inv) {
            $aid = (int) $inv['current_account_id'];
            if (!isset($map[$aid])) {
                continue;
            }
            $amt = (float) $inv['outstanding'];
            if ($amt <= 0) {
                continue;
            }
            $bucket = 'current';
            if (!empty($inv['due_date'])) {
                $days = (int) floor((strtotime($today) - strtotime($inv['due_date'])) / 86400);
                $bucket = $days > 90 ? 'd90p' : ($days > 60 ? 'd90' : ($days > 30 ? 'd60' : ($days > 0 ? 'd30' : 'current')));
            }
            $map[$aid][$bucket] += $amt;
            $map[$aid]['total'] += $amt;
        }
        $headers = array_merge([__('current_account.code'), __('current_account.name'), __('current_account.type')], array_values($bucketLabels), [__('report.aging_total')]);
        $rows = [];
        $totals = array_fill_keys($buckets, 0.0) + ['total' => 0.0];
        foreach ($accounts as $a) {
            $m = $map[(int) $a['id']];
            foreach ($totals as $k => $v) {
                $totals[$k] += $m[$k];
            }
            $row = [$a['code'], $a['name'], __('current_account.type_' . $a['type'])];
            foreach ($buckets as $b) {
                $row[] = number_format($m[$b], 2, ',', '.');
            }
            $row[] = number_format($m['total'], 2, ',', '.');
            $rows[] = $row;
        }
        $t = [__('common.total'), '', ''];
        foreach ($buckets as $b) {
            $t[] = number_format($totals[$b], 2, ',', '.');
        }
        $t[] = number_format($totals['total'], 2, ',', '.');
        $rows[] = $t;
        return $this->view('app.reports.screen', [
            'layout' => 'layouts.app', 'title' => __('report.aging'), 'subtitle' => __('report.aging_sub'),
            'headers' => $headers, 'rows' => $rows, 'exportSlug' => 'yaslandirma',
            'companies' => $companies, 'companyId' => $companyId, 'periods' => $periods, 'periodId' => $periodId,
        ]);
    }

    /** Stok / Değerleme ekranı. */
    public function stockScreen(Request $request): Response
    {
        Auth::requireCan('report.view');
        [$companies, $companyId, $periods, $periodId] = $this->screenContext($request);
        $companyId = $companyId ?: $this->firstCompanyId();
        $rows = DB::select('SELECT code, name, type, stock_quantity, critical_stock, purchase_price, sale_price FROM products WHERE company_id = :c AND deleted_at IS NULL ORDER BY code', ['c' => $companyId]);
        $headers = [__('inventory.code'), __('inventory.name'), __('inventory.type'), __('report.stock_qty'), __('inventory.critical_stock'), __('inventory.purchase_price'), __('inventory.sale_price'), __('report.stock_value')];
        $out = [];
        $sum = 0.0;
        foreach ($rows as $r) {
            $val = (float) $r['stock_quantity'] * (float) $r['purchase_price'];
            $sum += $val;
            $out[] = [$r['code'], $r['name'], $r['type'], number_format((float) $r['stock_quantity'], 2, ',', '.'), number_format((float) $r['critical_stock'], 2, ',', '.'), number_format((float) $r['purchase_price'], 2, ',', '.'), number_format((float) $r['sale_price'], 2, ',', '.'), number_format($val, 2, ',', '.')];
        }
        $out[] = ['', __('common.total'), '', '', '', '', '', number_format($sum, 2, ',', '.')];
        return $this->view('app.reports.screen', [
            'layout' => 'layouts.app', 'title' => __('report.stock'), 'subtitle' => __('report.stock_sub'),
            'headers' => $headers, 'rows' => $out, 'exportSlug' => 'stok',
            'companies' => $companies, 'companyId' => $companyId, 'periods' => $periods, 'periodId' => $periodId,
        ]);
    }

    /** Kârlılık ekranı. */
    public function profitabilityScreen(Request $request): Response
    {
        Auth::requireCan('report.view');
        [$companies, $companyId, $periods, $periodId] = $this->screenContext($request);
        $companyId = $companyId ?: $this->firstCompanyId();
        $data = AccountingService::incomeStatement($companyId, $periodId);
        $headers = ['Hesap', __('common.name'), __('report.amount')];
        $rows = [];
        $revenue = 0.0;
        $expense = 0.0;
        $rows[] = [__('report.revenue'), '', ''];
        foreach ($data['income'] as $r) {
            $bal = (float) $r['credit'] - (float) $r['debit'];
            $revenue += $bal;
            $rows[] = [$r['code'], $r['name'], number_format($bal, 2, ',', '.')];
        }
        $rows[] = [__('report.expenses'), '', ''];
        foreach ($data['expense'] as $r) {
            $bal = (float) $r['debit'] - (float) $r['credit'];
            $expense += $bal;
            $rows[] = [$r['code'], $r['name'], number_format($bal, 2, ',', '.')];
        }
        $rows[] = [__('report.net_profit'), '', number_format($revenue - $expense, 2, ',', '.')];
        return $this->view('app.reports.screen', [
            'layout' => 'layouts.app', 'title' => __('report.profitability'), 'subtitle' => __('report.profit_sub'),
            'headers' => $headers, 'rows' => $rows, 'exportSlug' => 'karlilik',
            'companies' => $companies, 'companyId' => $companyId, 'periods' => $periods, 'periodId' => $periodId,
        ]);
    }

    /** KDV özet ekranı. */
    public function kdvScreen(Request $request): Response
    {
        Auth::requireCan('report.view');
        [$companies, $companyId, $periods, $periodId] = $this->screenContext($request);
        $companyId = $companyId ?: $this->firstCompanyId();
        $rows = DB::select(
            "SELECT i.type, ii.tax_rate AS rate, SUM(ii.line_total) AS net, SUM(ii.tax) AS tax,
                    SUM(COALESCE(ii.withholding,0)) AS withholding, SUM(ii.total) AS total
               FROM invoice_items ii JOIN invoices i ON i.id = ii.invoice_id
              WHERE i.company_id = :c AND i.fiscal_period_id = :p AND i.deleted_at IS NULL AND i.status = 'posted'
              GROUP BY i.type, ii.tax_rate ORDER BY i.type, ii.tax_rate",
            ['c' => $companyId, 'p' => $periodId]
        );
        $headers = [__('report.vat_type'), __('report.vat_rate'), __('report.tax_base'), __('report.vat'), __('report.vat_withholding'), __('report.total_incl')];
        $out = [];
        $tBase = 0.0; $tVat = 0.0; $tW = 0.0; $tTotal = 0.0;
        foreach ($rows as $r) {
            $tBase += (float) $r['net']; $tVat += (float) $r['tax']; $tW += (float) $r['withholding']; $tTotal += (float) $r['total'];
            $out[] = [$r['type'] === 'sales' ? __('report.vat_sales_out') : __('report.vat_purchase_in'), number_format((float) $r['rate'], 0, ',', '.') . '%', number_format((float) $r['net'], 2, ',', '.'), number_format((float) $r['tax'], 2, ',', '.'), number_format((float) $r['withholding'], 2, ',', '.'), number_format((float) $r['total'], 2, ',', '.')];
        }
        $out[] = [__('common.total'), '', number_format($tBase, 2, ',', '.'), number_format($tVat, 2, ',', '.'), number_format($tW, 2, ',', '.'), number_format($tTotal, 2, ',', '.')];
        return $this->view('app.reports.screen', [
            'layout' => 'layouts.app', 'title' => __('report.vat_summary'), 'subtitle' => __('report.vat_summary_sub'),
            'headers' => $headers, 'rows' => $out, 'exportSlug' => 'kdv',
            'companies' => $companies, 'companyId' => $companyId, 'periods' => $periods, 'periodId' => $periodId,
        ]);
    }

    /** KDV Beyanname ekranı. */
    public function kdvBeyannameScreen(Request $request): Response
    {
        Auth::requireCan('report.view');
        [$companies, $companyId, $periods, $periodId] = $this->screenContext($request);
        $companyId = $companyId ?: $this->firstCompanyId();
        $rows = DB::select(
            "SELECT i.type, SUM(ii.line_total) AS net, SUM(ii.tax) AS vat, SUM(COALESCE(ii.withholding,0)) AS withholding
               FROM invoice_items ii JOIN invoices i ON i.id = ii.invoice_id
              WHERE i.company_id = :c AND i.fiscal_period_id = :p AND i.deleted_at IS NULL AND i.status = 'posted'
              GROUP BY i.type",
            ['c' => $companyId, 'p' => $periodId]
        );
        $sales = null; $purch = null;
        foreach ($rows as $r) {
            if ($r['type'] === 'sales') $sales = $r; elseif ($r['type'] === 'purchase') $purch = $r;
        }
        $outBase = (float) ($sales['net'] ?? 0); $outVat = (float) ($sales['vat'] ?? 0); $outW = (float) ($sales['withholding'] ?? 0);
        $inBase = (float) ($purch['net'] ?? 0); $inVat = (float) ($purch['vat'] ?? 0); $inW = (float) ($purch['withholding'] ?? 0);
        $payable = max(0.0, $outVat - $inVat); $refund = max(0.0, $inVat - $outVat);
        $fmt = fn ($x) => number_format((float) $x, 2, ',', '.');
        $headers = [__('report.vat_declaration'), __('report.vat_base'), __('report.vat_amount'), __('report.vat_withholding')];
        $out = [
            [__('report.vat_out_base'), $fmt($outBase), $fmt($outVat), $fmt($outW)],
            [__('report.vat_in_base'), $fmt($inBase), $fmt($inVat), $fmt($inW)],
            ['', '', '', ''],
            [__('report.vat_payable'), '', $fmt($payable), ''],
            [__('report.vat_refund'), '', $fmt($refund), ''],
        ];
        return $this->view('app.reports.screen', [
            'layout' => 'layouts.app', 'title' => __('report.vat_declaration'), 'subtitle' => __('report.vat_summary_sub'),
            'headers' => $headers, 'rows' => $out, 'exportSlug' => 'kdv-beyanname',
            'companies' => $companies, 'companyId' => $companyId, 'periods' => $periods, 'periodId' => $periodId,
        ]);
    }

    // ---- Kasa / Banka Ekstre & e-Fatura durum ekranları ----

    /** Kasa ekstre ekranı. */
    public function kasaScreen(Request $request): Response
    {
        Auth::requireCan('report.view');
        [$companies, $companyId, $periods, $periodId] = $this->screenContext($request);
        $companyId = $companyId ?: $this->firstCompanyId();
        $rows = DB::select(
            "SELECT a.code, a.name, t.date, t.description, t.type, t.amount, a.balance
               FROM cash_transactions t JOIN cash_accounts a ON a.id = t.cash_account_id
              WHERE a.company_id = :c ORDER BY t.date",
            ['c' => $companyId]
        );
        $headers = [__('cash.code'), __('cash.name'), __('common.date'), __('common.description'), __('report.flow'), __('report.amount'), __('report.balance')];
        $out = [];
        foreach ($rows as $r) {
            $out[] = [$r['code'], $r['name'], format_date($r['date']), $r['description'] ?? '', $r['type'] === 'income' ? __('report.income') : __('report.expense'), number_format((float) $r['amount'], 2, ',', '.'), number_format((float) $r['balance'], 2, ',', '.')];
        }
        return $this->view('app.reports.screen', [
            'layout' => 'layouts.app', 'title' => __('report.cash'), 'subtitle' => __('report.statement_sub'),
            'headers' => $headers, 'rows' => $out, 'exportSlug' => 'kasa',
            'companies' => $companies, 'companyId' => $companyId, 'periods' => $periods, 'periodId' => $periodId,
        ]);
    }

    /** Banka ekstre ekranı (running bakiye ile). */
    public function bankaScreen(Request $request): Response
    {
        Auth::requireCan('report.view');
        [$companies, $companyId, $periods, $periodId] = $this->screenContext($request);
        $companyId = $companyId ?: $this->firstCompanyId();
        $rows = DB::select(
            "SELECT ba.id AS aid, ba.bank_name, ba.iban, ba.balance, t.date, t.description, t.type, t.amount
               FROM bank_transactions t JOIN bank_accounts ba ON ba.id = t.bank_account_id
              WHERE ba.company_id = :c ORDER BY ba.id, t.date, t.id",
            ['c' => $companyId]
        );
        $headers = [__('bank.bank_name'), __('bank.iban'), __('common.date'), __('common.description'), __('report.flow'), __('report.amount'), __('report.balance')];
        $running = [];
        $out = [];
        $isIncrease = fn ($t) => in_array($t, ['deposit', 'interest'], true);
        foreach ($rows as $r) {
            $aid = (int) $r['aid'];
            if (!isset($running[$aid])) {
                $running[$aid] = 0.0;
            }
            $amt = (float) $r['amount'];
            $running[$aid] += $isIncrease($r['type']) ? $amt : -$amt;
            $out[] = [$r['bank_name'], $r['iban'] ?? '', format_date($r['date']), $r['description'] ?? '', $isIncrease($r['type']) ? __('report.income') : __('report.expense'), number_format($amt, 2, ',', '.'), number_format($running[$aid], 2, ',', '.')];
        }
        return $this->view('app.reports.screen', [
            'layout' => 'layouts.app', 'title' => __('report.bank'), 'subtitle' => __('report.statement_sub'),
            'headers' => $headers, 'rows' => $out, 'exportSlug' => 'banka',
            'companies' => $companies, 'companyId' => $companyId, 'periods' => $periods, 'periodId' => $periodId,
        ]);
    }

    /** e-Fatura durum raporu ekranı. */
    public function efaturaScreen(Request $request): Response
    {
        Auth::requireCan('report.view');
        [$companies, $companyId, $periods, $periodId] = $this->screenContext($request);
        $tenantId = (int) Auth::tenantId();
        $companyId2 = (int) ($request->query('company_id') ?? 0);
        $status = $request->query('status') ?: null;
        $sql = "SELECT i.number, i.date, i.type, i.total, i.efatura_status, i.efatura_doc_type, c.name AS company_name, ca.name AS cari
                  FROM invoices i JOIN companies c ON c.id = i.company_id LEFT JOIN current_accounts ca ON ca.id = i.current_account_id
                 WHERE i.tenant_id = :t AND i.deleted_at IS NULL";
        $params = ['t' => $tenantId];
        if ($companyId2) { $sql .= ' AND i.company_id = :c'; $params['c'] = $companyId2; }
        if ($status && in_array($status, ['draft', 'sending', 'sent', 'accepted', 'rejected', 'error'], true)) { $sql .= ' AND i.efatura_status = :st'; $params['st'] = $status; }
        $sql .= ' ORDER BY i.date DESC, i.id DESC';
        $rows = DB::select($sql, $params);
        $headers = [__('accounting.number'), __('common.date'), __('invoice.type'), __('invoice.company'), __('current_account.name'), __('report.amount'), __('efatura.doc_type'), __('efatura.status')];
        $out = [];
        foreach ($rows as $r) {
            $out[] = [$r['number'], format_date($r['date']), __('invoice.type_' . $r['type']), $r['company_name'], $r['cari'] ?? '', number_format((float) $r['total'], 2, ',', '.'), $r['efatura_doc_type'] ? __('efatura.type_' . $r['efatura_doc_type']) : '—', __('efatura.st_' . $r['efatura_status'])];
        }
        return $this->view('app.reports.screen', [
            'layout' => 'layouts.app', 'title' => __('report.efatura_status'), 'subtitle' => __('report.efatura_sub'),
            'headers' => $headers, 'rows' => $out, 'exportSlug' => 'efatura',
            'companies' => $companies, 'companyId' => $companyId, 'periods' => $periods, 'periodId' => $periodId,
        ]);
    }

    // ---- Bütçe & Karşılaştırmalı Gelir Tablosu ----

    private function comparativeData(int $companyId, int $periodId): array
    {
        $cur = AccountingService::trialBalance($companyId, $periodId);
        $curPeriod = DB::first('SELECT start_date FROM fiscal_periods WHERE id = :id', ['id' => $periodId]);
        $prevBy = [];
        if ($curPeriod) {
            $p = DB::first('SELECT id FROM fiscal_periods WHERE company_id = :c AND start_date < :sd ORDER BY start_date DESC LIMIT 1', ['c' => $companyId, 'sd' => $curPeriod['start_date']]);
            if ($p) {
                foreach (AccountingService::trialBalance($companyId, (int) $p['id']) as $r) {
                    $prevBy[(string) $r['code']] = $r;
                }
            }
        }
        $budgetBy = [];
        foreach (DB::select('SELECT code, budget_amount FROM accounting_accounts WHERE company_id = :c AND fiscal_period_id = :p', ['c' => $companyId, 'p' => $periodId]) as $b) {
            $budgetBy[(string) $b['code']] = (float) $b['budget_amount'];
        }
        $rows = [];
        foreach ($cur as $r) {
            $code = (string) $r['code'];
            $bal = $r['type'] === 'income' ? (float) $r['credit'] - (float) $r['debit'] : (float) $r['debit'] - (float) $r['credit'];
            $prev = null;
            if (isset($prevBy[$code])) {
                $prev = $r['type'] === 'income' ? (float) $prevBy[$code]['credit'] - (float) $prevBy[$code]['debit'] : (float) $prevBy[$code]['debit'] - (float) $prevBy[$code]['credit'];
            }
            $rows[] = ['code' => $code, 'name' => $r['name'], 'type' => $r['type'], 'current' => $bal, 'previous' => $prev ?? 0.0, 'budget' => $budgetBy[$code] ?? 0.0];
        }
        return [
            'income' => array_values(array_filter($rows, fn ($x) => $x['type'] === 'income')),
            'expense' => array_values(array_filter($rows, fn ($x) => $x['type'] === 'expense')),
        ];
    }

    /** Bütçe & karşılaştırmalı gelir tablosu (export). */
    public function comparative(Request $request): Response
    {
        Auth::requireCan('report.export');
        $format = $request->query('format', 'csv');
        [$companyId, $periodId] = $this->ctx($request);
        $companyId = $companyId ?: $this->firstCompanyId();
        $data = $this->comparativeData($companyId, $periodId);
        $h = [__('accounting.chart_of_accounts'), __('common.name'), __('report.period_current'), __('report.period_previous'), __('report.budget'), __('report.budget_variance')];
        $fmt = fn ($x) => number_format((float) $x, 2, ',', '.');
        $out = [];
        foreach ([['income', __('report.revenue')], ['expense', __('report.expenses')]] as [$k, $label]) {
            $out[] = [$label, '', '', '', '', ''];
            foreach ($data[$k] as $r) {
                $out[] = [$r['code'], $r['name'], $fmt($r['current']), $fmt($r['previous']), $fmt($r['budget']), $fmt((float) $r['budget'] - (float) $r['current'])];
            }
        }
        return $this->export($format, __('report.comparative'), 'C:' . $companyId . ' P:' . $periodId, $h, $out, 'karsilastirmali');
    }

    /** Bütçe & karşılaştırmalı gelir tablosu ekranı. */
    public function comparativeScreen(Request $request): Response
    {
        Auth::requireCan('report.view');
        [$companies, $companyId, $periods, $periodId] = $this->screenContext($request);
        $companyId = $companyId ?: $this->firstCompanyId();
        $data = $this->comparativeData($companyId, $periodId);
        $h = [__('accounting.chart_of_accounts'), __('common.name'), __('report.period_current'), __('report.period_previous'), __('report.budget'), __('report.budget_variance')];
        $fmt = fn ($x) => number_format((float) $x, 2, ',', '.');
        $rows = [];
        $tCur = 0.0; $tPrev = 0.0; $tBud = 0.0;
        foreach ([['income', __('report.revenue')], ['expense', __('report.expenses')]] as [$k, $label]) {
            $rows[] = [$label, '', '', '', '', ''];
            foreach ($data[$k] as $r) {
                $tCur += (float) $r['current']; $tPrev += (float) $r['previous']; $tBud += (float) $r['budget'];
                $rows[] = [$r['code'], $r['name'], $fmt($r['current']), $fmt($r['previous']), $fmt($r['budget']), $fmt((float) $r['budget'] - (float) $r['current'])];
            }
        }
        $rows[] = [__('common.total'), '', $fmt($tCur), $fmt($tPrev), $fmt($tBud), $fmt($tBud - $tCur)];
        return $this->view('app.reports.screen', [
            'layout' => 'layouts.app', 'title' => __('report.comparative'), 'subtitle' => __('report.comparative_sub'),
            'headers' => $h, 'rows' => $rows, 'exportSlug' => 'comparative',
            'companies' => $companies, 'companyId' => $companyId, 'periods' => $periods, 'periodId' => $periodId,
        ]);
    }

    // ---- Yaklaşan yükümlülükler (unpaid invoices / upcoming & overdue) ----

    private function upcomingData(Request $request): array
    {
        $companyId = (int) ($request->query('company_id') ?? 0);
        $sql = "SELECT i.number, i.date, i.due_date, i.total, i.paid, i.type, c.name AS company_name, ca.name AS cari
                  FROM invoices i
                  JOIN companies c ON c.id = i.company_id
                  LEFT JOIN current_accounts ca ON ca.id = i.current_account_id
                 WHERE i.tenant_id = :t AND i.deleted_at IS NULL AND i.status = 'posted' AND i.total > i.paid";
        $params = ['t' => Auth::tenantId()];
        if ($companyId) {
            $sql .= ' AND i.company_id = :c';
            $params['c'] = $companyId;
        }
        $sql .= ' ORDER BY (i.due_date IS NULL), i.due_date ASC, i.id ASC';
        return DB::select($sql, $params);
    }

    public function upcoming(Request $request): Response
    {
        Auth::requireCan('report.export');
        $format = $request->query('format', 'csv');
        $today = date('Y-m-d');
        $rows = $this->upcomingData($request);
        $headers = [__('accounting.number'), __('common.date'), __('current_account.name'), __('invoice.company'), __('report.due_date'), __('report.outstanding'), __('report.upcoming_status')];
        $out = [];
        foreach ($rows as $r) {
            $due = (float) $r['total'] - (float) $r['paid'];
            $days = !empty($r['due_date']) ? (int) floor((strtotime($today) - strtotime($r['due_date'])) / 86400) : 0;
            $status = $days > 0 ? __('report.upcoming_overdue') : __('report.upcoming_upcoming');
            $out[] = [$r['number'], format_date($r['date']), $r['cari'] ?? '', $r['company_name'], $r['due_date'] ? format_date($r['due_date']) : '—', number_format($due, 2, ',', '.'), $status];
        }
        return $this->export($format, __('report.upcoming_liabilities'), '', $headers, $out, 'yuklumlulukler');
    }

    public function upcomingScreen(Request $request): Response
    {
        Auth::requireCan('report.view');
        [$companies, $companyId, $periods, $periodId] = $this->screenContext($request);
        $today = date('Y-m-d');
        $rows = $this->upcomingData($request);
        $headers = [__('accounting.number'), __('common.date'), __('current_account.name'), __('invoice.company'), __('report.due_date'), __('report.outstanding'), __('report.upcoming_status')];
        $out = [];
        $totalOut = 0.0;
        foreach ($rows as $r) {
            $due = (float) $r['total'] - (float) $r['paid'];
            $totalOut += $due;
            $days = !empty($r['due_date']) ? (int) floor((strtotime($today) - strtotime($r['due_date'])) / 86400) : 0;
            $status = $days > 0 ? __('report.upcoming_overdue') : __('report.upcoming_upcoming');
            $out[] = [$r['number'], format_date($r['date']), $r['cari'] ?? '', $r['company_name'], $r['due_date'] ? format_date($r['due_date']) : '—', number_format($due, 2, ',', '.'), $status];
        }
        $out[] = [__('common.total'), '', '', '', '', number_format($totalOut, 2, ',', '.'), ''];
        return $this->view('app.reports.screen', [
            'layout' => 'layouts.app', 'title' => __('report.upcoming_liabilities'), 'subtitle' => __('report.upcoming_sub'),
            'headers' => $headers, 'rows' => $out, 'exportSlug' => 'yuklumlulukler',
            'companies' => $companies, 'companyId' => $companyId, 'periods' => $periods, 'periodId' => $periodId,
        ]);
    }
}
