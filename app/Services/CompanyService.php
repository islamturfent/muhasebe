<?php

declare(strict_types=1);

namespace Muh\Services;

use Muh\Core\Auth;
use Muh\Core\DB;
use Muh\Core\Validator;
use Muh\Core\ValidationException;

/**
 * Company (client firm) management for accounting-office users (Phase 2).
 * Creating a company also provisions its first fiscal period and the Turkish
 * chart of accounts, all in one transaction, respecting plan limits.
 */
final class CompanyService
{
    public function create(array $data): int
    {
        PlanLimitsService::assertCanCreate('companies');

        $tenantId = (int) Auth::tenantId();
        (new Validator())->validateOrFail($data, [
            'name'   => 'required|min:2',
            'fiscal_year' => 'nullable|integer',
        ]);

        $companyId = (int) DB::transaction(function () use ($tenantId, $data) {
            $currency = in_array($data['currency'] ?? 'TRY', ['TRY', 'USD', 'EUR', 'GBP'], true) ? $data['currency'] : 'TRY';
            $companyId = (int) DB::insert('companies', [
                'tenant_id'     => $tenantId,
                'name'          => $data['name'],
                'trade_name'    => $data['trade_name'] ?? null,
                'tax_number'    => $data['tax_number'] ?? null,
                'tax_office'    => $data['tax_office'] ?? null,
                'mersis'        => $data['mersis'] ?? null,
                'address'       => $data['address'] ?? null,
                'phone'         => $data['phone'] ?? null,
                'email'         => $data['email'] ?? null,
                'company_type'  => $data['company_type'] ?? null,
                'currency'      => $currency,
                'locale'        => ($data['locale'] ?? '') === 'en' ? 'en' : 'tr',
                'status'        => 'active',
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            // First fiscal period (current year unless specified).
            $year = (int) ($data['fiscal_year'] ?? date('Y'));
            if ($year < 2000 || $year > 2100) {
                $year = (int) date('Y');
            }
            DB::insert('fiscal_periods', [
                'tenant_id'   => $tenantId,
                'company_id'  => $companyId,
                'name'        => $year . ' Dönemi',
                'start_date'  => $year . '-01-01',
                'end_date'    => $year . '-12-31',
                'is_closed'   => 0,
                'is_current'  => 1,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            // Turkish chart of accounts for the first period.
            $period = DB::first('SELECT id FROM fiscal_periods WHERE company_id = :c ORDER BY id DESC LIMIT 1', ['c' => $companyId]);
            $rows = [];
            foreach (TenantOnboardingService::TURKISH_CHART_OF_ACCOUNTS as $acc) {
                $rows[] = [
                    'tenant_id'        => $tenantId,
                    'company_id'       => $companyId,
                    'fiscal_period_id' => (int) $period['id'],
                    'code'             => $acc['code'],
                    'name'             => $acc['name'],
                    'type'             => $acc['type'],
                    'group'            => substr($acc['code'], 0, 1),
                    'is_header'        => $acc['is_header'],
                    'currency'         => $currency,
                    'opening_debit'    => 0,
                    'opening_credit'   => 0,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ];
            }
            DB::insertMany('accounting_accounts', $rows);

            AuditLogService::record('company.create', 'company', 'companies', (string) $companyId, null, $data);
            return $companyId;
        });

        return $companyId;
    }
}
