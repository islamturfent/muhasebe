<?php

declare(strict_types=1);

namespace Muh\Controllers;

use Muh\Core\Auth;
use Muh\Core\Controller;
use Muh\Core\DB;
use Muh\Core\Request;
use Muh\Core\Response;
use Muh\Core\Session;
use Muh\Services\Import\XlsxReader;

/**
 * Excel / CSV içe aktarma (Cari ve Stok) — kolon eşleştirme + önizleme + doğrulama + hata raporu.
 */
final class ImportController extends Controller
{
    private const FIELD_ALIASES = [
        'code' => ['kod', 'code', 'kodu', 'hesap', 'stok', 'stokkodu', 'ürün', 'urun'],
        'name' => ['ad', 'adı', 'adi', 'isim', 'name', 'unvan', 'tanım', 'tanim'],
        'type' => ['tür', 'tur', 'tip', 'type', 'cari türü', 'cari turu'],
        'barcode' => ['barkod', 'barcode'],
        'tax_number' => ['vergi no', 'vergi', 'tax no', 'vkn', 'tc'],
        'phone' => ['telefon', 'tel', 'phone'],
        'email' => ['e-posta', 'eposta', 'email', 'posta'],
        'iban' => ['iban'],
        'balance' => ['bakiye', 'balance', 'açılış', 'acilis'],
        'risk_limit' => ['risk limiti', 'risk', 'limit'],
        'unit' => ['birim', 'unit'],
        'purchase_price' => ['alış fiyat', 'alis fiyat', 'purchase', 'maliyet'],
        'sale_price' => ['satış fiyat', 'satis fiyat', 'sale', 'fiyat'],
        'vat_rate' => ['kdv', 'kdv oranı', 'vat'],
        'stock_quantity' => ['stok miktarı', 'miktar', 'quantity', 'adet'],
        'critical_stock' => ['kritik stok', 'critical'],
        'description' => ['açıklama', 'aciklama', 'description', 'not'],
    ];

    private const MODULES = [
        'cari' => ['label' => 'import.module_cari', 'fields' => ['code', 'name', 'type', 'tax_number', 'phone', 'email', 'iban', 'balance', 'risk_limit']],
        'stok' => ['label' => 'import.module_stok', 'fields' => ['code', 'name', 'barcode', 'type', 'purchase_price', 'sale_price', 'vat_rate', 'stock_quantity', 'critical_stock', 'description', 'unit']],
    ];

    public function index(Request $request): Response
    {
        Auth::requireCan('import.run');
        $companies = DB::select('SELECT id, name FROM companies WHERE tenant_id = :t AND deleted_at IS NULL ORDER BY name', ['t' => Auth::tenantId()]);
        return $this->view('app.import.index', ['layout' => 'layouts.app', 'companies' => $companies]);
    }

