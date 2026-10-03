<?php
/** @var array $plans */
use Muh\Core\Translator;
$locale = Translator::instance()->locale();
?>
<div class="max-w-7xl mx-auto px-4 py-16">
    <div class="text-center mb-12">
        <h1 class="text-4xl font-bold text-slate-900"><?= e(__('plans.title')) ?></h1>
        <p class="text-slate-500 mt-2"><?= e(__('landing.section_pricing_title')) ?></p>
    </div>
    <div class="grid md:grid-cols-4 gap-6">
        <?php foreach ($plans as $i => $p):
            $feats = json_decode($p['features'], true);
            $pname = json_decode($p['name'], true);
        ?>
        <div class="rounded-2xl border <?= $i===1?'border-brand-600 ring-2 ring-brand-600/20': 'border-slate-200' ?> p-6 flex flex-col bg-white">
            <h3 class="font-bold text-lg text-slate-900"><?= e($pname[$locale] ?? $p['code']) ?></h3>
            <div class="mt-2 text-3xl font-extrabold text-slate-900"><?= $i===3 ? __('common.yes') : money($p['price_monthly']) ?><span class="text-sm font-normal text-slate-400"><?= e(__('plans.per_month')) ?></span></div>
            <ul class="mt-5 space-y-2 text-sm text-slate-600 flex-1">
                <li>✓ <?= e(__('plans.companies', ['count' => $feats['companies'] ?? 0])) ?></li>
                <li>✓ <?= e(__('plans.users', ['count' => $feats['users'] ?? 0])) ?></li>
                <li>✓ <?= e(__('plans.warehouses', ['count' => $feats['warehouses'] ?? 0])) ?></li>
                <li>✓ <?= e(__('plans.invoices', ['count' => $feats['invoices'] ?? 0])) ?></li>
                <li><?= !empty($feats['efatura']) ? '✓' : '✕' ?> <?= e(__('plans.efatura')) ?></li>
                <li><?= !empty($feats['reports']) ? '✓' : '✕' ?> <?= e(__('plans.reports')) ?></li>
            </ul>
            <a href="<?= e(url('/register')) ?>" class="mt-6 px-5 py-3 rounded-xl text-center font-semibold text-sm <?= $i===1?'bg-brand-600 text-white hover:bg-brand-700':'bg-slate-900 text-white hover:bg-slate-800' ?>"><?= e(__('plans.choose')) ?></a>
        </div>
        <?php endforeach; ?>
    </div>
</div>
