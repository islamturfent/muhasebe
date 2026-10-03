<?php

declare(strict_types=1);

namespace Muh\Services;

use Muh\Core\Auth;
use Muh\Core\DB;
use Muh\Core\Hash;
use Muh\Core\ValidationException;
use Muh\Core\Request;

/**
 * Import from CSV/Excel-exported files with column mapping, preview and an
 * error report (Phase 14 / optional import-export). CSV is used because it
 * opens natively in Excel and requires no third-party library.
 */
final class ImportService
{
    public const CARI_COLUMNS = ['code', 'name', 'type', 'tax_number', 'email', 'phone', 'opening_balance'];
    public const STOCK_COLUMNS = ['code', 'name', 'type', 'barcode', 'purchase_price', 'sale_price', 'vat_rate', 'critical_stock', 'opening_stock'];
    public const ACCOUNTING_COLUMNS = ['code', 'name', 'type', 'group', 'is_header', 'opening_debit', 'opening_credit'];

    /** Parse an uploaded CSV file into rows keyed by header. */
    public function parseCsv(?array $file): array
    {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new ValidationException(['file' => __('import.file_required')]);
        }
        $content = file_get_contents($file['tmp_name']);
        // Strip UTF-8 BOM if present.
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;

        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
        if (empty($lines)) {
            throw new ValidationException(['file' => __('import.empty_file')]);
        }

        // Detect delimiter: semicolon or comma.
        $delimiter = substr_count($lines[0], ';') >= substr_count($lines[0], ',') ? ';' : ',';
        $headers = str_getcsv($lines[0], $delimiter);
        $headers = array_map(fn ($h) => trim((string) $h), $headers);

        $rows = [];
        foreach (array_slice($lines, 1) as $line) {
            if (trim($line) === '') {
                continue;
            }
            $vals = str_getcsv($line, $delimiter);
            $row = [];
            foreach ($headers as $i => $h) {
                $row[$h] = $vals[$i] ?? '';
            }
            $rows[] = $row;
        }

