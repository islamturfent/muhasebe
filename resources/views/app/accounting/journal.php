<?php
/** @var array $companies @var int $companyId @var array $periods @var int $periodId @var array $entries */
use Muh\Core\Auth;
$active = 'journal';
?>
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-900"><?= e(__('accounting.journal')) ?></h1>
        <p class="text-slate-500"><?= e(__('accounting.title')) ?></p>
    </div>
    <?php if (Auth::can('accounting.create')): ?>
    <a href="<?= e(url('/app/accounting/entry/create?company_id='.$companyId.'&period_id='.$periodId)) ?>" class="px-4 py-2 rounded-xl bg-brand-600 text-white text-sm font-semibold hover:bg-brand-700">+ <?= e(__('accounting.new_entry')) ?></a>
    <?php endif; ?>
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
                <?php if (Auth::can('accounting.update') || Auth::can('accounting.delete')): ?><th class="px-5 py-2 text-right"></th><?php endif; ?>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            <?php if (!$entries): ?><tr><td colspan="7" class="px-5 py-8 text-center text-slate-400"><?= e(__('accounting.no_entries')) ?></td></tr><?php endif; ?>
            <?php foreach ($entries as $e): ?>
            <tr class="hover:bg-slate-50 align-top">
                <td class="px-5 py-2.5 font-mono text-brand-600"><?= e($e['number']) ?></td>
                <td class="px-5 py-2.5 text-slate-500"><?= e(format_date($e['date'])) ?></td>
                <td class="px-5 py-2.5 space-y-1">
                    <div><span class="px-2 py-0.5 rounded-full text-xs bg-slate-100 text-slate-600"><?= e(__('accounting.type_' . $e['voucher_type'])) ?></span></div>
                    <?php if (($e['approval_status'] ?? 'none') !== 'none'): $ac = [ 'pending' => 'bg-amber-50 text-amber-700', 'approved' => 'bg-emerald-50 text-emerald-700', 'rejected' => 'bg-red-50 text-red-600' ]; ?>
                    <div><span class="px-2 py-0.5 rounded-full text-[11px] <?= $ac[$e['approval_status']] ?? 'bg-slate-100 text-slate-600' ?>"><?= e(__('accounting.approval_' . $e['approval_status'])) ?></span><?= !empty($e['approval_note']) ? ' <span class="text-[11px] text-slate-400">(' . e($e['approval_note']) . ')</span>' : '' ?></div>
                    <?php endif; ?>
                </td>
                <td class="px-5 py-2.5 text-slate-700">
                    <?= e($e['description']) ?>
                    <div class="text-xs text-slate-400 mt-0.5"><?= e($e['lines_summary'] ?? '') ?></div>
                </td>
                <td class="px-5 py-2.5 text-right text-slate-700 font-medium"><?= e(money($e['debit_total'])) ?></td>
                <td class="px-5 py-2.5 text-right text-slate-700 font-medium"><?= e(money($e['credit_total'])) ?></td>
                <?php if (Auth::can('accounting.update') || Auth::can('accounting.delete') || (($e['approval_status'] ?? 'none') === 'pending' && Auth::can('accounting.post'))): ?>
                <td class="px-5 py-2.5 text-right space-x-2 whitespace-nowrap">
                    <?php if (($e['approval_status'] ?? 'none') === 'pending' && Auth::can('accounting.post')): ?>
                    <form method="post" action="<?= e(url('/app/accounting/entry/' . (int)$e['id'] . '/approve')) ?>" class="inline">
                        <?= csrf_field() ?>
                        <button class="text-emerald-600 hover:underline text-xs"><?= e(__('accounting.approve_entry')) ?></button>
                    </form>
                    <form method="post" action="<?= e(url('/app/accounting/entry/' . (int)$e['id'] . '/reject')) ?>" class="inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="note" value="">
                        <button type="submit" onclick="var n=prompt('<?= e(__('accounting.reject_note_prompt')) ?>'); if(n===null||n.trim()===''){return false;} this.form.querySelector('[name=note]').value=n; return true;"><?= e(__('accounting.reject_entry')) ?></button>
                    </form>
                    <?php endif; ?>
                    <?php if (Auth::can('accounting.update')): ?>
                    <a href="<?= e(url('/app/accounting/entry/' . (int)$e['id'] . '/edit')) ?>" class="text-brand-600 hover:underline text-xs"><?= e(__('accounting.edit_entry')) ?></a>
                    <?php endif; ?>
                    <?php if (Auth::can('accounting.delete')): ?>
                    <form method="post" action="<?= e(url('/app/accounting/entry/' . (int)$e['id'] . '/delete')) ?>" class="inline" onsubmit="return confirm('<?= e(__('accounting.delete_entry')) ?>?')">
                        <?= csrf_field() ?>
                        <button class="text-slate-400 hover:text-red-500 text-xs" title="<?= e(__('common.delete')) ?>"><?= e(__('common.delete')) ?></button>
                    </form>
                    <?php endif; ?>
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
