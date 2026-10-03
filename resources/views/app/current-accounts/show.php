<?php
/** @var array $account @var array $company @var array $transactions */
use Muh\Core\Translator;
use Muh\Core\Auth;
$locale = Translator::instance()->locale();
$balance = (float) $account['balance'];
$running = $balance; // walk transactions newest-first is misleading; compute opening below
?>
<div class="mb-6">
    <a href="<?= e(url('/app/current-accounts')) ?>" class="text-sm text-brand-600 hover:underline">← <?= e(__('current_account.title')) ?></a>
    <div class="mt-2 flex flex-col md:flex-row md:items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <span class="w-12 h-12 rounded-xl bg-brand-100 text-brand-700 flex items-center justify-center text-lg font-bold"><?= e(mb_strtoupper(mb_substr($account['name'], 0, 1))) ?></span>
            <div>
                <h1 class="text-2xl font-bold text-slate-900"><?= e($account['name']) ?></h1>
                <p class="text-sm text-slate-500"><?= e($account['code']) ?> · <?= e($company['name'] ?? '') ?></p>
            </div>
        </div>
        <div class="flex gap-2">
            <?php if (Auth::can('current_account.update')): ?>
            <a href="<?= e(url('/app/current-accounts/' . $account['id'] . '/edit')) ?>" class="px-4 py-2 rounded-lg border border-slate-200 text-sm font-medium text-slate-600 hover:bg-slate-50"><?= e(__('common.edit')) ?></a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- KPI cards -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="rounded-2xl bg-white border border-slate-200 p-5">
        <div class="text-xs text-slate-400 uppercase"><?= e(__('current_account.balance')) ?></div>
        <div class="text-xl font-bold <?= $balance<0?'text-red-600':'text-emerald-600' ?>"><?= e(money($balance)) ?></div>
    </div>
    <div class="rounded-2xl bg-white border border-slate-200 p-5">
        <div class="text-xs text-slate-400 uppercase"><?= e(__('current_account.debit')) ?></div>
        <div class="text-xl font-bold text-slate-800"><?= e(money(max(0,$balance))) ?></div>
    </div>
    <div class="rounded-2xl bg-white border border-slate-200 p-5">
        <div class="text-xs text-slate-400 uppercase"><?= e(__('current_account.credit')) ?></div>
        <div class="text-xl font-bold text-slate-800"><?= e(money(max(0,-$balance))) ?></div>
    </div>
    <div class="rounded-2xl bg-white border border-slate-200 p-5">
        <div class="text-xs text-slate-400 uppercase"><?= e(__('current_account.risk_limit')) ?></div>
        <div class="text-xl font-bold text-slate-800"><?= e(money($account['risk_limit'])) ?></div>
    </div>
</div>

<?php if ((float) $account['risk_limit'] > 0 && $balance > (float) $account['risk_limit']): ?>
<div class="mb-6 px-4 py-3 rounded-lg bg-amber-50 border border-amber-200 text-amber-700 text-sm">⚠ <?= e(__('current_account.risk_exceeded')) ?> · <?= e(money($balance)) ?> / <?= e(money($account['risk_limit'])) ?></div>
<?php endif; ?>

<?php if (!empty($statement)): ?>
<!-- Ekstre özeti (opening/closing) + export -->
<div class="mb-6 bg-white border border-slate-200 rounded-2xl p-5">
    <div class="flex items-center justify-between mb-4">
        <h3 class="font-semibold text-slate-800"><?= e(__('report.cari_ekstre')) ?> <span class="text-xs text-slate-400 font-normal">(<?= e($from ?? '—') ?> → <?= e($to ?? '—') ?>)</span></h3>
        <div class="flex gap-2">
            <?php $ek = url('/app/reports/cari-ekstre/export?account_id=' . $account['id'] . '&from=' . ($from ?? '') . '&to=' . ($to ?? '')); ?>
            <?php foreach (['pdf' => 'PDF', 'excel' => 'Excel', 'csv' => 'CSV'] as $fmt => $lbl): ?>
            <a href="<?= e($ek . '&format=' . $fmt) ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 text-xs font-medium text-slate-600 hover:border-brand-300 hover:text-brand-600"><?= e($lbl) ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
        <div><div class="text-xs text-slate-400"><?= e(__('report.ekstre_opening')) ?></div><div class="font-semibold text-slate-800"><?= e(number_format($statement['opening'], 2, ',', '.')) ?></div></div>
        <div><div class="text-xs text-slate-400"><?= e(__('current_account.debit')) ?></div><div class="font-semibold text-red-600"><?= e(number_format($statement['debit'], 2, ',', '.')) ?></div></div>
        <div><div class="text-xs text-slate-400"><?= e(__('current_account.credit')) ?></div><div class="font-semibold text-emerald-600"><?= e(number_format($statement['credit'], 2, ',', '.')) ?></div></div>
        <div><div class="text-xs text-slate-400"><?= e(__('report.ekstre_closing')) ?></div><div class="font-semibold <?= $statement['closing'] < 0 ? 'text-red-600' : 'text-emerald-600' ?>"><?= e(number_format($statement['closing'], 2, ',', '.')) ?></div></div>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($cashAccounts) || !empty($bankAccounts)): ?>
