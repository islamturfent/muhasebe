<?php

declare(strict_types=1);

/**
 * Route registration. Returns a closure that registers routes on the router.
 */

use Muh\Core\Router;
use Muh\Controllers\HomeController;
use Muh\Controllers\AuthController;
use Muh\Controllers\OnboardingController;
use Muh\Controllers\DashboardController;
use Muh\Controllers\ThemeController;

return function (Router $router): void {
    // ---- Public / marketing ----
    $router->get('/', [HomeController::class, 'landing']);
    $router->get('/pricing', [HomeController::class, 'pricing']);
    $router->get('/locale', [HomeController::class, 'switchLocale']);
    $router->get('/theme', [ThemeController::class, 'toggle']);

    // ---- Auth (guest) ----
    $router->group(['middleware' => [\Muh\Middleware\GuestMiddleware::class, \Muh\Middleware\CsrfMiddleware::class]], function (\Muh\Core\RouterGroup $g): void {
        $g->get('/login', [AuthController::class, 'showLogin']);
        $g->get('/register', [AuthController::class, 'showRegister']);
        $g->post('/login', [AuthController::class, 'login']);
        $g->post('/register', [AuthController::class, 'register']);
    });

    // ---- Authenticated (onboarding wizard, logout) ----
    $router->group(['middleware' => [\Muh\Middleware\AuthMiddleware::class, \Muh\Middleware\CsrfMiddleware::class]], function (\Muh\Core\RouterGroup $g): void {
        $g->post('/logout', [AuthController::class, 'logout']);
        $g->get('/onboarding', [OnboardingController::class, 'index']);
        $g->post('/onboarding/company', [OnboardingController::class, 'storeCompany']);
        $g->get('/onboarding/complete', [OnboardingController::class, 'complete']);
    });

    // ---- App (authenticated + tenant) ----
    $router->group(['middleware' => [\Muh\Middleware\AuthMiddleware::class, \Muh\Middleware\CsrfMiddleware::class, \Muh\Middleware\RateLimitMiddleware::class]], function (\Muh\Core\RouterGroup $g): void {
        $g->get('/app/dashboard', [DashboardController::class, 'office'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->post('/app/switch-company', [DashboardController::class, 'switchCompany'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->post('/app/impersonate/stop', [DashboardController::class, 'stopImpersonation'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->get('/app/company', [DashboardController::class, 'company'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->get('/app/companies', [\Muh\Controllers\CompanyController::class, 'index'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->get('/app/companies/create', [\Muh\Controllers\CompanyController::class, 'create'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->post('/app/companies', [\Muh\Controllers\CompanyController::class, 'store'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->get('/app/companies/{id}', [\Muh\Controllers\CompanyController::class, 'show'], [\Muh\Middleware\TenantMiddleware::class]);

        $g->get('/app/current-accounts', [\Muh\Controllers\CurrentAccountController::class, 'index'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->get('/app/customers', function (\Muh\Core\Request $request) {
            return \Muh\Core\Response::redirect('/app/current-accounts?type=customer');
        }, [\Muh\Middleware\TenantMiddleware::class]);
        $g->get('/app/current-accounts/create', [\Muh\Controllers\CurrentAccountController::class, 'create'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->post('/app/current-accounts', [\Muh\Controllers\CurrentAccountController::class, 'store'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->get('/app/current-accounts/{id}/edit', [\Muh\Controllers\CurrentAccountController::class, 'edit'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->post('/app/current-accounts/{id}', [\Muh\Controllers\CurrentAccountController::class, 'update'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->post('/app/current-accounts/{id}/delete', [\Muh\Controllers\CurrentAccountController::class, 'destroy'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->get('/app/current-accounts/{id}', [\Muh\Controllers\CurrentAccountController::class, 'show'], [\Muh\Middleware\TenantMiddleware::class]);

        $g->get('/app/inventory', [\Muh\Controllers\ProductController::class, 'index'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->get('/app/inventory/create', [\Muh\Controllers\ProductController::class, 'create'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->post('/app/inventory', [\Muh\Controllers\ProductController::class, 'store'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->get('/app/inventory/{id}/edit', [\Muh\Controllers\ProductController::class, 'edit'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->post('/app/inventory/{id}', [\Muh\Controllers\ProductController::class, 'update'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->post('/app/inventory/{id}/delete', [\Muh\Controllers\ProductController::class, 'destroy'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->get('/app/inventory/{id}', [\Muh\Controllers\ProductController::class, 'show'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->post('/app/inventory/{id}/stock-take', [\Muh\Controllers\ProductController::class, 'stockTake'], [\Muh\Middleware\TenantMiddleware::class]);

        $g->get('/app/inventory/warehouses', [\Muh\Controllers\WarehouseController::class, 'index'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->get('/app/inventory/warehouses/create', [\Muh\Controllers\WarehouseController::class, 'create'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->post('/app/inventory/warehouses', [\Muh\Controllers\WarehouseController::class, 'store'], [\Muh\Middleware\TenantMiddleware::class]);

        $g->get('/app/invoices', [\Muh\Controllers\InvoiceController::class, 'index'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->get('/app/invoices/create', [\Muh\Controllers\InvoiceController::class, 'create'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->get('/app/invoices/bulk/create', [\Muh\Controllers\InvoiceController::class, 'bulkCreate'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->post('/app/invoices/bulk', [\Muh\Controllers\InvoiceController::class, 'bulk'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->post('/app/invoices', [\Muh\Controllers\InvoiceController::class, 'store'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->get('/app/invoices/{id}', [\Muh\Controllers\InvoiceController::class, 'show'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->post('/app/invoices/{id}/efatura', [\Muh\Controllers\InvoiceController::class, 'sendEfatura'], [\Muh\Middleware\TenantMiddleware::class]);

        $g->get('/app/accounting', [\Muh\Controllers\AccountingController::class, 'index'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->get('/app/accounting/journal', [\Muh\Controllers\AccountingController::class, 'journal'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->get('/app/accounting/trial-balance', [\Muh\Controllers\AccountingController::class, 'trialBalance'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->get('/app/accounting/balance-sheet', [\Muh\Controllers\AccountingController::class, 'balanceSheet'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->get('/app/accounting/income-statement', [\Muh\Controllers\AccountingController::class, 'incomeStatement'], [\Muh\Middleware\TenantMiddleware::class]);

        // Cash (kasa)
        $g->get('/app/cash', [\Muh\Controllers\CashController::class, 'index'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->get('/app/cash/create', [\Muh\Controllers\CashController::class, 'create'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->post('/app/cash', [\Muh\Controllers\CashController::class, 'store'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->get('/app/cash/{id}', [\Muh\Controllers\CashController::class, 'show'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->post('/app/cash/{id}/transaction', [\Muh\Controllers\CashController::class, 'transaction'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->post('/app/cash/{id}/virman', [\Muh\Controllers\CashController::class, 'virman'], [\Muh\Middleware\TenantMiddleware::class]);

        // Bank
        $g->get('/app/bank', [\Muh\Controllers\BankController::class, 'index'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->get('/app/bank/create', [\Muh\Controllers\BankController::class, 'create'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->post('/app/bank', [\Muh\Controllers\BankController::class, 'store'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->get('/app/bank/{id}', [\Muh\Controllers\BankController::class, 'show'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->post('/app/bank/{id}/transaction', [\Muh\Controllers\BankController::class, 'transaction'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->post('/app/bank/{id}/virman', [\Muh\Controllers\BankController::class, 'virman'], [\Muh\Middleware\TenantMiddleware::class]);

        // Checks & promissory notes
        $g->get('/app/checks', [\Muh\Controllers\CheckController::class, 'index'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->get('/app/checks/create', [\Muh\Controllers\CheckController::class, 'create'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->post('/app/checks', [\Muh\Controllers\CheckController::class, 'store'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->post('/app/checks/{id}/check/status', [\Muh\Controllers\CheckController::class, 'updateStatus'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->get('/app/checks/notes', [\Muh\Controllers\CheckController::class, 'index', 'note'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->get('/app/checks/notes/create', [\Muh\Controllers\CheckController::class, 'create', 'note'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->post('/app/checks/notes', [\Muh\Controllers\CheckController::class, 'store', 'note'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->post('/app/checks/{id}/note/status', [\Muh\Controllers\CheckController::class, 'updateStatus', 'note'], [\Muh\Middleware\TenantMiddleware::class]);

        // Reports export (Phase 9)
        $g->get('/app/reports', [\Muh\Controllers\ReportsController::class, 'index'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->get('/app/reports/mizan/export', [\Muh\Controllers\ReportsController::class, 'mizan'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->get('/app/reports/yevmiye/export', [\Muh\Controllers\ReportsController::class, 'yevmiye'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->get('/app/reports/bilanco/export', [\Muh\Controllers\ReportsController::class, 'bilanco'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->get('/app/reports/gelir/export', [\Muh\Controllers\ReportsController::class, 'gelir'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->get('/app/reports/cari/export', [\Muh\Controllers\ReportsController::class, 'cari'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->get('/app/reports/kdv/export', [\Muh\Controllers\ReportsController::class, 'kdv'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->get('/app/reports/stok/export', [\Muh\Controllers\ReportsController::class, 'stok'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->get('/app/reports/satis/export', [\Muh\Controllers\ReportsController::class, 'satis'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->get('/app/reports/alis/export', [\Muh\Controllers\ReportsController::class, 'alis'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->get('/app/reports/kasa/export', [\Muh\Controllers\ReportsController::class, 'kasa'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->get('/app/reports/banka/export', [\Muh\Controllers\ReportsController::class, 'banka'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->get('/app/reports/karlilik/export', [\Muh\Controllers\ReportsController::class, 'karlilik'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->get('/app/reports/borc-alacak/export', [\Muh\Controllers\ReportsController::class, 'borcAlacak'], [\Muh\Middleware\TenantMiddleware::class]);

        // Subscription (Phase 10)
        $g->get('/app/profile', [\Muh\Controllers\ProfileController::class, 'show'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->post('/app/profile', [\Muh\Controllers\ProfileController::class, 'update'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->get('/app/settings', [\Muh\Controllers\SettingsController::class, 'index'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->get('/app/settings/subscription', [\Muh\Controllers\SubscriptionController::class, 'index'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->post('/app/settings/subscription/subscribe', [\Muh\Controllers\SubscriptionController::class, 'subscribe'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->post('/app/settings/subscription/checkout', [\Muh\Controllers\SubscriptionController::class, 'checkout'], [\Muh\Middleware\TenantMiddleware::class]);

        // Documents (Phase 11)
        $g->get('/app/documents', [\Muh\Controllers\DocumentController::class, 'index'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->post('/app/documents', [\Muh\Controllers\DocumentController::class, 'store'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->get('/app/documents/{id}/download', [\Muh\Controllers\DocumentController::class, 'download'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->post('/app/documents/{id}/delete', [\Muh\Controllers\DocumentController::class, 'destroy'], [\Muh\Middleware\TenantMiddleware::class]);

        // Notifications (Phase 11)
        $g->get('/app/notifications', [\Muh\Controllers\NotificationsController::class, 'index'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->post('/app/notifications/{id}/read', [\Muh\Controllers\NotificationsController::class, 'markRead'], [\Muh\Middleware\TenantMiddleware::class]);
                $g->post('/app/notifications/mark-all-read', [\Muh\Controllers\NotificationsController::class, 'markAllRead'], [\Muh\Middleware\TenantMiddleware::class]);

        // Tax rates (KDV / tevkifat) management
        $g->get('/app/tax-rates', [\Muh\Controllers\TaxRateController::class, 'index'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->get('/app/tax-rates/create', [\Muh\Controllers\TaxRateController::class, 'create'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->post('/app/tax-rates', [\Muh\Controllers\TaxRateController::class, 'store'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->post('/app/tax-rates/{id}/delete', [\Muh\Controllers\TaxRateController::class, 'destroy'], [\Muh\Middleware\TenantMiddleware::class]);

        // Branches (şube) management
        $g->get('/app/branches', [\Muh\Controllers\BranchController::class, 'index'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->get('/app/branches/create', [\Muh\Controllers\BranchController::class, 'create'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->post('/app/branches', [\Muh\Controllers\BranchController::class, 'store'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->post('/app/branches/{id}/delete', [\Muh\Controllers\BranchController::class, 'destroy'], [\Muh\Middleware\TenantMiddleware::class]);

        // Users & invitations (Phase 19)
        $g->get('/app/users', [\Muh\Controllers\UserController::class, 'index'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->get('/app/users/invite', [\Muh\Controllers\UserController::class, 'invite'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->post('/app/users/invite', [\Muh\Controllers\UserController::class, 'store'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->get('/app/users/{id}', [\Muh\Controllers\UserController::class, 'show'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->post('/app/users/{id}/company', [\Muh\Controllers\UserController::class, 'attachCompany'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->post('/app/users/{id}/company/{cid}/remove', [\Muh\Controllers\UserController::class, 'removeCompany'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->post('/app/users/invites/{id}/cancel', [\Muh\Controllers\UserController::class, 'cancelInvite'], [\Muh\Middleware\TenantMiddleware::class]);

        // Global search
        $g->get('/app/search', [\Muh\Controllers\SearchController::class, 'index'], [\Muh\Middleware\TenantMiddleware::class]);

        // Import / export
        $g->get('/app/import', [\Muh\Controllers\ImportController::class, 'index'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->post('/app/import/upload', [\Muh\Controllers\ImportController::class, 'upload'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->post('/app/import/run', [\Muh\Controllers\ImportController::class, 'run'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->get('/app/import/template/{type}', [\Muh\Controllers\ImportController::class, 'template'], [\Muh\Middleware\TenantMiddleware::class]);

        // Security & audit (Phase 12)
        $g->get('/app/settings/security', [\Muh\Controllers\SecurityController::class, 'show'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->post('/app/settings/security/mfa/enable', [\Muh\Controllers\SecurityController::class, 'enableMfa'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->post('/app/settings/security/mfa/verify', [\Muh\Controllers\SecurityController::class, 'verifyMfa'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->post('/app/settings/security/mfa/disable', [\Muh\Controllers\SecurityController::class, 'disableMfa'], [\Muh\Middleware\TenantMiddleware::class]);
        $g->get('/app/audit', [\Muh\Controllers\AuditLogController::class, 'index'], [\Muh\Middleware\TenantMiddleware::class]);

        $g->get('/api/health', function () {
            return ['ok' => true, 'time' => now()];
        });
    });

    // ---- Super-admin panel (owner / assigned system admins only) ----
    $router->group(['middleware' => [\Muh\Middleware\AuthMiddleware::class, \Muh\Middleware\CsrfMiddleware::class, \Muh\Middleware\AdminMiddleware::class]], function (\Muh\Core\RouterGroup $g): void {
        $g->get('/admin', [\Muh\Controllers\AdminController::class, 'index']);
        $g->get('/admin/tenants', [\Muh\Controllers\AdminController::class, 'tenants']);
        $g->get('/admin/tenants/{id}', [\Muh\Controllers\AdminController::class, 'tenantShow']);
        $g->post('/admin/tenants/{id}/toggle', [\Muh\Controllers\AdminController::class, 'toggleTenant']);
        $g->post('/admin/tenants/{id}/impersonate', [\Muh\Controllers\AdminController::class, 'impersonate']);
        $g->post('/admin/tenants/{id}/plan', [\Muh\Controllers\AdminController::class, 'setTenantPlan']);
        $g->get('/admin/audit', [\Muh\Controllers\AdminController::class, 'audit']);
        $g->get('/admin/subscriptions', [\Muh\Controllers\AdminController::class, 'subscriptions']);
        $g->get('/admin/plans', [\Muh\Controllers\AdminController::class, 'plans']);
        $g->post('/admin/plans', [\Muh\Controllers\AdminController::class, 'planStore']);
        $g->post('/admin/plans/{id}', [\Muh\Controllers\AdminController::class, 'planUpdate']);
        $g->post('/admin/plans/{id}/delete', [\Muh\Controllers\AdminController::class, 'planDelete']);
        $g->get('/admin/settings', [\Muh\Controllers\AdminController::class, 'settings']);
        $g->post('/admin/settings', [\Muh\Controllers\AdminController::class, 'saveSettings']);
        $g->get('/admin/admins', [\Muh\Controllers\AdminController::class, 'admins']);
        $g->post('/admin/admins/create', [\Muh\Controllers\AdminController::class, 'createAdmin']);
        $g->post('/admin/admins/promote', [\Muh\Controllers\AdminController::class, 'promoteAdmin']);
        $g->post('/admin/admins/{id}/revoke', [\Muh\Controllers\AdminController::class, 'revokeAdmin']);
    });

    // ---- Public invite acceptance (CSRF only; works logged-out or logged-in) ----
    $router->group(['middleware' => [\Muh\Middleware\CsrfMiddleware::class]], function (\Muh\Core\RouterGroup $g): void {
        $g->get('/invite/accept', [\Muh\Controllers\InviteController::class, 'acceptForm']);
        $g->post('/invite/accept', [\Muh\Controllers\InviteController::class, 'accept']);
    });

    // ---- Billing webhook (provider→app, auth via shared secret signature) ----
    $router->post('/api/billing/webhook', [\Muh\Controllers\BillingWebhookController::class, 'handle']);
};
