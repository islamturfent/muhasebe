<?php
/** @var array $companies @var int $selectedCompany @var array $input */
use Muh\Core\Session;
$errors = Session::get('_form_errors', []);
?>
<div class="max-w-2xl mx-auto p-6">
    <a href="<?= e(url('/app/branches')) ?>" class="text-sm text-brand-600 hover:underline">← <?= e(__('branch.title')) ?></a>
    <div class="mt-4 bg-white border border-slate-200 rounded-2xl p-6">
        <h1 class="text-xl font-bold text-slate-900"><?= e(__('branch.new')) ?></h1>

        <?php if ($errors): ?><div class="mt-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>

        <form method="post" action="<?= e(url('/app/branches')) ?>" class="mt-6 space-y-5">
            <?= csrf_field() ?>
            <?php if (!empty($input['return'])): ?><input type="hidden" name="return" value="<?= e($input['return']) ?>"><?php endif; ?>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('branch.company')) ?> *</label>
                <select name="company_id" required class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                    <?php foreach ($companies as $c): ?><option value="<?= e($c['id']) ?>" <?= (int) $selectedCompany === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('branch.name')) ?> *</label>
                <input name="name" required value="<?= e($input['name'] ?? '') ?>" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
            </div>

            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('branch.city')) ?></label>
                    <input name="city" value="<?= e($input['city'] ?? '') ?>" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('branch.phone')) ?></label>
                    <input name="phone" value="<?= e($input['phone'] ?? '') ?>" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('branch.address')) ?></label>
                <textarea name="address" rows="3" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none"><?= e($input['address'] ?? '') ?></textarea>
            </div>

            <button class="w-full px-6 py-3 rounded-xl bg-brand-600 text-white font-semibold hover:bg-brand-700"><?= e(__('branch.new')) ?></button>
        </form>
    </div>
</div>
