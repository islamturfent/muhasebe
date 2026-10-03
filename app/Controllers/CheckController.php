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
use Muh\Services\CheckService;

final class CheckController extends Controller
{
    private function viewName(string $kind): string
    {
        return 'app.check.index';
    }

    public function index(Request $request, string $kind = 'check'): Response
    {
        Auth::requireCan('check.read');
        $companyId = (int) ($request->query('company_id') ?? 0);
        $service = new CheckService();
        $companies = DB::select('SELECT id, name FROM companies WHERE tenant_id = :t AND deleted_at IS NULL ORDER BY name', ['t' => Auth::tenantId()]);
        return $this->view($this->viewName($kind), [
            'layout' => 'layouts.app',
            'kind' => $kind,
            'records' => $service->list($companyId ?: null, $kind),
            'companies' => $companies,
            'companyId' => $companyId,
        ]);
    }

    public function create(Request $request, string $kind = 'check'): Response
    {
        Auth::requireCan('check.create');
        $tenantId = Auth::tenantId();
        $companies = DB::select('SELECT id, name FROM companies WHERE tenant_id = :t AND deleted_at IS NULL ORDER BY name', ['t' => $tenantId]);
        $selectedCompany = (int) ($request->query('company_id') ?? ($companies[0]['id'] ?? 0));
        $accounts = $selectedCompany ? DB::select('SELECT id, code, name FROM current_accounts WHERE company_id = :c AND deleted_at IS NULL ORDER BY name', ['c' => $selectedCompany]) : [];
        return $this->view('app.check.create', [
            'layout' => 'layouts.app',
            'kind' => $kind,
            'companies' => $companies,
            'selectedCompany' => $selectedCompany,
            'accounts' => $accounts,
        ]);
    }

    public function store(Request $request, string $kind = 'check'): Response
    {
        Auth::requireCan('check.create');
        try {
            (new CheckService())->create($request->all(), $kind);
        } catch (ValidationException $e) {
            Session::set('_form_errors', $e->errors);
            return Response::redirect('/app/checks/' . ($kind === 'note' ? 'notes/' : '') . 'create');
        }
        Session::flash('success', __('check.created'));
        return Response::redirect($kind === 'note' ? '/app/checks/notes' : '/app/checks');
    }

    public function updateStatus(Request $request, $id, string $kind = 'check'): Response
    {
        Auth::requireCan('check.update');
        (new CheckService())->updateStatus($kind, (int) $id, (string) $request->input('status'));
        Session::flash('success', __('check.update_status'));
        return Response::redirect($kind === 'note' ? '/app/checks/notes' : '/app/checks');
    }
}
