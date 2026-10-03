<?php

declare(strict_types=1);

namespace Muh\Controllers;

use Muh\Core\Auth;
use Muh\Core\Controller;
use Muh\Core\DB;
use Muh\Core\Hash;
use Muh\Core\Request;
use Muh\Core\Response;
use Muh\Core\Session;
use Muh\Core\ValidationException;
use Muh\Core\Validator;
use Muh\Services\AuditLogService;
use Muh\Services\Billing\BillingService;

/**
 * Super-administrator panel. Only users with is_system_admin=1 (the software
 * owner, or admins assigned by them) can reach this area. It spans all tenants.
 */
final class AdminController extends Controller
{
    public function index(Request $request): Response
    {
        $stats = [
            'tenants' => (int) DB::scalar('SELECT COUNT(*) FROM tenants'),
            'active_tenants' => (int) DB::scalar("SELECT COUNT(*) FROM tenants WHERE status = 'active'"),
            'companies' => (int) DB::scalar('SELECT COUNT(*) FROM companies WHERE deleted_at IS NULL'),
            'users' => (int) DB::scalar('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL'),
            'invoices' => (int) DB::scalar('SELECT COUNT(*) FROM invoices WHERE deleted_at IS NULL'),
            'system_admins' => (int) DB::scalar('SELECT COUNT(*) FROM users WHERE is_system_admin = 1 AND deleted_at IS NULL'),
        ];

        $recentTenants = DB::select('SELECT * FROM tenants ORDER BY id DESC LIMIT 8');

        return $this->view('admin.index', [
            'layout' => 'layouts.admin',
            'stats' => $stats,
            'recentTenants' => $recentTenants,
        ]);
    }

    // ---- Tenants ----
    public function tenants(Request $request): Response
    {
        $search = trim((string) $request->query('search'));

        $where = '';
        $params = [];
        if ($search !== '') {
            // Positional params (repeated ?) because the name is used 3x and
            // native prepared statements reject repeated named placeholders.
            $where = ' WHERE t.name LIKE ? OR t.slug LIKE ? OR t.email LIKE ?';
            $params = ['%' . $search . '%', '%' . $search . '%', '%' . $search . '%'];
        }

        // Manual pagination (the SELECT uses correlated subqueries, so the
        // generic paginate() COUNT rewrite would be wrong).
        $perPage = 25;
        $page = max(1, (int) ($request->query('page') ?? 1));
        $total = (int) DB::scalar('SELECT COUNT(*) FROM tenants t' . $where, $params);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $offset = ($page - 1) * $perPage;

        $sql = 'SELECT t.*,
                       (SELECT COUNT(*) FROM companies c WHERE c.tenant_id = t.id AND c.deleted_at IS NULL) AS companies,
                       (SELECT COUNT(*) FROM users u WHERE u.tenant_id = t.id AND u.deleted_at IS NULL) AS members
                  FROM tenants t' . $where . ' ORDER BY t.id DESC LIMIT ' . $offset . ', ' . $perPage;
        $tenants = DB::select($sql, $params);

        return $this->view('admin.tenants', [
            'layout' => 'layouts.admin',
            'tenants' => $tenants,
            'search' => $search,
            'page' => $page,
            'lastPage' => $lastPage,
            'total' => $total,
        ]);
    }

    public function tenantShow(Request $request, $id): Response
    {
        $tenant = DB::first('SELECT * FROM tenants WHERE id = :id', ['id' => (int) $id]);
        if (!$tenant) {
            return Response::redirect('/admin/tenants');
        }
        $companies = DB::select('SELECT * FROM companies WHERE tenant_id = :t AND deleted_at IS NULL ORDER BY name', ['t' => $tenant['id']]);
        $users = DB::select('SELECT id, name, email, is_owner, status FROM users WHERE tenant_id = :t AND deleted_at IS NULL ORDER BY name', ['t' => $tenant['id']]);
        $sub = DB::first(
            'SELECT s.*, p.code AS plan_code FROM subscriptions s JOIN plans p ON p.id = s.plan_id WHERE s.tenant_id = :t ORDER BY s.id DESC LIMIT 1',
            ['t' => $tenant['id']]
        );

        return $this->view('admin.tenant-show', [
            'layout' => 'layouts.admin',
            'tenant' => $tenant,
            'companies' => $companies,
            'users' => $users,
            'sub' => $sub,
        ]);
    }

