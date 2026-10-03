<?php

declare(strict_types=1);

namespace Muh\Controllers;

use Muh\Core\Auth;
use Muh\Core\Controller;
use Muh\Core\DB;
use Muh\Core\Request;
use Muh\Core\Response;
use Muh\Core\Session;
use Muh\Services\AccountingService;
use Muh\Services\CurrentContextService;
use Muh\Services\SessionContext;

/**
 * Office-level dashboard showing all client firms at a glance.
 */
final class DashboardController extends Controller
{
    public function office(Request $request): Response
    {
        $tenantId = Auth::tenantId();
        $userId = Auth::id();

        $t = ':t';
        $today = date('Y-m-d');

        // Scope data to the user's assigned companies (owner/admin see all).
        $user = Auth::user();
        $allCompanies = !empty($user['is_owner']) || !empty($user['is_system_admin']);
        $accessible = [];
        $companyFilter = '';
        if (!$allCompanies) {
            foreach (DB::select('SELECT company_id FROM user_company WHERE user_id = :u', ['u' => $userId]) as $r) {
                $accessible[(int) $r['company_id']] = 1;
            }
            $ids = array_keys($accessible);
            if (!$ids) {
                $companyFilter = ' AND 1 = 0';
            } else {
                $in = implode(',', array_map('intval', $ids));
                $companyFilter = " AND company_id IN ({$in})";
            }
        }

        $kpis = [
            'total_companies' => (int) DB::scalar("SELECT COUNT(*) FROM companies WHERE tenant_id = {$t} AND deleted_at IS NULL" . ($allCompanies ? '' : (' AND id IN (' . (implode(',', array_map('intval', array_keys($accessible))) ?: -1) . ')')), ['t' => $tenantId]),
            'active_customers' => (int) DB::scalar("SELECT COUNT(*) FROM customers WHERE tenant_id = {$t} AND deleted_at IS NULL", ['t' => $tenantId]),
            'pending_invoices' => (int) DB::scalar("SELECT COUNT(*) FROM invoices WHERE tenant_id = {$t} AND status = 'draft' AND deleted_at IS NULL" . $companyFilter, ['t' => $tenantId]),
            'overdue' => (int) DB::scalar("SELECT COUNT(*) FROM invoices WHERE tenant_id = {$t} AND status = 'posted' AND due_date < :today AND (paid < total) AND deleted_at IS NULL" . $companyFilter, ['t' => $tenantId, 'today' => $today]),
        ];

        $recentInvoices = DB::select(
            "SELECT i.*, c.name AS company_name FROM invoices i
              JOIN companies c ON c.id = i.company_id
             WHERE i.tenant_id = {$t} AND i.deleted_at IS NULL" . $companyFilter . "
             ORDER BY i.id DESC LIMIT 8",
            ['t' => $tenantId]
        );

        $recentCompanies = DB::select(
            "SELECT * FROM companies WHERE tenant_id = {$t} AND deleted_at IS NULL" . ($allCompanies ? '' : (' AND id IN (' . (implode(',', array_map('intval', array_keys($accessible))) ?: '0') . ')')) . " ORDER BY id DESC LIMIT 10",
            ['t' => $tenantId]
        );

        // Upcoming obligations (from invoices due soon)
        $upcoming = DB::select(
            "SELECT i.*, c.name AS company_name FROM invoices i
              JOIN companies c ON c.id = i.company_id
             WHERE i.tenant_id = {$t} AND i.status = 'posted' AND i.due_date >= :today
               AND (i.paid < i.total) AND i.deleted_at IS NULL" . $companyFilter . "
             ORDER BY i.due_date ASC LIMIT 8",
            ['t' => $tenantId, 'today' => $today]
        );

        return $this->view('app.office-dashboard', [
            'layout' => 'layouts.app',
            'kpis'    => $kpis,
            'recentInvoices' => $recentInvoices,
            'recentCompanies' => $recentCompanies,
            'upcoming' => $upcoming,
        ]);
    }

    public function switchCompany(Request $request): Response
    {
        $companyId = (int) $request->input('company_id');
        if (CurrentContextService::switchCompany($companyId)) {
            Session::flash('success', __('app.company_switched'));
        }
        return Response::redirect('/app/dashboard');
    }

