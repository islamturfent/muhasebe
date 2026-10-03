<?php

declare(strict_types=1);

namespace Muh\Services;

use Muh\Core\Config;
use Muh\Core\DB;
use Muh\Core\Hash;
use Muh\Core\Validator;
use Muh\Core\ValidationException;
use Muh\Core\Auth;

/**
 * Handles the registration & onboarding of a new accounting office (tenant):
 * creates the tenant, the owner user, the owner role binding, a FREE/trial
 * subscription, and the initial (Turkish) chart of accounts for onboarding.
 */
final class TenantOnboardingService
{
    public const TURKISH_CHART_OF_ACCOUNTS = [
        // Assets
        ['code' => '100', 'name' => 'Kasa', 'type' => 'asset', 'is_header' => 0],
        ['code' => '102', 'name' => 'Bankalar', 'type' => 'asset', 'is_header' => 0],
        ['code' => '120', 'name' => 'Alıcılar', 'type' => 'asset', 'is_header' => 0],
        ['code' => '121', 'name' => 'Alacak Senetleri', 'type' => 'asset', 'is_header' => 0],
        ['code' => '153', 'name' => 'Ticari Mallar', 'type' => 'asset', 'is_header' => 0],
        ['code' => '191', 'name' => 'İndirilecek KDV', 'type' => 'asset', 'is_header' => 0],
        ['code' => '255', 'name' => 'Demirbaşlar', 'type' => 'asset', 'is_header' => 0],
        // Liabilities
        ['code' => '320', 'name' => 'Satıcılar', 'type' => 'liability', 'is_header' => 0],
        ['code' => '321', 'name' => 'Borç Senetleri', 'type' => 'liability', 'is_header' => 0],
        ['code' => '391', 'name' => 'Hesaplanan KDV', 'type' => 'liability', 'is_header' => 0],
        ['code' => '360', 'name' => 'Ödenecek Vergi ve Fonlar', 'type' => 'liability', 'is_header' => 0],
        // Equity
        ['code' => '500', 'name' => 'Sermaye', 'type' => 'equity', 'is_header' => 0],
        ['code' => '590', 'name' => 'Dönem Net Karı (Zararı)', 'type' => 'equity', 'is_header' => 0],
        // Income
        ['code' => '600', 'name' => 'Yurtiçi Satışlar', 'type' => 'income', 'is_header' => 0],
        ['code' => '610', 'name' => 'Satıştan İadeler', 'type' => 'income', 'is_header' => 0],
        ['code' => '642', 'name' => 'Faiz Gelirleri', 'type' => 'income', 'is_header' => 0],
        // Expense
        ['code' => '620', 'name' => 'Satılan Ticari Mallar Maliyeti', 'type' => 'expense', 'is_header' => 0],
        ['code' => '770', 'name' => 'Genel Yönetim Giderleri', 'type' => 'expense', 'is_header' => 0],
        ['code' => '780', 'name' => 'Finansman Giderleri', 'type' => 'expense', 'is_header' => 0],
    ];

