<?php
/** @var array $input @var array $errors */
use Muh\Core\Session;
$errors = Session::get('_form_errors', []);
?>
<div class="max-w-2xl mx-auto p-6">
    <a href="<?= e(url('/app/tax-rates')) ?>" class="text-sm text-brand-600 hover:underline">← <?= e(__('taxrate.title')) ?></a>

    <div class="mt-4 bg-white border border-slate-200 rounded-2xl p-6">
        <h1 class="text-xl font-bold text-slate-900"><?= e(__('taxrate.new')) ?></h1>

        <?php if ($errors): ?><div class="mt-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>

        <form method="post" action="<?= e(url('/app/tax-rates')) ?>" class="mt-6 space-y-5">
            <?= csrf_field() ?>

            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('taxrate.name')) ?> *</label>
                    <input name="name" required value="<?= e($input['name'] ?? '') ?>" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('taxrate.rate')) ?> *</label>
                    <input name="rate" type="number" step="0.01" min="0" max="100" required value="<?= e($input['rate'] ?? '') ?>" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                </div>
            </div>

            <div class="flex items-center gap-6">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_vat" value="1"> <?= e(__('taxrate.type_vat')) ?></label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_withholding" value="1"> <?= e(__('taxrate.type_withholding')) ?></label>
            </div>

            <button class="w-full px-6 py-3 rounded-xl bg-brand-600 text-white font-semibold hover:bg-brand-700"><?= e(__('taxrate.new')) ?></button>
        </form>
    </div>
</div>
