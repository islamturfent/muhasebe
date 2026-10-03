<?php
/** @var array $companies @var int $selectedCompany @var array $account */
use Muh\Core\Session;
$errors = Session::get('_form_errors', []);
Session::forget('_form_errors');
?>
<div class="max-w-2xl">
    <div class="mb-6">
        <a href="<?= e(url('/app/current-accounts')) ?>" class="text-sm text-brand-600 hover:underline">← <?= e(__('current_account.title')) ?></a>
        <h1 class="text-2xl font-bold text-slate-900 mt-1"><?= e(__('current_account.new')) ?></h1>
    </div>

    <form method="post" action="<?= e(url('/app/current-accounts')) ?>" class="bg-white border border-slate-200 rounded-2xl p-6 space-y-4">
        <?= csrf_field() ?>

        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('current_account.company')) ?> *</label>
                <select name="company_id" required class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                    <?php foreach ($companies as $c): ?>
                    <option value="<?= e($c['id']) ?>" <?= (int)$selectedCompany===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('current_account.code')) ?> *</label>
                <input name="code" required value="<?= e($account['code'] ?? '') ?>" placeholder="120-001" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('current_account.name')) ?> *</label>
            <input name="name" required value="<?= e($account['name'] ?? '') ?>" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('current_account.type')) ?> *</label>
            <div class="flex gap-4">
                <?php foreach (['customer','supplier','both'] as $t): ?>
                <label class="flex items-center gap-2"><input type="radio" name="type" value="<?= $t ?>" <?= ($account['type'] ?? 'customer')===$t?'checked':'' ?>> <?= e(__('current_account.type_' . $t)) ?></label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="grid md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('current_account.tax_number')) ?></label>
                <input name="tax_number" value="<?= e($account['tax_number'] ?? '') ?>" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('current_account.email')) ?></label>
                <input type="email" name="email" value="<?= e($account['email'] ?? '') ?>" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('current_account.phone')) ?></label>
                <input name="phone" value="<?= e($account['phone'] ?? '') ?>" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('current_account.address')) ?></label>
            <textarea name="address" rows="2" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none"><?= e($account['address'] ?? '') ?></textarea>
        </div>

        <div class="grid md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('current_account.iban')) ?></label>
                <input name="iban" value="<?= e($account['iban'] ?? '') ?>" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('current_account.risk_limit')) ?></label>
                <input name="risk_limit" value="<?= e($account['risk_limit'] ?? '') ?>" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('current_account.opening_amount')) ?></label>
                <input name="opening_balance" value="<?= e($account['opening_balance'] ?? '') ?>" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none" placeholder="0.00">
            </div>
        </div>

        <?php if ($errors): ?>
        <div class="p-3 rounded-lg bg-red-50 border border-red-200 text-sm text-red-700">
            <?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="flex gap-3 pt-2">
            <button class="px-6 py-3 rounded-xl bg-brand-600 text-white font-semibold hover:bg-brand-700"><?= e(__('common.save')) ?></button>
            <a href="<?= e(url('/app/current-accounts')) ?>" class="px-6 py-3 rounded-xl border border-slate-200 text-slate-600 font-medium"><?= e(__('common.cancel')) ?></a>
        </div>
    </form>
</div>
