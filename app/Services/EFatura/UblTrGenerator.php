<?php

declare(strict_types=1);

namespace Muh\Services\EFatura;

/**
 * UBL-TR XML generator for e-Fatura (Invoice) and İrsaliye (DespatchAdvice).
 *
 * Produces a well-formed UBL-TR document from a normalized invoice array so a
 * real GİB integrator can consume it. Wraps the body in the standard envelope
 * with the Turkish extension namespaces. Dependency-free (no xmlwriter needed —
 * we build the string with proper escaping).
 */
final class UblTrGenerator
{
    private const NS = [
        'xmlns' => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
        'xmlns:cac' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
        'xmlns:cbc' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
        'xmlns:ext' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2',
        'xmlns:qdt' => 'urn:oasis:names:specification:ubl:schema:xsd:QualifiedDatatypes-2',
        'xmlns:udt' => 'urn:oasis:names:specification:ubl:schema:xsd:UnqualifiedDataTypes-2',
        'xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
    ];

    /** Generate UBL-TR XML (InvoiceType unless $despatch = true). */
    public static function generate(array $inv, array $items, bool $despatch = false): string
    {
        $root = $despatch ? 'DespatchAdvice' : 'Invoice';
        $ns = $despatch ? str_replace('Invoice-2', 'DespatchAdvice-2', self::NS['xmlns']) : self::NS['xmlns'];
        $nsStr = "xmlns=\"{$ns}\"";
        foreach (self::NS as $k => $v) {
            if ($k !== 'xmlns') {
                $nsStr .= " {$k}=\"{$v}\"";
            }
        }

        $x = [];
        $x[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $x[] = "<{$root} {$nsStr}>";
        $x[] = '<cbc:UBLVersionID>2.1</cbc:UBLVersionID>';
        $x[] = '<cbc:CustomizationID>TR1.2.1</cbc:CustomizationID>';
        $x[] = '<cbc:ProfileID>' . ($despatch ? 'TR-Despatch' : 'TEMEL-FATURA') . '</cbc:ProfileID>';
        $x[] = '<cbc:ID>' . self::esc($inv['number'] ?? '') . '</cbc:ID>';
        $x[] = '<cbc:IssueDate>' . ($inv['date'] ?? date('Y-m-d')) . '</cbc:IssueDate>';
        if (!empty($inv['due_date'])) {
            $x[] = '<cbc:DueDate>' . $inv['due_date'] . '</cbc:DueDate>';
        }
        $x[] = '<cbc:InvoiceTypeCode>' . ($inv['type'] ?? 'SATIS') . '</cbc:InvoiceTypeCode>';
        $x[] = '<cbc:DocumentCurrencyCode>' . ($inv['currency_code'] ?? $inv['company_currency'] ?? 'TRY') . '</cbc:DocumentCurrencyCode>';
        if (!empty($inv['exchange_rate'])) {
            $x[] = '<cbc:PricingCurrencyCode>TRY</cbc:PricingCurrencyCode>';
        }

        // Supplier (SellerParty)
        $x[] = '<cac:AccountingSupplierParty><cac:Party>';
        $x[] = '<cac:PartyIdentification><cbc:ID schemeID="VKN">' . self::esc($inv['company_tax'] ?? '') . '</cbc:ID></cac:PartyIdentification>';
        $x[] = '<cac:PartyLegalEntity><cbc:RegistrationName>' . self::esc($inv['company_name'] ?? '') . '</cbc:RegistrationName></cac:PartyLegalEntity>';
        if (!empty($inv['company_address'])) {
            $x[] = '<cac:Party><cac:PostalAddress><cbc:StreetName>' . self::esc($inv['company_address']) . '</cbc:StreetName></cac:PostalAddress></cac:Party>';
        }
        $x[] = '</cac:Party></cac:AccountingSupplierParty>';

        // Customer (BuyerParty)
        $x[] = '<cac:AccountingCustomerParty><cac:Party>';
        $x[] = '<cac:PartyIdentification><cbc:ID schemeID="VKN">' . self::esc($inv['account_tax'] ?? '') . '</cbc:ID></cac:PartyIdentification>';
        $x[] = '<cac:PartyLegalEntity><cbc:RegistrationName>' . self::esc($inv['account_name'] ?? '') . '</cbc:RegistrationName></cac:PartyLegalEntity>';
        $x[] = '</cac:Party></cac:AccountingCustomerParty>';

        // Totals
        $x[] = '<cac:LegalMonetaryTotal>';
        $x[] = '<cbc:LineExtensionAmount currencyID="' . ($inv['currency_code'] ?? 'TRY') . '">' . self::num($inv['subtotal'] ?? 0) . '</cbc:LineExtensionAmount>';
        $x[] = '<cbc:TaxExclusiveAmount currencyID="' . ($inv['currency_code'] ?? 'TRY') . '">' . self::num($inv['subtotal'] ?? 0) . '</cbc:TaxExclusiveAmount>';
        $x[] = '<cbc:TaxInclusiveAmount currencyID="' . ($inv['currency_code'] ?? 'TRY') . '">' . self::num($inv['total'] ?? 0) . '</cbc:TaxInclusiveAmount>';
        $x[] = '<cbc:PayableAmount currencyID="' . ($inv['currency_code'] ?? 'TRY') . '">' . self::num($inv['total'] ?? 0) . '</cbc:PayableAmount>';
        $x[] = '</cac:LegalMonetaryTotal>';

        // Lines
        foreach ($items as $i => $it) {
            $line = $i + 1;
            $x[] = '<cac:InvoiceLine><cbc:ID>' . $line . '</cbc:ID>';
            $x[] = '<cbc:InvoicedQuantity unitCode="C62">' . self::num($it['quantity'] ?? 1) . '</cbc:InvoicedQuantity>';
            $x[] = '<cbc:LineExtensionAmount currencyID="' . ($inv['currency_code'] ?? 'TRY') . '">' . self::num($it['total'] ?? 0) . '</cbc:LineExtensionAmount>';
            $x[] = '<cac:Item><cbc:Name>' . self::esc($it['product_name'] ?? $it['description'] ?? '') . '</cbc:Name></cac:Item>';
            $x[] = '<cac:Price><cbc:PriceAmount currencyID="' . ($inv['currency_code'] ?? 'TRY') . '">' . self::num($it['unit_price'] ?? 0) . '</cbc:PriceAmount></cac:Price>';
            $x[] = '</cac:InvoiceLine>';
        }

        $x[] = "</{$root}>";
        return implode("\n", $x);
    }

    private static function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function num($v): string
    {
        $n = (float) $v;
        return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    }
}
