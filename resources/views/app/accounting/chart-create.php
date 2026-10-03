<?php
/** @var array $companies @var int $companyId @var array $periods @var int $periodId @var array|null $account */
use Muh\Core\Auth;
use Muh\Core\Session;
$errors = Session::get('_form_errors', []);
Session::forget('_form_errors');
$types = ['asset', 'liability', 'equity', 'income', 'expense'];
$isEdit = !empty($account);
$val = function ($field, $default = '') use ($account, $isEdit) {
    if ($isEdit && $account !== null) {
        return $account[$field] ?? $default;
    }
    return $default;
};
?>
<div class="max-w-xl">
    <div class="mb-6">
        <a href="<?= e(url('/app/accounting/chart')) ?>" class="text-sm text-brand-600 hover:underline">← <?= e(__('accounting.chart_of_accounts')) ?></a>
        <h1 class="text-2xl font-bold text-slate-900 mt-1"><?= e(__($isEdit ? 'accounting.edit_account' : 'accounting.new_account')) ?></h1>
    </div>

    <form method="post" action="<?= e(url($isEdit ? '/app/accounting/chart/' . (int)$account['id'] : '/app/accounting/chart')) ?>" class="bg-white border border-slate-200 rounded-2xl p-6 space-y-4">
        <?= csrf_field() ?>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('accounting.account_code')) ?> *</label>
                <input name="code" value="<?= e($val('code')) ?>" required class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('accounting.account_type')) ?> *</label>
                <select name="type" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                    <?php foreach ($types as $t): ?><option value="<?= e($t) ?>" <?= $val('type') === $t ? 'selected' : '' ?>><?= e(__('accounting.type_' . $t)) ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('common.name')) ?> *</label>
            <input name="name" value="<?= e($val('name')) ?>" required class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('accounting.subtype')) ?></label>
                <input name="subtype" value="<?= e($val('subtype')) ?>" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('accounting.account_is_header')) ?></label>
                <label class="flex items-center gap-2 mt-3 text-sm text-slate-600">
                    <input type="checkbox" name="is_header" value="1" <?= $val('is_header') ? 'checked' : '' ?> class="w-4 h-4 text-brand-600"> <?= e(__('accounting.is_header_help')) ?>
                </label>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('common.debit')) ?> (<?= e(__('accounting.opening_balance')) ?>)</label>
                <input type="number" name="opening_debit" step="0.01" min="0" value="<?= e($val('opening_debit', '0')) ?>" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('common.credit')) ?> (<?= e(__('accounting.opening_balance')) ?>)</label>
                <input type="number" name="opening_credit" step="0.01" min="0" value="<?= e($val('opening_credit', '0')) ?>" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('accounting.budget_amount')) ?></label>
            <input type="number" name="budget_amount" step="0.01" value="<?= e($val('budget_amount', '0')) ?>" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
            <p class="mt-1 text-xs text-slate-400"><?= e(__('accounting.budget_help')) ?></p>
        </div>

        <?php if ($isEdit): ?>
        <div class="grid grid-cols-2 gap-4">
            <input type="hidden" name="company_id" value="<?= (int)$account['company_id'] ?>">
            <input type="hidden" name="period_id" value="<?= (int)$account['fiscal_period_id'] ?>">
        </div>
        <?php else: ?>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('invoice.company')) ?> *</label>
                <select name="company_id" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                    <?php foreach ($companies as $c): ?><option value="<?= e($c['id']) ?>" <?= (int)$companyId===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('accounting.chart_of_accounts')) ?></label>
                <select name="period_id" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                    <?php foreach ($periods as $p): ?><option value="<?= e($p['id']) ?>" <?= (int)$periodId===(int)$p['id']?'selected':'' ?>><?= e($p['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($errors): ?><div class="p-3 rounded-lg bg-red-50 border border-red-200 text-sm text-red-700"><?php foreach ($errors as $er): ?><div>• <?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>

        <div class="flex gap-3">
            <button class="px-6 py-3 rounded-xl bg-brand-600 text-white font-semibold hover:bg-brand-700"><?= e(__('common.save')) ?></button>
            <a href="<?= e(url('/app/accounting/chart')) ?>" class="px-6 py-3 rounded-xl border border-slate-200 text-slate-600 font-medium"><?= e(__('common.cancel')) ?></a>
        </div>
    </form>
</div>
