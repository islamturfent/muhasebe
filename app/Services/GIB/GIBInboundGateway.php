<?php

declare(strict_types=1);

namespace Muh\Services\GIB;

/**
 * GİB e-Belge (Express) çekme soyutlaması (Faz 2). Simulated sürücü örnek
 * e-Fatura üretir; RestGIBInboundGateway gerçek GİB/entegratör uç noktasıdır.
 */
interface GIBInboundGateway
{
    /**
     * @return array<int,array{document_number:string,supplier_name:string,supplier_taxno:string,date:string,currency:string,base:float,vat:float,total:float,raw_xml:string}>
     */
    public function fetch(string $docType, string $from, string $to): array;
}