    /**
     * Company (firm) dashboard (spec #18): KPIs for the active company.
     */
    public function company(Request $request): Response
    {
        $tenantId = Auth::tenantId();
        $companyId = SessionContext::companyId();
        $company = $companyId
            ? DB::first('SELECT * FROM companies WHERE id = :id AND tenant_id = :t AND deleted_at IS NULL', ['id' => (int) $companyId, 't' => (int) $tenantId])
            : null;
        if (!$company) {
            Session::flash('error', __('dashboard.select_company'));
            return Response::redirect('/app/dashboard');
        }
        $cid = (int) $companyId;
        $period = DB::first('SELECT id FROM fiscal_periods WHERE company_id = :c AND is_current = 1 AND deleted_at IS NULL', ['c' => $cid])
            ?: DB::first('SELECT id FROM fiscal_periods WHERE company_id = :c ORDER BY id LIMIT 1', ['c' => $cid]);
        $periodId = $period ? (int) $period['id'] : 0;

        // Single batched query for all balance/sum KPIs (fewer round-trips).
        // Positional params (repeated ?) work with native prepared statements.
        $c = $cid;
        $row = DB::first(
            'SELECT
                (SELECT COALESCE(SUM(balance),0) FROM cash_accounts    WHERE company_id = ? AND deleted_at IS NULL) AS cash,
                (SELECT COALESCE(SUM(balance),0) FROM bank_accounts    WHERE company_id = ? AND deleted_at IS NULL) AS bank,
                (SELECT COALESCE(SUM(balance),0) FROM current_accounts WHERE company_id = ? AND balance > 0 AND deleted_at IS NULL) AS receivable,
                (SELECT COALESCE(SUM(-balance),0) FROM current_accounts WHERE company_id = ? AND balance < 0 AND deleted_at IS NULL) AS payable,
                (SELECT COALESCE(SUM(total),0) FROM invoices WHERE company_id = ? AND type = \'sales\' AND status = \'posted\' AND deleted_at IS NULL) AS sales,
                (SELECT COALESCE(SUM(total),0) FROM invoices WHERE company_id = ? AND type = \'purchase\' AND status = \'posted\' AND deleted_at IS NULL) AS purchase,
                (SELECT COALESCE(SUM(stock_quantity * purchase_price),0) FROM products WHERE company_id = ? AND deleted_at IS NULL) AS stock',
            array_fill(0, 7, $c)
        );
        $kpis = [
            'cash'       => (float) ($row['cash'] ?? 0),
            'bank'       => (float) ($row['bank'] ?? 0),
            'receivable' => (float) ($row['receivable'] ?? 0),
            'payable'    => (float) ($row['payable'] ?? 0),
            'sales'      => (float) ($row['sales'] ?? 0),
            'purchase'   => (float) ($row['purchase'] ?? 0),
            'stock'      => (float) ($row['stock'] ?? 0),
        ];
        $kpis['products'] = (int) DB::scalar('SELECT COUNT(*) FROM products WHERE company_id = ? AND deleted_at IS NULL', [$c]);

        $inc = AccountingService::incomeStatement($cid, $periodId);
        $revenue = 0.0;
        $expense = 0.0;
        foreach ($inc['income'] as $r) {
            $revenue += (float) $r['credit'] - (float) $r['debit'];
        }
        foreach ($inc['expense'] as $r) {
            $expense += (float) $r['debit'] - (float) $r['credit'];
        }
        $kpis['profit'] = $revenue - $expense;

        $upcoming = DB::select(
            "SELECT i.number, i.due_date, i.total, i.paid, ca.name AS cari
               FROM invoices i LEFT JOIN current_accounts ca ON ca.id = i.current_account_id
              WHERE i.company_id = :c AND i.status = 'posted' AND i.due_date >= :today
                AND (i.paid < i.total) AND i.deleted_at IS NULL
              ORDER BY i.due_date ASC LIMIT 8",
            ['c' => $cid, 'today' => date('Y-m-d')]
        );

        return $this->view('app.company-dashboard', [
            'layout' => 'layouts.app',
            'company' => $company,
            'kpis' => $kpis,
            'upcoming' => $upcoming,
            'periodId' => $periodId,
        ]);
    }

    /**
     * End a super-admin impersonation and return to the /admin panel.
     */
    public function stopImpersonation(Request $request): Response
    {
        $impersonator = Session::get('_impersonator');
        Session::forget('_impersonator');
        if ($impersonator) {
            \Muh\Core\Auth::loginById((int) $impersonator);
            Session::flash('success', __('admin.impersonate_stopped'));
            return Response::redirect('/admin');
        }
        return Response::redirect('/app/dashboard');
    }
}