    public function toggleTenant(Request $request, $id): Response
    {
        $tenant = DB::first('SELECT * FROM tenants WHERE id = :id', ['id' => (int) $id]);
        if ($tenant) {
            $newStatus = ($tenant['status'] === 'active') ? 'suspended' : 'active';
            DB::execute('UPDATE tenants SET status = :s, updated_at = :n WHERE id = :id', ['s' => $newStatus, 'n' => now(), 'id' => (int) $id]);
            AuditLogService::record('admin.tenant.toggle', 'admin', 'tenants', (string) $tenant['id'], null, ['status' => $newStatus]);
            Session::flash('success', __('admin.tenant_toggled'));
        }
        return Response::redirect('/admin/tenants');
    }

    // ---- System admins ----
    public function admins(Request $request): Response
    {
        $admins = DB::select(
            'SELECT id, name, email, tenant_id, is_owner, last_login_at, created_at FROM users
              WHERE is_system_admin = 1 AND deleted_at IS NULL ORDER BY id DESC'
        );
        // All non-system users who could be promoted (owner-level or any user).
        $promotable = DB::select(
            'SELECT id, name, email FROM users WHERE is_system_admin = 0 AND deleted_at IS NULL ORDER BY name'
        );
        return $this->view('admin.admins', [
            'layout' => 'layouts.admin',
            'admins' => $admins,
            'promotable' => $promotable,
            'errors' => Session::get('_form_errors', []),
        ]);
    }

