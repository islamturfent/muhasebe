<?php

declare(strict_types=1);

namespace Muh\Services;

use Muh\Core\Auth;
use Muh\Core\DB;
use Muh\Services\EFatura\EFaturaGateway;
use Muh\Services\EFatura\SimulatedEFaturaGateway;
use Muh\Services\EFatura\RESTEFaturaGateway;

/**
 * e-Fatura / e-Arşiv orchestration (optional feature).
 *
 * Sends an invoice to the configured integrator and tracks its status across
 * draft → sending → sent → accepted/rejected/error.
 */
final class EFaturaService
{
    private EFaturaGateway $gateway;

    public function __construct(?EFaturaGateway $gateway = null)
    {
        if ($gateway !== null) {
            $this->gateway = $gateway;
            return;
        }
        $this->gateway = $this->buildGateway($this->tenantConfig((int) Auth::tenantId()));
    }

    private function buildGateway(array $cfg): EFaturaGateway
    {
        $provider = $cfg['provider'] ?? 'simulated';
        // Any named integrator (entegrator/logo/foriba/izibiz) is backed by the REST adapter.
        if (\Muh\Services\EFatura\EFaturaProviders::isReal($provider)) {
            $rest = new RESTEFaturaGateway($cfg);
            if ($rest->configured()) {
                return $rest;
            }
        }
        return new SimulatedEFaturaGateway();
    }

    /** @return array<string,string> provider key => label translation key. */
    public static function supportedProviders(): array
    {
        return \Muh\Services\EFatura\EFaturaProviders::labels();
    }

    /**
     * Effective e-Fatura config for the given tenant: tenant-stored settings
     * override the global env config. Falls back to simulated when unset.
     */
    public function tenantConfig(int $tenantId): array
    {
        $keys = ['provider', 'mode', 'test_url', 'production_url', 'username', 'password'];
        $stored = [];
        if ($tenantId) {
            foreach (DB::select(
                "SELECT `key`, value FROM settings WHERE tenant_id = :t AND `group` = 'efatura'",
                ['t' => $tenantId]
            ) as $row) {
                $stored[$row['key']] = $row['value'];
            }
        }
        return [
            'provider'       => $stored['provider'] ?? config('efatura.provider', 'simulated'),
            'mode'           => $stored['mode'] ?? config('efatura.mode', 'test'),
            'test_url'       => $stored['test_url'] ?? '',
            'production_url' => $stored['production_url'] ?? '',
            'username'       => $stored['username'] ?? '',
            'password'       => $stored['password'] ?? '',
        ];
    }

    /**
     * Poll pending e-documents (sending/sent) for a tenant and advance the
     * invoice status when the integrator reports accepted/rejected.
     *
     * @return array<int,array{id:int,from:string,to:string}>
     */
    public function pollForTenant(int $tenantId, ?EFaturaGateway $gateway = null): array
    {
        $gateway = $gateway ?? $this->buildGateway($this->tenantConfig($tenantId));
        $invoices = DB::select(
            "SELECT id, number, efatura_envelope_id, efatura_status, company_id
               FROM invoices
              WHERE tenant_id = :t AND deleted_at IS NULL
                AND efatura_status IN ('sending','sent') AND efatura_envelope_id IS NOT NULL",
            ['t' => $tenantId]
        );
        $results = [];
        foreach ($invoices as $inv) {
            $new = $gateway->getStatus((string) $inv['efatura_envelope_id']);
            if (!in_array($new, ['accepted', 'rejected', 'sent', 'error'], true)) {
                $new = 'sent';
            }
            if ($new === $inv['efatura_status']) {
                continue;
            }
            if (in_array($new, ['accepted', 'rejected'], true)) {
                DB::update('invoices', ['efatura_status' => $new], 'id = :id', ['id' => (int) $inv['id']]);
                $results[] = ['id' => (int) $inv['id'], 'from' => $inv['efatura_status'], 'to' => $new];
                AuditLogService::record('invoice.efatura.poll', 'efatura', 'invoices', (string) $inv['id'], null, ['status' => $new], (int) $inv['company_id'], $tenantId);
            }
        }
        return $results;
    }

