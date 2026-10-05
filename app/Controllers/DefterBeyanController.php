<?php

declare(strict_types=1);

namespace Muh\Controllers;

use Muh\Core\Auth;
use Muh\Core\DB;
use Muh\Core\Controller;
use Muh\Core\Request;
use Muh\Core\Response;
use Muh\Core\Session;
use Muh\Services\DefterBeyanService;

/**
 * Defter-Beyan (Faz 3): işletme defteri kayıtları + e-SMM — GİB'e gönderim
 * ve geçmiş CSV içe aktarım.
 */
final class DefterBeyanController extends Controller
{
    public function index(Request $request): Response
    {
        Auth::requireCan('dashboard.view');
        $tenantId = Auth::tenantId();
        $companies = DB::select('SELECT id, name FROM companies WHERE tenant_id = :t AND deleted_at IS NULL ORDER BY name', ['t' => $tenantId]);
        $companyId = (int) ($request->query('company_id') ?: ($companies[0]['id'] ?? 0));
        $records = $companyId ? DefterBeyanService::list($tenantId, $companyId) : [];
        return $this->view('app.defter-beyan.index', [
            'layout' => 'layouts.app',
            'companies' => $companies,
            'companyId' => $companyId,
            'records' => $records,
        ]);
    }

    public function store(Request $request): Response
    {
        Auth::requireCan('dashboard.view');
        $companyId = (int) $request->input('company_id');
        DefterBeyanService::addRecord(
            Auth::tenantId(),
            $companyId,
            (string) $request->input('record_date'),
            (string) $request->input('doc_type', 'diger'),
            (string) $request->input('description'),
            (float) $request->input('amount'),
            (float) $request->input('vat'),
            (string) $request->input('doc_number') ?: null
        );
        Session::flash('success', __('defter_beyan.added'));
        return Response::redirect('/app/defter-beyan?company_id=' . $companyId);
    }

    public function import(Request $request): Response
    {
        Auth::requireCan('dashboard.view');
        $companyId = (int) $request->input('company_id');
        try {
            $n = DefterBeyanService::importCsv(Auth::tenantId(), $companyId, (string) $request->input('csv'));
            Session::flash('success', __('defter_beyan.imported', ['n' => $n]));
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        return Response::redirect('/app/defter-beyan?company_id=' . $companyId);
    }

    public function submit(Request $request, $id): Response
    {
        Auth::requireCan('dashboard.view');
        $r = DefterBeyanService::submitRecord((int) $id, Auth::tenantId());
        Session::flash($r['ok'] ? 'success' : 'error', $r['message'] . ($r['reference'] ? ' — ' . $r['reference'] : ''));
        return Response::redirect('/app/defter-beyan?company_id=' . (int) $request->input('company_id'));
    }
}
