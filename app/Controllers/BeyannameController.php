<?php

declare(strict_types=1);

namespace Muh\Controllers;

use Muh\Core\Auth;
use Muh\Core\DB;
use Muh\Core\Controller;
use Muh\Core\Request;
use Muh\Core\Response;
use Muh\Core\Session;
use Muh\Services\BeyannameService;
use Muh\Services\GibSettingService;

/**
 * e-Beyan (Faz 1): KDV beyannamesi hazırlama → paketleme → GİB'e gönderme →
 * GİB'te onaylama.
 */
final class BeyannameController extends Controller
{
    public function index(Request $request): Response
    {
        Auth::requireCan('dashboard.view');
        $tenantId = Auth::tenantId();
        $declarations = DB::select(
            'SELECT d.*, c.name AS company_name FROM beyannameler d
               LEFT JOIN companies c ON c.id = d.company_id
              WHERE d.tenant_id = :t ORDER BY d.id DESC LIMIT 200',
            ['t' => $tenantId]
        );
        $companies = DB::select(
            'SELECT id, name, tax_number, tax_office, tax_office_code FROM companies
              WHERE tenant_id = :t AND deleted_at IS NULL ORDER BY name',
            ['t' => $tenantId]
        );
        return $this->view('app.beyanname.index', [
            'layout' => 'layouts.app',
            'declarations' => $declarations,
            'companies' => $companies,
            'settings' => GibSettingService::get($tenantId),
        ]);
    }

    public function prepare(Request $request): Response
    {
        Auth::requireCan('dashboard.view');
        $companyId = (int) $request->input('company_id');
        $month = (string) $request->input('month');
        if ($companyId <= 0 || !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            Session::flash('error', __('beyan.valid_month'));
            return Response::redirect('/app/beyanname');
        }
        try {
            $id = BeyannameService::prepareKdv(Auth::tenantId(), $companyId, $month, Auth::id());
            BeyannameService::package($id);
            Session::flash('success', __('beyan.prepared'));
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        return Response::redirect('/app/beyanname');
    }

    public function submit(Request $request, $id): Response
    {
        Auth::requireCan('dashboard.view');
        $r = BeyannameService::submit((int) $id);
        Session::flash($r['ok'] ? 'success' : 'error', $r['message'] . ($r['reference'] ? ' — ' . $r['reference'] : ''));
        return Response::redirect('/app/beyanname');
    }

    public function approve(Request $request, $id): Response
    {
        Auth::requireCan('dashboard.view');
        BeyannameService::approve((int) $id);
        Session::flash('success', __('beyan.approved'));
        return Response::redirect('/app/beyanname');
    }
}
