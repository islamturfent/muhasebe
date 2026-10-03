<?php
/** @var array $account @var array $transactions */
?>
<div class="max-w-4xl">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <a href="<?= e(url('/app/bank')) ?>" class="text-sm text-brand-600 hover:underline">← <?= e(__('bank.title')) ?></a>
            <h1 class="text-2xl font-bold text-slate-900 mt-1"><?= e($account['account_name'] ?? $account['bank_name']) ?></h1>
            <p class="text-slate-500"><?= e($account['bank_name']) ?> · <?= e($account['iban'] ?? '') ?></p>
        </div>
        <div class="text-right">
            <div class="text-xs uppercase text-slate-400"><?= e(__('bank.balance')) ?></div>
            <div class="text-2xl font-bold <?= $account['balance'] < 0 ? 'text-red-600' : 'text-emerald-600' ?>"><?= e(money($account['balance'], $account['currency'])) ?></div>
        </div>
    </div>

    <?php if (!empty($recon)): ?>
    <div class="mb-6 bg-white border border-slate-200 rounded-2xl p-5">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold text-slate-800"><?= e(__('report.bank_reconciliation')) ?></h3>
            <span class="px-2.5 py-1 rounded-full text-xs font-medium <?= $recon['ok'] ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' ?>"><?= e($recon['ok'] ? __('report.rc_ok') : __('report.rc_mismatch')) ?></span>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-sm">
            <div><div class="text-xs text-slate-400"><?= e(__('report.rc_bank_balance')) ?></div><div class="font-semibold text-slate-800"><?= e(number_format($recon['balance'], 2, ',', '.')) ?></div></div>
            <div><div class="text-xs text-slate-400"><?= e(__('report.rc_deposits')) ?></div><div class="font-semibold text-emerald-600"><?= e(number_format($recon['deposits'], 2, ',', '.')) ?></div></div>
            <div><div class="text-xs text-slate-400"><?= e(__('report.rc_withdrawals')) ?></div><div class="font-semibold text-red-600"><?= e(number_format($recon['withdrawals'], 2, ',', '.')) ?></div></div>
            <div><div class="text-xs text-slate-400"><?= e(__('report.rc_net')) ?></div><div class="font-semibold text-slate-800"><?= e(number_format($recon['net'], 2, ',', '.')) ?></div></div>
            <div><div class="text-xs text-slate-400"><?= e(__('report.rc_difference')) ?></div><div class="font-semibold <?= $recon['difference'] == 0 ? 'text-slate-500' : 'text-amber-600' ?>"><?= e(number_format($recon['difference'], 2, ',', '.')) ?></div></div>
        </div>
    </div>
    <?php endif; ?>

    <div class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1 bg-white border border-slate-200 rounded-2xl p-5 h-fit">
            <h3 class="font-semibold text-slate-800 mb-4"><?= e(__('bank.new_transaction')) ?></h3>
            <form method="post" action="<?= e(url('/app/bank/' . $account['id'] . '/transaction')) ?>" class="space-y-3">
                <?= csrf_field() ?>
                <div><label class="block text-xs font-medium text-slate-500 mb-1"><?= e(__('bank.type')) ?></label>
                    <select name="type" class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                        <option value="deposit"><?= e(__('bank.type_deposit')) ?></option>
                        <option value="withdrawal"><?= e(__('bank.type_withdrawal')) ?></option>
                        <option value="transfer"><?= e(__('bank.type_transfer')) ?></option>
                        <option value="fee"><?= e(__('bank.type_fee')) ?></option>
                        <option value="interest"><?= e(__('bank.type_interest')) ?></option>
                    </select></div>
                <div class="grid grid-cols-2 gap-2">
                    <div><label class="block text-xs font-medium text-slate-500 mb-1"><?= e(__('bank.amount')) ?></label>
                        <input type="number" step="0.01" name="amount" class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none"></div>
                    <div><label class="block text-xs font-medium text-slate-500 mb-1"><?= e(__('bank.date')) ?></label>
                        <input type="date" name="date" value="<?= date('Y-m-d') ?>" class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none"></div>
                </div>
                <div><label class="block text-xs font-medium text-slate-500 mb-1"><?= e(__('bank.description')) ?></label>
                    <input name="description" class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none"></div>
                <button class="w-full px-4 py-2 rounded-lg bg-brand-600 text-white text-sm font-semibold"><?= e(__('bank.add_transaction')) ?></button>
            </form>
        </div>

        <?php if (!empty($transferDests)): ?>
        <div class="bg-white border border-slate-200 rounded-2xl p-5 mt-4">
            <h2 class="font-semibold text-slate-800 text-sm mb-3"><?= e(__('cash.virman')) ?></h2>
            <form method="post" action="<?= e(url('/app/bank/' . $account['id'] . '/virman')) ?>" class="space-y-3">
                <?= csrf_field() ?>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1"><?= e(__('cash.to_account')) ?></label>
                    <select name="to" required class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                        <?php foreach ($transferDests as $td): ?><option value="<?= e($td['account_type'] . ':' . $td['id']) ?>">[<?= e($td['label']) ?>] <?= e($td['name']) ?> (<?= e($td['code']) ?>)</option><?php endforeach; ?>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div><label class="block text-xs font-medium text-slate-500 mb-1"><?= e(__('bank.amount')) ?></label>
                        <input type="number" step="0.01" name="amount" min="0" required class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none"></div>
                    <div><label class="block text-xs font-medium text-slate-500 mb-1"><?= e(__('bank.date')) ?></label>
                        <input type="date" name="date" value="<?= date('Y-m-d') ?>" class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none"></div>
                </div>
                <button class="w-full px-4 py-2 rounded-lg bg-brand-600 text-white text-sm font-semibold"><?= e(__('cash.virman')) ?></button>
            </form>
        </div>
        <?php endif; ?>

        <div class="lg:col-span-2 bg-white border border-slate-200 rounded-2xl overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-100 font-semibold text-slate-800 text-sm"><?= e(__('bank.extract')) ?></div>
            <form method="get" action="<?= e(url('/app/bank/' . $account['id'])) ?>" class="px-5 py-3 border-b border-slate-100 flex flex-wrap items-end gap-2 text-sm">
                <label class="text-xs text-slate-500"><?= e(__('common.from')) ?> <input type="date" name="from" value="<?= e($from ?? '') ?>" class="ml-1 px-2 py-1.5 rounded-lg border border-slate-200"></label>
                <label class="text-xs text-slate-500"><?= e(__('common.to')) ?> <input type="date" name="to" value="<?= e($to ?? '') ?>" class="ml-1 px-2 py-1.5 rounded-lg border border-slate-200"></label>
                <label class="text-xs text-slate-500"><?= e(__('bank.type')) ?>
                    <select name="type" class="ml-1 px-2 py-1.5 rounded-lg border border-slate-200">
                        <option value="">—</option>
                        <?php foreach (['deposit','withdrawal','transfer','interest','expense'] as $bt): ?><option value="<?= $bt ?>" <?= $type===$bt?'selected':'' ?>><?= e(__('bank.type_' . $bt)) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <button class="px-3 py-1.5 rounded-lg bg-brand-600 text-white text-xs font-semibold"><?= e(__('common.filter')) ?></button>
                <a href="<?= e(url('/app/bank/' . $account['id'])) ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 text-slate-500 text-xs"><?= e(__('common.reset')) ?></a>
            </form>
            <table class="w-full text-sm">
                <thead class="text-left text-xs text-slate-400 uppercase bg-slate-50">
                    <tr><th class="px-5 py-2"><?= e(__('bank.date')) ?></th><th class="px-5 py-2"><?= e(__('bank.type')) ?></th><th class="px-5 py-2"><?= e(__('common.description')) ?></th><th class="px-5 py-2 text-right"><?= e(__('bank.amount')) ?></th></tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if (!$transactions): ?><tr><td colspan="4" class="px-5 py-6 text-center text-slate-400"><?= e(__('bank.no_transactions')) ?></td></tr><?php endif; ?>
                    <?php foreach ($transactions as $t): $pos = in_array($t['type'], ['deposit','interest','transfer'], true); ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-5 py-2.5 text-slate-500"><?= e(format_date($t['date'])) ?></td>
                        <td class="px-5 py-2.5"><span class="px-2 py-0.5 rounded-full text-xs bg-slate-100 text-slate-600"><?= e(__('bank.type_' . $t['type'])) ?></span></td>
                        <td class="px-5 py-2.5 text-slate-600"><?= e($t['description'] ?: '—') ?></td>
                        <td class="px-5 py-2.5 text-right font-medium <?= $pos ? 'text-emerald-600' : 'text-red-600' ?>"><?= $pos ? '+' : '-' ?><?= e(number_format((float)$t['amount'], 2, ',', '.')) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?= $this->partial('partials.pagination', ['page' => $page ?? 1, 'lastPage' => $lastPage ?? 1, 'total' => $total ?? null]) ?>
        </div>
    </div>
</div>