    public function createAdmin(Request $request): Response
    {
        $name = trim((string) $request->input('name'));
        $email = strtolower(trim((string) $request->input('email')));
        $password = (string) $request->input('password');

        $v = new Validator();
        if (!$v->validate(
            ['name' => $name, 'email' => $email, 'password' => $password],
            ['name' => 'required|min:2', 'email' => 'required|email|unique:users,email', 'password' => 'required|min:8']
        )) {
            Session::set('_form_errors', $v->errors());
            return Response::redirect('/admin/admins');
        }

        $platform = DB::first("SELECT id FROM tenants WHERE slug = 'platform'");
        $userId = (int) DB::insert('users', [
            'tenant_id' => $platform ? (int) $platform['id'] : null,
            'name' => $name,
            'email' => $email,
            'phone' => null,
            'password' => Hash::make($password),
            'locale' => 'tr',
            'currency' => 'TRY',
            'status' => 'active',
            'is_owner' => 0,
            'is_system_admin' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        AuditLogService::record('admin.admin.create', 'admin', 'users', (string) $userId, null, ['email' => $email]);
        Session::flash('success', __('admin.admin_created'));
        return Response::redirect('/admin/admins');
    }

    public function promoteAdmin(Request $request): Response
    {
        $userId = (int) $request->input('user_id');
        if ($userId > 0) {
            DB::execute('UPDATE users SET is_system_admin = 1, updated_at = :n WHERE id = :id', ['n' => now(), 'id' => $userId]);
            AuditLogService::record('admin.admin.promote', 'admin', 'users', (string) $userId);
            Session::flash('success', __('admin.admin_promoted'));
        }
        return Response::redirect('/admin/admins');
    }

    public function revokeAdmin(Request $request, $id): Response
    {
        $userId = (int) $id;
        if (Auth::id() !== $userId) { // can't revoke yourself
            DB::execute('UPDATE users SET is_system_admin = 0, updated_at = :n WHERE id = :id', ['n' => now(), 'id' => $userId]);
            AuditLogService::record('admin.admin.revoke', 'admin', 'users', (string) $userId);
            Session::flash('success', __('admin.admin_revoked'));
        }
        return Response::redirect('/admin/admins');
    }

    // ---- Impersonation (login as a tenant owner, logged in audit log) ----
    public function impersonate(Request $request, $tenantId): Response
    {
        $tenant = DB::first('SELECT * FROM tenants WHERE id = :id', ['id' => (int) $tenantId]);
        if (!$tenant) {
            return Response::redirect('/admin/tenants');
        }
        $owner = DB::first('SELECT * FROM users WHERE tenant_id = :t AND is_owner = 1 AND status = :st AND deleted_at IS NULL ORDER BY id LIMIT 1', ['t' => (int) $tenantId, 'st' => 'active'])
            ?: DB::first('SELECT * FROM users WHERE tenant_id = :t AND deleted_at IS NULL ORDER BY id LIMIT 1', ['t' => (int) $tenantId]);
        if (!$owner) {
            Session::flash('error', __('admin.no_user_to_impersonate'));
            return Response::redirect('/admin/tenants/' . (int) $tenantId);
        }
        Session::set('_impersonator', Auth::id());
        AuditLogService::record('admin.impersonate', 'admin', 'tenants', (string) $tenant['id'], null, ['as_user' => (int) $owner['id']], null, (int) $tenant['id']);
        Auth::loginById((int) $owner['id']);
        Session::flash('success', __('admin.impersonating', ['office' => $tenant['name']]));
        return Response::redirect('/app/dashboard');
    }

    // ---- Global audit log (all tenants) ----
    public function audit(Request $request): Response
    {
        $where = ' WHERE 1 = 1';
        $params = [];
        if ($t = (int) $request->query('tenant_id')) {
            $where .= ' AND a.tenant_id = :t';
            $params['t'] = $t;
        }
        if ($module = $request->query('module')) {
            $where .= ' AND a.module = :m';
            $params['m'] = $module;
        }

        $perPage = 50;
        $page = max(1, (int) ($request->query('page') ?? 1));
        $total = (int) DB::scalar('SELECT COUNT(*) FROM audit_logs a ' . $where, $params);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $offset = ($page - 1) * $perPage;

        $logs = DB::select(
            'SELECT a.*, u.name AS user_name, c.name AS company_name, t.name AS tenant_name
               FROM audit_logs a
               LEFT JOIN users u ON u.id = a.user_id
               LEFT JOIN companies c ON c.id = a.company_id
               LEFT JOIN tenants t ON t.id = a.tenant_id
             ' . $where . ' ORDER BY a.id DESC LIMIT ' . $offset . ', ' . $perPage,
            $params
        );
        $tenants = DB::select('SELECT id, name FROM tenants ORDER BY name');
        $modules = array_column(DB::select('SELECT DISTINCT module FROM audit_logs'), 'module');

        return $this->view('admin.audit', [
            'layout' => 'layouts.admin',
            'logs' => $logs, 'tenants' => $tenants, 'modules' => $modules,
            'tenantId' => (int) $request->query('tenant_id'), 'module' => $request->query('module'),
            'page' => $page, 'lastPage' => $lastPage, 'total' => $total,
        ]);
    }

    // ---- Subscriptions / billing across tenants ----
    public function subscriptions(Request $request): Response
    {
        $rows = DB::select(
            'SELECT s.*, t.name AS tenant_name, p.code AS plan_code, p.name AS plan_name
               FROM subscriptions s
               JOIN tenants t ON t.id = s.tenant_id
               LEFT JOIN plans p ON p.id = s.plan_id
              WHERE s.deleted_at IS NULL ORDER BY s.id DESC LIMIT 300'
        );
        $plans = DB::select('SELECT id, code, name FROM plans WHERE is_active = 1 ORDER BY sort_order');

        // Revenue summary per plan.
        $summary = [];
        foreach ($rows as $s) {
            $code = $s['plan_code'] ?? '?';
            $summary[$code] = ($summary[$code] ?? 0) + 1;
        }

        return $this->view('admin.subscriptions', [
            'layout' => 'layouts.admin',
            'rows' => $rows, 'plans' => $plans, 'summary' => $summary,
        ]);
    }

    public function setTenantPlan(Request $request, $tenantId): Response
    {
        $planId = (int) $request->input('plan_id');
        $cycle = in_array($request->input('cycle'), ['monthly', 'yearly'], true) ? $request->input('cycle') : 'monthly';
        try {
            (new BillingService())->subscribe((int) $tenantId, $planId, $cycle);
        } catch (\Muh\Core\ValidationException $e) {
            Session::flash('error', implode('; ', $e->errors));
            return Response::redirect('/admin/subscriptions');
        }
        AuditLogService::record('admin.plan.set', 'admin', 'tenants', (string) $tenantId, null, ['plan_id' => $planId]);
        Session::flash('success', __('admin.plan_updated'));
        return Response::redirect('/admin/subscriptions');
    }

    // ---- Plans CRUD ----
    public function plans(Request $request): Response
    {
        $plans = DB::select('SELECT * FROM plans WHERE deleted_at IS NULL ORDER BY sort_order, id');
        return $this->view('admin.plans', ['layout' => 'layouts.admin', 'plans' => $plans, 'errors' => Session::get('_form_errors', [])]);
    }

    private function featureJson(array $data): string
    {
        return json_encode([
            'companies' => (int) ($data['companies'] ?? 1),
            'users' => (int) ($data['users'] ?? 1),
            'warehouses' => (int) ($data['warehouses'] ?? 1),
            'invoices' => (int) ($data['invoices'] ?? 0),
            'efatura' => isset($data['efatura']),
            'storage_mb' => (int) ($data['storage_mb'] ?? 0),
            'reports' => true,
        ], JSON_UNESCAPED_UNICODE);
    }

    public function planStore(Request $request): Response
    {
        $code = strtoupper(trim((string) $request->input('code')));
        $name = trim((string) $request->input('name'));
        if ($code === '' || $name === '' || DB::first('SELECT id FROM plans WHERE code = :c AND deleted_at IS NULL', ['c' => $code])) {
            Session::set('_form_errors', ['code' => __('admin.plan_valid')]);
            return Response::redirect('/admin/plans');
        }
        DB::insert('plans', [
            'code' => $code, 'name' => json_encode(['tr' => $name, 'en' => $name], JSON_UNESCAPED_UNICODE),
            'description' => $request->input('description') ?: null,
            'price_monthly' => (float) $request->input('price_monthly', 0),
            'price_yearly' => (float) $request->input('price_yearly', 0),
            'currency' => $request->input('currency', 'TRY'),
            'features' => $this->featureJson($request->input('features', [])),
            'stripe_price_monthly_id' => $request->input('stripe_price_monthly_id') ?: null,
            'stripe_price_yearly_id' => $request->input('stripe_price_yearly_id') ?: null,
            'sort_order' => (int) $request->input('sort_order', 0),
            'is_active' => isset($request->input('features')['efatura']) ? 1 : 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        AuditLogService::record('admin.plan.create', 'admin', 'plans', null, null, ['code' => $code]);
        Session::flash('success', __('admin.plan_created'));
        return Response::redirect('/admin/plans');
    }

    public function planUpdate(Request $request, $id): Response
    {
        $plan = DB::first('SELECT * FROM plans WHERE id = :id AND deleted_at IS NULL', ['id' => (int) $id]);
        if (!$plan) {
            return Response::redirect('/admin/plans');
        }
        $name = trim((string) $request->input('name'));
        if ($name === '') {
            Session::set('_form_errors', ['name' => __('admin.plan_valid')]);
            return Response::redirect('/admin/plans');
        }
        DB::update('plans', [
            'name' => json_encode(['tr' => $name, 'en' => $name], JSON_UNESCAPED_UNICODE),
            'description' => $request->input('description') ?: null,
            'price_monthly' => (float) $request->input('price_monthly', 0),
            'price_yearly' => (float) $request->input('price_yearly', 0),
            'currency' => $request->input('currency', 'TRY'),
            'features' => $this->featureJson($request->input('features', [])),
            'stripe_price_monthly_id' => $request->input('stripe_price_monthly_id') ?: null,
            'stripe_price_yearly_id' => $request->input('stripe_price_yearly_id') ?: null,
            'sort_order' => (int) $request->input('sort_order', 0),
            'is_active' => isset($request->input('features')['efatura']) ? 1 : 0,
            'updated_at' => now(),
        ], 'id = :id', ['id' => (int) $id]);
        AuditLogService::record('admin.plan.update', 'admin', 'plans', (string) $id, null, ['code' => $plan['code']]);
        Session::flash('success', __('admin.plan_updated'));
        return Response::redirect('/admin/plans');
    }

    public function planDelete(Request $request, $id): Response
    {
        $plan = DB::first('SELECT * FROM plans WHERE id = :id AND deleted_at IS NULL', ['id' => (int) $id]);
        if ($plan) {
            DB::execute('UPDATE plans SET deleted_at = :n WHERE id = :id', ['n' => now(), 'id' => (int) $id]);
            AuditLogService::record('admin.plan.delete', 'admin', 'plans', (string) $id, null, ['code' => $plan['code']]);
            Session::flash('success', __('admin.plan_deleted'));
        }
        return Response::redirect('/admin/plans');
    }

    // ---- Platform settings (maintenance + announcement) ----
    public function settings(Request $request): Response
    {
        $platform = function (string $key) {
            $r = DB::first("SELECT value FROM settings WHERE `group` = 'platform' AND `key` = :k", ['k' => $key]);
            return $r ? $r['value'] : null;
        };
        $announcement = $platform('announcement') ?? '';
        $maintenance = ((string) $platform('maintenance') === '1');

        return $this->view('admin.settings', [
            'layout' => 'layouts.admin',
            'announcement' => $announcement,
            'maintenance' => $maintenance,
        ]);
    }

    public function saveSettings(Request $request): Response
    {
        $maintenance = (bool) $request->input('maintenance');
        $announcement = trim((string) $request->input('announcement'));
        $this->platformSetting('maintenance', $maintenance ? '1' : '0');
        $this->platformSetting('announcement', $announcement);
        AuditLogService::record('admin.settings.save', 'admin', 'settings', null, null, ['maintenance' => $maintenance]);
        Session::flash('success', __('admin.settings_saved'));
        return Response::redirect('/admin/settings');
    }

    private function platformSetting(string $key, string $value): void
    {
        $exists = DB::first("SELECT id FROM settings WHERE `group` = 'platform' AND `key` = :k", ['k' => $key]);
        if ($exists) {
            DB::execute("UPDATE settings SET value = :v, updated_at = :n WHERE id = :id", ['v' => $value, 'n' => now(), 'id' => (int) $exists['id']]);
        } else {
            DB::insert('settings', ['tenant_id' => null, 'group' => 'platform', 'key' => $key, 'value' => $value, 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
