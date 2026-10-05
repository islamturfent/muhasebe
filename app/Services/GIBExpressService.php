<?php

declare(strict_types=1);

namespace Muh\Services;

use Muh\Core\DB;
use Muh\Services\GIB\GIBInboundGateway;
use Muh\Services\GIB\SimulatedGIBInboundGateway;
use Muh\Services\GIB\RestGIBInboundGateway;

/**
 * GİB e-Belge Express (Faz 2): GİB'ten e-Fatura/e-Arşiv evraklarını çeker ve
 * otomatik muhasebe fişine (genel muhasebe) dönüştürür.
 */
final class GIBExpressService
{
    /** GİB'ten evrakları çek ve sakla (document_number ile tekilleştir). */
    public static function pull(int $tenantId, int $companyId, string $docType, string $from, string $to): int
    {
        $gateway = self::buildGateway(GibSettingService::config($tenantId));
        $docs = $gateway->fetch($docType, $from, $to);
        $added = 0;
        foreach ($docs as $d) {
            $exists = DB::first('SELECT id FROM gib_inbound_documents WHERE tenant_id = :t AND document_number = :n', ['t' => $tenantId, 'n' => $d['document_number']]);
            if ($exists) {
                continue;
            }
            DB::insert('gib_inbound_documents', [
                'tenant_id' => $tenantId,
                'company_id' => $companyId,
                'doc_type' => $docType,
                'document_number' => $d['document_number'],
                'supplier_name' => $d['supplier_name'] ?? null,
                'supplier_taxno' => $d['supplier_taxno'] ?? null,
                'doc_date' => $d['date'] ?? null,
                'currency' => $d['currency'] ?? 'TRY',
                'base' => $d['base'] ?? 0,
                'vat' => $d['vat'] ?? 0,
                'total' => $d['total'] ?? 0,
                'status' => 'downloaded',
                'raw_xml' => $d['raw_xml'] ?? null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $added++;
        }
        return $added;
    }

    public static function list(int $tenantId, int $companyId): array
    {
        return DB::select(
            'SELECT * FROM gib_inbound_documents WHERE tenant_id = :t AND company_id = :c ORDER BY id DESC',
            ['t' => $tenantId, 'c' => $companyId]
        );
    }

    /**
     * Çekilen evrakı dengeli muhasebe fişine çevir.
     * Hesap kodları: inventory (stok), vat (KDV), supplier (satıcı cari).
     * @return int accounting entry id
     */
    public static function createJournal(int $tenantId, int $docId, array $codes): int
    {
        $doc = DB::first('SELECT * FROM gib_inbound_documents WHERE id = :id AND tenant_id = :t', ['id' => $docId, 't' => $tenantId]);
        if (!$doc) {
            throw new \RuntimeException('Evrak bulunamadı');
        }
        $companyId = (int) $doc['company_id'];
        $periodId = (int) DB::scalar('SELECT id FROM fiscal_periods WHERE company_id = :c ORDER BY id LIMIT 1', ['c' => $companyId]);
        if ($periodId <= 0) {
            throw new \RuntimeException('Firma için mali dönem tanımlı değil');
        }
        $inventory = (string) ($codes['inventory'] ?? '153');
        $vatCode = (string) ($codes['vat'] ?? '191');
        $supplier = (string) ($codes['supplier'] ?? '320');
        $description = 'GİB e-Belge içe aktarım: ' . $doc['document_number'] . ' — ' . ($doc['supplier_name'] ?? '');

        $entryId = AccountingService::postEntry(
            $tenantId,
            $companyId,
            $periodId,
            'journal',
            (string) $doc['doc_date'],
            $description,
            [
                ['account_code' => $inventory, 'debit' => (float) $doc['base']],
                ['account_code' => $vatCode, 'debit' => (float) $doc['vat']],
                ['account_code' => $supplier, 'credit' => (float) $doc['total']],
            ],
            null,
            'gib_inbound',
            (string) $docId
        );
        DB::update('gib_inbound_documents', ['status' => 'converted', 'entry_id' => $entryId, 'updated_at' => now()], 'id = :id', ['id' => $docId]);
        return $entryId;
    }

    private static function buildGateway(array $cfg): GIBInboundGateway
    {
        $rest = new RestGIBInboundGateway($cfg);
        if ($cfg['provider'] === 'rest' && $rest->configured()) {
            return $rest;
        }
        return new SimulatedGIBInboundGateway();
    }
}
