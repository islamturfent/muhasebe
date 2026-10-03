<?php

declare(strict_types=1);

namespace Muh\Services;

use Muh\Core\DB;

/**
 * Builds the branded header/footer data used when rendering invoices and other
 * documents for print/PDF/e-mail. Pure read helpers (no writes).
 */
final class BrandingService
{
    /**
     * @param array $company a companies row (must include id/tenant_id)
     * @return array<string,mixed> resolved brand block for the print template
     */
    public static function forCompany(array $company): array
    {
        $logoUrl = null;
        $logoPath = (string) ($company['logo_path'] ?? '');
        if ($logoPath !== '') {
            $abs = self::logoAbsolutePath((int) ($company['id'] ?? 0), $logoPath);
            if ($abs !== null && is_file($abs)) {
                $logoUrl = url('/company-logo/' . (int) ($company['id'] ?? 0));
            }
        }
        return [
            'name'       => $company['name'] ?? '',
            'trade_name' => $company['trade_name'] ?? '',
            'logo_url'   => $logoUrl,
            'tax_number' => $company['tax_number'] ?? '',
            'tax_office' => $company['tax_office'] ?? '',
            'mersis'     => $company['mersis'] ?? '',
            'address'    => $company['address'] ?? '',
            'phone'      => $company['phone'] ?? '',
            'email'      => $company['email'] ?? '',
            'website'    => $company['website'] ?? '',
            'currency'   => strtoupper((string) ($company['currency'] ?? 'TRY')),
            'iban'       => self::primaryIban((int) ($company['id'] ?? 0)),
        ];
    }

    /** Absolute filesystem path for a stored logo (logos live under storage/documents/logos). */
    public static function logoAbsolutePath(int $companyId, string $storedPath): ?string
    {
        $base = rtrim((string) config('app.filesystem.documents', dirname(__DIR__, 2) . '/storage/documents'), '/') . '/logos';
        // storedPath is a relative filename under .../logos/company_{id}/
        $candidate = $base . '/' . trim($storedPath, '/');
        $dir = dirname($candidate);
        if (strpos($dir, $base) !== 0) {
            return null; // path traversal guard
        }
        return $candidate;
    }

    /** First (non-empty) IBAN of the company's bank accounts, used for payment footer. */
    public static function primaryIban(int $companyId): string
    {
        $row = DB::first(
            "SELECT iban FROM bank_accounts WHERE company_id = :c AND deleted_at IS NULL AND iban IS NOT NULL AND iban <> '' ORDER BY id LIMIT 1",
            ['c' => $companyId]
        );
        return (string) ($row['iban'] ?? '');
    }
}
