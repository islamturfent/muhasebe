<?php

declare(strict_types=1);

namespace Muh\Controllers;

use Muh\Core\Auth;
use Muh\Core\DB;
use Muh\Core\Session;
use Muh\Core\Controller;
use Muh\Core\Request;
use Muh\Core\Response;
use Muh\Core\ValidationException;
use Muh\Services\ImportService;

/**
 * Import / Export (optional feature). CSV-based import of current accounts and
 * stock cards with column mapping, preview and an error report.
 */
final class ImportController extends Controller
{
    public function index(Request $request): Response
    {
        Auth::requireCan('import.run');
        $tenantId = Auth::tenantId();
        $companies = DB::select('SELECT id, name FROM companies WHERE tenant_id = :t AND deleted_at IS NULL ORDER BY name', ['t' => $tenantId]);
        return $this->view('app.import.index', [
            'layout' => 'layouts.app',
            'companies' => $companies,
        ]);
    }

    private static function normalizeType(string $raw): string
    {
        return in_array($raw, ['cari', 'stock', 'accounting'], true) ? $raw : 'cari';
    }

    private static function columnsFor(string $type): array
    {
        return match ($type) {
            'cari' => ImportService::CARI_COLUMNS,
            'accounting' => ImportService::ACCOUNTING_COLUMNS,
            default => ImportService::STOCK_COLUMNS,
        };
    }

    public function upload(Request $request): Response
    {
        Auth::requireCan('import.run');
        $type = self::normalizeType((string) $request->input('type'));
        $companyId = (int) $request->input('company_id');
        $service = new ImportService();

        try {
            $data = $service->parseCsv($request->file('file'));
        } catch (ValidationException $e) {
            Session::set('_form_errors', $e->errors);
            return Response::redirect('/app/import');
        }

        $map = $service->autoMap($data['headers'], $type);
        $columns = self::columnsFor($type);

        // Keep the parsed data in session for the run step.
        Session::set('import_data', ['type' => $type, 'company_id' => $companyId, 'rows' => $data['rows'], 'map' => $map]);

        return $this->view('app.import.preview', [
            'layout' => 'layouts.app',
            'type' => $type,
            'companyId' => $companyId,
            'headers' => $data['headers'],
            'rows' => array_slice($data['rows'], 0, 8),
            'rowTotal' => count($data['rows']),
            'map' => $map,
            'columns' => $columns,
        ]);
    }

    public function run(Request $request): Response
    {
        Auth::requireCan('import.run');
        $importData = Session::get('import_data');
        if (!$importData) {
            return Response::redirect('/app/import');
        }
        $type = self::normalizeType((string) ($importData['type'] ?? 'cari'));
        $companyId = (int) $importData['company_id'];

        // Allow the user to adjust mapping on the run screen before committing.
        $map = $request->input('map', $importData['map'] ?? []);
        $rows = $importData['rows'] ?? [];

        $service = new ImportService();
        $result = match ($type) {
            'stock' => $service->importProducts($rows, $map, Auth::tenantId(), $companyId),
            'accounting' => $service->importAccountingAccounts($rows, $map, Auth::tenantId(), $companyId),
            default => $service->importCurrentAccounts($rows, $map, Auth::tenantId(), $companyId),
        };

        Session::forget('import_data');
        return $this->view('app.import.result', [
            'layout' => 'layouts.app',
            'type' => $type,
            'imported' => $result['imported'],
            'errors' => $result['errors'],
        ]);
    }

    public function template(Request $request, $type = 'cari'): Response
    {
        $type = self::normalizeType((string) $type);
        if ($type === 'stock') {
            $headers = ImportService::STOCK_COLUMNS;
            $content = implode(';', $headers) . "\n" . 'STK-001;Örnek Ürün;product;8690;450;250;20;5;0' . "\n";
        } elseif ($type === 'accounting') {
            $headers = ImportService::ACCOUNTING_COLUMNS;
            $content = implode(';', $headers) . "\n" . '100;Kasa;asset;1;0;10000;0' . "\n" . '120;Alıcılar;asset;1;0;0;0' . "\n";
        } else {
            $headers = ImportService::CARI_COLUMNS;
            $content = implode(';', $headers) . "\n" . 'CARI-001;Örnek;customer;1234567890;ornek@firma.com;+90;1000' . "\n";
        }
        return Response::make($content, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="muh-' . $type . '-template.csv"',
        ]);
    }
}
