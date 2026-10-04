<?php

declare(strict_types=1);

namespace Muh\Controllers;

use Muh\Core\Auth;
use Muh\Core\DB;
use Muh\Core\Controller;
use Muh\Core\Request;
use Muh\Core\Response;
use Muh\Services\ReportExportService;

/**
 * Audit log viewer + export (Phase 12).
 */
final class AuditLogController extends Controller
{
    /**
     * Build the tenant-scoped WHERE clause + params for audit log filters.
     */
    private function filters(Request $request): array
    {
        $tenantId = Auth::tenantId();
        $where = ' WHERE a.tenant_id = :t';
        $params = ['t' => $tenantId];

        if ($module = $request->query('module')) {
            $where .= ' AND a.module = :m';
            $params['m'] = $module;
        }
        if ($action = $request->query('action')) {
            $where .= ' AND a.action LIKE :a';
            $params['a'] = '%' . $action . '%';
        }
        if ($userId = (int) ($request->query('user_id') ?? 0)) {
            $where .= ' AND a.user_id = :uid';
            $params['uid'] = $userId;
        }
        if ($companyId = (int) ($request->query('company_id') ?? 0)) {
            $where .= ' AND a.company_id = :cid';
            $params['cid'] = $companyId;
        }
        if ($from = $request->query('from')) {
            $where .= ' AND a.created_at >= :from';
            $params['from'] = $from . ' 00:00:00';
        }
        if ($to = $request->query('to')) {
            $where .= ' AND a.created_at <= :to';
            $params['to'] = $to . ' 23:59:59';
        }

        return [$where, $params];
    }

    public function index(Request $request): Response
    {
        Auth::requireCan('audit.view');
        $tenantId = Auth::tenantId();
        [$where, $params] = $this->filters($request);

        // Pagination (Phase 14)
        $perPage = 50;
        $page = max(1, (int) ($request->query('page') ?? 1));
        $total = (int) DB::scalar('SELECT COUNT(*) FROM audit_logs a ' . $where, $params);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $offset = ($page - 1) * $perPage;

        $sql = 'SELECT a.*, u.name AS user_name, c.name AS company_name
                 FROM audit_logs a
                 LEFT JOIN users u ON u.id = a.user_id
                 LEFT JOIN companies c ON c.id = a.company_id
               ' . $where . ' ORDER BY a.id DESC LIMIT ' . $offset . ', ' . $perPage;
        $logs = DB::select($sql, $params);
        $modules = array_column(DB::select('SELECT DISTINCT module FROM audit_logs WHERE tenant_id = :t', ['t' => $tenantId]), 'module');
        $users = DB::select('SELECT DISTINCT u.id AS user_id, u.name FROM audit_logs a JOIN users u ON u.id = a.user_id WHERE a.tenant_id = :t ORDER BY u.name', ['t' => $tenantId]);
        $companies = DB::select('SELECT DISTINCT c.id AS company_id, c.name FROM audit_logs a JOIN companies c ON c.id = a.company_id WHERE a.tenant_id = :t ORDER BY c.name', ['t' => $tenantId]);

        return $this->view('app.settings.audit', [
            'layout' => 'layouts.app',
            'logs' => $logs,
            'modules' => $modules,
            'users' => $users,
            'companies' => $companies,
            'module' => $request->query('module'),
            'action' => $request->query('action'),
            'userId' => (int) ($request->query('user_id') ?? 0),
            'companyId' => (int) ($request->query('company_id') ?? 0),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'page' => $page,
            'lastPage' => $lastPage,
            'total' => $total,
        ]);
    }

    /** Export filtered audit logs as CSV / Excel / PDF. */
    public function export(Request $request): Response
    {
        Auth::requireCan('audit.view');
        $format = $request->query('format', 'csv');
        [$where, $params] = $this->filters($request);

        $sql = 'SELECT a.*, u.name AS user_name, c.name AS company_name
                 FROM audit_logs a
                 LEFT JOIN users u ON u.id = a.user_id
                 LEFT JOIN companies c ON c.id = a.company_id
               ' . $where . ' ORDER BY a.id DESC LIMIT 5000';
        $logs = DB::select($sql, $params);

        $headers = [__('audit.when'), __('audit.user'), __('audit.company'), __('audit.action'), __('audit.module'), __('audit.ip')];
        $rows = array_map(function ($l) {
            return [
                $l['created_at'], $l['user_name'] ?? '—', $l['company_name'] ?? '—',
                $l['action'], $l['module'] ?? '', $l['ip'] ?? '',
            ];
        }, $logs);

        switch ($format) {
            case 'pdf':
                return ReportExportService::pdf(__('audit.title'), __('common.records') . ': ' . count($rows), $headers, $rows, 'audit-log.pdf');
            case 'excel':
                return ReportExportService::excel(__('audit.title'), $headers, $rows, 'audit-log.xls');
            default:
                return ReportExportService::csv($headers, $rows, 'audit-log.csv');
        }
    }
}
