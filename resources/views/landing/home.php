<?php
use Muh\Core\Translator;
use Muh\Core\Session;
use Muh\Core\Auth;
use Muh\Models\Plan;
$locale = Translator::instance()->locale();
$dark = Session::get('theme') === 'dark';
$plans = Plan::allActive();
$isAuthed = Auth::check();
$nav = [
    ['#features', __('landing.nav_features')],
    ['#solutions', __('landing.nav_solutions')],
    ['#pricing', __('landing.nav_pricing')],
    ['#faq', __('landing.nav_faq')],
    ['#contact', __('landing.nav_contact')],
];
?>
<!DOCTYPE html>
<html lang="<?= e($locale) ?>" class="<?= $dark ? 'dark' : '' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="<?= e(asset('assets/img/favicon.ico')) ?>">
    <title>Hesap360 · <?= e(__('common.tagline')) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
    <script>
        tailwind.config = { theme: { extend: { colors: { brand: {50:'#eef4ff',100:'#dbe7ff',500:'#3b6cff',600:'#2b55e0',700:'#1f3fa8'} } } } }
    </script>
</head>
<body class="bg-white text-slate-800 font-sans antialiased">

<!-- Navbar -->
<header class="sticky top-0 z-30 bg-white/90 backdrop-blur border-b border-slate-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between">
        <a href="#" class="flex items-center gap-2">
            <img src="<?= e(asset('assets/img/logo-mark.svg')) ?>" alt="Hesap360" class="w-9 h-9">
            <span class="font-bold text-xl text-slate-900">Hesap360</span>
        </a>
        <nav class="hidden md:flex items-center gap-6 text-sm font-medium text-slate-600">
            <?php foreach ($nav as [$href, $label]): ?><a href="<?= e($href) ?>" class="hover:text-brand-600"><?= e($label) ?></a><?php endforeach; ?>
        </nav>
        <div class="flex items-center gap-2">
            <a href="<?= e(url('/locale?locale=tr')) ?>" class="px-2 text-sm <?= $locale==='tr'?'text-brand-600 font-semibold':'text-slate-400' ?>">TR</a>
            <span class="text-slate-300">|</span>
            <a href="<?= e(url('/locale?locale=en')) ?>" class="px-2 text-sm <?= $locale==='en'?'text-brand-600 font-semibold':'text-slate-400' ?>">EN</a>
            <a href="<?= e(url('/theme?mode=' . ($dark ? 'light' : 'dark') . '&return=' . urlencode(request_path()))) ?>" class="px-2 text-sm text-slate-400" title="Toggle theme"><?php if ($dark): ?><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path stroke-linecap="round" d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg><?php else: ?><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z"/></svg><?php endif; ?></a>
            <?php if ($isAuthed): ?>
                <a href="<?= e(url('/app/dashboard')) ?>" class="ml-2 px-5 py-2.5 rounded-lg bg-brand-600 text-white font-semibold text-sm hover:bg-brand-700"><?= e(__('nav.dashboard')) ?></a>
            <?php else: ?>
                <a href="<?= e(url('/login')) ?>" class="ml-2 px-4 py-2.5 text-sm font-medium text-slate-600 hover:text-brand-600"><?= e(__('landing.nav_login')) ?></a>
                <a href="<?= e(url('/register')) ?>" class="px-5 py-2.5 rounded-lg bg-brand-600 text-white font-semibold text-sm hover:bg-brand-700"><?= e(__('landing.hero_cta')) ?></a>
            <?php endif; ?>
        </div>
    </div>
</header>

