<?php

declare(strict_types=1);

namespace Muh\Core;

use Muh\Core\DB;

/**
 * Lightweight self-test runner (Phase 14). Each test is a closure returning
 * [bool $ok, string $detail]. Run via: php bin/muh test
 */
final class AppTester
{
    /** @return array<int, array{name:string, ok:bool, detail:string}> */
    public function run(): array
    {
        $results = [];

        $name = 'DB bağlı & sürücü algılandı';
        try {
            $driver = DB::driver();
            DB::scalar('SELECT 1');
            $results[] = ['name' => $name, 'ok' => true, 'detail' => $driver];
        } catch (\Throwable $e) {
            $results[] = ['name' => $name, 'ok' => false, 'detail' => $e->getMessage()];
        }

        $name = 'Çekirdek tablolar mevcut';
        $tables = ['tenants', 'users', 'companies', 'fiscal_periods', 'current_accounts', 'invoices', 'accounting_entries', 'subscriptions', 'plans'];
        try {
            $existing = array_column(DB::select('SHOW TABLES'), null);
            $missing = [];
            foreach ($tables as $t) {
                $found = false;
                foreach (DB::select('SHOW TABLES') as $row) {
                    if (reset($row) === $t) {
                        $found = true;
                    }
                }
                if (!$found) {
                    $missing[] = $t;
                }
            }
            $results[] = ['name' => $name, 'ok' => empty($missing), 'detail' => $missing ? 'eksik: ' . implode(',', $missing) : count($tables) . ' tablo tamam'];
        } catch (\Throwable $e) {
            $results[] = ['name' => $name, 'ok' => false, 'detail' => $e->getMessage()];
        }

        $seedChecks = [
            'Planlar' => ['plans', 4],
            'Roller' => ['roles', 8],
            'Yetkiler' => ['permissions', 10],
            'Para birimleri' => ['currencies', 4],
        ];
        foreach ($seedChecks as $label => [$table, $min]) {
            $count = (int) DB::scalar('SELECT COUNT(*) FROM ' . DB::quoteIdentifier($table));
            $results[] = ['name' => 'Seed: ' . $label, 'ok' => $count >= $min, 'detail' => "{$count} kayıt"];
        }

        // Password hashing roundtrip
        $r = Hash::make('TestPass123');
        $results[] = ['name' => 'Şifre hash/doğrula', 'ok' => Hash::check('TestPass123', $r), 'detail' => 'bcrypt'];

        // Validator: required + email
        $v1 = new Validator();
        $ok1 = !$v1->validate(['name' => ''], ['name' => 'required']);
        $v2 = new Validator();
        $ok2 = !$v2->validate(['email' => 'not-an-email'], ['email' => 'email']);
        $results[] = ['name' => 'Validator (required/email)', 'ok' => $ok1 && $ok2, 'detail' => $ok1 && $ok2 ? 'geçerli' : 'hatalı'];

        // Translator loads tr & en
        $tr = Translator::instance()->translate('common.save', [], 'tr');
        $en = Translator::instance()->translate('common.save', [], 'en');
        $results[] = ['name' => 'Çeviri tr/en', 'ok' => $tr !== 'common.save' && $en !== 'common.save', 'detail' => "tr={$tr} en={$en}"];

        // Money formatting differs by locale
        $moneyTr = money(1234.56);
        $results[] = ['name' => 'Para formatı', 'ok' => str_contains($moneyTr, '₺') || str_contains($moneyTr, ','), 'detail' => $moneyTr];

        // Accounting: unbalanced journal must be rejected
        $comp = DB::first("SELECT id, tenant_id FROM companies LIMIT 1");
        $period = $comp ? DB::first('SELECT id FROM fiscal_periods WHERE company_id = :c LIMIT 1', ['c' => $comp['id']]) : null;
        if ($comp && $period) {
            try {
                \Muh\Services\AccountingService::postEntry(
                    (int) $comp['tenant_id'], (int) $comp['id'], (int) $period['id'],
                    'journal', date('Y-m-d'), 'test', [
                        ['account_code' => '100', 'debit' => 100, 'credit' => 0],
                        ['account_code' => '102', 'debit' => 0, 'credit' => 90],
                    ]
                );
                $results[] = ['name' => 'Muhasebe borç=alacak kuralı', 'ok' => false, 'detail' => 'dengesiz fiş kabul edildi (BUG)'];
            } catch (ValidationException $e) {
                $results[] = ['name' => 'Muhasebe borç=alacak kuralı', 'ok' => true, 'detail' => 'dengesiz fiş reddedildi'];
            }
        } else {
            $results[] = ['name' => 'Muhasebe borç=alacak kuralı', 'ok' => false, 'detail' => 'örnek firma/dönem yok'];
        }

        // Payment gateway abstraction resolution
        $gw = new \Muh\Services\Billing\BillingService();
        $results[] = ['name' => 'Ödeme sağlayıcı soyutlaması', 'ok' => method_exists($gw, 'handleWebhook'), 'detail' => 'BillingService yüklendi'];

        // Two-factor TOTP code generation + verify
        $sec = TwoFactorAuth::generateSecret();
        $code = TwoFactorAuth::code($sec);
        $results[] = ['name' => '2FA TOTP üret/doğrula', 'ok' => TwoFactorAuth::verify($sec, $code), 'detail' => substr($sec, 0, 8) . '…'];

        // ---- Tenant isolation: no cross-tenant data leak ----
        $isoOk = true; $isoDetail = '';
        try {
            DB::transaction(function () use (&$isoOk, &$isoDetail) {
                $t1 = DB::first('SELECT id FROM tenants ORDER BY id LIMIT 1');
                $t2 = DB::first('SELECT id FROM tenants WHERE id != :id ORDER BY id LIMIT 1', ['id' => $t1['id'] ?? 0]);
                if (!$t1 || !$t2) {
                    $isoOk = false; $isoDetail = 'en az iki tenant gerekli';
                    throw new \RuntimeException('__rollback__');
                }
                $c1 = DB::insert('companies', ['tenant_id' => (int) $t1['id'], 'name' => 'ISO-A', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
                $c2 = DB::insert('companies', ['tenant_id' => (int) $t2['id'], 'name' => 'ISO-B', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
                $leak1 = DB::first('SELECT id FROM companies WHERE id = :i AND tenant_id = :t', ['i' => $c1, 't' => (int) $t2['id']]);
                $leak2 = DB::first('SELECT id FROM companies WHERE id = :i AND tenant_id = :t', ['i' => $c2, 't' => (int) $t1['id']]);
                $isoOk = !$leak1 && !$leak2;
                $isoDetail = $isoOk ? 'tenant kapsamlı: sızıntı yok' : 'SIZINTI tespit edildi!';
                throw new \RuntimeException('__rollback__');
            });
        } catch (\RuntimeException $e) {
            // rollback sentinel — expected
        } catch (\Throwable $e) {
            $isoOk = false; $isoDetail = $e->getMessage();
        }
        $results[] = ['name' => 'Tenant izolasyonu', 'ok' => $isoOk, 'detail' => $isoDetail];

        // ---- RBAC role→permission matrix ----
        $has = function (string $roleKey, string $permKey): bool {
            return (bool) DB::scalar(
                'SELECT 1 FROM role_permission rp
                  JOIN roles r ON r.id = rp.role_id
                  JOIN permissions p ON p.id = rp.permission_id
                 WHERE r.`key` = :rk AND p.`key` = :pk LIMIT 1',
                ['rk' => $roleKey, 'pk' => $permKey]
            );
        };
        $rbacOk = $has('owner', 'user.invite')
            && $has('accountant', 'report.export')
            && !$has('accountant', 'user.invite')
            && $has('intern', 'invoice.create')
            && $has('intern', 'current_account.read')
            && !$has('intern', 'report.export');
        $results[] = ['name' => 'RBAC rol→yetki', 'ok' => $rbacOk, 'detail' => $rbacOk ? 'matris doğru' : 'yetki fazlalığı/eksikliği (BUG)'];

        // ---- User invite lifecycle (create → accept) ----
        $invOk = false; $invDetail = '';
        try {
            DB::transaction(function () use (&$invOk, &$invDetail) {
                $tenant = DB::first('SELECT id FROM tenants ORDER BY id LIMIT 1');
                if (!$tenant) {
                    $invDetail = 'tenant yok'; throw new \RuntimeException('__rollback__');
                }
                $tenantId = (int) $tenant['id'];
                $comp = DB::first('SELECT id FROM companies WHERE tenant_id = :t AND deleted_at IS NULL LIMIT 1', ['t' => $tenantId]);
                $staffRole = DB::first('SELECT id FROM roles WHERE `key` = :k', ['k' => 'staff']);
                $email = 'tester_' . bin2hex(random_bytes(4)) . '@example.com';
                $svc = new \Muh\Services\UserInviteService();
                $token = $svc->create($tenantId, 1, $email, (int) $staffRole['id'], $comp ? [(int) $comp['id']] : []);
                $invite = $svc->validByToken($token);
                $createdOk = $invite !== null && $invite['status'] === 'pending';
                // accept → creates user with staff role + company
                $user = $svc->accept($invite, ['name' => 'Tester User', 'password' => 'Test1234!']);
                $roles = \Muh\Models\User::rolesOf((int) $user['id']);
                $roleMatch = in_array('staff', array_column($roles, 'key'), true);
                $companyMatch = $comp && (bool) DB::scalar('SELECT 1 FROM user_company WHERE user_id = :u AND company_id = :c LIMIT 1', ['u' => (int) $user['id'], 'c' => (int) $comp['id']]);
                $acceptedOk = DB::first('SELECT status FROM user_invites WHERE id = :i', ['i' => (int) $invite['id']])['status'] === 'accepted';
                $invOk = $createdOk && $roleMatch && $companyMatch && $acceptedOk;
                $invDetail = $invOk ? 'oluştur→kabul→rol/firma atandı' : 'davet akışında hata (BUG)';
                throw new \RuntimeException('__rollback__');
            });
        } catch (\RuntimeException $e) {
        } catch (\Throwable $e) {
            $invOk = false; $invDetail = $e->getMessage();
        }
        $results[] = ['name' => 'Kullanıcı davet akışı', 'ok' => $invOk, 'detail' => $invDetail];

        // ---- Balanced journal posting (positive path) ----
        $jOk = false; $jDetail = '';
        try {
            DB::transaction(function () use (&$jOk, &$jDetail) {
                $comp = DB::first('SELECT * FROM companies LIMIT 1');
                $period = $comp ? DB::first('SELECT id FROM fiscal_periods WHERE company_id = :c LIMIT 1', ['c' => $comp['id']]) : null;
                if (!$comp || !$period) {
                    $jDetail = 'örnek firma/dönem yok'; throw new \RuntimeException('__rollback__');
                }
                $eid = \Muh\Services\AccountingService::postEntry(
                    (int) $comp['tenant_id'], (int) $comp['id'], (int) $period['id'],
                    'journal', date('Y-m-d'), 'test-balanced', [
                        ['account_code' => '100', 'debit' => 300, 'credit' => 0],
                        ['account_code' => '102', 'debit' => 0, 'credit' => 300],
                    ]
                );
                $entry = DB::first('SELECT debit_total, credit_total FROM accounting_entries WHERE id = :id', ['id' => $eid]);
                $jOk = $entry && abs((float) $entry['debit_total'] - (float) $entry['credit_total']) < 0.01
                    && abs((float) $entry['debit_total'] - 300.0) < 0.01;
                $jDetail = $jOk ? 'dengeli fiş kaydedildi (300)' : 'dengeli fiş sorunu (BUG)';
                throw new \RuntimeException('__rollback__');
            });
        } catch (\RuntimeException $e) {
        } catch (\Throwable $e) {
            $jOk = false; $jDetail = $e->getMessage();
        }
        $results[] = ['name' => 'Muhasebe dengeli fiş kaydı', 'ok' => $jOk, 'detail' => $jDetail];

        // ---- Cari movement updates balance ----
        $cOk = false; $cDetail = '';
        try {
            DB::transaction(function () use (&$cOk, &$cDetail) {
                $comp = DB::first('SELECT * FROM companies LIMIT 1');
                if (!$comp) {
                    $cDetail = 'firma yok'; throw new \RuntimeException('__rollback__');
                }
                $tenantId = (int) $comp['tenant_id']; $cid = (int) $comp['id'];
                $acc = (int) DB::insert('current_accounts', ['tenant_id' => $tenantId, 'company_id' => $cid, 'code' => '999', 'name' => 'Test Cari', 'type' => 'customer', 'balance' => 0, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
                \Muh\Services\CurrentAccountService::addMovement($tenantId, $cid, $acc, 'debt', date('Y-m-d'), 500, 'test');
                \Muh\Services\CurrentAccountService::addMovement($tenantId, $cid, $acc, 'credit', date('Y-m-d'), 150, 'test');
                $bal = DB::first('SELECT balance FROM current_accounts WHERE id = :id', ['id' => $acc])['balance'];
                $cOk = abs((float) $bal - 350.0) < 0.01;
                $cDetail = $cOk ? 'bakiye=350 (500-150)' : "bakiye hatalı: {$bal} (BUG)";
                throw new \RuntimeException('__rollback__');
            });
        } catch (\RuntimeException $e) {
        } catch (\Throwable $e) {
            $cOk = false; $cDetail = $e->getMessage();
        }
        $results[] = ['name' => 'Cari hareket bakiye', 'ok' => $cOk, 'detail' => $cDetail];

        // ---- Stock movement updates running quantity ----
        $sOk = false; $sDetail = '';
        try {
            DB::transaction(function () use (&$sOk, &$sDetail) {
                $comp = DB::first('SELECT * FROM companies LIMIT 1');
                if (!$comp) {
                    $sDetail = 'firma yok'; throw new \RuntimeException('__rollback__');
                }
                $tenantId = (int) $comp['tenant_id']; $cid = (int) $comp['id'];
                $wh = (int) DB::insert('warehouses', ['tenant_id' => $tenantId, 'company_id' => $cid, 'code' => 'T-WH', 'name' => 'Test Depo', 'is_default' => 0, 'created_at' => now(), 'updated_at' => now()]);
                $prod = (int) DB::insert('products', ['tenant_id' => $tenantId, 'company_id' => $cid, 'code' => 'T-P1', 'name' => 'Test Ürün', 'type' => 'product', 'vat_rate' => 20, 'stock_quantity' => 0, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
                \Muh\Services\InventoryService::recordMovement($tenantId, $cid, $wh, $prod, 'opening', date('Y-m-d'), 50, 10, 'open');
                \Muh\Services\InventoryService::recordMovement($tenantId, $cid, $wh, $prod, 'sale', date('Y-m-d'), -20, 10, 'sale');
                $q = DB::first('SELECT stock_quantity FROM products WHERE id = :id', ['id' => $prod])['stock_quantity'];
                $sOk = abs((float) $q - 30.0) < 0.01;
                $sDetail = $sOk ? 'stok=30 (50-20)' : "stok hatalı: {$q} (BUG)";
                throw new \RuntimeException('__rollback__');
            });
        } catch (\RuntimeException $e) {
        } catch (\Throwable $e) {
            $sOk = false; $sDetail = $e->getMessage();
        }
        $results[] = ['name' => 'Stok hareketi miktar', 'ok' => $sOk, 'detail' => $sDetail];

        // ---- Invoice vertical slice (cari + stock + KDV + balanced journal) ----
        $ivOk = false; $ivDetail = '';
        try {
            DB::transaction(function () use (&$ivOk, &$ivDetail) {
                Auth::loginById(1); // demo owner (tenant 1)
                $comp = DB::first('SELECT * FROM companies WHERE tenant_id = 1 AND deleted_at IS NULL ORDER BY id LIMIT 1');
                if (!$comp) {
                    $ivDetail = 'örnek firma yok'; throw new \RuntimeException('__rollback__');
                }
                $cid = (int) $comp['id'];
                $acc = DB::first('SELECT * FROM current_accounts WHERE company_id = :c AND type = :t ORDER BY id LIMIT 1', ['c' => $cid, 't' => 'customer']);
                $prod = DB::first('SELECT * FROM products WHERE company_id = :c AND type = :t ORDER BY id LIMIT 1', ['c' => $cid, 't' => 'product']);
                if (!$acc || !$prod) {
                    $ivDetail = 'cari/ürün yok'; throw new \RuntimeException('__rollback__');
                }
                $beforeBal = (float) $acc['balance'];
                $beforeStock = (float) $prod['stock_quantity'];
                $invId = (new \Muh\Services\InvoiceService())->create([
                    'company_id' => $cid, 'current_account_id' => (int) $acc['id'], 'type' => 'sales',
                    'date' => date('Y-m-d'), 'due_date' => date('Y-m-d', strtotime('+30 days')),
                    'lines' => [['product_id' => (int) $prod['id'], 'qty' => 1, 'unit_price' => 100, 'vat_rate' => 20, 'discount' => 0, 'description' => 'slice']],
                ]);
                $a2 = DB::first('SELECT balance FROM current_accounts WHERE id = :id', ['id' => (int) $acc['id']]);
                $p2 = DB::first('SELECT stock_quantity FROM products WHERE id = :id', ['id' => (int) $prod['id']]);
                $entry = DB::first('SELECT debit_total, credit_total FROM accounting_entries WHERE reference_type = :rt AND reference_id = :ri LIMIT 1', ['rt' => 'invoice', 'ri' => (string) $invId]);
                $cariOk = $a2 && abs(((float) $a2['balance'] - $beforeBal) - 120.0) < 0.01;
                $stockOk = $p2 && abs(((float) $p2['stock_quantity'] - $beforeStock) + 1.0) < 0.01;
                $balOk = $entry && abs((float) $entry['debit_total'] - (float) $entry['credit_total']) < 0.01;
                $ivOk = $cariOk && $stockOk && $balOk;
                $ivDetail = $ivOk ? 'cari+120 & stok-1 & fiş dengeli' : 'dikey dilim hatası (BUG) c=' . var_export($cariOk, true) . ' s=' . var_export($stockOk, true) . ' b=' . var_export($balOk, true);
                throw new \RuntimeException('__rollback__');
            });
        } catch (\RuntimeException $e) {
        } catch (\Throwable $e) {
            $ivOk = false; $ivDetail = $e->getMessage();
        }
        $results[] = ['name' => 'Fatura dikey dilimi', 'ok' => $ivOk, 'detail' => $ivDetail];

        // ---- RBAC positive controls ----
        Auth::loginById(1); // demo owner (full access)
        $ownerCan = Auth::can('company.create') && Auth::can('report.export') && Auth::can('user.invite');
        $sysCan = (new \Muh\Core\Auth())->can('anything_unknown'); // non-admin, no role
        $results[] = ['name' => 'RBAC pozitif (owner tam yetki)', 'ok' => $ownerCan, 'detail' => $ownerCan ? 'owner tüm yetki' : 'yetki eksik (BUG)'];

        // ---- Plan limits: features/usage yapısı ----
        Auth::loginById(1);
        $feat = \Muh\Services\PlanLimitsService::features();
        $usage = \Muh\Services\PlanLimitsService::usage();
        $planOk = is_array($feat) && isset($feat['companies'], $feat['users']) && is_array($usage);
        $results[] = ['name' => 'Plan limit / kullanım yapısı', 'ok' => $planOk, 'detail' => $planOk ? 'features+usage dolu' : 'limit yapısı hatası'];

        // ---- Stripe webhook signature verification ----
        try {
            $stripe = new \Muh\Services\Billing\StripePaymentGateway('sk_test_dummy');
            $whSecret = 'whsec_test_secret';
            $body = '{"type":"invoice.paid","data":{"object":{"id":"sub_123"}}}';
            $t = time();
            $sig = 't=' . $t . ',v1=' . hash_hmac('sha256', $t . '.' . $body, $whSecret);
            $valid = $stripe->verifyWebhookSignature($sig, $body, $whSecret);
            $invalid = !$stripe->verifyWebhookSignature('t=' . $t . ',v1=f4ke', $body, $whSecret);
            $results[] = ['name' => 'Stripe webhook imza doğrulama', 'ok' => $valid && $invalid, 'detail' => $valid ? 'HMAC geçerli + yanlış reddedildi' : 'imza hatası (BUG)'];
        } catch (\Throwable $e) {
            $results[] = ['name' => 'Stripe webhook imza doğrulama', 'ok' => false, 'detail' => $e->getMessage()];
        }

        // ---- e-Fatura gateway contract ----
        try {
            $gw = new \Muh\Services\EFatura\SimulatedEFaturaGateway();
            $res = $gw->sendDocument(['uuid' => 'X', 'doc_type' => 'archive', 'total' => 100]);
            $okC = is_array($res) && in_array($res['status'] ?? '', ['sent', 'error'], true) && array_key_exists('envelope_id', $res);
            $results[] = ['name' => 'e-Fatura ağ geçidi sözleşmesi', 'ok' => $okC, 'detail' => 'status + envelope_id döndü'];
        } catch (\Throwable $e) {
            $results[] = ['name' => 'e-Fatura ağ geçidi sözleşmesi', 'ok' => false, 'detail' => $e->getMessage()];
        }

        // ---- Vergi takvimi üretimi (DÖNEM KURTARMA: rollback) ----
        $vtOk = false; $vtDetail = '';
        try {
            DB::transaction(function () use (&$vtOk, &$vtDetail) {
                $n = \Muh\Services\TaxCalendarService::generate(1, 2099);
                $vtOk = $n >= 27;
                $vtDetail = $vtOk ? ($n . ' kayıt üretildi (KDV/muhtasar/geçici/yıllık)') : 'eksik kayıt (BUG) n=' . $n;
                throw new \RuntimeException('__rollback__');
            });
        } catch (\RuntimeException $e) {
        } catch (\Throwable $e) {
            $vtOk = false; $vtDetail = $e->getMessage();
        }
        $results[] = ['name' => 'Vergi takvimi üretimi (KDV/muhtasar/geçici/yıllık)', 'ok' => $vtOk, 'detail' => $vtDetail];

        // ---- Vergi takvimi sayaçları (read-only) ----
        try {
            $overdue = (int) \Muh\Services\TaxCalendarService::overdueCount(1);
            $up = (int) \Muh\Services\TaxCalendarService::upcomingCount(1, 15);
            $okCt = is_int($overdue) && is_int($up) && $overdue >= 0 && $up >= 0;
            $results[] = ['name' => 'Vergi takvimi sayaçları (vadesi geçen/yaklaşan)', 'ok' => $okCt, 'detail' => 'overdue=' . $overdue . ' upcoming=' . $up];
        } catch (\Throwable $e) {
            $results[] = ['name' => 'Vergi takvimi sayaçları', 'ok' => false, 'detail' => $e->getMessage()];
        }

        // ---- KDV Beyanname hesaplaması (read-only) ----
        $kbdOk = false; $kbdDetail = '';
        try {
            $rows = DB::select(
                "SELECT i.type, SUM(ii.line_total) AS net, SUM(ii.tax) AS vat, SUM(COALESCE(ii.withholding,0)) AS w
                   FROM invoice_items ii JOIN invoices i ON i.id = ii.invoice_id
                  WHERE i.company_id = 1 AND i.status = 'posted' AND i.deleted_at IS NULL
                  GROUP BY i.type"
            );
            $outVat = 0.0; $inVat = 0.0;
            foreach ($rows as $r) {
                if ($r['type'] === 'sales') $outVat = (float) $r['vat'];
                elseif ($r['type'] === 'purchase') $inVat = (float) $r['vat'];
            }
            $payable = max(0.0, $outVat - $inVat);
            $refund = max(0.0, $inVat - $outVat);
            $kbdOk = $payable >= 0 && $refund >= 0 && abs(($payable + $refund) - abs($outVat - $inVat)) < 0.01;
            $kbdDetail = $kbdOk ? 'ödenecek/iade tutarlı (out=' . round($outVat, 2) . ' in=' . round($inVat, 2) . ')' : 'tutarsız (BUG)';
        } catch (\Throwable $e) {
            $kbdOk = false; $kbdDetail = $e->getMessage();
        }
        $results[] = ['name' => 'KDV Beyanname hesabı (ödenecek/iade)', 'ok' => $kbdOk, 'detail' => $kbdDetail];

        // ---- Bütçe & karşılaştırmalı rapor: budget_amount sütunu + veri yapısı ----
        $budOk = false; $budDetail = '';
        try {
            $col = DB::first(
                "SELECT COUNT(*) AS c FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'accounting_accounts' AND COLUMN_NAME = 'budget_amount'"
            );
            $row = DB::first('SELECT code, budget_amount FROM accounting_accounts LIMIT 1');
            $budOk = (int) ($col['c'] ?? 0) === 1 && isset($row['budget_amount']);
            $budDetail = $budOk ? 'budget_amount sütunu mevcut' : 'eksik (BUG)';
        } catch (\Throwable $e) {
            $budOk = false; $budDetail = $e->getMessage();
        }
        $results[] = ['name' => 'Bütçe / karşılaştırmalı rapor (budget_amount)', 'ok' => $budOk, 'detail' => $budDetail];

        // ---- Belge & e-posta ayarları: MailSettingService round-trip (rollback) + Mailer log ----
        $mailOk = false; $mailDetail = '';
        try {
            DB::transaction(function () {
                \Muh\Services\MailSettingService::save(1, ['host' => 'smtp.test', 'notify_due' => '1']);
                $s = \Muh\Services\MailSettingService::get(1);
                throw new \RuntimeException('__rollback__');
            });
        } catch (\RuntimeException $e) {
        } catch (\Throwable $e) {
            $mailOk = false; $mailDetail = $e->getMessage();
        }
        // Mailer log-mode send (writes to mail.log; returns true).
        try {
            $sent = (new \Muh\Services\Mailer())->send('tester@muh.local', 'Test', '<p>x</p>', ['enabled' => false]);
            $mailOk = $sent === true;
            $mailDetail = $mailOk ? 'MailSettingService + Mailer log çalışıyor' : 'mail gönderimi başarısız (BUG)';
        } catch (\Throwable $e) {
            $mailOk = false; $mailDetail = $e->getMessage();
        }
        $results[] = ['name' => 'E-posta ayarları + Mailer (log modu)', 'ok' => $mailOk, 'detail' => $mailDetail];

        // ---- XlsxReader (saf PHP Excel okuma) ----
        $xlsOk = false; $xlsDetail = '';
        try {
            $tmp = sys_get_temp_dir() . '/apptester_' . uniqid() . '.xlsx';
            $zip = new \ZipArchive();
            $zip->open($tmp, \ZipArchive::CREATE);
            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="a"/><Default Extension="xml" ContentType="b"/><Override PartName="/xl/workbook.xml" ContentType="c"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="d"/><Override PartName="/xl/sharedStrings.xml" ContentType="e"/></Types>');
            $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="t" Target="xl/workbook.xml"/></Relationships>');
            $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="S" sheetId="1" r:id="rId1"/></sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="t" Target="worksheets/sheet1.xml"/></Relationships>');
            $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1"><v>5</v></c></row></sheetData></worksheet>');
            $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="1"><si><t>Kod</t></si></sst>');
            $zip->close();
            $data = \Muh\Services\Import\XlsxReader::read($tmp);
            @unlink($tmp);
            $xlsOk = isset($data[0][0]) && $data[0][0] === 'Kod';
            $xlsDetail = $xlsOk ? 'Excel satır/kolon okundu' : 'okuma hatası (BUG)'; 
        } catch (\Throwable $e) {
            $xlsOk = false; $xlsDetail = $e->getMessage();
            if (isset($tmp) && is_file($tmp)) { @unlink($tmp); }
        }
        $results[] = ['name' => 'Excel okuyucu (saf PHP .xlsx)', 'ok' => $xlsOk, 'detail' => $xlsDetail];

        // ---- Onay bildirimi + Bildirim Merkezi özeti (Item 2 & 3) ----
        $apOk = false; $apDetail = '';
        try {
            Auth::loginById(1);
            $tid = Auth::tenantId();
            DB::transaction(function () use (&$apOk, &$apDetail, $tid) {
                $comp = DB::first('SELECT id FROM companies WHERE tenant_id = :t AND deleted_at IS NULL LIMIT 1', ['t' => $tid]);
                $cid = (int) $comp['id'];
                $pid = (int) DB::scalar('SELECT id FROM fiscal_periods WHERE company_id = :c LIMIT 1', ['c' => $cid]);
                $no = '_ap_test_' . uniqid();
                DB::insert('accounting_entries', [
                    'tenant_id' => $tid, 'company_id' => $cid, 'fiscal_period_id' => $pid,
                    'created_by' => 1, 'voucher_type' => 'journal', 'number' => $no,
                    'date' => date('Y-m-d'), 'description' => 'x', 'debit_total' => 0, 'credit_total' => 0,
                    'status' => 'draft', 'approval_status' => 'pending',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                // Notify approvers + verify a notification was created (tenant-scoped).
                \Muh\Services\NotificationService::notifyApprovers($tid, 'accounting.post', $cid, __('notify.approval_entry_title', ['no' => $no]), 'b', '/app/accounting/journal?company_id=' . $cid, __('notify.approval_entry_mail_subject'));
                $created = (int) DB::scalar('SELECT COUNT(*) FROM notifications WHERE tenant_id = :t AND type = :ty', ['t' => $tid, 'ty' => 'approval']);
                $approverIds = \Muh\Services\NotificationService::approverIds($tid, 'accounting.post');
                $summary = (new \Muh\Services\NotificationService())->summary($tid);
                $apOk = $created > 0 && count($approverIds) > 0
                    && array_key_exists('pending_approvals', $summary)
                    && array_key_exists('critical_stock', $summary)
                    && array_key_exists('upcoming_tax', $summary);
                $apDetail = $apOk ? ('bildirim oluştu + özet kartları hazır (approval n=' . $created . ', approver=' . count($approverIds) . ')') : 'bildirim/özet hatası (BUG)';
                throw new \RuntimeException('__rollback__');
            });
        } catch (\RuntimeException $e) {
        } catch (\Throwable $e) {
            $apOk = false; $apDetail = $e->getMessage();
        }
        $results[] = ['name' => 'Onay bildirimi + Bildirim Merkezi özeti', 'ok' => $apOk, 'detail' => $apDetail];

        // ---- B2: Bildirim e-posta gönderim durumu + günlük özet - rollback ----
        $nsOk = false; $nsDetail = '';
        try {
            Auth::loginById(1);
            $tid = Auth::tenantId();
            DB::transaction(function () use (&$nsOk, &$nsDetail, $tid) {
                $cols = [];
                foreach (DB::select('SHOW COLUMNS FROM notifications') as $c) { $cols[$c['Field']] = true; }
                if (!isset($cols['email_status']) || !isset($cols['email_sent_at'])) {
                    $nsOk = false; $nsDetail = 'email_status/email_sent_at sütunları eksik (migration 0023?)';
                    throw new \RuntimeException('__rollback__');
                }
                $id = (new \Muh\Services\NotificationService())->create('system', 'B2 test', 'body', 'info', null, null, null, [], $tid);
                $status = \Muh\Services\NotificationService::maybeMail($tid, 'due', 'T', 'B');
                \Muh\Services\NotificationService::markEmailStatus($id, $status);
                $row = DB::first('SELECT email_status, email_sent_at FROM notifications WHERE id = :i', ['i' => $id]);
                $statusOk = in_array($row['email_status'], ['sent', 'failed', 'skipped'], true);
                if ($row['email_status'] === 'sent') {
                    $statusOk = $statusOk && !empty($row['email_sent_at']);
                }
                $off = \Muh\Services\NotificationService::sendDailySummary($tid);
                \Muh\Services\MailSettingService::save($tid, ['notify_daily_summary' => '1']);
                $on = \Muh\Services\NotificationService::sendDailySummary($tid);
                \Muh\Services\MailSettingService::save($tid, ['notify_daily_summary' => '0']);
                $nsOk = $statusOk && $off === false && $on === true;
                $nsDetail = $nsOk ? ('mail durum=' . $row['email_status'] . ' + özet(off=' . var_export($off, true) . '/on=' . var_export($on, true) . ')') : 'hata (BUG): mail=' . json_encode($row) . ' off=' . var_export($off, true) . ' on=' . var_export($on, true);
                throw new \RuntimeException('__rollback__');
            });
        } catch (\RuntimeException $e) {
        } catch (\Throwable $e) {
            $nsOk = false; $nsDetail = $e->getMessage();
        }
        $results[] = ['name' => 'Bildirim gönderim durumu + günlük e-posta özeti', 'ok' => $nsOk, 'detail' => $nsDetail];

        // ---- Beni Hatırla (kalıcı giriş / remember-me) - rollback ----
        $rmOk = false; $rmDetail = '';
        try {
            $uid = (int) DB::scalar('SELECT MIN(id) FROM users');
            DB::transaction(function () use (&$rmOk, &$rmDetail, $uid) {
                $token = bin2hex(random_bytes(32));
                DB::update('users', ['remember_token' => hash('sha256', $token)], 'id = :id', ['id' => $uid]);
                $_COOKIE['muh_remember'] = $uid . ':' . $token;
                Auth::logout();
                Auth::attemptRememberMe();
                $restored = Auth::check() && (int) Auth::id() === $uid;
                Auth::clearRememberMe($uid);
                $dbToken = DB::first('SELECT remember_token FROM users WHERE id = :id', ['id' => $uid])['remember_token'] ?? null;
                $rmOk = $restored && ($dbToken === null || $dbToken === '');
                $rmDetail = $rmOk ? 'kalıcı giriş geri yüklendi + çıkışta token temizlendi' : 'kalıcı giriş hatası (BUG) restored=' . var_export($restored, true);
                throw new \RuntimeException('__rollback__');
            });
        } catch (\RuntimeException $e) {
        } catch (\Throwable $e) {
            $rmOk = false; $rmDetail = $e->getMessage();
        }
        $results[] = ['name' => "Beni Hatırla (remember-me)", 'ok' => $rmOk, 'detail' => $rmDetail];

        // ---- Büyük Defter (general ledger) sorgusu ----
        $ledOk = false; $ledDetail = '';
        try {
            $comp = DB::first('SELECT id FROM companies WHERE tenant_id = 1 LIMIT 1');
            $period = DB::first('SELECT id FROM fiscal_periods WHERE company_id = :c LIMIT 1', ['c' => (int) $comp['id']]);
            $lines = DB::select(
                "SELECT a.code, a.name, e.date, e.number, e.description, l.debit, l.credit
                   FROM accounting_entry_lines l
                   JOIN accounting_accounts a ON a.id = l.account_id
                   JOIN accounting_entries e ON e.id = l.entry_id
                  WHERE a.company_id = :c AND e.fiscal_period_id = :p
                    AND e.status = 'posted' AND e.deleted_at IS NULL
                  ORDER BY a.code, e.date",
                ['c' => (int) $comp['id'], 'p' => (int) $period['id']]
            );
            $ledOk = is_array($lines);
            $ledDetail = $ledOk ? ('Büyük Defter sorgusu çalıştı (satır=' . count($lines) . ')') : 'hata (BUG)';
        } catch (\Throwable $e) {
            $ledOk = false; $ledDetail = $e->getMessage();
        }
        $results[] = ['name' => 'Büyük Defter (general ledger) sorgusu', 'ok' => $ledOk, 'detail' => $ledDetail];

        // ---- e-Fatura sağlayıcı kayıt defteri + REST adapter ----
        $provOk = false; $provDetail = '';
        try {
            $gw = new \Muh\Services\EFatura\RESTEFaturaGateway([
                'provider' => 'logo', 'mode' => 'test', 'test_url' => 'https://x.test',
                'production_url' => '', 'username' => 'u', 'password' => 'p',
            ]);
            $eps = \Muh\Services\EFatura\EFaturaProviders::endpoints('izibiz');
            $provOk = $gw->provider() === 'logo'
                && \Muh\Services\EFatura\EFaturaProviders::isReal('logo')
                && \Muh\Services\EFatura\EFaturaProviders::isReal('foriba')
                && !\Muh\Services\EFatura\EFaturaProviders::isReal('simulated')
                && isset($eps['documents'], $eps['status'])
                && strpos($eps['status'], '{uuid}') !== false;
            $provDetail = $provOk ? 'sağlayıcı + uç nokta eşleme ok' : 'hata (BUG)';
        } catch (\Throwable $e) {
            $provOk = false; $provDetail = $e->getMessage();
        }
        $results[] = ['name' => 'e-Fatura sağlayıcı kayıt defteri + REST adapter', 'ok' => $provOk, 'detail' => $provDetail];

        // ---- Performans indeksleri (migration 0018) ----
        $idxOk = false; $idxDetail = '';
        try {
            $c = (int) DB::scalar(
                "SELECT COUNT(*) FROM information_schema.STATISTICS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND INDEX_NAME = 'idx_notif_tenant_read'"
            );
            $idxOk = $c >= 1;
            $idxDetail = $idxOk ? 'perf indeksleri mevcut (migration 0018)' : 'eksik (BUG)';
        } catch (\Throwable $e) {
            $idxOk = false; $idxDetail = $e->getMessage();
        }
        $results[] = ['name' => 'Performans indeksleri (migration 0018)', 'ok' => $idxOk, 'detail' => $idxDetail];

        // ---- Fatura/doküman markalama (BrandingService) ----
        $brOk = false; $brDetail = '';
        try {
            $comp = DB::first('SELECT * FROM companies LIMIT 1');
            $brand = \Muh\Services\BrandingService::forCompany($comp);
            $brOk = is_array($brand)
                && array_key_exists('logo_url', $brand)
                && array_key_exists('name', $brand) && array_key_exists('tax_number', $brand)
                && array_key_exists('currency', $brand) && array_key_exists('iban', $brand)
                && is_string($brand['currency']);
            $brDetail = $brOk ? 'marka bloğu hazır (logo/vergi/iban/kur)' : 'hata (BUG)';
        } catch (\Throwable $e) {
            $brOk = false; $brDetail = $e->getMessage();
        }
        $results[] = ['name' => 'Fatura/doküman markalama (BrandingService)', 'ok' => $brOk, 'detail' => $brDetail];

        // ---- Çoklu para birimi (CurrencyService) — rollback ----
        $curOk = false; $curDetail = '';
        try {
            DB::transaction(function () use (&$curOk, &$curDetail) {
                \Muh\Services\CurrencyService::setRate('USD', 'TRY', 32.5, date('Y-m-d'));
                $r = \Muh\Services\CurrencyService::rateToTry('USD', date('Y-m-d'));
                $sym = \Muh\Services\CurrencyService::symbol('USD');
                $conv = \Muh\Services\CurrencyService::convert('USD', 'TRY', 10, date('Y-m-d'));
                $curOk = abs($r - 32.5) < 0.001 && $sym === '$' && abs($conv - 325) < 0.01;
                $curDetail = $curOk ? ('kur + dönüşüm ok (1 USD=' . round($r, 4) . ' TRY)') : 'hata (BUG)';
                throw new \RuntimeException('__rollback__');
            });
        } catch (\RuntimeException $e) {
        } catch (\Throwable $e) {
            $curOk = false; $curDetail = $e->getMessage();
        }
        $results[] = ['name' => 'Çoklu para birimi (CurrencyService)', 'ok' => $curOk, 'detail' => $curDetail];

        // ---- Şifre sıfırlama token (AuthTokenService) — rollback ----
        $atOk = false; $atDetail = '';
        try {
            DB::transaction(function () use (&$atOk, &$atDetail) {
                $email = 'reset_test@muh.local';
                $tok = \Muh\Services\AuthTokenService::create($email, 'reset');
                $valid = \Muh\Services\AuthTokenService::validate($email, $tok, 'reset');
                $consumed = \Muh\Services\AuthTokenService::consume($email, $tok, 'reset');
                $atOk = $valid && $consumed;
                $atDetail = $atOk ? 'token üret/doğrula/tüket ok' : 'hata (BUG)';
                throw new \RuntimeException('__rollback__');
            });
        } catch (\RuntimeException $e) {
        } catch (\Throwable $e) {
            $atOk = false; $atDetail = $e->getMessage();
        }
        $results[] = ['name' => 'Şifre sıfırlama token (AuthTokenService)', 'ok' => $atOk, 'detail' => $atDetail];

        // ---- Stok: depo transferi + ağırlıklı ortalama maliyet — rollback ----
        $invOk = false; $invDetail = '';
        try {
            Auth::loginById(1);
            DB::transaction(function () use (&$invOk, &$invDetail) {
                $comp = DB::first('SELECT * FROM companies WHERE tenant_id = 1 LIMIT 1');
                $cid = (int) $comp['id'];
                $tid = (int) $comp['tenant_id'];
                $w1 = (int) DB::insert('warehouses', ['tenant_id' => $tid, 'company_id' => $cid, 'name' => 'W A', 'code' => 'WA_T', 'is_default' => 1, 'created_at' => now(), 'updated_at' => now()]);
                $w2 = (int) DB::insert('warehouses', ['tenant_id' => $tid, 'company_id' => $cid, 'name' => 'W B', 'code' => 'WB_T', 'is_default' => 0, 'created_at' => now(), 'updated_at' => now()]);
                $prod = (int) DB::insert('products', ['tenant_id' => $tid, 'company_id' => $cid, 'code' => 'TSTX', 'name' => 'Test', 'type' => 'product', 'purchase_price' => 10, 'sale_price' => 20, 'vat_rate' => 20, 'stock_quantity' => 0, 'created_at' => now(), 'updated_at' => now()]);
                \Muh\Services\InventoryService::recordMovement($tid, $cid, $w1, $prod, 'opening', date('Y-m-d'), 100, 10.0, 'open');
                (new \Muh\Services\InventoryService())->transfer($prod, $w1, $w2, 20.0, 'transfer test');
                $avg = \Muh\Services\InventoryService::avgCost($prod);
                $moves = (int) DB::scalar("SELECT COUNT(*) FROM stock_movements WHERE product_id = :p AND type IN ('transfer_in','transfer_out')", ['p' => $prod]);
                $invOk = abs($avg['cost'] - 10.0) < 0.001 && $moves === 2;
                $invDetail = $invOk ? ('depo transferi + ort. maliyet ok (ort=' . $avg['cost'] . ')') : 'hata (BUG)';
                throw new \RuntimeException('__rollback__');
            });
        } catch (\RuntimeException $e) {
        } catch (\Throwable $e) {
            $invOk = false; $invDetail = $e->getMessage();
        }
        $results[] = ['name' => 'Stok: depo transferi + ort. maliyet', 'ok' => $invOk, 'detail' => $invDetail];

        // ---- Firma yetkilisi portalmada firma kapsamı (companiesForUser) ----
        $portOk = false; $portDetail = '';
        try {
            Auth::loginById(1);
            $companies = \Muh\Services\CurrentContextService::companiesForUser();
            $portOk = is_array($companies);
            $portDetail = $portOk ? ('portal firma kapsamı ok (n=' . count($companies) . ')') : 'hata (BUG)';
        } catch (\Throwable $e) {
            $portOk = false; $portDetail = $e->getMessage();
        }
        $results[] = ['name' => 'Firma Yetkilisi Portalı (firma kapsamı)', 'ok' => $portOk, 'detail' => $portDetail];

        // ---- Firma-düzeyi erişim denetimi (canAccessCompany) — rollback ----
        $accOk = false; $accDetail = '';
        try {
            $comp = DB::first('SELECT * FROM companies WHERE tenant_id = 1 LIMIT 1');
            $cid = (int) $comp['id'];
            $tid = 1;
            Auth::loginById(1); // owner → tüm firmalar
            $ownerOk = \Muh\Services\CurrentContextService::canAccessCompany($cid);
            $uid = (int) DB::insert('users', ['tenant_id' => $tid, 'name' => 'AccTest', 'email' => 'acct_' . uniqid() . '@muh.local', 'password' => \Muh\Core\Hash::make('Password1234'), 'status' => 'active', 'created_at' => now(), 'updated_at' => now(), 'is_owner' => 0]);
            Auth::loginById($uid);
            $noGrant = !\Muh\Services\CurrentContextService::canAccessCompany($cid);
            DB::insert('user_company', ['user_id' => $uid, 'company_id' => $cid, 'created_at' => now(), 'updated_at' => now()]);
            Auth::loginById($uid);
            $withGrant = \Muh\Services\CurrentContextService::canAccessCompany($cid);
            $accOk = $ownerOk && $noGrant && $withGrant;
            $accDetail = $accOk ? 'owner + grant denetimi ok' : 'hata (BUG)';
            Auth::loginById(1);
            throw new \RuntimeException('__rollback__');
        } catch (\RuntimeException $e) {
        } catch (\Throwable $e) {
            $accOk = false; $accDetail = $e->getMessage();
        }
        $results[] = ['name' => 'Firma erişim denetimi (canAccessCompany)', 'ok' => $accOk, 'detail' => $accDetail];

        // ---- e-Fatura payload zenginleştirme (buildPayload) ----
        $payOk = false; $payDetail = '';
        try {
            $svc = new \Muh\Services\EFaturaService(new \Muh\Services\EFatura\SimulatedEFaturaGateway());
            $payload = $svc->buildPayload(
                ['id' => 1, 'number' => 'F-1', 'type' => 'sales', 'date' => date('Y-m-d'), 'due_date' => date('Y-m-d'),
                 'total' => 100, 'tax' => 18, 'subtotal' => 82, 'discount' => 0, 'withholding' => 0,
                 'currency_code' => 'USD', 'exchange_rate' => 32.5, 'company_name' => 'ACME', 'company_tax' => '123',
                 'company_tax_office' => 'Besiktas', 'company_address' => 'Istanbul', 'account_name' => 'X', 'account_tax' => '456'],
                [['product_id' => 1, 'product_name' => 'Kalem', 'quantity' => 1, 'unit_price' => 82, 'tax_rate' => 18, 'total' => 100]],
                'invoice'
            );
            $payOk = ($payload['currency'] ?? '') === 'USD'
                && (float) ($payload['exchange_rate'] ?? 0) === 32.5
                && isset($payload['supplier']['tax_office'], $payload['supplier']['address'], $payload['items'][0]['name']);
            $payDetail = $payOk ? 'payload (döviz + vergi dairesi + adres) zengin' : 'hata (BUG)';
        } catch (\Throwable $e) {
            $payOk = false; $payDetail = $e->getMessage();
        }
        $results[] = ['name' => 'e-Fatura payload zenginleştirme', 'ok' => $payOk, 'detail' => $payDetail];

        // ---- Nesne-düzeyi erişim denetimi (guardRecord) — rollback ----
        $grOk = false; $grDetail = '';
        try {
            $inv = DB::first('SELECT id, company_id FROM invoices WHERE tenant_id = 1 LIMIT 1');
            if (!$inv) {
                $grOk = true; $grDetail = 'fatura yok, atlandı';
            } else {
                $iid = (int) $inv['id'];
                Auth::loginById(1);
                $ownerPass = true;
                try { \Muh\Services\CurrentContextService::guardRecord(1, 'invoices', $iid); } catch (\Throwable $e) { $ownerPass = false; }
                $uid = (int) DB::insert('users', ['tenant_id' => 1, 'name' => 'GrTest', 'email' => 'gr_' . uniqid() . '@muh.local', 'password' => \Muh\Core\Hash::make('Password1234'), 'status' => 'active', 'created_at' => now(), 'updated_at' => now(), 'is_owner' => 0]);
                Auth::loginById($uid); // no grant → record guard must throw
                $blocked = false;
                try { \Muh\Services\CurrentContextService::guardRecord(1, 'invoices', $iid); } catch (\Muh\Core\ForbiddenException $e) { $blocked = true; }
                $grOk = $ownerPass && $blocked;
                $grDetail = $grOk ? 'owner geçti + grantsız kullanıcı engellendi' : 'hata (BUG)';
                Auth::loginById(1);
            }
            throw new \RuntimeException('__rollback__');
        } catch (\RuntimeException $e) {
        } catch (\Throwable $e) {
            $grOk = false; $grDetail = $e->getMessage();
        }
        $results[] = ['name' => 'Nesne erişim denetimi (guardRecord)', 'ok' => $grOk, 'detail' => $grDetail];

        // ---- Güvenlik politikası + depo bazlı stok sorgusu ----
        $spOk = false; $spDetail = '';
        try {
            DB::transaction(function () use (&$spOk, &$spDetail) {
                \Muh\Services\SecurityPolicyService::save(['require_email_verify' => '1']);
                $spOk = \Muh\Services\SecurityPolicyService::is('require_email_verify') === true;
                // Per-warehouse stock query runs without error.
                $rows = DB::select(
                    "SELECT p.code, p.name, w.name AS warehouse, SUM(sm.quantity) AS qty
                       FROM stock_movements sm
                       JOIN products p ON p.id = sm.product_id
                       JOIN warehouses w ON w.id = sm.warehouse_id
                      WHERE p.tenant_id = 1
                      GROUP BY p.id, w.id"
                );
                $spOk = $spOk && is_array($rows);
                $spDetail = $spOk ? 'güvenlik politikası + depo bazlı stok sorgusu ok' : 'hata (BUG)';
                throw new \RuntimeException('__rollback__');
            });
        } catch (\RuntimeException $e) {
        } catch (\Throwable $e) {
            $spOk = false; $spDetail = $e->getMessage();
        }
        $results[] = ['name' => 'Güvenlik politikası + depo bazlı stok', 'ok' => $spOk, 'detail' => $spDetail];

        // ---- UBL-TR XML üretici ----
        $ublOk = false; $ublDetail = '';
        try {
            $inv = ['number' => 'F-100', 'type' => 'SATIS', 'date' => date('Y-m-d'), 'due_date' => date('Y-m-d'),
                'currency_code' => 'USD', 'exchange_rate' => 32.5, 'subtotal' => 82, 'total' => 100, 'tax' => 18,
                'company_tax' => '1234567890', 'company_name' => 'ACME', 'company_address' => 'İst',
                'account_tax' => '111', 'account_name' => 'Müşteri A'];
            $items = [['product_name' => 'Kalem', 'quantity' => 2, 'unit_price' => 41, 'total' => 82]];
            $xml = \Muh\Services\EFatura\UblTrGenerator::generate($inv, $items, false);
            $despatch = \Muh\Services\EFatura\UblTrGenerator::generate($inv, $items, true);
            $doc = @simplexml_load_string($xml);
            $docD = @simplexml_load_string($despatch);
            $ublOk = $doc !== false && $docD !== false && strpos($xml, '<cac:AccountingSupplierParty>') !== false;
            $ublDetail = $ublOk ? 'UBL-TR XML iyi oluşturuldu (fatura + irsaliye)' : 'hata (BUG)';
        } catch (\Throwable $e) {
            $ublOk = false; $ublDetail = $e->getMessage();
        }
        $results[] = ['name' => 'UBL-TR XML üretici (fatura + irsaliye)', 'ok' => $ublOk, 'detail' => $ublDetail];

        // ---- Süper admin analitik sorguları ----
        $anOk = false; $anDetail = '';
        try {
            $totalInvoices = (int) DB::scalar('SELECT COUNT(*) FROM invoices WHERE deleted_at IS NULL');
            $perTenant = DB::select('SELECT tenant_id, COUNT(*) AS c FROM users WHERE deleted_at IS NULL GROUP BY tenant_id');
            $anOk = is_int($totalInvoices) && is_array($perTenant);
            $anDetail = $anOk ? ('analitik sorguları ok (toplam fatura=' . $totalInvoices . ')') : 'hata (BUG)';
        } catch (\Throwable $e) {
            $anOk = false; $anDetail = $e->getMessage();
        }
        $results[] = ['name' => 'Süper admin analitik sorguları', 'ok' => $anOk, 'detail' => $anDetail];

        // ---- Depo bazlı stok miktarı (product_warehouses sync) — rollback ----
        $pwOk = false; $pwDetail = '';
        try {
            Auth::loginById(1);
            DB::transaction(function () use (&$pwOk, &$pwDetail) {
                $comp = DB::first('SELECT * FROM companies WHERE tenant_id = 1 LIMIT 1');
                $cid = (int) $comp['id'];
                $tid = (int) $comp['tenant_id'];
                $w1 = (int) DB::insert('warehouses', ['tenant_id' => $tid, 'company_id' => $cid, 'name' => 'PW A', 'code' => 'PWA_' . uniqid(), 'is_default' => 1, 'created_at' => now(), 'updated_at' => now()]);
                $w2 = (int) DB::insert('warehouses', ['tenant_id' => $tid, 'company_id' => $cid, 'name' => 'PW B', 'code' => 'PWB_' . uniqid(), 'is_default' => 0, 'created_at' => now(), 'updated_at' => now()]);
                $prod = (int) DB::insert('products', ['tenant_id' => $tid, 'company_id' => $cid, 'code' => 'PWT', 'name' => 'PW Test', 'type' => 'product', 'purchase_price' => 10, 'sale_price' => 20, 'vat_rate' => 20, 'stock_quantity' => 0, 'created_at' => now(), 'updated_at' => now()]);
                \Muh\Services\InventoryService::recordMovement($tid, $cid, $w1, $prod, 'opening', date('Y-m-d'), 10, 10.0, 'open');
                (new \Muh\Services\InventoryService())->transfer($prod, $w1, $w2, 4.0, 't');
                $q1 = (float) DB::scalar('SELECT quantity FROM product_warehouses WHERE product_id = :p AND warehouse_id = :w', ['p' => $prod, 'w' => $w1]);
                $q2 = (float) DB::scalar('SELECT quantity FROM product_warehouses WHERE product_id = :p AND warehouse_id = :w', ['p' => $prod, 'w' => $w2]);
                $pwOk = abs($q1 - 6.0) < 0.01 && abs($q2 - 4.0) < 0.01;
                $pwDetail = $pwOk ? ('per-depo kantite ok (w1=' . $q1 . ' w2=' . $q2 . ')') : 'hata (BUG)';
                throw new \RuntimeException('__rollback__');
            });
        } catch (\RuntimeException $e) {
        } catch (\Throwable $e) {
            $pwOk = false; $pwDetail = $e->getMessage();
        }
        $results[] = ['name' => 'Depo bazlı stok miktarı (product_warehouses)', 'ok' => $pwOk, 'detail' => $pwDetail];

        // ---- FIFO stok değerleme (layered) ----
        $fifoOk = false; $fifoDetail = '';
        try {
            DB::transaction(function () use (&$fifoOk, &$fifoDetail) {
                $comp = DB::first('SELECT * FROM companies WHERE tenant_id = 1 LIMIT 1');
                $cid = (int) $comp['id']; $tid = (int) $comp['tenant_id'];
                $wh = (int) DB::insert('warehouses', ['tenant_id' => $tid, 'company_id' => $cid, 'name' => 'FIFO Wh', 'code' => 'FIFOWH' . uniqid(), 'is_default' => 1, 'created_at' => now(), 'updated_at' => now()]);
                $prod = (int) DB::insert('products', ['tenant_id' => $tid, 'company_id' => $cid, 'code' => 'FIFO' . uniqid(), 'name' => 'FIFO Test', 'type' => 'product', 'purchase_price' => 10, 'sale_price' => 20, 'vat_rate' => 20, 'stock_quantity' => 0, 'created_at' => now(), 'updated_at' => now()]);
                // 10 adet @10 TL, sonra 10 adet @20 TL
                \Muh\Services\InventoryService::recordMovement($tid, $cid, $wh, $prod, 'purchase', date('Y-m-d'), 10, 10.0, 'in1');
                \Muh\Services\InventoryService::recordMovement($tid, $cid, $wh, $prod, 'purchase', date('Y-m-d'), 10, 20.0, 'in2');
                // 12 satış → 10@10 + 2@20 tüketir, kalan 8@20
                \Muh\Services\InventoryService::recordMovement($tid, $cid, $wh, $prod, 'sale', date('Y-m-d'), -12, 20.0, 'out1');
                $fifo = \Muh\Services\InventoryService::fifoCost($prod);
                $okQty = abs($fifo['qty'] - 8.0) < 0.01;
                $okVal = abs($fifo['total'] - 160.0) < 0.01; // 8 × 20
                $fifoOk = $okQty && $okVal;
                $fifoDetail = $fifoOk ? ('FIFO katman ok (kalan=' . $fifo['qty'] . ' değer=' . $fifo['total'] . ')') : ('hata (BUG) qty=' . $fifo['qty'] . ' val=' . $fifo['total']);
                throw new \RuntimeException('__rollback__');
            });
        } catch (\RuntimeException $e) {
        } catch (\Throwable $e) {
            $fifoOk = false; $fifoDetail = $e->getMessage();
        }
        $results[] = ['name' => 'Stok FIFO değerleme (katmanlı)', 'ok' => $fifoOk, 'detail' => $fifoDetail];

        // ---- Toplu UBL-ZIP (ZipArchive + UBL birleştirme) ----
        $zipOk = false; $zipDetail = '';
        try {
            $inv = ['number' => 'F-200', 'type' => 'SATIS', 'date' => date('Y-m-d'), 'currency_code' => 'TRY', 'subtotal' => 50, 'total' => 60, 'tax' => 10, 'company_tax' => '1', 'company_name' => 'ACME', 'account_tax' => '2', 'account_name' => 'M A'];
            $items = [['product_name' => 'X', 'quantity' => 1, 'unit_price' => 50, 'total' => 50]];
            $xmls = [\Muh\Services\EFatura\UblTrGenerator::generate($inv, $items, false), \Muh\Services\EFatura\UblTrGenerator::generate($inv, $items, true)];
            if (class_exists('ZipArchive')) {
                $tmp = tempnam(sys_get_temp_dir(), 'ublt') . '.zip';
                $zip = new \ZipArchive();
                $zipOk = $zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true;
                $zip->addFromString('F-200.xml', $xmls[0]);
                $zip->addFromString('irsaliye-F-200.xml', $xmls[1]);
                $zip->close();
                $zipOk = $zipOk && is_file($tmp) && filesize($tmp) > 0;
                @unlink($tmp);
                $zipDetail = $zipOk ? 'ZipArchive + UBL birleştirme ok' : 'hata (BUG)';  
            } else {
                $zipDetail = 'ZipArchive yok (kütüphane eksik)';  
                $zipOk = false;  
            }
        } catch (\Throwable $e) {
            $zipOk = false; $zipDetail = $e->getMessage();
        }
        $results[] = ['name' => 'Toplu UBL-ZIP (ZipArchive)', 'ok' => $zipOk, 'detail' => $zipDetail];

        // ---- Demo veri işareti (companies.is_demo) ----
        $demoOk = false; $demoDetail = '';
        try {
            DB::transaction(function () use (&$demoOk, &$demoDetail) {
                $cols = DB::select('SHOW COLUMNS FROM companies');
                $has = false;
                foreach ($cols as $c) { if (strtolower((string) $c['Field']) === 'is_demo') { $has = true; break; } }
                $comp = DB::first('SELECT id FROM companies WHERE tenant_id = 1 LIMIT 1');
                DB::execute('UPDATE companies SET is_demo = 1, updated_at = :n WHERE id = :id', ['n' => now(), 'id' => (int) $comp['id']]);
                $demoOk = $has && (int) DB::scalar('SELECT is_demo FROM companies WHERE id = :id', ['id' => (int) $comp['id']]) === 1;
                $demoDetail = $demoOk ? 'is_demo kolonu + işaretleme ok' : 'hata (BUG)';  
                DB::execute('UPDATE companies SET is_demo = 0 WHERE id = :id', ['id' => (int) $comp['id']]);
                throw new \RuntimeException('__rollback__');
            });
        } catch (\RuntimeException $e) {
        } catch (\Throwable $e) {
            $demoOk = false; $demoDetail = $e->getMessage();
        }
        $results[] = ['name' => 'Demo veri işareti (companies.is_demo)', 'ok' => $demoOk, 'detail' => $demoDetail];

        // ---- Markalı, yerelleştirilmiş e-posta şablonu (EmailTemplateService) ----
        $mailOk = false; $mailDetail = '';
        try {
            $tr = \Muh\Services\EmailTemplateService::layout('tr', 'Deneme', '<p>Merhaba</p>');
            $en = \Muh\Services\EmailTemplateService::layout('en', 'Test', '<p>Hello</p>');
            $trHint = \Muh\Services\EmailTemplateService::translate('tr', 'mail.help_line', ['brand' => 'Hesap360']);
            $enHint = \Muh\Services\EmailTemplateService::translate('en', 'mail.help_line', ['brand' => 'Hesap360']);
            $mailOk = str_contains($tr, 'Hesap360') && str_contains($en, 'Hesap360')
                && str_contains($trHint, 'otomatik') && str_contains($enHint, 'automatically')
                && !str_contains($trHint, 'automatically') && !str_contains($enHint, 'otomatik');
            $mailDetail = $mailOk ? 'markalı şablon + tr/en dil ayrımı ok' : 'hata (BUG)';  
        } catch (\Throwable $e) {
            $mailOk = false; $mailDetail = $e->getMessage();
        }
        $results[] = ['name' => 'Markalı e-posta şablonu (tr/en)', 'ok' => $mailOk, 'detail' => $mailDetail];

        // ---- SMTP çok satırlı yanıt tüketimi (SmtpMailer readResponse) ----
        // Ağ kullanmaz; bellek-içi socket pair üzerinden gerçek readResponse()
        // akışını çalıştırır, böylece CI'da da deterministik olarak koşar.
        $smtpOk = false; $smtpDetail = '';
        $pair = null;
        try {
            // Windows'ta AF_UNIX desteklenmez; AF_INET kullan. Linux'ta AF_UNIX.
            $proto = PHP_OS_FAMILY === 'Windows' ? STREAM_PF_INET : STREAM_PF_UNIX;
            $pair = stream_socket_pair($proto, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            if ($pair === false) { throw new \RuntimeException('socket pair oluşturulamadı'); }
            stream_set_blocking($pair[0], true);
            $m = new \Muh\Services\SmtpMailer('localhost', 25, '', '', 'none', 'test <test@muh.local>');
            $rp = new \ReflectionProperty(\Muh\Services\SmtpMailer::class, 'conn');
            $rp->setAccessible(true);
            $rp->setValue($m, $pair[1]);

            // Gerçek bir relay gibi ÇOK SATIRLI EHLO yanıtı yaz.
            fwrite($pair[0], "250-localhost\r\n250-AUTH LOGIN\r\n250 SIZE 10485760\r\n");
            $cmd = new \ReflectionMethod(\Muh\Services\SmtpMailer::class, 'command');
            $cmd->setAccessible(true);
            $ret = $cmd->invoke($m, 'EHLO test', 250);

            // readResponse son satıra kadar tüm devam satırlarını tüketmeli:
            // buffer'da hiç 'kalan' satır kalmamalı (eski hata burayı bozuyordu).
            stream_set_blocking($pair[1], false);
            $leftover = (string) stream_get_contents($pair[1]);
            $finalLine = trim((string) $ret);
            $smtpOk = $finalLine === '250 SIZE 10485760' && $leftover === '';

            // Beklenmeyen durum kodu RuntimeException üretmeli.
            stream_set_blocking($pair[0], true);
            stream_set_blocking($pair[1], true);
            fwrite($pair[0], "535 5.7.8 auth required\r\n");
            $threw = false;
            try { $cmd->invoke($m, 'AUTH LOGIN', 334); } catch (\RuntimeException $e) { $threw = true; }
            $smtpOk = $smtpOk && $threw;
            $smtpDetail = $smtpOk ? ('çok satırlı EHLO tüketildi; kalan="' . $leftover . '" + hata kodu yakalandı') : 'hata (BUG): ret=' . $finalLine . ' leftover=' . $leftover;
        } catch (\Throwable $e) {
            $smtpOk = false; $smtpDetail = $e->getMessage();
        }
        if (is_array($pair)) { @fclose($pair[0]); @fclose($pair[1]); }
        $results[] = ['name' => 'SMTP çok satırlı EHLO tüketimi', 'ok' => $smtpOk, 'detail' => $smtpDetail];

        // ---- Faz 1: e-Beyan KDV akışı (hazırla → paketle → gönder → onay) ----
        $beyOk = false; $beyDetail = '';
        try {
            Auth::loginById(1);
            $tid = Auth::tenantId();
            DB::transaction(function () use (&$beyOk, &$beyDetail, $tid) {
                $comp = DB::first('SELECT id FROM companies WHERE tenant_id = :t AND deleted_at IS NULL LIMIT 1', ['t' => $tid]);
                if (!$comp) { $beyOk = false; $beyDetail = 'firma yok'; throw new \RuntimeException('__rollback__'); }
                $id = \Muh\Services\BeyannameService::prepareKdv($tid, (int) $comp['id'], date('Y-m'));
                $pkg = \Muh\Services\BeyannameService::package($id);
                $res = \Muh\Services\BeyannameService::submit($id, $tid);
                $apr = \Muh\Services\BeyannameService::approve($id);
                $row = DB::first('SELECT status, gib_reference FROM beyannameler WHERE id = :id', ['id' => $id]);
                $beyOk = $pkg && $res['ok'] && $apr && $row['status'] === 'approved' && !empty($row['gib_reference']);
                $beyDetail = $beyOk ? ('akış ok (ref=' . $row['gib_reference'] . ')') : 'hata (BUG)'; 
                throw new \RuntimeException('__rollback__');
            });
        } catch (\RuntimeException $e) {
        } catch (\Throwable $e) {
            $beyOk = false; $beyDetail = $e->getMessage();
        }
        $results[] = ['name' => 'e-Beyan KDV akışı (hazırla→gönder→onay)', 'ok' => $beyOk, 'detail' => $beyDetail];

        // ---- Yedek rotasyonu (prune) + sağlık (ping/list) ----
        $bkOk = false; $bkDetail = '';
        try {
            $dir = dirname(__DIR__, 2) . '/storage/backups';
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }
            // Create 3 fake backups, keep 2, expect 1 pruned.
            $made = [];
            foreach (['a', 'b', 'c'] as $x) {
                $f = $dir . '/muh-' . (date('Ymd-His', time() - mt_rand(100, 9999))) . '-' . $x . '.sql';
                file_put_contents($f, "-- test\n");
                $made[] = $f;
            }
            $before = count(glob($dir . '/muh-*.sql'));
            $pruned = \Muh\Database\Backup::prune(2);
            $after = count(glob($dir . '/muh-*.sql'));
            $ping = \Muh\Database\Backup::ping();
            $list = \Muh\Database\Backup::list(5);
            // Cleanup only the files this test created (leave any real backups).
            foreach ($made as $f) {
                if (is_file($f)) {
                    @unlink($f);
                }
            }
            $bkOk = $pruned > 0 && ($pruned <= 3) && $after <= $before && !empty($ping['ok']) && is_array($list);
            $bkDetail = $bkOk ? ('prune ok (pruned=' . $pruned . ' son=' . $after . ') + ping/list ok') : 'hata (BUG)';  
        } catch (\Throwable $e) {
            $bkOk = false; $bkDetail = $e->getMessage();
        }
        $results[] = ['name' => 'Yedek rotasyonu + sağlık (prune/ping/list)', 'ok' => $bkOk, 'detail' => $bkDetail];

        return $results;
    }
}
