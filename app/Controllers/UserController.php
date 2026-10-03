<?php

declare(strict_types=1);

namespace Muh\Controllers;

use Muh\Core\Auth;
use Muh\Core\Controller;
use Muh\Core\DB;
use Muh\Core\Request;
use Muh\Core\Response;
use Muh\Core\Session;
use Muh\Core\ValidationException;
use Muh\Services\UserInviteService;

/**
 * Office users & invitations management (Phase 19 / nav "Kullanıcılar").
 */
final class UserController extends Controller
{
    public function index(Request $request): Response
    {
        Auth::requireCan('user.read');
        $tenantId = Auth::tenantId();

        $users = DB::select(
            'SELECT u.*, GROUP_CONCAT(DISTINCT r.`key`) AS roles
               FROM users u
               LEFT JOIN user_role ur ON ur.user_id = u.id
               LEFT JOIN roles r ON r.id = ur.role_id
              WHERE u.tenant_id = :t AND u.deleted_at IS NULL
              GROUP BY u.id ORDER BY u.name ASC',
            ['t' => $tenantId]
        );

        $invites = DB::select(
            'SELECT i.*, r.`key` AS role_key,
                    (SELECT COUNT(*) FROM user_invite_company ic WHERE ic.invite_id = i.id) AS company_count
               FROM user_invites i
               LEFT JOIN roles r ON r.id = i.role_id
              WHERE i.tenant_id = :t ORDER BY i.created_at DESC',
            ['t' => $tenantId]
        );

        // Count user_company links per user.
        $links = [];
        foreach (DB::select('SELECT user_id, COUNT(*) AS c FROM user_company GROUP BY user_id') as $l) {
            $links[(int) $l['user_id']] = (int) $l['c'];
        }

        // Enforce plan user limits.
        $plan = $this->currentPlanUsers();
        $userCount = count($users);

        return $this->view('app.users.index', [
            'layout' => 'layouts.app',
            'users' => $users,
            'invites' => $invites,
            'links' => $links,
            'userCount' => $userCount,
            'planUsers' => $plan,
        ]);
    }

    public function invite(Request $request): Response
    {
        Auth::requireCan('user.invite');
        $tenantId = Auth::tenantId();

        $roles = DB::select('SELECT id, `key`, name FROM roles WHERE is_system = 1 ORDER BY id');
        $companies = DB::select('SELECT id, name FROM companies WHERE tenant_id = :t AND deleted_at IS NULL ORDER BY name', ['t' => $tenantId]);

        return $this->view('app.users.invite', [
            'layout' => 'layouts.app',
            'roles' => $roles,
            'companies' => $companies,
            'input' => $request->all(),
        ]);
    }

    public function store(Request $request): Response
    {
        Auth::requireCan('user.invite');
        $service = new UserInviteService();
        try {
            $token = $service->create(
                Auth::tenantId(),
                (int) Auth::id(),
                (string) $request->input('email'),
                (int) $request->input('role_id'),
                array_map('intval', (array) $request->input('company_ids', []))
            );
        } catch (ValidationException $e) {
            Session::set('_form_errors', $e->errors);
            return Response::redirect('/app/users/invite');
        }
        Session::flash('success', __('user.invite_created'));
        return Response::redirect('/app/users');
    }

    public function show(Request $request, $id): Response
    {
        Auth::requireCan('user.read');
        $userId = (int) $id;
        $tenantId = Auth::tenantId();

        $user = DB::first('SELECT * FROM users WHERE id = :id AND tenant_id = :t AND deleted_at IS NULL', ['id' => $userId, 't' => $tenantId]);
        if (!$user) {
            return Response::redirect('/app/users');
        }

        $roles = DB::select(
            'SELECT r.* FROM roles r JOIN user_role ur ON ur.role_id = r.id WHERE ur.user_id = :u',
            ['u' => $userId]
        );
        $roleIds = array_map(fn ($r) => (int) $r['id'], $roles);
        $companies = DB::select(
            'SELECT c.* FROM companies c
              JOIN user_company uc ON uc.company_id = c.id
             WHERE uc.user_id = :u AND c.deleted_at IS NULL ORDER BY c.name',
            ['u' => $userId]
        );
        $ownedCompanyIds = array_map(fn ($c) => (int) $c['id'], $companies);
        $available = DB::select('SELECT id, name FROM companies WHERE tenant_id = :t AND deleted_at IS NULL ORDER BY name', ['t' => $tenantId]);

        return $this->view('app.users.show', [
            'layout' => 'layouts.app',
            'user' => $user,
            'roles' => $roles,
            'roleIds' => $roleIds,
            'companies' => $companies,
            'ownedCompanyIds' => $ownedCompanyIds,
            'available' => $available,
        ]);
    }

    public function attachCompany(Request $request, $id): Response
    {
        Auth::requireCan('user.update');
        $userId = (int) $id;
        $companyId = (int) $request->input('company_id');
        $tenantId = Auth::tenantId();

        if ($companyId > 0 && DB::first('SELECT id FROM companies WHERE id = :id AND tenant_id = :t AND deleted_at IS NULL', ['id' => $companyId, 't' => $tenantId])) {
            DB::execute(
                'INSERT IGNORE INTO user_company (user_id, company_id, created_at, updated_at) VALUES (:u, :c, :n, :n)',
                ['u' => $userId, 'c' => $companyId, 'n' => now()]
            );
        }
        Session::flash('success', __('user.company_attached'));
        return Response::redirect('/app/users/' . $userId);
    }

    public function removeCompany(Request $request, $id, $cid): Response
    {
        Auth::requireCan('user.update');
        $userId = (int) $id;
        $companyId = (int) $cid;
        DB::execute(
            'DELETE FROM user_company WHERE user_id = :u AND company_id = :c',
            ['u' => $userId, 'c' => $companyId]
        );
        Session::flash('success', __('user.company_removed'));
        return Response::redirect('/app/users/' . $userId);
    }

    public function cancelInvite(Request $request, $id): Response
    {
        Auth::requireCan('user.invite');
        $invite = DB::first('SELECT * FROM user_invites WHERE id = :id AND tenant_id = :t', ['id' => (int) $id, 't' => Auth::tenantId()]);
        if ($invite) {
            (new UserInviteService())->cancel((int) $invite['id']);
            Session::flash('success', __('user.invite_cancelled'));
        }
        return Response::redirect('/app/users');
    }

    private function currentPlanUsers(): ?int
    {
        $plan = DB::first(
            'SELECT p.* FROM subscriptions s
              JOIN plans p ON p.id = s.plan_id
             WHERE s.tenant_id = :t ORDER BY s.id DESC LIMIT 1',
            ['t' => Auth::tenantId()]
        );
        if (!$plan || empty($plan['features'])) {
            return null;
        }
        $f = json_decode((string) $plan['features'], true);
        return is_array($f) ? (int) ($f['users'] ?? 0) : 0;
    }
}
