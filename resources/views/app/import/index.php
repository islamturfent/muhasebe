<?php
/** @var array $companies */
use Muh\Core\Session;
$errors = Session::get('_form_errors', []);
Session::forget('_form_errors');
?>
<div class="max-w-2xl">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900"><?= e(__('import.title')) ?></h1>
        <p class="text-slate-500"><?= e(__('import.upload_desc')) ?></p>
    </div>

    <form method="post" action="<?= e(url('/app/import/upload')) ?>" enctype="multipart/form-data" class="bg-white border border-slate-200 rounded-2xl p-6 space-y-4">
        <?= csrf_field() ?>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('import.choose_type')) ?></label>
            <div class="grid grid-cols-2 gap-3">
                <label class="flex items-center gap-2 border border-slate-200 rounded-xl px-4 py-3 cursor-pointer has-[:checked]:border-brand-500 has-[:checked]:ring-2 has-[:checked]:ring-brand-500/30">
                    <input type="radio" name="type" value="cari" checked> <?= e(__('import.type_cari')) ?>
                </label>
                <label class="flex items-center gap-2 border border-slate-200 rounded-xl px-4 py-3 cursor-pointer has-[:checked]:border-brand-500 has-[:checked]:ring-2 has-[:checked]:ring-brand-500/30">
                    <input type="radio" name="type" value="stock"> <?= e(__('import.type_stock')) ?>
                </label>
                <label class="flex items-center gap-2 border border-slate-200 rounded-xl px-4 py-3 cursor-pointer has-[:checked]:border-brand-500 has-[:checked]:ring-2 has-[:checked]:ring-brand-500/30">
                    <input type="radio" name="type" value="accounting"> <?= e(__('import.type_accounting')) ?>
                </label>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('import.company')) ?></label>
            <select name="company_id" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                <?php foreach ($companies as $c): ?><option value="<?= e($c['id']) ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('import.file')) ?></label>
            <input type="file" name="file" accept=".csv,.txt" class="w-full text-sm border border-slate-200 rounded-lg px-3 py-2">
        </div>

        <div class="flex gap-3">
            <a href="<?= e(url('/app/import/template/cari')) ?>" class="px-4 py-2 rounded-lg border border-slate-200 text-sm text-slate-600"><?= e(__('import.download_template')) ?> (Cari)</a>
            <a href="<?= e(url('/app/import/template/stock')) ?>" class="px-4 py-2 rounded-lg border border-slate-200 text-sm text-slate-600"><?= e(__('import.download_template')) ?> (Stok)</a>
            <a href="<?= e(url('/app/import/template/accounting')) ?>" class="px-4 py-2 rounded-lg border border-slate-200 text-sm text-slate-600"><?= e(__('import.download_template')) ?> (Hesap Planı)</a>
        </div>

        <?php if ($errors): ?><div class="p-3 rounded-lg bg-red-50 border border-red-200 text-sm text-red-700"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>

        <button class="w-full px-6 py-3 rounded-xl bg-brand-600 text-white font-semibold hover:bg-brand-700"><?= e(__('import.upload')) ?></button>
    </form>
</div>