    public function preview(Request $request): Response
    {
        Auth::requireCan('import.run');
        $module = $request->input('module');
        if (!isset(self::MODULES[$module])) {
            Session::flash('error', __('import.bad_module'));
            return Response::redirect('/app/import');
        }
        if (!$request->hasFile('file')) {
            Session::flash('error', __('import.file_required'));
            return Response::redirect('/app/import');
        }
        $file = $request->file('file');
        $tmp = (string) ($file['tmp_name'] ?? '');
        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'csv'], true)) {
            Session::flash('error', __('import.bad_format'));
            return Response::redirect('/app/import');
        }

        $rows = [];
        if ($ext === 'csv') {
            $rows = self::readCsv($tmp);
        } else {
            $rows = XlsxReader::read($tmp);
        }
        if (empty($rows)) {
            Session::flash('error', __('import.empty_file'));
            return Response::redirect('/app/import');
        }

        $companyId = (int) $request->input('company_id');
        // Store parsed data in session for the run step.
        Session::set('_import', [
            'module' => $module,
            'company_id' => $companyId,
            'rows' => $rows,
            'filename' => $file['name'] ?? '',
            'has_header' => $request->input('has_header') ? 1 : 0,
        ]);

        $fields = self::MODULES[$module]['fields'];
        $maxCols = max(array_map('count', $rows));
        // Auto-detect mapping (best effort by header aliases).
        $mapping = [];
        for ($i = 0; $i < $maxCols; $i++) {
            $mapping[$i] = '';
        }
        $headerRow = null;
        if ($request->input('has_header')) {
            $headerRow = $rows[0];
            foreach ($headerRow as $i => $h) {
                $mapping[$i] = self::guessField((string) $h);
            }
        }

        $companies = DB::select('SELECT id, name FROM companies WHERE tenant_id = :t AND deleted_at IS NULL ORDER BY name', ['t' => Auth::tenantId()]);
        return $this->view('app.import.preview', [
            'layout' => 'layouts.app',
            'module' => $module, 'fields' => $fields, 'rows' => $rows,
            'mapping' => $mapping, 'maxCols' => $maxCols, 'companies' => $companies,
            'companyId' => $companyId, 'filename' => $file['name'] ?? '',
            'hasHeader' => (bool) ($request->input('has_header')),
        ]);
    }

    public function run(Request $request): Response
    {
        Auth::requireCan('import.run');
        $data = Session::get('_import');
        if (!$data) {
            return Response::redirect('/app/import');
        }
        $module = $data['module'];
        if (!isset(self::MODULES[$module])) {
            return Response::redirect('/app/import');
        }
        $fields = self::MODULES[$module]['fields'];
        $rows = $data['rows'];
        $companyId = (int) $data['company_id'];
        $tenantId = Auth::tenantId();

        // Build mapping: column index -> field (inputs named map_col_<i> value=field).
        $mapping = [];
        $maxCols = max(array_map('count', $rows));
        for ($i = 0; $i < $maxCols; $i++) {
            $val = $request->input('map_col_' . $i);
            if ($val !== '' && $val !== null && in_array($val, $fields, true)) {
                $mapping[$i] = $val;
            }
        }

        $start = !empty($data['has_header']) ? 1 : 0;
        $created = 0;
        $errors = [];
        $dupes = [];

        DB::transaction(function () use (&$created, &$errors, &$dupes, $module, $rows, $mapping, $start, $companyId, $tenantId) {
            for ($idx = $start; $idx < count($rows); $idx++) {
                $row = $rows[$idx];
                $item = [];
                foreach ($mapping as $col => $field) {
                    $item[$field] = trim((string) ($row[$col] ?? ''));
                }
                if ($module === 'cari') {
                    [$ok, $err] = $this->importCari($tenantId, $companyId, $item);
                } else {
                    [$ok, $err] = $this->importStok($tenantId, $companyId, $item);
                }
                if ($ok) {
                    $created++;
                } else {
                    $errors[] = ['row' => $idx + 1, 'message' => $err];
                }
            }
        });

        $errorCsvUrl = null;
        if ($errors) {
            $sessionKey = '__import_errors_' . Auth::id();
            Session::set($sessionKey, $errors);
            $errorCsvUrl = url('/app/import/errors');
        }
        Session::forget('_import');

        return $this->view('app.import.result', [
            'layout' => 'layouts.app',
            'module' => $module,
            'created' => $created,
            'total' => count($rows) - $start,
            'errors' => $errors,
            'errorCsvUrl' => $errorCsvUrl,
        ]);
    }

    public function errors(Request $request): Response
    {
        Auth::requireCan('import.export');
        $sessionKey = '__import_errors_' . Auth::id();
        $errors = Session::get($sessionKey, []);
        $headers = [__('import.row'), __('import.message')];
        $out = array_map(fn ($e) => [$e['row'], $e['message']], $errors);
        return \Muh\Services\ReportExportService::csv($headers, $out, 'import-errors.csv');
    }

    /** @return array{0:bool,1:string} */
    private function importCari(int $tenantId, int $companyId, array $item): array
    {
        if (($item['code'] ?? '') === '' || ($item['name'] ?? '') === '') {
            return [false, __('import.required', ['fields' => 'Kod, Ad'])];
        }
        $exists = DB::first('SELECT id FROM current_accounts WHERE company_id = :c AND code = :code', ['c' => $companyId, 'code' => $item['code']]);
        if ($exists) {
            return [false, __('import.duplicate', ['code' => $item['code']])];
        }
        $type = in_array($item['type'] ?? '', ['customer', 'supplier', 'both'], true) ? $item['type'] : 'customer';
        DB::insert('current_accounts', [
            'tenant_id' => $tenantId, 'company_id' => $companyId,
            'code' => $item['code'], 'name' => $item['name'], 'type' => $type,
            'tax_number' => $item['tax_number'] ?? null, 'email' => $item['email'] ?? null,
            'phone' => $item['phone'] ?? null, 'iban' => $item['iban'] ?? null,
            'risk_limit' => (float) ($item['risk_limit'] ?? 0),
            'balance' => (float) ($item['balance'] ?? 0),
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        return [true, ''];
    }

    /** @return array{0:bool,1:string} */
    private function importStok(int $tenantId, int $companyId, array $item): array
    {
        if (($item['code'] ?? '') === '' || ($item['name'] ?? '') === '') {
            return [false, __('import.required', ['fields' => 'Kod, Ad'])];
        }
        $exists = DB::first('SELECT id FROM products WHERE company_id = :c AND code = :code', ['c' => $companyId, 'code' => $item['code']]);
        if ($exists) {
            return [false, __('import.duplicate', ['code' => $item['code']])];
        }
        $type = ($item['type'] ?? '') === 'service' ? 'service' : 'product';
        DB::insert('products', [
            'tenant_id' => $tenantId, 'company_id' => $companyId,
            'code' => $item['code'], 'name' => $item['name'],
            'barcode' => $item['barcode'] ?? null, 'type' => $type,
            'unit_id' => null,
            'purchase_price' => (float) ($item['purchase_price'] ?? 0),
            'sale_price' => (float) ($item['sale_price'] ?? 0),
            'vat_rate' => (float) ($item['vat_rate'] ?? 20),
            'stock_quantity' => (float) ($item['stock_quantity'] ?? 0),
            'critical_stock' => (float) ($item['critical_stock'] ?? 0),
            'description' => $item['description'] ?? null,
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        return [true, ''];
    }

    private function guessField(string $header): string
    {
        foreach (self::FIELD_ALIASES as $field => $aliases) {
            foreach ($aliases as $a) {
                if (mb_strtolower(trim($header)) === mb_strtolower($a)) {
                    return $field;
                }
            }
        }
        return '';
    }

    /** @return array<int, array<int, string>> */
    private static function readCsv(string $path): array
    {
        $rows = [];
        $handle = @fopen($path, 'r');
        if (!$handle) {
            return $rows;
        }
        while (($line = fgetcsv($handle, 0, ';', '"')) !== false) {
            foreach ($line as &$cell) {
                $cell = trim((string) $cell, "\"\xEF\xBB\xBF");
            }
            if (implode('', $line) === '') {
                continue;
            }
            $rows[] = $line;
        }
        fclose($handle);
        return $rows;
    }
}
