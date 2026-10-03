<?php
/** @var array $companies @var int $companyId @var array $periods @var int $periodId @var array $entries */
$active = 'journal';
?>
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900"><?= e(__('accounting.journal')) ?></h1>
    <p class="text-slate-500"><?= e(__('accounting.title')) ?></p>
</div>
<?= $this->partial('app.accounting._context', ['companies'=>$companies,'companyId'=>$companyId,'periods'=>$periods,'periodId'=>$periodId,'active'=>$active]) ?>
<?= $this->partial('app.accounting._subnav', ['active'=>$active,'companyId'=>$companyId,'periodId'=>$periodId]) ?>

<div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
    <table class="w-full text-sm">
        <thead class="text-left text-xs text-slate-400 uppercase bg-slate-50">
            <tr>
                <th class="px-5 py-2"><?= e(__('accounting.number')) ?></th>
                <th class="px-5 py-2"><?= e(__('accounting.date')) ?></th>
                <th class="px-5 py-2"><?= e(__('accounting.voucher_type')) ?></th>
                <th class="px-5 py-2"><?= e(__('accounting.description')) ?></th>
                <th class="px-5 py-2 text-right"><?= e(__('accounting.debit')) ?></th>
                <th class="px-5 py-2 text-right"><?= e(__('accounting.credit')) ?></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            <?php if (!$entries): ?><tr><td colspan="6" class="px-5 py-8 text-center text-slate-400"><?= e(__('accounting.no_entries')) ?></td></tr><?php endif; ?>
            <?php foreach ($entries as $e): ?>
            <tr class="hover:bg-slate-50 align-top">
                <td class="px-5 py-2.5 font-mono text-brand-600"><?= e($e['number']) ?></td>
                <td class="px-5 py-2.5 text-slate-500"><?= e(format_date($e['date'])) ?></td>
                <td class="px-5 py-2.5"><span class="px-2 py-0.5 rounded-full text-xs bg-slate-100 text-slate-600"><?= e(__('accounting.type_' . $e['voucher_type'])) ?></span></td>
                <td class="px-5 py-2.5 text-slate-700">
                    <?= e($e['description']) ?>
                    <div class="text-xs text-slate-400 mt-0.5"><?= e($e['lines_summary'] ?? '') ?></div>
                </td>
                <td class="px-5 py-2.5 text-right text-slate-700 font-medium"><?= e(money($e['debit_total'])) ?></td>
                <td class="px-5 py-2.5 text-right text-slate-700 font-medium"><?= e(money($e['credit_total'])) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
