<?php

declare(strict_types=1);

namespace Muh\Controllers;

use Muh\Core\Auth;
use Muh\Core\DB;
use Muh\Core\Controller;
use Muh\Core\Request;
use Muh\Core\Response;
use Muh\Core\Session;
use Muh\Services\GIBExpressService;

/**
 * GİB e-Belge Express (Faz 2): GİB'ten e-Fatura/e-Arşiv çek + fişe çevir.
 */
final class ExpressController extends Controller
{
    public function index(Request $request): Response
    {
        Auth::requireCan('dashboard.view');
        $tenantId = Auth::tenantId();
        $companies = DB::select('SELECT id, name FROM companies WHERE tenant_id = :t AND deleted_at IS NULL ORDER BY name', ['t' => $tenantId]);
        $companyId = (int) ($request->query('company_id') ?: ($companies[0]['id'] ?? 0));
        $docs = $companyId ? GIBExpressService::list($tenantId, $companyId) : [];
        return $this->view('app.express.index', [
            'layout' => 'layouts.app',
            'companies' => $companies,
            'companyId' => $companyId,
            'docs' => $docs,
        ]);
    }

    public function pull(Request $request): Response
    {
        Auth::requireCan('dashboard.view');
        $companyId = (int) $request->input('company_id');
        $docType = (string) $request->input('doc_type', 'e-fatura');
        $from = (string) $request->input('from');
        $to = (string) $request->input('to');
        try {
            $n = GIBExpressService::pull(Auth::tenantId(), $companyId, $docType, $from, $to);
            Session::flash('success', __('express.pulled', ['n' => $n]));
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        return Response::redirect('/app/express?company_id=' . $companyId);
    }

    public function convert(Request $request, $id): Response
    {
        Auth::requireCan('dashboard.view');
        try {
            $entryId = GIBExpressService::createJournal(Auth::tenantId(), (int) $id, [
                'inventory' => (string) $request->input('inventory', '153'),
                'vat' => (string) $request->input('vat', '191'),
                'supplier' => (string) $request->input('supplier', '320'),
            ]);
            Session::flash('success', __('express.converted', ['no' => $entryId]));
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        return Response::redirect('/app/express?company_id=' . (int) $request->input('company_id'));
    }
}
