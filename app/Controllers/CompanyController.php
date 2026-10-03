<?php

declare(strict_types=1);

namespace Muh\Controllers;

use Muh\Core\Auth;
use Muh\Core\Controller;
use Muh\Core\DB;
use Muh\Core\Request;
use Muh\Core\Response;
use Muh\Core\Session;
use Muh\Models\Company;

/**
 * Client company management (Phase 2).
 */
final class CompanyController extends Controller
{
    public function index(Request $request): Response
    {
        Auth::requireCan('company.read');
        $paged = paginate(
            'SELECT * FROM companies WHERE tenant_id = :t AND deleted_at IS NULL ORDER BY id DESC',
            ['t' => Auth::tenantId()],
            25
        );
        $companies = $paged['items'];

        $stats = [];
        foreach ($companies as $c) {
            $stats[(int) $c['id']] = [
                'periods' => (int) DB::scalar(
                    'SELECT COUNT(*) FROM fiscal_periods WHERE company_id = :c AND deleted_at IS NULL',
                    ['c' => $c['id']]
                ),
                'invoices' => (int) DB::scalar(
                    'SELECT COUNT(*) FROM invoices WHERE company_id = :c AND deleted_at IS NULL',
                    ['c' => $c['id']]
                ),
                'balance' => (float) (DB::scalar(
                    'SELECT COALESCE(SUM(balance),0) FROM current_accounts WHERE company_id = :c AND deleted_at IS NULL',
                    ['c' => $c['id']]
                ) ?? 0),
            ];
        }

        return $this->view('app.companies.index', [
            'layout' => 'layouts.app',
            'companies' => $companies,
            'stats' => $stats,
            'page' => $paged['page'],
            'lastPage' => $paged['lastPage'],
            'total' => $paged['total'],
        ]);
    }

    public function show(Request $request, $id): Response
    {
        $id = (int) $id;
        Auth::requireCan('company.read');

        $company = DB::first(
            'SELECT * FROM companies WHERE id = :id AND tenant_id = :t AND deleted_at IS NULL',
            ['id' => $id, 't' => Auth::tenantId()]
        );
        if (!$company) {
            return Response::redirect('/app/companies');
        }

        $periods = DB::select(
            'SELECT * FROM fiscal_periods WHERE company_id = :c AND deleted_at IS NULL ORDER BY start_date DESC',
            ['c' => $id]
        );

        // Latest chart of accounts (for the current period, fallback to any).
        $periodId = $periods[0]['id'] ?? null;
        $accounts = [];
        if ($periodId) {
            $accounts = DB::select(
                'SELECT code, name, type, opening_debit, opening_credit FROM accounting_accounts
                  WHERE company_id = :c AND fiscal_period_id = :p
                  ORDER BY code ASC',
                ['c' => $id, 'p' => $periodId]
            );
        }

        $currentAccounts = DB::select(
            'SELECT * FROM current_accounts WHERE company_id = :c AND deleted_at IS NULL ORDER BY name LIMIT 50',
            ['c' => $id]
        );

        $branches = DB::select(
            'SELECT * FROM branches WHERE company_id = :c AND deleted_at IS NULL ORDER BY name',
            ['c' => $id]
        );

        return $this->view('app.companies.show', [
            'layout' => 'layouts.app',
            'company' => $company,
            'periods' => $periods,
            'accounts' => $accounts,
            'currentAccounts' => $currentAccounts,
            'branches' => $branches,
        ]);
    }

    public function create(Request $request): Response
    {
        Auth::requireCan('company.create');
        return $this->view('app.companies.create', ['layout' => 'layouts.app']);
    }

    public function store(Request $request): Response
    {
        Auth::requireCan('company.create');
        try {
            $id = (new \Muh\Services\CompanyService())->create($request->all());
        } catch (\Muh\Core\ValidationException $e) {
            Session::set('_form_errors', $e->errors);
            return Response::redirect('/app/companies/create');
        }
        Session::flash('success', __('app.company_created'));
        return Response::redirect('/app/companies/' . $id);
    }

    /** Serve the company logo image (public route, no auth — used by print/PDF/e-mail). */
    public function serveLogo(Request $request, $id): Response
    {
        $id = (int) $id;
        $company = DB::first('SELECT id, logo_path, tenant_id FROM companies WHERE id = :id AND deleted_at IS NULL', ['id' => $id]);
        if (!$company || empty($company['logo_path'])) {
            // Branded fallback monogram.
            $svg = $this->monogramSvg($company['name'] ?? 'Hesap360');
            return Response::make($svg, 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'public, max-age=3600']);
        }
        $abs = \Muh\Services\BrandingService::logoAbsolutePath($id, $company['logo_path']);
        if ($abs === null || !is_file($abs)) {
            $svg = $this->monogramSvg($company['name'] ?? 'Hesap360');
            return Response::make($svg, 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'public, max-age=3600']);
        }
        $mime = (function_exists('mime_content_type')) ? (mime_content_type($abs) ?: 'image/png') : 'image/png';
        return Response::make((string) file_get_contents($abs), 200, [
            'Content-Type' => $mime, 'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /** Upload a company logo (auth required). Stores into storage/documents/logos. */
    public function uploadLogo(Request $request, $id): Response
    {
        $id = (int) $id;
        Auth::requireCan('company.update');
        if (!$request->hasFile('logo') || !$request->file('logo')) {
            Session::flash('error', __('app.logo_required'));
            return Response::redirect('/app/companies/' . $id);
        }
        $f = $request->file('logo');
        $tmp = (string) ($f['tmp_name'] ?? '');
        $ext = strtolower(pathinfo($f['name'] ?? '', PATHINFO_EXTENSION));
        if ($tmp === '' || !in_array($ext, ['png', 'jpg', 'jpeg', 'svg', 'webp'], true)) {
            Session::flash('error', __('app.logo_invalid'));
            return Response::redirect('/app/companies/' . $id);
        }
        $dir = rtrim((string) config('app.filesystem.documents', dirname(__DIR__, 2) . '/storage/documents'), '/') . '/logos';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $filename = 'company_' . $id . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (!move_uploaded_file($tmp, $dir . '/' . $filename)) {
            Session::flash('error', __('app.logo_invalid'));
            return Response::redirect('/app/companies/' . $id);
        }
        // Remove any previous logo file.
        $old = DB::first('SELECT logo_path FROM companies WHERE id = :id', ['id' => $id]);
        if (!empty($old['logo_path'])) {
            $prev = \Muh\Services\BrandingService::logoAbsolutePath($id, $old['logo_path']);
            if ($prev && is_file($prev)) {
                @unlink($prev);
            }
        }
        DB::update('companies', ['logo_path' => $filename, 'updated_at' => now()], 'id = :id', ['id' => $id]);
        \Muh\Services\AuditLogService::record('company.logo.upload', 'company', 'companies', (string) $id, null, ['file' => $filename]);
        Session::flash('success', __('app.logo_uploaded'));
        return Response::redirect('/app/companies/' . $id);
    }

    private function monogramSvg(string $name): string
    {
        $initial = mb_strtoupper(mb_substr(trim($name) ?: 'H', 0, 1));
        return '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 120 120">'
            . '<rect width="120" height="120" rx="20" fill="#2b55e0"/>'
            . '<text x="60" y="78" font-size="52" font-family="Arial,Helvetica,sans-serif" font-weight="bold" fill="#ffffff" text-anchor="middle">' . e($initial) . '</text></svg>';
    }
}
