<?php
/** @var array $companies @var int $companyId @var array $periods @var int $periodId @var array $data */
$active = 'income-statement';
$income = $data['income'] ?? [];
$expense = $data['expense'] ?? [];
$incomeTotal = array_sum(array_map(fn($r) => (float)$r['credit'] - (float)$r['debit'], $income));
$expenseTotal = array_sum(array_map(fn($r) => (float)$r['debit'] - (float)$r['credit'], $expense));
$netProfit = $incomeTotal - $expenseTotal;
?>
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900"><?= e(__('accounting.income_statement')) ?></h1>
</div>
<?= $this->partial('app.accounting._context', ['companies'=>$companies,'companyId'=>$companyId,'periods'=>$periods,'periodId'=>$periodId,'active'=>$active]) ?>
<?= $this->partial('app.accounting._subnav', ['active'=>$active,'companyId'=>$companyId,'periodId'=>$periodId]) ?>

<div class="grid md:grid-cols-2 gap-6">
    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
        <div class="px-5 py-3 bg-emerald-50 border-b border-emerald-100 font-semibold text-emerald-800"><?= e(__('accounting.income_section')) ?></div>
        <table class="w-full text-sm">
            <tbody class="divide-y divide-slate-50">
                <?php foreach ($income as $r): $bal = (float)$r['credit'] - (float)$r['debit']; ?>
                <tr><td class="px-5 py-2.5 font-mono text-slate-500"><?= e($r['code']) ?> <?= e($r['name']) ?></td><td class="px-5 py-2.5 text-right font-medium text-emerald-700"><?= e(money($bal)) ?></td></tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot><tr class="bg-emerald-50 font-bold text-emerald-800"><td class="px-5 py-2.5"><?= e(__('accounting.total_income')) ?></td><td class="px-5 py-2.5 text-right"><?= e(money($incomeTotal)) ?></td></tr></tfoot>
        </table>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
        <div class="px-5 py-3 bg-red-50 border-b border-red-100 font-semibold text-red-800"><?= e(__('accounting.expense_section')) ?></div>
        <table class="w-full text-sm">
            <tbody class="divide-y divide-slate-50">
                <?php foreach ($expense as $r): $bal = (float)$r['debit'] - (float)$r['credit']; ?>
                <tr><td class="px-5 py-2.5 font-mono text-slate-500"><?= e($r['code']) ?> <?= e($r['name']) ?></td><td class="px-5 py-2.5 text-right font-medium text-red-700"><?= e(money($bal)) ?></td></tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot><tr class="bg-red-50 font-bold text-red-800"><td class="px-5 py-2.5"><?= e(__('accounting.total_expense')) ?></td><td class="px-5 py-2.5 text-right"><?= e(money($expenseTotal)) ?></td></tr></tfoot>
        </table>
    </div>
</div>

<div class="mt-4 p-4 rounded-xl bg-white border border-slate-200 flex items-center justify-between">
    <span class="font-semibold text-slate-800"><?= e(__('accounting.net_profit')) ?></span>
    <span class="text-xl font-bold <?= $netProfit >= 0 ? 'text-emerald-600' : 'text-red-600' ?>"><?= e(money($netProfit)) ?></span>
</div>
