<?php

declare(strict_types=1);

namespace Muh\Controllers;

use Muh\Core\Auth;
use Muh\Core\DB;
use Muh\Core\Controller;
use Muh\Core\Request;
use Muh\Core\Response;

/**
 * Audit log viewer (Phase 12).
 */
final class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        Auth::requireCan('audit.view');
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

        return $this->view('app.settings.audit', [
            'layout' => 'layouts.app',
            'logs' => $logs,
            'modules' => $modules,
            'module' => $request->query('module'),
            'action' => $request->query('action'),
            'page' => $page,
            'lastPage' => $lastPage,
            'total' => $total,
        ]);
    }
}
