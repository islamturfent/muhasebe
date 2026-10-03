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
use Muh\Services\AuditLogService;
use Muh\Services\SessionContext;

/**
 * Branch (şube) management per client company.
 */
final class BranchController extends Controller
{
    public function index(Request $request): Response
    {
        Auth::requireCan('company.read');
        $tenantId = Auth::tenantId();

        $companyId = (int) $request->query('company_id', 0);
        $condition = 'WHERE b.tenant_id = :t AND b.deleted_at IS NULL';
        $params = ['t' => $tenantId];
        if ($companyId > 0) {
            $condition .= ' AND b.company_id = :c';
            $params['c'] = $companyId;
        }
        $branches = DB::select(
            "SELECT b.*, c.name AS company_name FROM branches b
               JOIN companies c ON c.id = b.company_id
               $condition ORDER BY c.name, b.name",
            $params
        );

        $companies = DB::select('SELECT id, name FROM companies WHERE tenant_id = :t AND deleted_at IS NULL ORDER BY name', ['t' => $tenantId]);

        return $this->view('app.branches.index', [
            'layout' => 'layouts.app',
            'branches' => $branches,
            'companies' => $companies,
            'companyId' => $companyId,
        ]);
    }

    public function create(Request $request): Response
    {
        Auth::requireCan('company.update');
        $tenantId = Auth::tenantId();
        $companies = DB::select('SELECT id, name FROM companies WHERE tenant_id = :t AND deleted_at IS NULL ORDER BY name', ['t' => $tenantId]);
        $selectedCompany = (int) ($request->query('company_id') ?? SessionContext::companyId() ?? ($companies[0]['id'] ?? 0));

        return $this->view('app.branches.create', [
            'layout' => 'layouts.app',
            'companies' => $companies,
            'selectedCompany' => $selectedCompany,
            'input' => $request->all(),
        ]);
    }

    public function store(Request $request): Response
    {
        Auth::requireCan('company.update');
        $tenantId = Auth::tenantId();
        $companyId = (int) $request->input('company_id');

        $valid = DB::first('SELECT id FROM companies WHERE id = :id AND tenant_id = :t AND deleted_at IS NULL', ['id' => $companyId, 't' => $tenantId]);
        if (!$valid) {
            return Response::redirect('/app/branches/create');
        }
        $name = trim((string) $request->input('name'));
        if ($name === '') {
            Session::set('_form_errors', ['name' => __('validation.required')]);
            return Response::redirect('/app/branches/create?company_id=' . $companyId);
        }

        DB::insert('branches', [
            'tenant_id' => $tenantId,
            'company_id' => $companyId,
            'name' => $name,
            'address' => $request->input('address') ?: null,
            'phone' => $request->input('phone') ?: null,
            'city' => $request->input('city') ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        AuditLogService::record('branch.create', 'branch', 'branches', null, null, ['company_id' => $companyId, 'name' => $name]);
        Session::flash('success', __('branch.created'));

        $back = $request->input('return') ?: '/app/branches';
        if (is_string($back) && str_contains($back, '/app/companies/')) {
            return Response::redirect($back);
        }
        return Response::redirect($back . '?company_id=' . $companyId);
    }

    public function destroy(Request $request, $id): Response
    {
        Auth::requireCan('company.delete');
        $branch = DB::first(
            'SELECT * FROM branches WHERE id = :id AND tenant_id = :t AND deleted_at IS NULL',
            ['id' => (int) $id, 't' => Auth::tenantId()]
        );
        if ($branch) {
            DB::execute('UPDATE branches SET deleted_at = :n WHERE id = :id', ['n' => now(), 'id' => (int) $id]);
            AuditLogService::record('branch.delete', 'branch', 'branches', (string) $branch['id'], null, ['name' => $branch['name']]);
            Session::flash('success', __('branch.deleted'));
        }
        return Response::redirect('/app/branches?company_id=' . (int) ($branch['company_id'] ?? 0));
    }
}
