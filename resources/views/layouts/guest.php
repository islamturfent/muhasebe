<?php
/** @var string $content */
use Muh\Core\Translator;
use Muh\Core\Session;
$locale = Translator::instance()->locale();
$dark = Session::get('theme') === 'dark';
?><!DOCTYPE html>
<html lang="<?= e($locale) ?>" dir="ltr" class="<?= $dark ? 'dark' : '' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="<?= e(asset('assets/img/favicon.ico')) ?>">
    <title><?= e(__('common.app_name')) ?> · <?= e(__('common.tagline')) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {50:'#eef4ff',100:'#dbe7ff',500:'#3b6cff',600:'#2b55e0',700:'#1f3fa8'},
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-50 text-slate-800 antialiased font-sans">

    <!-- Minimal top bar for auth/onboarding pages -->
    <header class="bg-white border-b border-slate-200">
        <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
            <a href="<?= e(url('/')) ?>" class="flex items-center gap-2">
                <img src="<?= e(asset('assets/img/logo-mark.svg')) ?>" alt="Hesap360" class="w-8 h-8">
                <span class="font-bold text-slate-900">Hesap360</span>
            </a>
            <div class="flex items-center gap-2 text-sm">
                <a href="<?= e(url('/locale?locale=tr')) ?>" class="px-2 py-1 rounded hover:bg-slate-100 <?= $locale==='tr'?'text-brand-600 font-semibold':'text-slate-500' ?>">Türkçe</a>
                <span class="text-slate-300">|</span>
                <a href="<?= e(url('/locale?locale=en')) ?>" class="px-2 py-1 rounded hover:bg-slate-100 <?= $locale==='en'?'text-brand-600 font-semibold':'text-slate-500' ?>">English</a>
                <a href="<?= e(url('/theme?mode=' . ($dark ? 'light' : 'dark') . '&return=' . urlencode(request_path()))) ?>" class="px-2 py-1 rounded hover:bg-slate-100 text-slate-500" title="Toggle theme"><?php if ($dark): ?><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path stroke-linecap="round" d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg><?php else: ?><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z"/></svg><?php endif; ?></a>
                <?php if (\Muh\Core\Auth::check()): ?>
                    <a href="<?= e(url('/app/dashboard')) ?>" class="ml-2 px-4 py-2 rounded-lg bg-brand-600 text-white font-medium"><?= e(__('nav.dashboard')) ?></a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="min-h-[70vh]">
        <?php if ($flash = \Muh\Core\Session::getFlash('error')): ?>
            <div class="max-w-md mx-auto mt-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm"><?= e($flash) ?></div>
        <?php endif; ?>
        <?php if ($flash = \Muh\Core\Session::getFlash('success')): ?>
            <div class="max-w-md mx-auto mt-4 px-4 py-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm"><?= e($flash) ?></div>
        <?php endif; ?>
        <?= $content ?>
    </main>

    <footer class="bg-white border-t border-slate-200 mt-12">
        <div class="max-w-6xl mx-auto px-4 py-6 flex flex-col sm:flex-row items-center justify-between gap-3 text-sm text-slate-500">
            <span>© <?= date('Y') ?> MUH. <?= e(__('landing.footer_rights')) ?></span>
            <span class="flex gap-4">
                <a href="<?= e(url('/pricing')) ?>" class="hover:text-brand-600"><?= e(__('landing.nav_pricing')) ?></a>
                <a href="<?= e(url('/login')) ?>" class="hover:text-brand-600"><?= e(__('auth.login')) ?></a>
                <a href="<?= e(url('/register')) ?>" class="hover:text-brand-600"><?= e(__('auth.register')) ?></a>
            </span>
        </div>
    </footer>
</body>
</html>
