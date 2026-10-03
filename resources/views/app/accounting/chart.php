<?php
/** @var array $companies @var int $companyId @var array $periods @var int $periodId @var array $accounts */
use Muh\Core\Auth;
$active = 'chart';
?>
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-900"><?= e(__('accounting.chart_of_accounts')) ?></h1>
        <p class="text-slate-500"><?= e(__('accounting.accounts')) ?> · <?= count($accounts) ?></p>
    </div>
    <?php if (Auth::can('accounting.create')): ?>
    <a href="<?= e(url('/app/accounting/chart/create?company_id=' . $companyId . '&period_id=' . $periodId)) ?>" class="px-4 py-2 rounded-xl bg-brand-600 text-white text-sm font-semibold hover:bg-brand-700">+ <?= e(__('accounting.new_account')) ?></a>
    <?php endif; ?>
</div>
<?= $this->partial('app.accounting._context', ['companies'=>$companies,'companyId'=>$companyId,'periods'=>$periods,'periodId'=>$periodId,'active'=>$active]) ?>
<?= $this->partial('app.accounting._subnav', ['active'=>$active,'companyId'=>$companyId,'periodId'=>$periodId]) ?>

<?php
$od = (float) array_sum(array_column($accounts, 'opening_debit'));
$oc = (float) array_sum(array_column($accounts, 'opening_credit'));
$balanced = abs($od - $oc) < 0.01;
?>
<div class="mb-4 p-4 bg-white border border-slate-200 rounded-2xl flex flex-wrap items-center gap-6 text-sm">
    <div><span class="text-slate-400"><?= e(__('accounting.opening_balance')) ?>:</span> <span class="font-semibold text-slate-800"><?= e(money($od)) ?></span></div>
    <div><span class="text-slate-400"><?= e(__('accounting.balance')) ?>:</span> <span class="font-semibold text-slate-800"><?= e(money($oc)) ?></span></div>
    <div class="<?= $balanced ? 'text-green-600' : 'text-red-600' ?> font-medium">
        <?= $balanced ? e(__('accounting.balance_status_ok')) : e(__('accounting.balance_status_diff', ['diff' => money(abs($od - $oc))])) ?>
    </div>
    <?php if (Auth::can('accounting.create')): ?>
    <a href="<?= e(url('/app/accounting/entry/create?type=opening&company_id=' . $companyId . '&period_id=' . $periodId)) ?>" class="ml-auto px-3 py-2 rounded-lg bg-brand-50 text-brand-700 text-xs font-medium hover:bg-brand-100">+ <?= e(__('accounting.type_opening')) ?></a>
    <?php endif; ?>
</div>

<div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-left text-xs text-slate-400 uppercase bg-slate-50">
                <tr>
                    <th class="px-5 py-2"><?= e(__('accounting.account_code')) ?></th>
                    <th class="px-5 py-2"><?= e(__('common.name')) ?></th>
                    <th class="px-5 py-2"><?= e(__('accounting.account_type')) ?></th>
                    <th class="px-5 py-2 text-center"><?= e(__('accounting.account_is_header')) ?></th>
                    <th class="px-5 py-2 text-right"><?= e(__('common.debit')) ?></th>
                    <th class="px-5 py-2 text-right"><?= e(__('common.credit')) ?></th>
                    <th class="px-5 py-2 text-right"><?= e(__('accounting.budget_amount')) ?></th>
                    <th class="px-5 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php if (!$accounts): ?><tr><td colspan="8" class="px-5 py-8 text-center text-slate-400"><?= e(__('accounting.no_entries')) ?></td></tr><?php endif; ?>
                <?php foreach ($accounts as $a): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-5 py-2.5 font-mono text-brand-600"><?= e($a['code']) ?></td>
                    <td class="px-5 py-2.5 text-slate-700"><?= e($a['name']) ?><?= $a['is_header'] ? ' <span class="text-[11px] text-slate-400">(header)</span>' : '' ?></td>
                    <td class="px-5 py-2.5"><span class="px-2 py-0.5 rounded-full text-[11px] bg-slate-100 text-slate-600"><?= e(__('accounting.type_' . $a['type'])) ?></span></td>
                    <td class="px-5 py-2.5 text-center"><?= $a['is_header'] ? '✓' : '—' ?></td>
                    <td class="px-5 py-2.5 text-right text-slate-700"><?= e(money($a['opening_debit'])) ?></td>
                    <td class="px-5 py-2.5 text-right text-slate-700"><?= e(money($a['opening_credit'])) ?></td>
                    <td class="px-5 py-2.5 text-right text-slate-700"><?= e(money($a['budget_amount'] ?? 0)) ?></td>
                    <td class="px-5 py-2.5 text-right space-x-2">
                        <?php if (Auth::can('accounting.update')): ?>
                        <a href="<?= e(url('/app/accounting/chart/' . (int)$a['id'] . '/edit')) ?>" class="text-brand-600 hover:underline text-xs"><?= e(__('common.edit')) ?></a>
                        <?php endif; ?>
                        <?php if (Auth::can('accounting.delete')): ?>
                        <form method="post" action="<?= e(url('/app/accounting/chart/' . (int)$a['id'] . '/delete')) ?>" class="inline" onsubmit="return confirm('<?= e(__('common.delete')) ?>?')">
                            <?= csrf_field() ?>
                            <button class="text-slate-400 hover:text-red-500 text-xs"><?= e(__('common.delete')) ?></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