<!-- Hero -->
<section class="relative overflow-hidden hero-gradient bg-gradient-to-b from-brand-50 via-white to-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-20 md:py-28 text-center">
        <span class="inline-block px-4 py-1.5 rounded-full bg-brand-100 text-brand-700 text-xs font-semibold mb-6"><?= e(__('landing.hero_badge')) ?></span>
        <h1 class="text-4xl md:text-6xl font-extrabold tracking-tight text-slate-900 max-w-4xl mx-auto leading-tight">
            <?= e(__('landing.hero_title_1')) ?><br><span class="text-brand-600"><?= e(__('landing.hero_title_2')) ?></span>
        </h1>
        <p class="mt-6 text-lg text-slate-500 max-w-2xl mx-auto"><?= e(__('landing.hero_subtitle')) ?></p>
        <div class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-4">
            <a href="<?= e(url('/register')) ?>" class="px-8 py-3.5 rounded-xl bg-brand-600 text-white font-semibold text-lg shadow-lg shadow-brand-500/30 hover:bg-brand-700"><?= e(__('landing.hero_cta')) ?></a>
            <a href="#features" class="px-8 py-3.5 rounded-xl bg-white border border-slate-200 text-slate-700 font-semibold text-lg hover:bg-slate-50"><?= e(__('landing.hero_cta_secondary')) ?></a>
        </div>
        <p class="mt-4 text-sm text-slate-400"><?= e(__('landing.hero_note')) ?></p>

        <div class="mt-14 rounded-2xl border border-slate-200 shadow-2xl shadow-brand-500/10 overflow-hidden bg-white mx-auto max-w-4xl">
            <div class="flex items-center gap-1.5 px-4 py-3 bg-slate-50 border-b border-slate-100">
                <span class="w-3 h-3 rounded-full bg-red-400"></span><span class="w-3 h-3 rounded-full bg-yellow-400"></span><span class="w-3 h-3 rounded-full bg-green-400"></span>
            </div>
            <div class="grid grid-cols-3 gap-4 p-6 text-left">
                <div class="col-span-2 space-y-3">
                    <div class="h-3 w-1/3 bg-brand-200 rounded"></div>
                    <div class="space-y-2">
                        <div class="h-10 rounded-lg bg-slate-100"></div>
                        <div class="h-10 rounded-lg bg-slate-100"></div>
                        <div class="h-10 rounded-lg bg-slate-100"></div>
                    </div>
                </div>
                <div class="space-y-2">
                    <div class="h-10 rounded-lg bg-brand-600"></div>
                    <div class="h-10 rounded-lg bg-emerald-400"></div>
                    <div class="h-10 rounded-lg bg-slate-100"></div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features -->
