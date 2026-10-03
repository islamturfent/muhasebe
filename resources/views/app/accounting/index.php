<?php
/** @var array $companies @var int $companyId @var array $periods @var int $periodId */
$active = 'index';
?>
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900"><?= e(__('nav.accounting')) ?></h1>
    <p class="text-slate-500"><?= e(__('accounting.title')) ?></p>
</div>
<?= $this->partial('app.accounting._context', ['companies'=>$companies,'companyId'=>$companyId,'periods'=>$periods,'periodId'=>$periodId,'active'=>$active]) ?>
<?= $this->partial('app.accounting._subnav', ['active'=>'index','companyId'=>$companyId,'periodId'=>$periodId]) ?>

<div class="grid md:grid-cols-2 gap-4">
    <?php
    $cards = [
        ['/app/accounting/journal', 'accounting.journal', '📒'],
        ['/app/accounting/trial-balance', 'accounting.trial_balance', '⚖️'],
        ['/app/accounting/balance-sheet', 'accounting.balance_sheet', '🏦'],
        ['/app/accounting/income-statement', 'accounting.income_statement', '📈'],
    ];
    foreach ($cards as [$path, $label, $icon]): $href = url($path . '?company_id=' . $companyId . '&period_id=' . $periodId); ?>
    <a href="<?= e($href) ?>" class="bg-white border border-slate-200 rounded-2xl p-5 hover:border-brand-300 hover:shadow-sm transition">
        <div class="text-2xl mb-3"><?= $icon ?></div>
        <div class="font-semibold text-slate-900"><?= e(__($label)) ?></div>
        <div class="text-sm text-brand-600 mt-1"><?= e(__('common.view')) ?> →</div>
    </a>
    <?php endforeach; ?>
</div>
