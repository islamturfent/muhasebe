<?php
/** @var string $content */
use Muh\Core\Auth;
use Muh\Core\DB;
use Muh\Core\Session;
use Muh\Core\Translator;

$locale = Translator::instance()->locale();
$dark = Session::get('theme') === 'dark';
$tenantId = Auth::tenantId();
$user = Auth::user();
$companies = [];
$activeCompany = null;
if ($tenantId) {
    $unreadCount = (new \Muh\Services\NotificationService())->unreadCount();
    $companies = \Muh\Services\CurrentContextService::companiesForUser();
    $activeCompanyId = \Muh\Services\SessionContext::companyId();
    foreach ($companies as $c) {
        if ((int)$c['id'] === (int)$activeCompanyId) {
            $activeCompany = $c;
            break;
        }
    }
}
/* Aktif menü eşleştirmesi: base path (örn. /muh) göz ardı edilir. */
function navItemActive(string $target, bool $exact = false): bool {
    $base = rtrim((string) parse_url(url('/'), PHP_URL_PATH), '/');
    $cur = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if ($base !== '' && str_starts_with($cur, $base)) {
        $cur = substr($cur, strlen($base));
    }
    $cur = rtrim($cur, '/') ?: '/';
    $q = $_SERVER['QUERY_STRING'] ?? '';
    $t = rtrim($target, '/') ?: '/';

    // Müşteriler (customers) — a filtered view of /app/current-accounts?type=customer.
    if ($target === '/app/customers') {
        return $cur === '/app/current-accounts' && str_contains($q, 'type=customer');
    }
    if ($target === '/app/current-accounts') {
        // Detail/create/transaction subpages keep Cari active.
        if (str_starts_with($cur, '/app/current-accounts/')) {
            return true;
        }
        // General list is active only when NOT the customer-only filter.
        return $cur === '/app/current-accounts' && !str_contains($q, 'type=customer');
    }

    if ($exact) {
        return $cur === $t;
    }
    return $cur === $t || str_starts_with($cur, $t . '/');
}
function navItem(string $path, string $label): string {
    $exact = ($path === '/app/dashboard'); // Dashboard yalnızca tam adreste aktif
    $active = navItemActive($path, $exact) ? 'text-brand-600 bg-brand-50' : 'text-slate-600 hover:bg-slate-100';
    return '<a href="' . e(url($path)) . '" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium ' . $active . '">' . e($label) . '</a>';
}
$menu = [
    '/app/dashboard' => __('nav.dashboard'),
    '/app/portal' => __('nav.portal'),
    '/app/companies' => __('nav.companies'),
    '/app/current-accounts' => __('nav.current_accounts'),
    '/app/customers' => __('nav.customers'),
    '/app/invoices' => __('nav.invoices'),
    '/app/inventory' => __('nav.inventory'),
    '/app/branches' => __('nav.branches'),
    '/app/tax-rates' => __('nav.tax_rates'),
    '/app/cash' => __('nav.cash'),
    '/app/bank' => __('nav.bank'),
    '/app/checks' => __('nav.checks'),
    '/app/accounting' => __('nav.accounting'),
    '/app/tax-calendar' => __('nav.tax_calendar'),
    '/app/reports' => __('nav.reports'),
    '/app/import' => __('import.title'),
    '/app/documents' => __('nav.documents'),
    '/app/users' => __('nav.users'),
    '/app/settings' => __('nav.settings'),
];
?>
<!DOCTYPE html>
<html lang="<?= e($locale) ?>" dir="ltr" class="<?= $dark ? 'dark' : '' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="<?= e(asset('assets/img/favicon.ico')) ?>">
    <title>Hesap360 · <?= e($activeCompany['name'] ?? __('app.all_companies')) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
    <script>
        tailwind.config = { theme: { extend: { colors: { brand: {50:'#eef4ff',100:'#dbe7ff',500:'#3b6cff',600:'#2b55e0',700:'#1f3fa8'} } } } }
    </script>
