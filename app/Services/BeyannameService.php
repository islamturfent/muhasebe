<?php

declare(strict_types=1);

namespace Muh\Services;

use Muh\Core\Auth;
use Muh\Core\DB;
use Muh\Services\Beyan\BeyanGateway;
use Muh\Services\Beyan\SimulatedBeyanGateway;
use Muh\Services\Beyan\RestBeyanGateway;

final class BeyannameService
{
    public static function computeKdv(int $tenantId, int $companyId, int $periodId): array
    {
        $rows = DB::select(
            "SELECT i.type, SUM(ii.line_total) AS net, SUM(ii.tax) AS vat,
                    SUM(COALESCE(ii.withholding,0)) AS withholding
               FROM invoice_items ii
               JOIN invoices i ON i.id = ii.invoice_id
              WHERE i.company_id = :c AND i.fiscal_period_id = :p
                AND i.tenant_id = :t AND i.deleted_at IS NULL AND i.status = 'posted'
              GROUP BY i.type",
            ['c' => $companyId, 'p' => $periodId, 't' => $tenantId]
        );
        $sales = null; $purch = null;
        foreach ($rows as $r) {
            if ($r['type'] === 'sales') { $sales = $r; }
            elseif ($r['type'] === 'purchase') { $purch = $r; }
        }
        $outBase = (float) ($sales['net'] ?? 0);
        $outVat = (float) ($sales['vat'] ?? 0);
        $outWithholding = (float) ($sales['withholding'] ?? 0);
        $inBase = (float) ($purch['net'] ?? 0);
        $inVat = (float) ($purch['vat'] ?? 0);
        $inWithholding = (float) ($purch['withholding'] ?? 0);
        $payable = $outVat - $inVat;
        $refund = $payable < 0 ? abs($payable) : 0.0;
        if ($payable < 0) { $payable = 0.0; }
        return compact('outBase', 'outVat', 'outWithholding', 'inBase', 'inVat', 'inWithholding', 'payable', 'refund');
    }

    /** KDV-1 hesabı, aya göre (fatura tarihi). */
    public static function computeKdvMonth(int $tenantId, int $companyId, string $month): array
    {
        $start = $month . '-01';
        $end = date('Y-m-t', strtotime($start));
        $rows = DB::select(
            "SELECT i.type, SUM(ii.line_total) AS net, SUM(ii.tax) AS vat,
                    SUM(COALESCE(ii.withholding,0)) AS withholding
               FROM invoice_items ii
               JOIN invoices i ON i.id = ii.invoice_id
              WHERE i.company_id = :c AND i.tenant_id = :t AND i.deleted_at IS NULL
                AND i.status = 'posted' AND i.date BETWEEN :s AND :e
              GROUP BY i.type",
            ['c' => $companyId, 't' => $tenantId, 's' => $start, 'e' => $end]
        );
        $sales = null; $purch = null;
        foreach ($rows as $r) {
            if ($r['type'] === 'sales') { $sales = $r; }
            elseif ($r['type'] === 'purchase') { $purch = $r; }
        }
        $outBase = (float) ($sales['net'] ?? 0);
        $outVat = (float) ($sales['vat'] ?? 0);
        $outWithholding = (float) ($sales['withholding'] ?? 0);
        $inBase = (float) ($purch['net'] ?? 0);
        $inVat = (float) ($purch['vat'] ?? 0);
        $inWithholding = (float) ($purch['withholding'] ?? 0);
        $payable = $outVat - $inVat;
        $refund = $payable < 0 ? abs($payable) : 0.0;
        if ($payable < 0) { $payable = 0.0; }
        return compact('outBase', 'outVat', 'outWithholding', 'inBase', 'inVat', 'inWithholding', 'payable', 'refund');
    }

    public static function prepareKdv(int $tenantId, int $companyId, string $month, ?int $userId = null): int
    {
        $figures = static::computeKdvMonth($tenantId, $companyId, $month);
        $company = DB::first('SELECT id, name, tax_number, tax_office, tax_office_code FROM companies WHERE id = :id AND tenant_id = :t', ['id' => $companyId, 't' => $tenantId]);
        if (!$company) {
            throw new \InvalidArgumentException('Firma bulunamadı');
        }
        $payload = [
            'schema_version' => 'kdv-1/2026.1',
            'type' => 'kdv',
            'period' => $month,
            'tax_number' => $company['tax_number'] ?? '',
            'tax_office' => $company['tax_office'] ?? '',
            'tax_office_code' => $company['tax_office_code'] ?? '',
            'figures' => array_map(fn ($v) => round((float) $v, 2), $figures),
            'package' => ['prepared_by' => $userId ?? Auth::id(), 'prepared_at' => now()],
        ];
        return (int) DB::insert('beyannameler', [
            'tenant_id' => $tenantId,
            'company_id' => $companyId,
            'type' => 'kdv',
            'period' => $month,
            'status' => 'draft',
            'tax_office_code' => $company['tax_office_code'] ?? null,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'created_by' => $userId ?? Auth::id(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public static function package(int $id): bool
    {
        return DB::update('beyannameler', ['status' => 'packaged', 'updated_at' => now()], 'id = :id AND status = :s', ['id' => $id, 's' => 'draft']) > 0;
    }

    public static function submit(int $id, ?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? Auth::tenantId();
        $row = static::guard($id, $tenantId);
        $gateway = self::buildGateway(GibSettingService::config($tenantId));
        $payload = json_decode((string) $row['payload'], true) ?: [];
        $result = $gateway->submit($payload ?: ['id' => $id]);
        if (!empty($result['ok'])) {
            DB::update('beyannameler', [
                'status' => 'sent', 'gib_reference' => $result['reference'] ?? null,
                'gib_error' => null, 'sent_at' => now(), 'updated_at' => now(),
            ], 'id = :id', ['id' => $id]);
        } else {
            DB::update('beyannameler', [
                'status' => 'error', 'gib_error' => $result['message'] ?? 'Gönderim hatası',
                'updated_at' => now(),
            ], 'id = :id', ['id' => $id]);
        }
        return ['ok' => (bool) $result['ok'], 'reference' => $result['reference'] ?? null, 'message' => (string) ($result['message'] ?? '')];
    }

    public static function approve(int $id): bool
    {
        return DB::update('beyannameler', ['status' => 'approved', 'approved_at' => now(), 'updated_at' => now()], 'id = :id AND status = :s', ['id' => $id, 's' => 'sent']) > 0;
    }

    public static function pendingForTenant(int $tenantId): array
    {
        return DB::select('SELECT * FROM beyannameler WHERE tenant_id = :t AND status = :s ORDER BY id DESC', ['t' => $tenantId, 's' => 'sent']);
    }

    private static function guard(int $id, ?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? Auth::tenantId();
        $row = DB::first('SELECT * FROM beyannameler WHERE id = :id AND tenant_id = :t', ['id' => $id, 't' => $tenantId]);
        if (!$row) {
            throw new \RuntimeException('Beyanname bulunamadı');
        }
        return $row;
    }

    private static function buildGateway(array $cfg): BeyanGateway
    {
        $rest = new RestBeyanGateway($cfg);
        if ($cfg['provider'] === 'rest' && $rest->configured()) {
            return $rest;
        }
        return new SimulatedBeyanGateway();
    }
}
