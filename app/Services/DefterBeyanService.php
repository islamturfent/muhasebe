<?php

declare(strict_types=1);

namespace Muh\Services;

use Muh\Core\DB;
use Muh\Services\Beyan\BeyanGateway;
use Muh\Services\Beyan\SimulatedBeyanGateway;
use Muh\Services\Beyan\RestBeyanGateway;

/**
 * Defter-Beyan (Faz 3): işletme defteri kayıtları (e-SMM dahil) — kayıt ekle,
 * GİB'e tek tıkla gönder, geçmiş veriyi CSV'den içe aktar.
 */
final class DefterBeyanService
{
    public static function addRecord(
        int $tenantId,
        int $companyId,
        string $recordDate,
        string $docType,
        string $description,
        float $amount,
        float $vat,
        ?string $docNumber = null
    ): int {
        return (int) DB::insert('defter_beyan_records', [
            'tenant_id' => $tenantId,
            'company_id' => $companyId,
            'record_date' => $recordDate,
            'doc_type' => $docType,
            'doc_number' => $docNumber,
            'description' => $description,
            'amount' => $amount,
            'vat' => $vat,
            'total' => $amount + $vat,
            'status' => 'draft',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** CSV'den geçmiş kayıt içe aktar: her satır [tarih, belge_tipi, açıklama, tutar, kdv, belge_no]. */
    public static function importCsv(int $tenantId, int $companyId, string $csv): int
    {
        $lines = preg_split('/\r?\n/', trim($csv));
        $added = 0;
        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }
            $cols = str_getcsv($line);
            if (count($cols) < 4) {
                continue;
            }
            [$date, $type, $desc, $amount] = $cols;
            $vat = (float) ($cols[4] ?? 0);
            $no = $cols[5] ?? null;
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($date))) {
                continue;
            }
            static::addRecord($tenantId, $companyId, trim($date), trim($type), trim($desc), (float) $amount, $vat, $no !== null ? trim($no) : null);
            $added++;
        }
        return $added;
    }

    public static function list(int $tenantId, int $companyId): array
    {
        return DB::select('SELECT * FROM defter_beyan_records WHERE tenant_id = :t AND company_id = :c ORDER BY id DESC', ['t' => $tenantId, 'c' => $companyId]);
    }

    public static function submitRecord(int $id, int $tenantId): array
    {
        $row = DB::first('SELECT * FROM defter_beyan_records WHERE id = :id AND tenant_id = :t', ['id' => $id, 't' => $tenantId]);
        if (!$row) {
            throw new \RuntimeException('Kayıt bulunamadı');
        }
        $gateway = self::buildGateway(GibSettingService::config($tenantId));
        $payload = [
            'schema_version' => 'defter-beyan/2026.1',
            'record_id' => (int) $row['id'],
            'record_date' => $row['record_date'],
            'doc_type' => $row['doc_type'],
            'doc_number' => $row['doc_number'],
            'description' => $row['description'],
            'amount' => round((float) $row['amount'], 2),
            'vat' => round((float) $row['vat'], 2),
            'total' => round((float) $row['total'], 2),
        ];
        $result = $gateway->submit($payload);
        if (!empty($result['ok'])) {
            DB::update('defter_beyan_records', ['status' => 'sent', 'gib_reference' => $result['reference'] ?? null, 'gib_error' => null, 'updated_at' => now()], 'id = :id', ['id' => $id]);
        } else {
            DB::update('defter_beyan_records', ['status' => 'error', 'gib_error' => $result['message'] ?? 'Gönderim hatası', 'updated_at' => now()], 'id = :id', ['id' => $id]);
        }
        return ['ok' => (bool) $result['ok'], 'reference' => $result['reference'] ?? null, 'message' => (string) ($result['message'] ?? '')];
    }

    public static function submitPendingForTenant(int $tenantId): int
    {
        $rows = DB::select("SELECT id FROM defter_beyan_records WHERE tenant_id = :t AND status IN ('draft','error') ORDER BY id", ['t' => $tenantId]);
        $sent = 0;
        foreach ($rows as $r) {
            $res = static::submitRecord((int) $r['id'], $tenantId);
            if ($res['ok']) {
                $sent++;
            }
        }
        return $sent;
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
