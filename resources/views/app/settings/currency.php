<?php
/** @var array $currencies @var string $today */
?>
<div class="max-w-3xl mx-auto p-6">
    <h1 class="text-2xl font-bold text-slate-900"><?= e(__('currency.title')) ?></h1>
    <p class="text-slate-500 mb-6 text-sm"><?= e(__('currency.hint')) ?></p>

    <form method="post" action="<?= e(url('/app/settings/currency')) ?>" class="bg-white border border-slate-200 rounded-2xl p-6 space-y-4">
        <?= csrf_field() ?>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('currency.rate_date')) ?></label>
            <input type="date" name="date" value="<?= e($today) ?>" class="w-full md:w-64 px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
            <p class="text-xs text-slate-400 mt-1"><?= e(__('currency.rate_date_hint')) ?></p>
        </div>

        <div class="divide-y divide-slate-100 border border-slate-200 rounded-xl overflow-hidden">
            <div class="grid grid-cols-[1fr_auto] items-center gap-3 bg-slate-50 px-4 py-2 text-xs uppercase text-slate-400 font-semibold">
                <span><?= e(__('currency.code')) ?></span><span class="text-right"><?= e(__('currency.rate_to_try')) ?> (1 birim = ?)</span>
            </div>
            <?php foreach ($currencies as $c): ?>
            <div class="grid grid-cols-[1fr_auto] items-center gap-3 px-4 py-3">
                <div>
                    <div class="font-semibold text-slate-800"><?= e($c['code']) ?> <span class="text-slate-400"><?= e($c['symbol']) ?></span></div>
                    <div class="text-xs text-slate-400"><?= e($c['name']) ?></div>
                </div>
                <?php if ($c['code'] === 'TRY'): ?>
                <span class="text-sm text-slate-400">1,00</span>
                <?php else: ?>
                <input type="text" name="rates[<?= e($c['code']) ?>]" value="<?= e(number_format((float) $c['rate'], 4, ',', '.')) ?>" class="w-32 text-right px-3 py-2 rounded-lg border border-slate-200 text-sm">
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <button class="w-full px-6 py-3 rounded-xl bg-brand-600 text-white font-semibold hover:bg-brand-700"><?= e(__('common.save')) ?></button>
    </form>
</div>
