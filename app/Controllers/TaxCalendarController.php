<?php

declare(strict_types=1);

namespace Muh\Controllers;

use Muh\Core\Auth;
use Muh\Core\Controller;
use Muh\Core\DB;
use Muh\Core\Request;
use Muh\Core\Response;
use Muh\Core\Session;
use Muh\Services\TaxCalendarService;

/**
 * Vergi Takvimi & Yükümlülük Panosu (phased under tax permission).
 */
final class TaxCalendarController extends Controller
{
    public function index(Request $request): Response
    {
        Auth::requireCan('tax.read');
        $tenantId = Auth::tenantId();
        $companies = DB::select('SELECT id, name FROM companies WHERE tenant_id = :t AND deleted_at IS NULL ORDER BY name', ['t' => $tenantId]);

        $where = ' WHERE o.tenant_id = :t AND o.deleted_at IS NULL';
        $params = ['t' => $tenantId];
        if ($year = (int) ($request->query('year') ?? 0)) {
            $where .= ' AND (o.period_label LIKE :yl OR o.due_date LIKE :yd)';
            $params['yl'] = '%' . $year . '%';
            $params['yd'] = $year . '%';
        }
        if ($status = $request->query('status')) {
            if (in_array($status, ['pending', 'done'], true)) {
                $where .= ' AND o.status = :st';
                $params['st'] = $status;
            }
        }
        if ($type = $request->query('type')) {
            $where .= ' AND o.obligation_type = :ty';
            $params['ty'] = $type;
        }

        $perPage = 50;
        $page = max(1, (int) ($request->query('page') ?? 1));
        $total = (int) DB::scalar('SELECT COUNT(*) FROM tax_obligations o ' . $where, $params);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $offset = ($page - 1) * $perPage;

        $items = DB::select(
            'SELECT o.*, c.name AS company_name FROM tax_obligations o LEFT JOIN companies c ON c.id = o.company_id '
            . $where . ' ORDER BY o.due_date IS NULL, o.due_date ASC LIMIT ' . $offset . ', ' . $perPage,
            $params
        );

        return $this->view('app.tax-calendar.index', [
            'layout' => 'layouts.app',
            'items' => $items,
            'companies' => $companies,
            'overdue' => TaxCalendarService::overdueCount($tenantId),
            'upcoming' => TaxCalendarService::upcomingCount($tenantId),
            'year' => (int) ($request->query('year') ?? date('Y')),
            'status' => $request->query('status'),
            'type' => $request->query('type'),
            'page' => $page, 'lastPage' => $lastPage, 'total' => $total,
        ]);
    }

    public function generate(Request $request): Response
    {
        Auth::requireCan('tax.update');
        $year = (int) ($request->input('year') ?? date('Y'));
        $count = TaxCalendarService::generate(Auth::tenantId(), $year, (int) ($request->input('company_id') ?: 0) ?: null);
        Session::flash('success', __('tax.generated', ['count' => $count]));
        return Response::redirect('/app/tax-calendar?year=' . $year);
    }

    public function store(Request $request): Response
    {
        Auth::requireCan('tax.update');
        $tenantId = Auth::tenantId();
        $name = trim((string) $request->input('name'));
        $due = $request->input('due_date');
        if ($name === '' || !$due) {
            Session::flash('error', __('tax.required'));
            return Response::redirect('/app/tax-calendar');
        }
        $companyId = (int) ($request->input('company_id') ?? 0) ?: null;
        DB::insert('tax_obligations', [
            'tenant_id' => $tenantId, 'company_id' => $companyId,
            'name' => $name, 'obligation_type' => $request->input('obligation_type') ?: 'other',
            'period_label' => $request->input('period_label') ?: null,
            'due_date' => $due, 'amount' => (float) ($request->input('amount') ?? 0),
            'status' => 'pending', 'notes' => $request->input('notes') ?: null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Session::flash('success', __('tax.created'));
        return Response::redirect('/app/tax-calendar?year=' . substr($due, 0, 4));
    }

    public function toggleStatus(Request $request, $id): Response
    {
        Auth::requireCan('tax.update');
        $id = (int) $id;
        $ob = DB::first('SELECT * FROM tax_obligations WHERE id = :id AND tenant_id = :t', ['id' => $id, 't' => Auth::tenantId()]);
        if ($ob) {
            $newStatus = $ob['status'] === 'done' ? 'pending' : 'done';
            DB::execute('UPDATE tax_obligations SET status = :s, updated_at = NOW() WHERE id = :id', ['s' => $newStatus, 'id' => $id]);
        }
        return Response::redirect('/app/tax-calendar?year=' . substr((string) ($ob['due_date'] ?? ''), 0, 4));
    }

    public function destroy(Request $request, $id): Response
    {
        Auth::requireCan('tax.update');
        $id = (int) $id;
        DB::execute('DELETE FROM tax_obligations WHERE id = :id AND tenant_id = :t', ['id' => $id, 't' => Auth::tenantId()]);
        Session::flash('success', __('tax.deleted'));
        return Response::redirect('/app/tax-calendar');
    }
}
