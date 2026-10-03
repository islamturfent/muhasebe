<?php
/** @var string $active @var int $companyId @var int $periodId */
$tabs = [
    'journal'         => ['/app/accounting/journal', 'accounting.journal'],
    'trial-balance'   => ['/app/accounting/trial-balance', 'accounting.trial_balance'],
    'balance-sheet'   => ['/app/accounting/balance-sheet', 'accounting.balance_sheet'],
    'income-statement'=> ['/app/accounting/income-statement', 'accounting.income_statement'],
];
?>
<div class="grid md:grid-cols-4 gap-3 mb-6 text-sm">
    <?php foreach ($tabs as $key => [$path, $label]): $href = url($path . '?company_id=' . $companyId . '&period_id=' . $periodId); ?>
    <a href="<?= e($href) ?>" class="px-4 py-3 rounded-xl text-center font-medium <?= $active === $key ? 'bg-brand-600 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:border-brand-200' ?>"><?= e(__($label)) ?></a>
    <?php endforeach; ?>
</div>
