<?php
/** @var string $content */
use Muh\Core\Auth;
use Muh\Core\Session;
use Muh\Core\Translator;
$locale = Translator::instance()->locale();
$dark = Session::get('theme') === 'dark';
$user = Auth::user();
function adminNav(string $path, string $label): string {
    $current = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
    $target = rtrim($path, '/');
    if ($target === '/admin') {
        // Dashboard yalnızca tam /admin adresinde aktif olur (alt sayfalarda değil).
        $active = ($current === '/admin');
    } else {
        $active = ($current === $target) || str_starts_with($current, $target . '/');
    }
    $cls = $active ? 'text-brand-600 bg-brand-50' : 'text-slate-600 hover:bg-slate-100';
    return '<a href="' . e(url($path)) . '" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium ' . $cls . '">' . e($label) . '</a>';
}
$menu = [
    '/admin' => __('admin.dashboard'),
    '/admin/tenants' => __('admin.tenants'),
    '/admin/subscriptions' => __('admin.subscriptions'),
    '/admin/plans' => __('admin.plans'),
    '/admin/audit' => __('admin.audit'),
    '/admin/admins' => __('admin.admins'),
    '/admin/settings' => __('admin.platform_settings'),
];
?>
<!DOCTYPE html>
<html lang="<?= e($locale) ?>" dir="ltr" class="<?= $dark ? 'dark' : '' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="<?= e(asset('assets/img/favicon.ico')) ?>">
    <title>Hesap360 · <?= e(__('admin.title')) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
    <script>tailwind.config = { theme: { extend: { colors: { brand: {50:'#eef4ff',100:'#dbe7ff',500:'#3b6cff',600:'#2b55e0',700:'#1f3fa8'} } } } }</script>
</head>
<body class="bg-slate-100 text-slate-800 antialiased">
<div class="flex h-screen overflow-hidden">

    <aside class="hidden md:flex flex-col w-64 bg-white border-r border-slate-200">
        <div class="p-4 border-b border-slate-100">
            <div class="flex items-center gap-2">
                <img src="<?= e(asset('assets/img/logo-mark.svg')) ?>" alt="Hesap360" class="w-9 h-9">
                <div>
                    <div class="font-bold text-slate-900 leading-none">Hesap360</div>
                    <div class="text-[11px] text-brand-600 font-semibold"><?= e(__('admin.title')) ?></div>
                </div>
            </div>
        </div>
        <nav class="flex-1 p-3 space-y-1">
            <?php foreach ($menu as $path => $label): echo adminNav($path, $label); endforeach; ?>
        </nav>
        <div class="p-3 border-t border-slate-100 text-xs text-slate-400">
            <div class="font-medium text-slate-600 mb-1"><?= e($user['name'] ?? '') ?></div>
            <div><?= e($user['email'] ?? '') ?></div>
        </div>
    </aside>

    <div class="flex-1 flex flex-col overflow-hidden">
        <header class="bg-white border-b border-slate-200 px-4 py-3 flex items-center justify-between">
            <span class="font-semibold text-slate-700"><?= e(__('admin.title')) ?></span>
            <div class="flex items-center gap-3">
                <a href="<?= e(url('/theme?mode=' . ($dark ? 'light' : 'dark') . '&return=/admin')) ?>" class="p-2 rounded-lg hover:bg-slate-100" title="Toggle theme"><?= $dark ? '☀' : '☾' ?></a>
                <a href="<?= e(url('/app/profile')) ?>" class="p-2 rounded-lg hover:bg-slate-100" title="<?= e(__('nav.profile')) ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 21v-2a4 4 0 00-4-4H7a4 4 0 00-4 4v2M15 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                </a>
                <form method="post" action="<?= e(url('/logout')) ?>">
                    <?= csrf_field() ?>
                    <button class="text-sm px-3 py-2 rounded-lg text-slate-500 hover:bg-slate-100 font-medium" type="submit"><?= e(__('auth.logout')) ?></button>
                </form>
            </div>
        </header>
        <main class="flex-1 overflow-y-auto p-4 md:p-6">
            <?php if ($flash = Session::getFlash('success')): ?><div class="mb-4 px-4 py-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm"><?= e($flash) ?></div><?php endif; ?>
            <?php if ($flash = Session::getFlash('error')): ?><div class="mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm"><?= e($flash) ?></div><?php endif; ?>
            <?= $content ?>
        </main>
    </div>
</div>
</body>
</html>
