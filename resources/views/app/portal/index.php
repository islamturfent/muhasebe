<?php
/** @var array $cards */
?>
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900"><?= e(__('portal.title')) ?></h1>
    <p class="text-slate-500"><?= e(__('portal.subtitle')) ?></p>
</div>

<?php if (!$cards): ?>
<div class="bg-white border border-slate-200 rounded-2xl p-10 text-center text-slate-400"><?= e(__('portal.no_companies')) ?></div>
<?php endif; ?>

<div class="grid md:grid-cols-2 gap-4">
    <?php foreach ($cards as $c): ?>
    <div class="bg-white border border-slate-200 rounded-2xl p-5">
        <div class="flex items-center gap-3 mb-4">
            <span class="w-10 h-10 rounded-lg bg-brand-100 text-brand-700 flex items-center justify-center text-sm font-bold"><?= e(mb_strtoupper(mb_substr($c['name'], 0, 1))) ?></span>
            <div>
                <h3 class="font-semibold text-slate-900"><?= e($c['name']) ?></h3>
                <div class="text-xs text-slate-400"><?= e($c['trade_name'] ?: '') ?> · <?= e($c['currency']) ?></div>
            </div>
        </div>
        <div class="grid grid-cols-3 gap-2 text-center mb-4">
            <div class="rounded-lg bg-slate-50 p-2">
                <div class="text-lg font-bold text-slate-800"><?= (int) $c['invoice_count'] ?></div>
                <div class="text-[11px] text-slate-400"><?= e(__('nav.invoices')) ?></div>
            </div>
            <div class="rounded-lg bg-slate-50 p-2">
                <div class="text-lg font-bold text-slate-800"><?= e(money($c['open_invoices'])) ?></div>
                <div class="text-[11px] text-slate-400"><?= e(__('portal.open_invoices')) ?></div>
            </div>
            <div class="rounded-lg bg-slate-50 p-2">
                <div class="text-lg font-bold text-slate-800"><?= e(money($c['cari_balance'])) ?></div>
                <div class="text-[11px] text-slate-400"><?= e(__('portal.cari_balance')) ?></div>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="<?= e(url('/app/invoices?company_id=' . $c['id'])) ?>" class="flex-1 text-center px-3 py-2 rounded-lg bg-brand-50 text-brand-700 text-sm font-medium hover:bg-brand-100"><?= e(__('nav.invoices')) ?></a>
            <a href="<?= e(url('/app/current-accounts?company_id=' . $c['id'])) ?>" class="flex-1 text-center px-3 py-2 rounded-lg border border-slate-200 text-sm text-slate-600 hover:border-brand-300"><?= e(__('nav.current_accounts')) ?></a>
            <a href="<?= e(url('/app/reports/mizan/export?format=csv&company_id=' . $c['id'] . '&period_id=0')) ?>" class="flex-1 text-center px-3 py-2 rounded-lg border border-slate-200 text-sm text-slate-600 hover:border-brand-300"><?= e(__('nav.reports')) ?></a>
        </div>
    </div>
    <?php endforeach; ?>
</div>
