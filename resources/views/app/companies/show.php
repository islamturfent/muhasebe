<?php
/** @var array $company @var array $periods @var array $accounts @var array $currentAccounts */
use Muh\Core\Auth;
use Muh\Core\Translator;
$locale = Translator::instance()->locale();
$totalDebit = array_sum(array_column($accounts, 'opening_debit'));
$totalCredit = array_sum(array_column($accounts, 'opening_credit'));
?>
<div class="mb-6">
    <a href="<?= e(url('/app/companies')) ?>" class="text-sm text-brand-600 hover:underline">← <?= e(__('nav.companies')) ?></a>
    <div class="flex items-center gap-3 mt-2">
        <?php if (!empty($company['logo_path'])): ?>
            <img src="<?= e(url('/company-logo/' . (int) $company['id'])) ?>" alt="" class="w-12 h-12 rounded-xl object-contain border border-slate-200">
        <?php else: ?>
            <span class="w-12 h-12 rounded-xl bg-brand-100 text-brand-700 flex items-center justify-center text-lg font-bold"><?= e(mb_strtoupper(mb_substr($company['name'], 0, 1))) ?></span>
        <?php endif; ?>
        <div class="flex flex-col">
            <h1 class="text-2xl font-bold text-slate-900"><?= e($company['name']) ?></h1>
            <p class="text-sm text-slate-500"><?= e($company['trade_name'] ?? '') ?><?= $company['tax_number'] ? ' · ' . e($company['tax_number']) : '' ?> · <?= e($company['tax_office'] ?? '') ?></p>
            <form method="post" enctype="multipart/form-data" action="<?= e(url('/app/companies/' . (int) $company['id'] . '/logo')) ?>" class="mt-2 flex items-center gap-2">
                <?= csrf_field() ?>
                <input type="file" name="logo" accept="image/png,image/jpeg,image/svg+xml,image/webp" class="text-xs">
                <button class="px-3 py-1.5 rounded-lg bg-brand-600 text-white text-xs font-semibold"><?= e(__('app.logo_upload')) ?></button>
            </form>
        </div>
    </div>
</div>