    /**
     * Register a new accounting office. Returns the created tenant id.
     *
     * @throws ValidationException
     */
    public function register(array $data, ?string $ip = null): array
    {
        $v = new Validator();
        $rules = [
            'name'       => 'required|min:2',
            'email'      => 'required|email|unique:users,email',
            'phone'      => 'nullable',
            'password'   => 'required|min:8',
            'office_name'=> 'required|min:2',
            'country'    => 'required|in:TR',
            'locale'     => 'required|in:tr,en',
            'currency'   => 'required|in:TRY,USD,EUR,GBP',
        ];
        if (!$v->validate($data, $rules)) {
            throw new ValidationException($v->errors());
        }

        $tenantId = (int) DB::transaction(function () use ($data) {
            $slug = $this->uniqueSlug($data['office_name']);
            $tenantId = (int) DB::insert('tenants', [
                'name'         => $data['office_name'],
                'slug'         => $slug,
                'legal_name'   => $data['office_name'],
                'email'        => strtolower(trim($data['email'])),
                'phone'        => $data['phone'] ?? null,
                'country'      => $data['country'],
                'locale'       => $data['locale'],
                'currency'     => $data['currency'],
                'status'       => 'active',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            // Owner user
            $userId = (int) DB::insert('users', [
                'tenant_id' => $tenantId,
                'name'      => $data['name'],
                'email'     => strtolower(trim($data['email'])),
                'phone'     => $data['phone'] ?? null,
                'password'  => Hash::make($data['password']),
                'locale'    => $data['locale'],
                'currency'  => $data['currency'],
                'status'    => 'active',
                'is_owner'  => 1,
                'is_system_admin' => 0,
                'created_at'=> now(),
                'updated_at'=> now(),
            ]);

            // Bind owner role
            $ownerRole = DB::first('SELECT id FROM roles WHERE `key` = :k', ['k' => 'owner']);
            if ($ownerRole) {
                DB::insert('user_role', ['user_id' => $userId, 'role_id' => (int) $ownerRole['id'], 'created_at' => now(), 'updated_at' => now()]);
            }

            // Attach FREE plan + trial subscription
            $plan = DB::first('SELECT id FROM plans WHERE code = :c', ['c' => 'FREE']);
            DB::insert('subscriptions', [
                'tenant_id' => $tenantId,
                'plan_id'   => $plan ? (int) $plan['id'] : null,
                'status'    => 'trial',
                'starts_at' => now(),
                'trial_ends_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
                'billing_cycle' => 'monthly',
                'created_at'=> now(),
                'updated_at'=> now(),
            ]);

            AuditLogService::record('tenant.registered', 'tenant', 'tenants', (string) $tenantId);
            return $tenantId;
        });

        return ['tenant_id' => $tenantId, 'user_email' => $data['email']];
    }

    /**
     * Create the first client company + fiscal period + chart of accounts,
     * as part of the onboarding wizard.
     *
     * @throws ValidationException
     */
    public function createFirstCompany(array $data, int $tenantId): array
    {
        \Muh\Services\PlanLimitsService::assertCanCreate('companies');
        $companyId = (int) DB::transaction(function () use ($data, $tenantId) {
            $companyId = (int) DB::insert('companies', [
                'tenant_id'     => $tenantId,
                'name'          => $data['company_name'],
                'trade_name'    => $data['trade_name'] ?? null,
                'tax_number'    => $data['tax_number'] ?? null,
                'tax_office'    => $data['tax_office'] ?? null,
                'mersis'        => $data['mersis'] ?? null,
                'currency'      => $data['currency'] ?? 'TRY',
                'status'        => 'active',
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            // Fiscal period
            $year = (int) ($data['fiscal_year'] ?? date('Y'));
            $periodId = (int) DB::insert('fiscal_periods', [
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

            // Chart of accounts (Turkish default)
            $rows = [];
            foreach (self::TURKISH_CHART_OF_ACCOUNTS as $acc) {
                $rows[] = [
                    'tenant_id'        => $tenantId,
                    'company_id'       => $companyId,
                    'fiscal_period_id' => $periodId,
                    'code'             => $acc['code'],
                    'name'             => $acc['name'],
                    'type'             => $acc['type'],
                    'group'            => substr($acc['code'], 0, 1),
                    'is_header'        => $acc['is_header'],
                    'currency'         => $data['currency'] ?? 'TRY',
                    'opening_debit'    => 0,
                    'opening_credit'   => 0,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ];
            }
            DB::insertMany('accounting_accounts', $rows);

            AuditLogService::record('company.created', 'company', 'companies', (string) $companyId);
            return $companyId;
        });

        return ['company_id' => $companyId];
    }

    private function uniqueSlug(string $name): string
    {
        $base = str_slug($name) ?: 'ofis';
        $slug = $base;
        $i = 1;
        while (DB::first('SELECT id FROM tenants WHERE slug = :s', ['s' => $slug])) {
            $slug = $base . '-' . (++$i);
        }
        return $slug;
    }
}