    /**
     * Persist the tenant e-Fatura settings (group = 'efatura').
     */
    public static function saveTenantConfig(array $data): void
    {
        $tenantId = (int) Auth::tenantId();
        $provider = (string) ($data['provider'] ?? '');
        if ($provider !== 'simulated' && !\Muh\Services\EFatura\EFaturaProviders::isReal($provider)) {
            $provider = 'simulated'; // whitelist only known providers
        }
        $map = [
            'provider' => $provider,
            'mode' => (string) ($data['mode'] ?? 'test'),
            'test_url' => trim((string) ($data['test_url'] ?? '')),
            'production_url' => trim((string) ($data['production_url'] ?? '')),
            'username' => trim((string) ($data['username'] ?? '')),
            'password' => (string) ($data['password'] ?? ''),
        ];
        foreach ($map as $key => $value) {
            $exists = DB::first(
                "SELECT id FROM settings WHERE tenant_id = :t AND `group` = 'efatura' AND `key` = :k",
                ['t' => $tenantId, 'k' => $key]
            );
            if ($exists) {
                DB::execute(
                    "UPDATE settings SET value = :v, updated_at = :n WHERE id = :id",
                    ['v' => $value, 'n' => now(), 'id' => (int) $exists['id']]
                );
            } else {
                DB::insert('settings', [
                    'tenant_id' => $tenantId, 'group' => 'efatura', 'key' => $key,
                    'value' => $value, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Current tenant e-Fatura settings for the settings screen.
     */
    public static function tenantSettings(): array
    {
        $tenantId = (int) Auth::tenantId();
        $defaults = [
            'provider' => config('efatura.provider', 'simulated'),
            'mode' => config('efatura.mode', 'test'),
            'test_url' => config('efatura.test_url', ''),
            'production_url' => config('efatura.production_url', ''),
            'username' => config('efatura.username', ''),
            'password' => config('efatura.password', ''),
        ];
        if (!$tenantId) {
            return $defaults;
        }
        foreach (DB::select(
            "SELECT `key`, value FROM settings WHERE tenant_id = :t AND `group` = 'efatura'",
            ['t' => $tenantId]
        ) as $row) {
            $defaults[$row['key']] = $row['value'];
        }
        return $defaults;
    }

    public function allowed(): bool
    {
        return in_array(config('efatura.mode', 'test'), ['test', 'production'], true);
    }

    /**
     * Build the normalized document payload for an invoice.
     */
    public function buildPayload(array $invoice, array $items, string $docType = 'invoice'): array
    {
        return [
            'uuid' => 'UUID-' . $invoice['id'] . '-' . md5($invoice['number']),
            'invoice_no' => $invoice['number'],
            'doc_type' => $docType,
            'type' => $invoice['type'],
            'date' => $invoice['date'],
            'due_date' => $invoice['due_date'] ?? null,
            'currency' => $invoice['currency_code'] ?? $invoice['company_currency'] ?? 'TRY',
            'exchange_rate' => ($invoice['exchange_rate'] ?? null) ? (float) $invoice['exchange_rate'] : null,
            'total' => (float) $invoice['total'],
            'tax' => (float) $invoice['tax'],
            'subtotal' => (float) $invoice['subtotal'],
            'discount' => (float) ($invoice['discount'] ?? 0),
            'withholding' => (float) ($invoice['withholding'] ?? 0),
            'document_no' => $invoice['document_no'] ?? null,
            'supplier' => [
                'name' => $invoice['company_name'] ?? null,
                'tax_number' => $invoice['company_tax'] ?? null,
                'tax_office' => $invoice['company_tax_office'] ?? null,
                'address' => $invoice['company_address'] ?? null,
                'country' => $invoice['company_country'] ?? 'TR',
            ],
            'customer' => [
                'name' => $invoice['account_name'] ?? null,
                'tax_number' => $invoice['account_tax'] ?? null,
                'address' => $invoice['account_address'] ?? null,
                'country' => $invoice['account_country'] ?? 'TR',
            ],
            'items' => array_map(fn ($it) => [
                'code' => $it['product_code'] ?? ($it['product_id'] ?: null),
                'name' => $it['product_name'] ?? $it['description'],
                'description' => $it['product_name'] ?? $it['description'],
                'qty' => (float) $it['quantity'],
                'unit' => $it['unit_abbr'] ?? null,
                'unit_price' => (float) $it['unit_price'],
                'discount' => (float) ($it['discount'] ?? 0),
                'tax_rate' => (float) $it['tax_rate'],
                'withholding_rate' => (float) ($it['withholding_rate'] ?? 0),
                'total' => (float) $it['total'],
            ], $items),
        ];
    }

    /**
     * Send an invoice (e-Fatura) or archive (e-Arşiv) to the integrator and
     * persist the resulting status.
     *
     * @param int    $invoiceId
     * @param string $docType   invoice | archive | despatch
     * @return string resulting e-fatura status
     */
    public function sendInvoice(int $invoiceId, string $docType = 'invoice'): string
    {
        $tenantId = Auth::tenantId();
        $invoice = DB::first(
            'SELECT i.*, c.name AS company_name, c.tax_number AS company_tax, c.tax_office AS company_tax_office,
                    c.address AS company_address, c.currency AS company_currency,
                    ca.name AS account_name, ca.tax_number AS account_tax, ca.address AS account_address
               FROM invoices i
               JOIN companies c ON c.id = i.company_id
               LEFT JOIN current_accounts ca ON ca.id = i.current_account_id
              WHERE i.id = :id AND i.tenant_id = :t AND i.deleted_at IS NULL',
            ['id' => $invoiceId, 't' => $tenantId]
        );
        if (!$invoice) {
            throw new \Muh\Core\NotFoundException();
        }

        // Only sales/purchase invoices (not proforma) can go to e-Fatura.
        if (!in_array($invoice['type'], ['sales', 'purchase'], true)) {
            throw new \Muh\Core\ValidationException(['efatura' => __('efatura.unsupported_type')]);
        }
        $docType = in_array($docType, ['invoice', 'archive', 'despatch'], true) ? $docType : 'invoice';

        $items = DB::select(
            'SELECT ii.*, p.name AS product_name FROM invoice_items ii
              LEFT JOIN products p ON p.id = ii.product_id
             WHERE ii.invoice_id = :id',
            ['id' => $invoiceId]
        );

        $payload = $this->buildPayload($invoice, $items, $docType);

        // Set to "sending"
        DB::update('invoices', ['efatura_status' => 'sending', 'efatura_doc_type' => $docType], 'id = :id', ['id' => $invoiceId]);

        try {
            $result = $this->gateway->sendDocument($payload);
            $status = in_array($result['status'], ['sent', 'accepted', 'rejected', 'error'], true) ? $result['status'] : 'error';
        } catch (\Throwable $e) {
            $status = 'error';
            $result = ['uuid' => $payload['uuid'], 'message' => $e->getMessage()];
        }

        DB::update('invoices', [
            'efatura_status' => $status,
            'efatura_doc_type' => $docType,
            'efatura_envelope_id' => $result['envelope_id'] ?? null,
        ], 'id = :id', ['id' => $invoiceId]);
        AuditLogService::record('invoice.efatura.send', 'efatura', 'invoices', (string) $invoiceId, null, [
            'status' => $status, 'doc_type' => $docType, 'uuid' => $result['uuid'] ?? null,
            'envelope_id' => $result['envelope_id'] ?? null, 'message' => $result['message'] ?? null,
        ], (int) $invoice['company_id'], (int) $tenantId);

        if ($status === 'error') {
            (new NotificationService())->create('efatura_error', __('efatura.error_title', ['no' => $invoice['number']]), $result['message'] ?? '', 'danger', null, (int) $invoice['company_id'], '/app/invoices/' . $invoiceId);
            NotificationService::maybeMail($tenantId, 'efatura', __('efatura.error_title', ['no' => $invoice['number']]), ($result['message'] ?? '') . ' — <a href="' . url('/app/invoices/' . $invoiceId) . '">' . __('common.view') . '</a>');
        }

        return $status;
    }
}
