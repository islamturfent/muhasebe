<?php

declare(strict_types=1);

/**
 * Reference data seeder: plans, roles, permissions, tax rates, currencies.
 */
use Muh\Core\DB;
use Muh\Models\Permission;

return function (): void {
    $now = now();

    // ---------------- Permissions ----------------
    $definitions = [
        'dashboard' => ['view' => 'View dashboard', 'export' => 'Export dashboard'],
        'company'   => ['read' => 'View companies', 'create' => 'Create companies', 'update' => 'Edit companies', 'delete' => 'Delete companies'],
        'customer'  => ['read' => 'View customers', 'create' => 'Create customers', 'update' => 'Edit customers', 'delete' => 'Delete customers'],
        'current_account' => ['read' => 'View current accounts', 'create' => 'Create current accounts', 'update' => 'Edit current accounts', 'delete' => 'Delete current accounts', 'statement' => 'View statements'],
        'invoice'   => ['read' => 'View invoices', 'create' => 'Create invoices', 'update' => 'Edit invoices', 'delete' => 'Delete invoices', 'approve' => 'Approve invoices', 'send' => 'Send invoices'],
        'order'     => ['read' => 'View orders', 'create' => 'Create orders', 'update' => 'Edit orders', 'delete' => 'Delete orders', 'approve' => 'Approve orders'],
        'inventory' => ['read' => 'View inventory', 'create' => 'Create stock cards', 'update' => 'Edit stock cards', 'delete' => 'Delete stock cards', 'adjust' => 'Stock adjustments'],
        'warehouse' => ['read' => 'View warehouses', 'create' => 'Create warehouses', 'update' => 'Edit warehouses'],
        'cash'      => ['read' => 'View cash', 'create' => 'Cash transactions', 'update' => 'Edit cash', 'delete' => 'Delete cash'],
        'bank'      => ['read' => 'View bank', 'create' => 'Bank transactions', 'update' => 'Edit bank', 'delete' => 'Delete bank'],
        'check'     => ['read' => 'View checks & notes', 'create' => 'Create checks & notes', 'update' => 'Edit checks & notes', 'delete' => 'Delete checks & notes'],
        'accounting'=> ['read' => 'View accounting', 'create' => 'Create entries', 'update' => 'Edit entries', 'delete' => 'Delete entries', 'charter' => 'Manage chart of accounts', 'post' => 'Post entries'],
        'report'    => ['view' => 'View reports', 'export' => 'Export reports', 'print' => 'Print reports'],
        'document'  => ['read' => 'View documents', 'create' => 'Upload documents', 'delete' => 'Delete documents'],
        'user'      => ['read' => 'View users', 'invite' => 'Invite users', 'update' => 'Edit users', 'delete' => 'Delete users'],
        'role'      => ['read' => 'View roles', 'update' => 'Manage roles'],
        'tax'       => ['read' => 'View tax rates', 'update' => 'Manage tax rates'],
        'setting'   => ['read' => 'View settings', 'update' => 'Update settings'],
        'subscription' => ['view' => 'View subscription', 'update' => 'Manage subscription'],
        'audit'     => ['view' => 'View audit logs', 'export' => 'Export audit logs'],
        'import'    => ['run' => 'Run data imports', 'export' => 'Export data'],
    ];
    Permission::syncFrom($definitions);

    // ---------------- Roles ----------------
    $roles = [
        ['key' => 'owner', 'name' => json_encode(['tr' => 'Ofis Sahibi', 'en' => 'Office Owner']), 'is_system' => 1, 'is_owner' => 1, 'description' => 'Full access to everything'],
        ['key' => 'manager', 'name' => json_encode(['tr' => 'Ofis Yöneticisi', 'en' => 'Office Manager']), 'is_system' => 1, 'is_owner' => 0, 'description' => 'Manages office operations'],
        ['key' => 'cpa', 'name' => json_encode(['tr' => 'Mali Müşavir', 'en' => 'Certified Public Accountant']), 'is_system' => 1, 'is_owner' => 0, 'description' => 'Full accounting access'],
        ['key' => 'accountant', 'name' => json_encode(['tr' => 'Muhasebeci', 'en' => 'Accountant']), 'is_system' => 1, 'is_owner' => 0, 'description' => 'General accounting duties'],
        ['key' => 'staff', 'name' => json_encode(['tr' => 'Muhasebe Personeli', 'en' => 'Accounting Staff']), 'is_system' => 1, 'is_owner' => 0, 'description' => 'Operational data entry'],
        ['key' => 'intern', 'name' => json_encode(['tr' => 'Stajyer', 'en' => 'Intern']), 'is_system' => 1, 'is_owner' => 0, 'description' => 'Limited view + basic entry'],
        ['key' => 'client_authorized', 'name' => json_encode(['tr' => 'Müşteri Firma Yetkilisi', 'en' => 'Client Authorised']), 'is_system' => 1, 'is_owner' => 0, 'description' => 'Client portal access'],
        ['key' => 'viewer', 'name' => json_encode(['tr' => 'Sadece Görüntüleme', 'en' => 'Viewer']), 'is_system' => 1, 'is_owner' => 0, 'description' => 'Read-only access'],
    ];

    $roleIds = [];
    foreach ($roles as $role) {
        $existing = DB::first('SELECT id FROM roles WHERE `key` = :k', ['k' => $role['key']]);
        if ($existing) {
            DB::update('roles', $role, 'id = :id', ['id' => $existing['id']]);
            $roleIds[$role['key']] = (int) $existing['id'];
        } else {
            $roleIds[$role['key']] = (int) DB::insert('roles', $role);
        }
    }

    // ---------------- Role -> permission matrix ----------------
    $permIds = [];
    foreach (DB::select('SELECT id, `key` FROM permissions') as $p) {
        $permIds[$p['key']] = (int) $p['id'];
    }

    $matrix = [
        'owner' => array_keys($permIds),
        'manager' => array_keys($permIds),
        'cpa' => array_keys($permIds),
        'accountant' => [
            'company.read','customer.read','current_account.read','current_account.create','current_account.update','current_account.statement',
            'invoice.read','invoice.create','invoice.update','invoice.delete','invoice.approve',
            'order.read','order.create','order.update',
            'inventory.read','inventory.create','inventory.update','warehouse.read',
            'cash.read','cash.create','bank.read','bank.create','check.read','check.create','check.update',
            'accounting.read','accounting.create','accounting.charter','accounting.post','tax.read',
            'report.view','report.export','report.print','document.read','document.create',
        ],
        'staff' => [
            'company.read','customer.read','current_account.read','current_account.create',
            'invoice.read','invoice.create','invoice.update','order.read','order.create',
            'inventory.read','inventory.create','warehouse.read','cash.read','cash.create','bank.read','bank.create','check.read','check.create',
            'accounting.read','document.read','document.create','report.view',
        ],
        'intern' => [
            'company.read','customer.read','current_account.read','invoice.read','invoice.create',
            'inventory.read','report.view','document.read',
        ],
        'client_authorized' => [
            'dashboard.view','company.read','invoice.read','current_account.read','current_account.statement',
            'bank.read','cash.read','report.view','document.read',
        ],
        'viewer' => [
            'dashboard.view','company.read','customer.read','invoice.read','current_account.read','inventory.read',
            'bank.read','cash.read','accounting.read','report.view',
        ],
    ];

    DB::execute('DELETE FROM role_permission');
    foreach ($matrix as $roleKey => $perms) {
        if (!isset($roleIds[$roleKey])) {
            continue;
        }
        foreach ($perms as $permKey) {
            if (!isset($permIds[$permKey])) {
                continue;
            }
            DB::execute('INSERT IGNORE INTO role_permission (role_id, permission_id, created_at, updated_at) VALUES (:r, :p, :c1, :c2)', [
                'r' => $roleIds[$roleKey], 'p' => $permIds[$permKey], 'c1' => $now, 'c2' => $now,
            ]);
        }
    }

    // ---------------- Plans ----------------
    $plans = [
        ['code' => 'FREE', 'name' => json_encode(['tr' => 'Ücretsiz', 'en' => 'Free']), 'price_monthly' => 0, 'price_yearly' => 0,
         'features' => json_encode(['companies' => 1, 'users' => 1, 'warehouses' => 1, 'invoices' => 50, 'efatura' => false, 'storage_mb' => 100, 'reports' => true]),
         'is_active' => 1, 'sort_order' => 1],
        ['code' => 'PRO', 'name' => json_encode(['tr' => 'Profesyonel', 'en' => 'Professional']), 'price_monthly' => 499, 'price_yearly' => 4790,
         'features' => json_encode(['companies' => 10, 'users' => 5, 'warehouses' => 3, 'invoices' => 1000, 'efatura' => true, 'storage_mb' => 2000, 'reports' => true]),
         'is_active' => 1, 'sort_order' => 2],
        ['code' => 'BUSINESS', 'name' => json_encode(['tr' => 'İşletme', 'en' => 'Business']), 'price_monthly' => 1299, 'price_yearly' => 12470,
         'features' => json_encode(['companies' => 50, 'users' => 20, 'warehouses' => 10, 'invoices' => 10000, 'efatura' => true, 'storage_mb' => 20000, 'reports' => true]),
         'is_active' => 1, 'sort_order' => 3],
        ['code' => 'ENTERPRISE', 'name' => json_encode(['tr' => 'Kurumsal', 'en' => 'Enterprise']), 'price_monthly' => 0, 'price_yearly' => 0,
         'features' => json_encode(['companies' => 99999, 'users' => 99999, 'warehouses' => 99999, 'invoices' => 999999, 'efatura' => true, 'storage_mb' => 999999, 'reports' => true]),
         'is_active' => 1, 'sort_order' => 4],
    ];
    foreach ($plans as $plan) {
        $existing = DB::first('SELECT id FROM plans WHERE code = :c', ['c' => $plan['code']]);
        if ($existing) {
            DB::update('plans', $plan, 'id = :id', ['id' => $existing['id']]);
        } else {
            DB::insert('plans', $plan);
        }
    }

    // ---------------- Tax rates (KDV) - Turkey ----------------
    $taxes = [
        ['name' => json_encode(['tr' => 'KDV %0 (İstisna)', 'en' => 'VAT 0% (Exempt)']), 'rate' => 0.00, 'is_vat' => 1, 'is_withholding' => 0, 'is_default' => 0, 'is_active' => 1, 'sort_order' => 1],
        ['name' => json_encode(['tr' => 'KDV %1', 'en' => 'VAT 1%']), 'rate' => 1.00, 'is_vat' => 1, 'is_withholding' => 0, 'is_default' => 0, 'is_active' => 1, 'sort_order' => 2],
        ['name' => json_encode(['tr' => 'KDV %10', 'en' => 'VAT 10%']), 'rate' => 10.00, 'is_vat' => 1, 'is_withholding' => 0, 'is_default' => 1, 'is_active' => 1, 'sort_order' => 3],
        ['name' => json_encode(['tr' => 'KDV %20', 'en' => 'VAT 20%']), 'rate' => 20.00, 'is_vat' => 1, 'is_withholding' => 0, 'is_default' => 0, 'is_active' => 1, 'sort_order' => 4],
        ['name' => json_encode(['tr' => 'Tevkifat %10', 'en' => 'Withholding 10%']), 'rate' => 10.00, 'is_vat' => 0, 'is_withholding' => 1, 'is_default' => 0, 'is_active' => 1, 'sort_order' => 5],
        ['name' => json_encode(['tr' => 'Tevkifat %20', 'en' => 'Withholding 20%']), 'rate' => 20.00, 'is_vat' => 0, 'is_withholding' => 1, 'is_default' => 0, 'is_active' => 1, 'sort_order' => 6],
    ];
    foreach ($taxes as $tax) {
        DB::insert('tax_rates', array_merge(['tenant_id' => null], $tax, ['created_at' => $now, 'updated_at' => $now]));
    }

    // ---------------- Currencies ----------------
    $currencies = [
        ['code' => 'TRY', 'name' => 'Turkish Lira', 'symbol' => '₺'],
        ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$'],
        ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€'],
        ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£'],
    ];
    foreach ($currencies as $cur) {
        $existing = DB::first('SELECT id FROM currencies WHERE code = :c', ['c' => $cur['code']]);
        if (!$existing) {
            DB::insert('currencies', array_merge($cur, ['created_at' => $now, 'updated_at' => $now]));
        }
    }
};