<section id="features" class="py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <div class="text-center max-w-2xl mx-auto mb-12">
            <span class="text-brand-600 font-semibold text-sm uppercase tracking-wide"><?= e(__('landing.section_features')) ?></span>
            <h2 class="mt-2 text-3xl md:text-4xl font-bold text-slate-900"><?= e(__('landing.section_features_title')) ?></h2>
        </div>
        <div class="grid md:grid-cols-3 gap-6">
            <?php $feats = [
                ['landing.feature_multi_company', 'landing.feature_multi_company_desc', 'M17 20h5v-2a3 3 0 00-5.36-1.86M7 20H2v-2a3 3 0 015.36-1.86M16 8a4 4 0 11-8 0 4 4 0 018 0zM20 8a3 3 0 11-6 0 3 3 0 016 0z'],
                ['landing.feature_current', 'landing.feature_current_desc', 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
                ['landing.feature_invoice', 'landing.feature_invoice_desc', 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.6a2 2 0 011.4.6l4.4 4.4a2 2 0 01.6 1.4V19a2 2 0 01-2 2z'],
                ['landing.feature_inventory', 'landing.feature_inventory_desc', 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],
                ['landing.feature_cash_bank', 'landing.feature_cash_bank_desc', 'M3 6h18M3 6v12a2 2 0 002 2h14a2 2 0 002-2V6M3 6l3 3m15-3l-3 3m0 0l-3-3M5 9l-2-2m16 2l2-2'],
                ['landing.feature_accounting', 'landing.feature_accounting_desc', 'M9 12l2 2 4-4m5.6-2.6L21 12l-5.4 5.4a2 2 0 01-1.4.6H4a2 2 0 01-2-2V8a2 2 0 012-2h10.2a2 2 0 011.4.6z'],
                ['landing.feature_reporting', 'landing.feature_reporting_desc', 'M3 3v18h18M7 15l3-4 3 3 4-6'],
                ['landing.feature_efatura', 'landing.feature_efatura_desc', 'M13 10V3L4 14h7v7l9-11h-7z'],
                ['landing.feature_security', 'landing.feature_security_desc', 'M12 3l8 3.5V11c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6.5L12 3zm-2 9l2 2 4-4'],
            ]; foreach ($feats as [$k, $kd, $path]): ?>
            <div class="rounded-2xl border border-slate-100 p-6 hover:shadow-lg hover:border-brand-100 transition">
                <div class="w-12 h-12 rounded-xl bg-brand-50 text-brand-600 flex items-center justify-center mb-4">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="<?= $path ?>"/></svg>
                </div>
                <h3 class="font-semibold text-slate-900"><?= e(__($k)) ?></h3>
                <p class="mt-2 text-sm text-slate-500"><?= e(__($kd)) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Solutions / for offices -->
<section id="solutions" class="py-20 bg-slate-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 grid md:grid-cols-2 gap-12 items-center">
        <div>
            <span class="text-brand-600 font-semibold text-sm uppercase tracking-wide"><?= e(__('landing.section_solutions')) ?></span>
            <h2 class="mt-2 text-3xl md:text-4xl font-bold text-slate-900 mb-4"><?= e(__('landing.feature_multi_company')) ?></h2>
            <p class="text-slate-500 text-lg"><?= e(__('landing.feature_multi_company_desc')) ?></p>
            <div class="mt-8 rounded-2xl bg-white border border-slate-200 divide-y divide-slate-100">
                <div class="flex items-center gap-4 p-4"><span class="w-10 h-10 rounded-lg bg-brand-100 text-brand-700 flex items-center justify-center font-bold">A</span><div><div class="font-medium text-slate-800">Alpha Ltd. Şti.</div><div class="text-xs text-slate-400">2026 Dönemi · ₺128.500</div></div></div>
                <div class="flex items-center gap-4 p-4"><span class="w-10 h-10 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold">B</span><div><div class="font-medium text-slate-800">Beta Ticaret</div><div class="text-xs text-slate-400">2025 Dönemi · ₺64.200</div></div></div>
                <div class="flex items-center gap-4 p-4"><span class="w-10 h-10 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center font-bold">C</span><div><div class="font-medium text-slate-800">Gamma Endüstri</div><div class="text-xs text-slate-400">2026 Dönemi · ₺312.000</div></div></div>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <?php foreach ([
                ['landing.feature_current','landing.feature_current_desc'],
                ['landing.feature_invoice','landing.feature_invoice_desc'],
                ['landing.feature_accounting','landing.feature_accounting_desc'],
                ['landing.feature_reporting','landing.feature_reporting_desc'],
            ] as [$k,$d]): ?>
            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                <h4 class="font-semibold text-slate-900"><?= e(__($k)) ?></h4>
                <p class="mt-1 text-sm text-slate-500"><?= e(__($d)) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Pricing -->
<section id="pricing" class="py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <div class="text-center mb-12">
            <span class="text-brand-600 font-semibold text-sm uppercase tracking-wide"><?= e(__('landing.section_pricing')) ?></span>
            <h2 class="mt-2 text-3xl md:text-4xl font-bold text-slate-900"><?= e(__('landing.section_pricing_title')) ?></h2>
        </div>
        <div class="grid md:grid-cols-4 gap-6">
            <?php foreach ($plans as $i => $p):
                $feats = json_decode($p['features'], true);
                $pname = json_decode($p['name'], true);
            ?>
            <div class="rounded-2xl border <?= $i===1 ? 'border-brand-600 ring-2 ring-brand-600/20 bg-brand-50/30' : 'border-slate-200' ?> p-6 flex flex-col">
                <h3 class="font-bold text-lg text-slate-900"><?= e($pname[$locale] ?? $p['code']) ?></h3>
                <div class="mt-3 text-3xl font-extrabold text-slate-900"><?= money($p['price_monthly']) ?><span class="text-sm font-normal text-slate-400"><?= e(__('plans.per_month')) ?></span></div>
                <ul class="mt-5 space-y-2 text-sm text-slate-600 flex-1">
                    <li>✓ <?= e(__('plans.companies', ['count' => $feats['companies'] ?? 0])) ?></li>
                    <li>✓ <?= e(__('plans.users', ['count' => $feats['users'] ?? 0])) ?></li>
                    <li>✓ <?= e(__('plans.warehouses', ['count' => $feats['warehouses'] ?? 0])) ?></li>
                    <li>✓ <?= e(__('plans.invoices', ['count' => $feats['invoices'] ?? 0])) ?></li>
                    <li><?= !empty($feats['efatura']) ? '✓' : '✕' ?> <?= e(__('plans.efatura')) ?></li>
                    <li><?= !empty($feats['reports']) ? '✓' : '✕' ?> <?= e(__('plans.reports')) ?></li>
                </ul>
                <a href="<?= e(url('/register')) ?>" class="mt-6 px-5 py-3 rounded-xl text-center font-semibold text-sm <?= $i===1 ? 'bg-brand-600 text-white hover:bg-brand-700' : 'bg-slate-900 text-white hover:bg-slate-800' ?>"><?= e(__('plans.choose')) ?></a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- FAQ -->
<section id="faq" class="py-20 bg-slate-50">
    <div class="max-w-3xl mx-auto px-4 sm:px-6">
        <div class="text-center mb-10">
            <span class="text-brand-600 font-semibold text-sm uppercase tracking-wide"><?= e(__('landing.section_faq')) ?></span>
            <h2 class="mt-2 text-3xl font-bold text-slate-900"><?= e(__('landing.section_faq')) ?></h2>
        </div>
        <div class="space-y-3">
            <?php for ($q = 1; $q <= 4; $q++): ?>
            <details class="group bg-white border border-slate-200 rounded-xl p-5">
                <summary class="flex justify-between items-center cursor-pointer font-medium text-slate-800 list-none">
                    <?= e(__('landing.faq_' . $q . '_q')) ?>
                    <span class="text-slate-400 group-open:rotate-180 transition">▾</span>
                </summary>
                <p class="mt-3 text-sm text-slate-500"><?= e(__('landing.faq_' . $q . '_a')) ?></p>
            </details>
            <?php endfor; ?>
        </div>
    </div>
</section>

<!-- Contact -->
<section id="contact" class="py-20 bg-white">
    <div class="max-w-2xl mx-auto px-4 sm:px-6">
        <div class="text-center mb-10">
            <span class="text-brand-600 font-semibold text-sm uppercase tracking-wide"><?= e(__('landing.section_contact')) ?></span>
            <h2 class="mt-2 text-3xl font-bold text-slate-900"><?= e(__('landing.contact_title')) ?></h2>
            <p class="text-slate-500 mt-2"><?= e(__('landing.contact_subtitle')) ?></p>
        </div>
        <form class="bg-slate-50 border border-slate-200 rounded-2xl p-6 space-y-4">
            <div class="grid md:grid-cols-2 gap-4">
                <input placeholder="<?= e(__('landing.contact_name')) ?>" class="px-4 py-3 rounded-lg border border-slate-200 bg-white focus:ring-2 focus:ring-brand-500 outline-none">
                <input placeholder="<?= e(__('landing.contact_email')) ?>" type="email" class="px-4 py-3 rounded-lg border border-slate-200 bg-white focus:ring-2 focus:ring-brand-500 outline-none">
            </div>
            <textarea rows="4" placeholder="<?= e(__('landing.contact_message')) ?>" class="w-full px-4 py-3 rounded-lg border border-slate-200 bg-white focus:ring-2 focus:ring-brand-500 outline-none"></textarea>
            <button class="w-full px-6 py-3 rounded-xl bg-brand-600 text-white font-semibold hover:bg-brand-700"><?= e(__('landing.contact_send')) ?></button>
        </form>
    </div>
</section>

<!-- Footer + CTA -->
<footer class="bg-slate-900 text-slate-300">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-14">
        <div class="flex flex-col items-center text-center mb-10">
            <img src="<?= e(asset('assets/img/logo-mark.svg')) ?>" alt="Hesap360" class="w-12 h-12 mb-4">
            <h3 class="text-2xl font-bold text-white"><?= e(__('landing.hero_cta')) ?></h3>
            <a href="<?= e(url('/register')) ?>" class="mt-5 px-8 py-3.5 rounded-xl bg-brand-600 text-white font-semibold text-lg hover:bg-brand-700"><?= e(__('landing.hero_cta')) ?></a>
        </div>
        <div class="border-t border-slate-800 pt-6 flex flex-col md:flex-row items-center justify-between gap-4 text-sm">
            <span>© <?= date('Y') ?> MUH. <?= e(__('landing.footer_rights')) ?></span>
            <div class="flex gap-6">
                <a href="<?= e(url('/pricing')) ?>" class="hover:text-white"><?= e(__('landing.nav_pricing')) ?></a>
                <a href="<?= e(url('/login')) ?>" class="hover:text-white"><?= e(__('landing.nav_login')) ?></a>
                <a href="<?= e(url('/register')) ?>" class="hover:text-white"><?= e(__('landing.nav_register')) ?></a>
            </div>
        </div>
    </div>
</footer>

</body>
</html>
