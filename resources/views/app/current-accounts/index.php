<?php
/** @var array $accounts @var array $companies @var int $companyId @var int $defaultCompanyId @var string|null $type @var string|null $search */
use Muh\Core\Translator;
$locale = Translator::instance()->locale();
?>
<div class="mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-slate-900"><?= e(__('current_account.title')) ?></h1>
        <p class="text-slate-500"><?= count($accounts) ?> <?= e(__('common.records')) ?></p>
    </div>
    <a href="<?= e(url('/app/current-accounts/create')) ?>" class="px-4 py-2 rounded-lg bg-brand-600 text-white text-sm font-semibold hover:bg-brand-700">+ <?= e(__('current_account.new')) ?></a>
</div>

<!-- Filters -->
<form method="get" action="<?= e(url('/app/current-accounts')) ?>" class="mb-6 bg-white border border-slate-200 rounded-2xl p-4 flex flex-col md:flex-row gap-3">
    <select name="company_id" class="text-sm border border-slate-200 rounded-lg px-3 py-2 bg-white focus:ring-2 focus:ring-brand-500 outline-none">
        <option value=""><?= e(__('current_account.company')) ?> (<?= e(__('common.all')) ?>)</option>
        <?php foreach ($companies as $c): ?>
        <option value="<?= e($c['id']) ?>" <?= (int)$companyId===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="type" class="text-sm border border-slate-200 rounded-lg px-3 py-2 bg-white focus:ring-2 focus:ring-brand-500 outline-none">
        <option value=""><?= e(__('current_account.type_filter')) ?></option>
        <option value="customer" <?= $type==='customer'?'selected':'' ?>><?= e(__('current_account.type_customer')) ?></option>
        <option value="supplier" <?= $type==='supplier'?'selected':'' ?>><?= e(__('current_account.type_supplier')) ?></option>
        <option value="both" <?= $type==='both'?'selected':'' ?>><?= e(__('current_account.type_both')) ?></option>
    </select>
    <input type="text" name="search" value="<?= e($search ?? '') ?>" placeholder="<?= e(__('current_account.search_placeholder')) ?>" class="flex-1 text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-brand-500 outline-none">
    <div class="flex gap-2">
        <button class="px-4 py-2 rounded-lg bg-brand-600 text-white text-sm font-semibold"><?= e(__('common.search')) ?></button>
        <a href="<?= e(url('/app/current-accounts')) ?>" class="px-3 py-2 rounded-lg border border-slate-200 text-sm text-slate-500"><?= e(__('common.cancel')) ?></a>
    </div>
</form>

<div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-left text-xs text-slate-400 uppercase bg-slate-50">
                <tr>
                    <th class="px-5 py-3"><?= e(__('current_account.code')) ?></th>
                    <th class="px-5 py-3"><?= e(__('current_account.name')) ?></th>
                    <th class="px-5 py-3"><?= e(__('current_account.company')) ?></th>
                    <th class="px-5 py-3"><?= e(__('current_account.type')) ?></th>
                    <th class="px-5 py-3 text-right"><?= e(__('current_account.debit')) ?></th>
                    <th class="px-5 py-3 text-right"><?= e(__('current_account.credit')) ?></th>
                    <th class="px-5 py-3 text-right"><?= e(__('current_account.balance')) ?></th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php if (!$accounts): ?>
                <tr><td colspan="8" class="px-5 py-8 text-center text-slate-400"><?= e(__('current_account.no_records')) ?></td></tr>
                <?php endif; ?>
                <?php foreach ($accounts as $a): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-5 py-3 font-mono text-brand-600"><?= e($a['code']) ?></td>
                    <td class="px-5 py-3 text-slate-800 font-medium">
                        <a href="<?= e(url('/app/current-accounts/' . $a['id'])) ?>" class="hover:text-brand-600"><?= e($a['name']) ?></a>
                    </td>
                    <td class="px-5 py-3 text-slate-500"><?= e($a['company_name']) ?></td>
                    <td class="px-5 py-3">
                        <span class="px-2 py-0.5 rounded-full text-xs <?= $a['type']==='supplier' ? 'bg-amber-50 text-amber-700' : 'bg-brand-50 text-brand-600' ?>"><?= e(__('current_account.type_' . $a['type'])) ?></span>
                    </td>
                    <td class="px-5 py-3 text-right text-slate-700"><?= e(money(max(0, $a['balance']))) ?></td>
                    <td class="px-5 py-3 text-right text-slate-700"><?= e(money(max(0, -$a['balance']))) ?></td>
                    <td class="px-5 py-3 text-right font-semibold <?= $a['balance'] < 0 ? 'text-red-600' : 'text-emerald-600' ?>"><?= e(money($a['balance'])) ?></td>
                    <td class="px-5 py-3 text-right">
                        <a href="<?= e(url('/app/current-accounts/' . $a['id'])) ?>" class="text-xs text-brand-600 font-medium"><?= e(__('common.details')) ?></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