<div class="grid lg:grid-cols-3 gap-6">
    <!-- Left column: fiscal periods + current accounts -->
    <div class="lg:col-span-1 space-y-6">
        <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-100 font-semibold text-slate-800 text-sm"><?= e(__('period.title')) ?></div>
            <div class="divide-y divide-slate-50">
                <?php foreach ($periods as $p): $hasNext = false; ?>
                <div class="px-5 py-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="font-medium text-slate-700 text-sm"><?= e($p['name']) ?></div>
                            <div class="text-xs text-slate-400"><?= e(format_date($p['start_date'])) ?> – <?= e(format_date($p['end_date'])) ?></div>
                        </div>
                        <?php if ($p['is_closed']): ?><span class="px-2 py-0.5 rounded-full text-[11px] bg-slate-100 text-slate-500"><?= e(__('period.status_closed')) ?></span>
                        <?php elseif ($p['is_current']): ?><span class="px-2 py-0.5 rounded-full text-[11px] bg-brand-50 text-brand-600"><?= e(__('common.active')) ?></span>
                        <?php else: ?><span class="px-2 py-0.5 rounded-full text-[11px] bg-green-50 text-green-600"><?= e(__('period.status_open')) ?></span><?php endif; ?>
                    </div>
                    <?php if (!$p['is_closed'] && Auth::can('accounting.create')):
                        $next = null;
                        foreach ($periods as $pp) { if (!$pp['is_closed'] && $pp['start_date'] > $p['start_date']) { $next = $pp; break; } }
                        $hasNext = (bool) $next;
                    ?>
                    <form method="post" action="<?= e(url('/app/periods/close')) ?>" class="mt-2 flex items-center gap-2" onsubmit="return confirm('<?= e(__('period.close_confirm')) ?>')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="company_id" value="<?= (int)$company['id'] ?>">
                        <input type="hidden" name="period_id" value="<?= (int)$p['id'] ?>">
                        <?php if ($hasNext): ?>
                        <select name="target_period_id" class="text-xs border border-slate-200 rounded-lg px-2 py-1 bg-white">
                            <option value="<?= (int)$next['id'] ?>"><?= e(__('period.choose_target')) ?>: <?= e($next['name']) ?></option>
                        </select>
                        <?php endif; ?>
                        <button class="px-2.5 py-1 rounded-lg bg-amber-50 text-amber-700 text-xs font-medium hover:bg-amber-100"><?= e(__('period.close')) ?></button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
                <span class="font-semibold text-slate-800 text-sm"><?= e(__('branch.title')) ?></span>
                <a href="<?= e(url('/app/branches/create?company_id=' . $company['id'])) ?>" class="text-xs text-brand-600 hover:underline"><?= e(__('branch.add_branch')) ?></a>
            </div>
            <div class="divide-y divide-slate-50">
                <?php foreach ($branches as $b): ?>
                <div class="px-5 py-2.5 flex items-center justify-between text-sm">
                    <span class="text-slate-700 font-medium"><?= e($b['name']) ?></span>
                    <span class="text-xs text-slate-400"><?= e($b['city'] ?? '') ?></span>
                </div>
                <?php endforeach; ?>
                <?php if (!$branches): ?><div class="px-5 py-4 text-sm text-slate-400"><?= e(__('branch.no_branches')) ?></div><?php endif; ?>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-100 font-semibold text-slate-800 text-sm"><?= e(__('nav.current_accounts')) ?></div>
            <div class="divide-y divide-slate-50">
                <?php foreach ($currentAccounts as $ca): ?>
                <div class="px-5 py-2.5 flex items-center justify-between text-sm">
                    <span class="text-slate-600"><?= e($ca['code']) ?> · <?= e($ca['name']) ?></span>
                    <span class="font-medium <?= $ca['balance'] < 0 ? 'text-red-600' : 'text-slate-800' ?>"><?= e(money($ca['balance'])) ?></span>
                </div>
                <?php endforeach; ?>
                <?php if (!$currentAccounts): ?><div class="px-5 py-4 text-sm text-slate-400"><?= e(__('common.no_data')) ?></div><?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right: chart of accounts -->
    <div class="lg:col-span-2 bg-white border border-slate-200 rounded-2xl overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
            <span class="font-semibold text-slate-800 text-sm"><?= e(__('nav.chart_of_accounts')) ?></span>
            <span class="text-xs text-slate-400"><?= count($accounts) ?> <?= e(__('common.total')) ?></span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-xs text-slate-400 uppercase bg-slate-50">
                    <tr><th class="px-5 py-2 w-24"><?= e(__('common.code')) ?></th><th class="px-5 py-2"><?= e(__('common.name')) ?></th><th class="px-5 py-2"><?= e(__('common.status')) ?></th><th class="px-5 py-2 text-right"><?= e(__('common.debit')) ?></th><th class="px-5 py-2 text-right"><?= e(__('common.credit')) ?></th></tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php foreach ($accounts as $a): ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-5 py-2.5 font-mono text-brand-600"><?= e($a['code']) ?></td>
                        <td class="px-5 py-2.5 text-slate-700"><?= e($a['name']) ?></td>
                        <td class="px-5 py-2.5"><span class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-500"><?= e($a['type']) ?></span></td>
                        <td class="px-5 py-2.5 text-right text-slate-700"><?= e(money($a['opening_debit'])) ?></td>
                        <td class="px-5 py-2.5 text-right text-slate-700"><?= e(money($a['opening_credit'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="bg-slate-50 font-semibold text-slate-800">
                        <td class="px-5 py-2.5" colspan="3"><?= e(__('common.total')) ?></td>
                        <td class="px-5 py-2.5 text-right"><?= e(money($totalDebit)) ?></td>
                        <td class="px-5 py-2.5 text-right"><?= e(money($totalCredit)) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