</head>
<body class="bg-slate-100 text-slate-800 antialiased">
<div class="flex h-screen overflow-hidden">

    <!-- Sidebar -->
    <aside class="hidden md:flex flex-col w-64 bg-white border-r border-slate-200">
        <div class="p-4 flex items-center gap-2 border-b border-slate-100">
            <img src="<?= e(asset('assets/img/logo-mark.svg')) ?>" alt="Hesap360" class="w-9 h-9">
            <div>
                <div class="font-bold text-slate-900 leading-none">Hesap360</div>
                <div class="text-[11px] text-slate-400"><?= e(__('common.tagline')) ?></div>
            </div>
        </div>
        <nav class="flex-1 p-3 space-y-1 overflow-y-auto">
            <?php foreach ($menu as $path => $label): echo navItem($path, $label); endforeach; ?>
        </nav>
        <div class="p-3 border-t border-slate-100 text-xs text-slate-400">
            <div class="font-medium text-slate-600 mb-1"><?= e($user['name'] ?? '') ?></div>
            <div><?= e($user['email'] ?? '') ?></div>
        </div>
    </aside>

    <!-- Main -->
    <div class="flex-1 flex flex-col overflow-hidden">

        <!-- Topbar -->
        <header class="bg-white border-b border-slate-200 px-4 py-3 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3 flex-1">
                <form method="post" action="<?= e(url('/app/switch-company')) ?>" class="flex items-center gap-2">
                    <?= csrf_field() ?>
                    <select name="company_id" onchange="this.form.submit()" class="text-sm border border-slate-200 rounded-lg px-3 py-2 bg-white focus:ring-2 focus:ring-brand-500 outline-none">
                        <option value=""><?= e(__('app.all_companies')) ?></option>
                        <?php foreach ($companies as $c): ?>
                            <option value="<?= e($c['id']) ?>" <?= $activeCompany && (int)$c['id']===(int)$activeCompany['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="hidden lg:inline text-xs text-slate-400"><?= e(__('nav.switch_company')) ?></span>
                </form>

                <form method="get" action="<?= e(url('/app/search')) ?>" class="hidden md:flex flex-1 max-w-md ml-4">
                    <input type="text" name="q" id="globalSearch" placeholder="<?= e(__('nav.search')) ?> (/) " class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-brand-500 outline-none">
                </form>
            </div>

            <div class="flex items-center gap-2">
                <a href="<?= e(url('/locale?locale=' . ($locale==='tr'?'en':'tr').'&return=' . urlencode(request_path()))) ?>" class="text-sm px-3 py-2 rounded-lg hover:bg-slate-100 font-medium">
                    <?= $locale==='tr' ? 'EN' : 'TR' ?>
                </a>
                <a href="<?= e(url('/theme?mode=' . ($dark ? 'light' : 'dark') . '&return=' . urlencode(request_path()))) ?>" class="p-2 rounded-lg hover:bg-slate-100" title="Toggle theme"><?php if ($dark): ?><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path stroke-linecap="round" d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg><?php else: ?><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z"/></svg><?php endif; ?></a>
                <a href="<?= e(url('/app/notifications')) ?>" class="relative p-2 rounded-lg hover:bg-slate-100" title="<?= e(__('nav.notifications')) ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2c0 .53-.21 1.04-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    <?php if ($unreadCount ?? 0 > 0): ?><span class="absolute -top-0.5 -right-0.5 w-4 h-4 bg-red-500 text-white rounded-full text-[10px] flex items-center justify-center"><?= (int) ($unreadCount ?? 0) ?></span><?php endif; ?>
                </a>
                <a href="<?= e(url('/app/profile')) ?>" class="p-2 rounded-lg hover:bg-slate-100" title="<?= e(__('nav.profile')) ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 21v-2a4 4 0 00-4-4H7a4 4 0 00-4 4v2M15 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                </a>
                <a href="<?= e(url('/app/settings')) ?>" class="p-2 rounded-lg hover:bg-slate-100" title="<?= e(__('nav.help')) ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.2 8.2A4 4 0 0112 6c2 0 3.5 1.5 3.5 3.5 0 2-2 2.5-2 4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </a>
                <form method="post" action="<?= e(url('/logout')) ?>">
                    <?= csrf_field() ?>
                    <button class="text-sm px-3 py-2 rounded-lg text-slate-500 hover:bg-slate-100 font-medium" type="submit"><?= e(__('auth.logout')) ?></button>
                </form>
            </div>
        </header>

        <?php if (!empty($activeCompany['is_demo'])): ?>
        <div class="bg-sky-100 border-b border-sky-300 px-4 py-2 text-center text-sm text-sky-800">
            🧪 <?= e(__('app.demo_banner', ['company' => $activeCompany['name'] ?? ''])) ?>
        </div>
        <?php endif; ?>

        <?php if ($impersonator = \Muh\Core\Session::get('_impersonator')): ?>
        <div class="bg-amber-100 border-b border-amber-300 px-4 py-2 flex items-center justify-center gap-3 text-sm text-amber-800">
            <span>👁 <?= e(__('admin.impersonation_bar')) ?></span>
            <form method="post" action="<?= e(url('/app/impersonate/stop')) ?>">
                <?= csrf_field() ?>
                <button class="px-3 py-1 rounded-lg bg-amber-800 text-white text-xs font-medium"><?= e(__('admin.impersonate_stop_btn')) ?></button>
            </form>
        </div>
        <?php endif; ?>
        <main class="flex-1 overflow-y-auto p-4 md:p-6">
            <?php if ($flash = \Muh\Core\Session::getFlash('success')): ?>
                <div class="mb-4 px-4 py-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm"><?= e($flash) ?></div>
            <?php endif; ?>
            <?php if ($flash = \Muh\Core\Session::getFlash('error')): ?>
                <div class="mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm"><?= e($flash) ?></div>
            <?php endif; ?>
            <?= $content ?>
        </main>
    </div>
</div>
<script>
// Global quick-search shortcut: press "/" or Ctrl/Cmd+K from anywhere to
// focus the top search box (unless already typing in an input/textarea).
document.addEventListener('keydown', function (e) {
  const el = document.getElementById('globalSearch');
  if (!el) return;
  const tag = (e.target && e.target.tagName) || '';
  const typing = tag === 'INPUT' || tag === 'TEXTAREA' || (e.target && e.target.isContentEditable);
  const want = e.key === '/' || ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k');
  if (want && !typing) {
    e.preventDefault();
    el.focus();
    el.select();
  }
});
</script>
</body>
</html>
