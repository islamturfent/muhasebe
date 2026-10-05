<?php

declare(strict_types=1);

namespace Muh\Services\GIB;

/**
 * Simulated Express: deterministik örnek e-Fatura/e-Arşiv evrakları üretir
 * (offline içe aktarma akışını test/CI'da çalıştırmak için).
 */
final class SimulatedGIBInboundGateway implements GIBInboundGateway
{
    public function fetch(string $docType, string $from, string $to): array
    {
        $docs = [];
        foreach (['EXP-1001', 'EXP-1002'] as $i => $no) {
            $vat = 180.00;
            $base = 1000.00 * ($i + 1);
            $total = $base + $vat;
            $docs[] = [
                'document_number' => $no,
                'supplier_name' => 'Simüle Tedarik AŞ',
                'supplier_taxno' => '1110002223',
                'date' => date('Y-m-d', strtotime($from . ' +' . $i . ' day')),
                'currency' => 'TRY',
                'base' => $base,
                'vat' => $vat,
                'total' => $total,
                'raw_xml' => '<Invoice><No>' . $no . '</No><Supplier>AŞ</Supplier><VAT>' . $vat . '</VAT></Invoice>',
            ];
        }
        return $docs;
    }
}
