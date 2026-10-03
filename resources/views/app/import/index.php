<?php
/** @var array $companies */
use Muh\Core\Session;
$errors = Session::get('_form_errors', []);
Session::forget('_form_errors');
?>
<div class="max-w-2xl">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900"><?= e(__('import.title')) ?></h1>
        <p class="text-slate-500"><?= e(__('import.upload_file')) ?></p>
    </div>

    <form method="post" action="<?= e(url('/app/import/preview')) ?>" enctype="multipart/form-data" class="bg-white border border-slate-200 rounded-2xl p-6 space-y-4">
        <?= csrf_field() ?>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('import.choose_module')) ?> *</label>
            <select name="module" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                <option value="cari"><?= e(__('import.module_cari')) ?></option>
                <option value="stok"><?= e(__('import.module_stok')) ?></option>
                <option value="hesap"><?= e(__('import.module_chart')) ?></option>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('import.company')) ?> *</label>
            <select name="company_id" required class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                <?php foreach ($companies as $c): ?><option value="<?= e($c['id']) ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('import.file')) ?> *</label>
            <input type="file" name="file" accept=".xlsx,.csv" required class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="has_header" value="1" checked class="w-4 h-4 text-brand-600"> <?= e(__('import.has_header')) ?>
        </label>

        <button class="w-full px-6 py-3 rounded-xl bg-brand-600 text-white font-semibold hover:bg-brand-700"><?= e(__('import.upload')) ?></button>
    </form>
</div>
