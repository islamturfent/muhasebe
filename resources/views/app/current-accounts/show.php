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
    </div>
</div>
