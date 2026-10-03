<?php
/** @var array $companies @var int $selectedCompany */
use Muh\Core\Session;
$errors = Session::get('_form_errors', []);
Session::forget('_form_errors');
?>
<div class="max-w-xl">
    <div class="mb-6">
        <a href="<?= e(url('/app/cash')) ?>" class="text-sm text-brand-600 hover:underline">← <?= e(__('cash.title')) ?></a>
        <h1 class="text-2xl font-bold text-slate-900 mt-1"><?= e(__('cash.new_account')) ?></h1>
    </div>
    <form method="post" action="<?= e(url('/app/cash')) ?>" class="bg-white border border-slate-200 rounded-2xl p-6 space-y-4">
        <?= csrf_field() ?>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('cash.company')) ?> *</label>
            <select name="company_id" required class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                <?php foreach ($companies as $c): ?><option value="<?= e($c['id']) ?>" <?= (int)$selectedCompany===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('cash.code')) ?> *</label>
                <input name="code" required class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('cash.currency')) ?></label>
                <select name="currency" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                    <option value="TRY">₺ TRY</option><option value="USD">$ USD</option><option value="EUR">€ EUR</option>
                </select>
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('cash.account_name')) ?> *</label>
            <input name="name" required class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
        </div>
        <?php if ($errors): ?><div class="p-3 rounded-lg bg-red-50 border border-red-200 text-sm text-red-700"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>
        <button class="w-full px-6 py-3 rounded-xl bg-brand-600 text-white font-semibold hover:bg-brand-700"><?= e(__('common.save')) ?></button>
    </form>
</div>