<!-- Tahsilat / Ödeme -->
<div class="mb-6 bg-white border border-slate-200 rounded-2xl p-5">
    <h3 class="font-semibold text-slate-800 mb-4"><?= e(__('current_account.collection_payment')) ?></h3>
    <form method="post" action="<?= e(url('/app/current-accounts/' . $account['id'] . '/transaction')) ?>" class="grid grid-cols-2 md:grid-cols-4 gap-3 items-end">
        <?= csrf_field() ?>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1"><?= e(__('current_account.type')) ?></label>
            <select name="type" class="w-full px-3 py-2 rounded-lg border border-slate-200">
                <option value="collection"><?= e(__('current_account.new_collection')) ?></option>
                <option value="payment"><?= e(__('current_account.new_payment')) ?></option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1"><?= e(__('current_account.target')) ?></label>
            <select name="target_type" onchange="toggleTarget(this.value)" class="w-full px-3 py-2 rounded-lg border border-slate-200">
                <option value="cash"><?= e(__('current_account.target_cash')) ?></option>
                <?php if (!empty($bankAccounts)): ?><option value="bank"><?= e(__('current_account.target_bank')) ?></option><?php endif; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1"><?= e(__('current_account.target')) ?></label>
            <select name="target_id" id="targetCash" class="w-full px-3 py-2 rounded-lg border border-slate-200">
                <?php if ((int) $account['company_id']): ?>
                <?php foreach ($cashAccounts as $ca): ?><option value="<?= e($ca['id']) ?>"><?= e($ca['name']) ?> (<?= e($ca['code']) ?>)</option><?php endforeach; ?>
                <?php endif; ?>
            </select>
            <?php if (!empty($bankAccounts)): ?>
            <select name="target_id" id="targetBank" class="w-full px-3 py-2 rounded-lg border border-slate-200 hidden" disabled>
                <?php foreach ($bankAccounts as $ba): ?><option value="<?= e($ba['id']) ?>"><?= e($ba['bank_name']) ?> — <?= e($ba['account_name'] ?? '') ?></option><?php endforeach; ?>
            </select>
            <?php endif; ?>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1"><?= e(__('current_account.amount')) ?></label>
            <input type="number" name="amount" step="0.01" min="0" required class="w-full px-3 py-2 rounded-lg border border-slate-200">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1"><?= e(__('current_account.date')) ?></label>
            <input type="date" name="date" value="<?= date('Y-m-d') ?>" class="w-full px-3 py-2 rounded-lg border border-slate-200">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1"><?= e(__('common.description')) ?></label>
            <input name="description" class="w-full px-3 py-2 rounded-lg border border-slate-200">
        </div>
        <?php if (!empty($unpaidInvoices)): ?>
        <div class="col-span-2 md:col-span-1">
            <label class="block text-xs font-medium text-slate-500 mb-1"><?= e(__('current_account.apply_to_invoice')) ?></label>
            <select name="invoice_id" class="w-full px-3 py-2 rounded-lg border border-slate-200">
                <option value="">—</option>
                <?php foreach ($unpaidInvoices as $vi): ?><option value="<?= e($vi['id']) ?>"><?= e($vi['number']) ?> · <?= e(money((float)$vi['total'] - (float)$vi['paid'])) ?></option><?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="col-span-2 md:col-span-1">
            <button class="w-full px-4 py-2 rounded-lg bg-brand-600 text-white text-sm font-semibold"><?= e(__('common.save')) ?></button>
        </div>
    </form>
</div>
<script>
function toggleTarget(v){
  document.getElementById('targetCash').classList.toggle('hidden', v!=='cash');
  document.getElementById('targetCash').disabled = v!=='cash';
  var b=document.getElementById('targetBank');
  if(b){ b.classList.toggle('hidden', v!=='bank'); b.disabled = v!=='bank'; }
}
</script>
<?php endif; ?>

