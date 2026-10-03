<?php

declare(strict_types=1);

namespace Muh\Services;

use Muh\Core\Auth;
use Muh\Core\Config;
use Muh\Core\DB;
use Muh\Core\Request;

/**
 * File & document management (Phase 11).
 * Files are stored outside the web root (storage/documents) and served via
 * authenticated download endpoints. Only metadata + path are stored in DB.
 */
final class DocumentService
{
    private const CATEGORIES = ['invoice', 'receipt', 'contract', 'bank_statement', 'other'];

    public function list(int $companyId): array
    {
        return DB::select(
            'SELECT d.*, u.name AS uploaded_by_name,
                    ca.name AS account_name, i.number AS invoice_number
               FROM documents d
               LEFT JOIN users u ON u.id = d.uploaded_by
               LEFT JOIN current_accounts ca ON ca.id = d.current_account_id
               LEFT JOIN invoices i ON i.id = d.invoice_id
              WHERE d.tenant_id = :t AND d.company_id = :c AND d.deleted_at IS NULL
              ORDER BY d.id DESC',
            ['t' => Auth::tenantId(), 'c' => $companyId]
        );
    }

    /** Documents linked to an invoice. */
    public function forInvoice(int $tenantId, int $invoiceId): array
    {
        return DB::select(
            'SELECT * FROM documents WHERE tenant_id = :t AND invoice_id = :i AND deleted_at IS NULL ORDER BY id DESC',
            ['t' => $tenantId, 'i' => $invoiceId]
        );
    }

    /** Documents linked to a current account (cari). */
    public function forCurrentAccount(int $tenantId, int $currentAccountId): array
    {
        return DB::select(
            'SELECT * FROM documents WHERE tenant_id = :t AND current_account_id = :a AND deleted_at IS NULL ORDER BY id DESC',
            ['t' => $tenantId, 'a' => $currentAccountId]
        );
    }

    public function find(int $tenantId, int $id): ?array
    {
        return DB::first('SELECT * FROM documents WHERE id = :id AND tenant_id = :t AND deleted_at IS NULL', ['id' => $id, 't' => $tenantId]);
    }

    public function upload(?Request $request, array $data): array
    {
        $tenantId = Auth::tenantId();
        $companyId = (int) ($data['company_id'] ?? 0);

        // Verify company belongs to tenant.
        if (!DB::first('SELECT id FROM companies WHERE id = :id AND tenant_id = :t AND deleted_at IS NULL', ['id' => $companyId, 't' => $tenantId])) {
            throw new \Muh\Core\ValidationException(['company_id' => __('document.invalid_company')]);
        }

        $file = $request ? $request->file('file') : null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \Muh\Core\ValidationException(['file' => __('document.file_required')]);
        }

        $maxBytes = 25 * 1024 * 1024; // 25MB
        if ($file['size'] > $maxBytes) {
            throw new \Muh\Core\ValidationException(['file' => __('document.file_too_large')]);
        }

        // Ext + storage dir
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $slug = str_slug(pathinfo($file['name'], PATHINFO_FILENAME)) ?: 'doc';
        $uuid = random_uuid();
        $relPath = 'tenant-' . $tenantId . '/company-' . $companyId . '/' . $uuid . '.' . $ext;
        $base = Config::get('app.filesystem.documents', dirname(__DIR__, 2) . '/storage/documents');
        $absDir = $base . '/tenant-' . $tenantId . '/company-' . $companyId;
        if (!is_dir($absDir)) {
            @mkdir($absDir, 0777, true);
        }
        $absPath = $base . '/' . $relPath;
        if (!move_uploaded_file($file['tmp_name'], $absPath)) {
            throw new \Muh\Core\ValidationException(['file' => __('document.upload_failed')]);
        }

        $id = (int) DB::insert('documents', [
            'tenant_id' => $tenantId,
            'company_id' => $companyId,
            'current_account_id' => !empty($data['current_account_id']) ? (int) $data['current_account_id'] : null,
            'invoice_id' => !empty($data['invoice_id']) ? (int) $data['invoice_id'] : null,
            'uploaded_by' => Auth::id(),
            'name' => $file['name'],
            'original_name' => $file['name'],
            'path' => $relPath,
            'mime' => $file['type'] ?? 'application/octet-stream',
            'size' => (int) $file['size'],
            'category' => in_array($data['category'] ?? null, self::CATEGORIES, true) ? $data['category'] : 'other',
            'notes' => $data['notes'] ?? null,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return ['id' => $id, 'name' => $file['name'], 'size' => (int) $file['size']];
    }

    public function getStoragePath(string $relPath): string
    {
        $base = Config::get('app.filesystem.documents', dirname(__DIR__, 2) . '/storage/documents');
        return $base . '/' . ltrim($relPath, '/');
    }

    public function delete(int $tenantId, int $id): bool
    {
        $doc = $this->find($tenantId, $id);
        if (!$doc) {
            return false;
        }
        $abs = $this->getStoragePath($doc['path']);
        if (is_file($abs)) {
            @unlink($abs);
        }
        DB::update('documents', ['deleted_at' => now()], 'id = :id', ['id' => $id]);
        AuditLogService::record('document.delete', 'document', 'documents', (string) $id, $doc['path'], null, (int) $doc['company_id'], $tenantId);
        return true;
    }
}
