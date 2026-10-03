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
use Muh\Services\DocumentService;

final class DocumentController extends Controller
{
    public function index(Request $request): Response
    {
        Auth::requireCan('document.read');
        $tenantId = Auth::tenantId();
        $companies = DB::select('SELECT id, name FROM companies WHERE tenant_id = :t AND deleted_at IS NULL ORDER BY name', ['t' => $tenantId]);
        $companyId = (int) ($request->query('company_id') ?? ($companies[0]['id'] ?? 0));

        $documents = $companyId ? (new DocumentService())->list($companyId) : [];
        return $this->view('app.documents.index', [
            'layout' => 'layouts.app',
            'documents' => $documents,
            'companies' => $companies,
            'companyId' => $companyId,
        ]);
    }

    public function store(Request $request): Response
    {
        Auth::requireCan('document.create');
        try {
            (new DocumentService())->upload($request, $request->all());
        } catch (ValidationException $e) {
            Session::set('_form_errors', $e->errors);
            return Response::redirect('/app/documents?company_id=' . (int) $request->input('company_id'));
        }
        Session::flash('success', __('document.uploaded'));
        // Honor a safe return URL (e.g. back to invoice/cari detail).
        $redirect = (string) $request->input('redirect');
        if ($redirect !== '' && str_contains($redirect, '/app/')) {
            return Response::redirect($redirect);
        }
        return Response::redirect('/app/documents?company_id=' . (int) $request->input('company_id'));
    }

    public function download(Request $request, $id): Response
    {
        $id = (int) $id;
        Auth::requireCan('document.read');
        $service = new DocumentService();
        $doc = $service->find(Auth::tenantId(), $id);
        if (!$doc) {
            return Response::json(['error' => __('document.not_found')], 404);
        }
        $abs = $service->getStoragePath($doc['path']);
        if (!is_file($abs)) {
            return Response::json(['error' => __('document.not_found')], 404);
        }
        return Response::download($abs, $doc['original_name'], $doc['mime'] ?? 'application/octet-stream');
    }

    public function destroy(Request $request, $id): Response
    {
        $id = (int) $id;
        Auth::requireCan('document.delete');
        (new DocumentService())->delete(Auth::tenantId(), $id);
        Session::flash('success', __('document.deleted'));
        $redirect = (string) $request->input('redirect');
        if ($redirect !== '' && str_contains($redirect, '/app/')) {
            return Response::redirect($redirect);
        }
        return Response::redirect('/app/documents?company_id=' . (int) $request->input('company_id'));
    }
}