<!-- Details + transactions -->
<div class="grid lg:grid-cols-3 gap-6">
    <div class="lg:col-span-1 bg-white border border-slate-200 rounded-2xl p-5 space-y-3 h-fit">
        <?php foreach ([
            ['current_account.type', __('current_account.type_' . $account['type'])],
            ['current_account.tax_number', $account['tax_number'] ?: '—'],
            ['current_account.email', $account['email'] ?: '—'],
            ['current_account.phone', $account['phone'] ?: '—'],
            ['current_account.iban', $account['iban'] ?: '—'],
            ['current_account.status', __('common.' . $account['status'])],
        ] as [$k, $val]): ?>
        <div class="flex justify-between text-sm">
            <span class="text-slate-400"><?= e(__($k)) ?></span>
            <span class="font-medium text-slate-700 text-right"><?= e($val) ?></span>
        </div>
        <?php endforeach; ?>
        <?php if ($account['address']): ?>
        <div class="pt-2 border-t border-slate-100 text-sm text-slate-500"><?= nl2br(e($account['address'])) ?></div>
        <?php endif; ?>

        <?php if (Auth::can('current_account.delete')): ?>
        <form method="post" action="<?= e(url('/app/current-accounts/' . $account['id'] . '/delete')) ?>" onsubmit="return confirm('<?= e(__('common.delete')) ?>?')">
            <?= csrf_field() ?>
            <button class="w-full mt-4 px-4 py-2 rounded-lg border border-red-200 text-red-600 text-sm font-medium hover:bg-red-50"><?= e(__('common.delete')) ?></button>
        </form>
        <?php endif; ?>
    </div>

    <div class="lg:col-span-2 bg-white border border-slate-200 rounded-2xl overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 font-semibold text-slate-800 text-sm"><?= e(__('current_account.transactions')) ?></div>
        <form method="get" action="<?= e(url('/app/current-accounts/' . $account['id'])) ?>" class="px-5 py-3 border-b border-slate-100 flex flex-wrap items-end gap-2 text-sm">
            <label class="text-xs text-slate-500"><?= e(__('common.from')) ?> <input type="date" name="from" value="<?= e($from ?? '') ?>" class="ml-1 px-2 py-1.5 rounded-lg border border-slate-200"></label>
            <label class="text-xs text-slate-500"><?= e(__('common.to')) ?> <input type="date" name="to" value="<?= e($to ?? '') ?>" class="ml-1 px-2 py-1.5 rounded-lg border border-slate-200"></label>
            <label class="text-xs text-slate-500"><?= e(__('current_account.type')) ?>
                <select name="type" class="ml-1 px-2 py-1.5 rounded-lg border border-slate-200">
                    <option value="">—</option>
                    <?php foreach (['debt','credit','payment','collection'] as $ct): ?><option value="<?= $ct ?>" <?= $type===$ct?'selected':'' ?>><?= e(__('current_account.type_' . $ct)) ?></option><?php endforeach; ?>
                </select>
            </label>
            <button class="px-3 py-1.5 rounded-lg bg-brand-600 text-white text-xs font-semibold"><?= e(__('common.filter')) ?></button>
            <a href="<?= e(url('/app/current-accounts/' . $account['id'])) ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 text-slate-500 text-xs"><?= e(__('common.reset')) ?></a>
        </form>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-xs text-slate-400 uppercase bg-slate-50">
                    <tr>
                        <th class="px-5 py-2"><?= e(__('current_account.date')) ?></th>
                        <th class="px-5 py-2"><?= e(__('current_account.type')) ?></th>
                        <th class="px-5 py-2"><?= e(__('common.description')) ?></th>
                        <th class="px-5 py-2 text-right"><?= e(__('common.amount')) ?></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if (!$transactions): ?>
                    <tr><td colspan="4" class="px-5 py-6 text-center text-slate-400"><?= e(__('current_account.no_transactions')) ?></td></tr>
                    <?php endif; ?>
                    <?php foreach ($transactions as $t): ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-5 py-2.5 text-slate-500"><?= e(format_date($t['date'])) ?></td>
                        <td class="px-5 py-2.5">
                            <span class="text-xs px-2 py-0.5 rounded-full <?= in_array($t['type'],['credit','collection'],true) ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-600' ?>"><?= e(__('current_account.type_' . $t['type'])) ?></span>
                        </td>
                        <td class="px-5 py-2.5 text-slate-600"><?= e($t['description'] ?: '—') ?></td>
                        <td class="px-5 py-2.5 text-right font-medium <?= in_array($t['type'],['credit','collection'],true) ? 'text-emerald-600' : 'text-red-600' ?>"><?= e(money($t['amount'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= $this->partial('partials.pagination', ['page' => $page ?? 1, 'lastPage' => $lastPage ?? 1, 'total' => $total ?? null]) ?>
    </div>
</div>
