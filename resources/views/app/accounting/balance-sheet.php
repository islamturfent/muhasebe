<?php
/** @var array $companies @var int $companyId @var array $periods @var int $periodId @var array $data */
$active = 'balance-sheet';
$as = $data['asset'] ?? [];
$li = $data['liability'] ?? [];
$eq = $data['equity'] ?? [];
$assetTotal = array_sum(array_map(fn($r) => (float)$r['debit'] - (float)$r['credit'], $as));
$liabilityTotal = array_sum(array_map(fn($r) => (float)$r['credit'] - (float)$r['debit'], $li));
$equityTotal = array_sum(array_map(fn($r) => (float)$r['credit'] - (float)$r['debit'], $eq));
$balanced = abs($assetTotal - ($liabilityTotal + $equityTotal)) < 1;
?>
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900"><?= e(__('accounting.balance_sheet')) ?></h1>
</div>
<?= $this->partial('app.accounting._context', ['companies'=>$companies,'companyId'=>$companyId,'periods'=>$periods,'periodId'=>$periodId,'active'=>$active]) ?>
<?= $this->partial('app.accounting._subnav', ['active'=>$active,'companyId'=>$companyId,'periodId'=>$periodId]) ?>

<div class="grid md:grid-cols-2 gap-6">
    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
        <div class="px-5 py-3 bg-emerald-50 border-b border-emerald-100 font-semibold text-emerald-800"><?= e(__('accounting.assets')) ?></div>
        <table class="w-full text-sm">
            <tbody class="divide-y divide-slate-50">
                <?php foreach ($as as $r): $bal = (float)$r['debit'] - (float)$r['credit']; ?>
                <tr><td class="px-5 py-2.5 font-mono text-slate-500"><?= e($r['code']) ?> <?= e($r['name']) ?></td><td class="px-5 py-2.5 text-right font-medium text-emerald-700"><?= e(money($bal)) ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$as): ?><tr><td class="px-5 py-3 text-center text-slate-400 text-sm"><?= e(__('accounting.no_entries')) ?></td></tr><?php endif; ?>
            </tbody>
            <tfoot><tr class="bg-emerald-50 font-bold text-emerald-800"><td class="px-5 py-2.5"><?= e(__('accounting.section_total')) ?></td><td class="px-5 py-2.5 text-right"><?= e(money($assetTotal)) ?></td></tr></tfoot>
        </table>
    </div>

    <div class="space-y-6">
        <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
            <div class="px-5 py-3 bg-red-50 border-b border-red-100 font-semibold text-red-800"><?= e(__('accounting.liabilities')) ?></div>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-slate-50">
                    <?php foreach ($li as $r): $bal = (float)$r['credit'] - (float)$r['debit']; ?>
                    <tr><td class="px-5 py-2.5 font-mono text-slate-500"><?= e($r['code']) ?> <?= e($r['name']) ?></td><td class="px-5 py-2.5 text-right font-medium text-red-700"><?= e(money($bal)) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot><tr class="bg-red-50 font-bold text-red-800"><td class="px-5 py-2.5"><?= e(__('accounting.section_total')) ?></td><td class="px-5 py-2.5 text-right"><?= e(money($liabilityTotal)) ?></td></tr></tfoot>
            </table>
        </div>
        <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
            <div class="px-5 py-3 bg-brand-50 border-b border-brand-100 font-semibold text-brand-800"><?= e(__('accounting.equity')) ?></div>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-slate-50">
                    <?php foreach ($eq as $r): $bal = (float)$r['credit'] - (float)$r['debit']; ?>
                    <tr><td class="px-5 py-2.5 font-mono text-slate-500"><?= e($r['code']) ?> <?= e($r['name']) ?></td><td class="px-5 py-2.5 text-right font-medium text-brand-700"><?= e(money($bal)) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot><tr class="bg-brand-50 font-bold text-brand-800"><td class="px-5 py-2.5"><?= e(__('accounting.section_total')) ?></td><td class="px-5 py-2.5 text-right"><?= e(money($equityTotal)) ?></td></tr></tfoot>
            </table>
        </div>
    </div>
</div>

<div class="mt-4 p-4 rounded-xl bg-slate-50 border border-slate-200 text-sm text-slate-600 flex items-center justify-between">
    <span><?= e(__('accounting.assets')) ?>: <?= e(money($assetTotal)) ?> · <?= e(__('accounting.liabilities')) ?> + <?= e(__('accounting.equity')) ?>: <?= e(money($liabilityTotal + $equityTotal)) ?></span>
    <span class="font-semibold <?= $balanced ? 'text-emerald-600' : 'text-red-600' ?>">
        <?= $balanced ? '✓ ' . e(__('accounting.active_equals_passive')) : '✕ ' . e(__('accounting.imbalanced')) ?>
    </span>
</div>