        if (empty($rows)) {
            throw new ValidationException(['file' => __('import.empty_file')]);
        }
        return ['headers' => $headers, 'rows' => $rows];
    }

    /** Auto-detect a column mapping from headers for a given import type. */
    public function autoMap(array $headers, string $type): array
    {
        $columns = match ($type) {
            'cari' => self::CARI_COLUMNS,
            'accounting' => self::ACCOUNTING_COLUMNS,
            default => self::STOCK_COLUMNS,
        };
        $aliases = [
            'code' => ['kodu', 'code', 'kod'],
            'name' => ['ad', 'unvan', 'adi', 'name', 'name', 'title'],
            'type' => ['tip', 'type', 'tur'],
            'tax_number' => ['vkn', 'vergi', 'vergi no', 'tax', 'tax number', 'tax_number'],
            'email' => ['e-posta', 'email', 'mail'],
            'phone' => ['telefon', 'phone', 'tel'],
            'opening_balance' => ['acilis', 'bakiye', 'opening', 'opening_balance'],
            'barcode' => ['barkod', 'barcode'],
            'purchase_price' => ['alis fiyati', 'alis', 'purchase_price'],
            'sale_price' => ['satis fiyati', 'satis', 'sale_price'],
            'vat_rate' => ['kdv', 'kdv orani', 'vat', 'vat_rate'],
            'critical_stock' => ['kritik', 'critical', 'critical_stock'],
            'opening_stock' => ['acilis stok', 'stok', 'opening_stock'],
            'group' => ['grup', 'grubu', 'group'],
            'is_header' => ['is_header', 'ust kalem', 'ana hesap', 'header'],
            'opening_debit' => ['acilis borc', 'opening_debit', 'borc'],
            'opening_credit' => ['acilis alacak', 'opening_credit', 'alacak'],
        ];

        $map = [];
        foreach ($headers as $h) {
            $key = strtolower(trim((string) $h));
            foreach ($columns as $col) {
                if (array_key_exists($col, $map)) {
                    continue;
                }
                if ($key === $col || in_array($key, $aliases[$col] ?? [], true)) {
                    $map[$col] = $h;
                    break;
                }
            }
        }
        return $map;
    }

    /** Import current accounts from parsed rows. @return array{imported:int, errors:array} */
    public function importCurrentAccounts(array $rows, array $map, int $tenantId, int $companyId): array
    {
        $imported = 0;
        $errors = [];
        foreach ($rows as $idx => $row) {
            $lineNo = $idx + 2;
            $data = [
                'company_id' => $companyId,
                'code' => $row[$map['code']] ?? '',
                'name' => $row[$map['name']] ?? '',
                'type' => in_array($row[$map['type']] ?? '', ['customer', 'supplier', 'both'], true) ? $row[$map['type']] : 'customer',
                'tax_number' => $row[$map['tax_number']] ?? null,
                'email' => $row[$map['email']] ?? null,
                'phone' => $row[$map['phone']] ?? null,
                'opening_balance' => ($row[$map['opening_balance']] ?? ''),
            ];

            try {
                (new CurrentAccountService())->create($data);
                $imported++;
            } catch (ValidationException $e) {
                $msg = reset($e->errors) ?: __('import.invalid_row');
                $errors[] = ['line' => $lineNo, 'code' => $data['code'], 'name' => $data['name'], 'message' => $msg];
            }
        }
        AuditLogService::record('import.current_accounts', 'import', 'current_accounts', null, null, ['imported' => $imported, 'errors' => count($errors)], $companyId, $tenantId);
        return ['imported' => $imported, 'errors' => $errors];
    }

    /** Import products from parsed rows. @return array{imported:int, errors:array} */
    public function importProducts(array $rows, array $map, int $tenantId, int $companyId): array
    {
        $imported = 0;
        $errors = [];
        $svc = new InventoryService();
        foreach ($rows as $idx => $row) {
            $lineNo = $idx + 2;
            $type = ($row[$map['type']] ?? '') === 'service' ? 'service' : 'product';
            $data = [
                'company_id' => $companyId,
                'code' => $row[$map['code']] ?? '',
                'name' => $row[$map['name']] ?? '',
                'type' => $type,
                'barcode' => $row[$map['barcode']] ?? null,
                'purchase_price' => $row[$map['purchase_price']] ?? '',
                'sale_price' => $row[$map['sale_price']] ?? '',
                'vat_rate' => $row[$map['vat_rate']] ?? 0,
                'critical_stock' => $row[$map['critical_stock']] ?? 0,
                'opening_stock' => $row[$map['opening_stock']] ?? 0,
            ];
            try {
                $svc->createProduct($data);
                $imported++;
            } catch (ValidationException $e) {
                $msg = reset($e->errors) ?: __('import.invalid_row');
                $errors[] = ['line' => $lineNo, 'code' => $data['code'], 'name' => $data['name'], 'message' => $msg];
            }
        }
        AuditLogService::record('import.products', 'import', 'products', null, null, ['imported' => $imported, 'errors' => count($errors)], $companyId, $tenantId);
        return ['imported' => $imported, 'errors' => $errors];
    }

    /**
     * Import hesap planı (chart of accounts) into the company's current fiscal
     * period from parsed rows. @return array{imported:int, errors:array}
     */
    public function importAccountingAccounts(array $rows, array $map, int $tenantId, int $companyId): array
    {
        $imported = 0;
        $errors = [];
        $periodId = (int) DB::scalar(
            'SELECT id FROM fiscal_periods WHERE company_id = :c AND is_current = 1 AND deleted_at IS NULL ORDER BY id LIMIT 1',
            ['c' => $companyId]
        ) ?: (int) DB::scalar('SELECT id FROM fiscal_periods WHERE company_id = :c ORDER BY id LIMIT 1', ['c' => $companyId]);

        foreach ($rows as $idx => $row) {
            $lineNo = $idx + 2;
            $code = trim((string) ($row[$map['code']] ?? ''));
            $name = trim((string) ($row[$map['name']] ?? ''));
            $type = (string) ($row[$map['type']] ?? 'asset');
            $type = in_array($type, ['asset', 'liability', 'equity', 'income', 'expense'], true) ? $type : 'asset';
            $group = trim((string) ($row[$map['group']] ?? '')) ?: substr($code, 0, 1);
            $isHeader = in_array(strtolower(trim((string) ($row[$map['is_header']] ?? ''))), ['1', 'true', 'yes', 'header'], true) ? 1 : 0;
            $opDebit = (float) ($row[$map['opening_debit']] ?? 0);
            $opCredit = (float) ($row[$map['opening_credit']] ?? 0);

            if ($code === '' || $name === '') {
                $errors[] = ['line' => $lineNo, 'code' => $code, 'name' => $name, 'message' => __('import.invalid_row')];
                continue;
            }
            $exists = DB::first(
                'SELECT id FROM accounting_accounts WHERE company_id = :c AND fiscal_period_id = :p AND code = :code',
                ['c' => $companyId, 'p' => $periodId, 'code' => $code]
            );
            if ($exists) {
                $errors[] = ['line' => $lineNo, 'code' => $code, 'name' => $name, 'message' => __('import.dup_code')];
                continue;
            }
            DB::insert('accounting_accounts', [
                'tenant_id' => $tenantId, 'company_id' => $companyId, 'fiscal_period_id' => $periodId,
                'code' => $code, 'name' => $name, 'type' => $type, 'subtype' => null,
                'group' => $group, 'is_header' => $isHeader, 'currency' => 'TRY',
                'opening_debit' => $opDebit,
                'opening_credit' => $opCredit,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $imported++;
        }
        AuditLogService::record('import.accounting', 'import', 'accounting_accounts', null, null, ['imported' => $imported, 'errors' => count($errors)], $companyId, $tenantId);
        return ['imported' => $imported, 'errors' => $errors];
    }
}
