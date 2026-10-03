<?php
/** @var array $companies @var int $companyId @var array $periods @var int $periodId @var array $rows */
$active = 'trial-balance';
$tDebit = array_sum(array_map(fn($r) => (float)$r['debit'], $rows));
$tCredit = array_sum(array_map(fn($r) => (float)$r['credit'], $rows));
?>
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900"><?= e(__('accounting.trial_balance')) ?></h1>
    <p class="text-slate-500"><?= e(__('accounting.title')) ?></p>
</div>
<?= $this->partial('app.accounting._context', ['companies'=>$companies,'companyId'=>$companyId,'periods'=>$periods,'periodId'=>$periodId,'active'=>$active]) ?>
<?= $this->partial('app.accounting._subnav', ['active'=>$active,'companyId'=>$companyId,'periodId'=>$periodId]) ?>

<div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-left text-xs text-slate-400 uppercase bg-slate-50">
                <tr>
                    <th class="px-5 py-2"><?= e(__('accounting.chart_of_accounts')) ?> · <?= e(__('common.code')) ?></th>
                    <th class="px-5 py-2"><?= e(__('common.name')) ?></th>
                    <th class="px-5 py-2 text-right"><?= e(__('accounting.debit')) ?></th>
                    <th class="px-5 py-2 text-right"><?= e(__('accounting.credit')) ?></th>
                    <th class="px-5 py-2 text-right"><?= e(__('accounting.balance')) ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php if (!$rows): ?><tr><td colspan="5" class="px-5 py-8 text-center text-slate-400"><?= e(__('accounting.no_entries')) ?></td></tr><?php endif; ?>
                <?php foreach ($rows as $r): $bal = (float)$r['debit'] - (float)$r['credit']; ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-5 py-2.5 font-mono text-brand-600"><?= e($r['code']) ?></td>
                    <td class="px-5 py-2.5 text-slate-700"><?= e($r['name']) ?></td>
                    <td class="px-5 py-2.5 text-right text-slate-700"><?= e(number_format((float)$r['debit'], 2, ',', '.')) ?></td>
                    <td class="px-5 py-2.5 text-right text-slate-700"><?= e(number_format((float)$r['credit'], 2, ',', '.')) ?></td>
                    <td class="px-5 py-2.5 text-right font-medium <?= $bal < 0 ? 'text-red-600' : 'text-emerald-600' ?>"><?= e(money($bal)) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="bg-slate-50 font-semibold text-slate-800">
                    <td class="px-5 py-2.5" colspan="2"><?= e(__('accounting.total')) ?></td>
                    <td class="px-5 py-2.5 text-right"><?= e(number_format($tDebit, 2, ',', '.')) ?></td>
                    <td class="px-5 py-2.5 text-right"><?= e(number_format($tCredit, 2, ',', '.')) ?></td>
                    <td class="px-5 py-2.5 text-right"><?= e(money($tDebit - $tCredit)) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
