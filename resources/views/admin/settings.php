<?php
/** @var string $activeTab @var string $announcement @var bool $maintenance */
?>
<div class="max-w-2xl mx-auto space-y-6">
    <h1 class="text-2xl font-bold text-slate-900"><?= e(__('admin.settings_title')) ?></h1>
    <p class="text-slate-500 -mt-4"><?= e(__('admin.settings_subtitle')) ?></p>

    <?= $this->partial('admin._settings_nav', ['activeTab' => $activeTab]) ?>

    <form method="post" action="<?= e(url('/admin/settings')) ?>" class="bg-white border border-slate-200 rounded-2xl p-6 space-y-5">
        <?= csrf_field() ?>

        <label class="flex items-center gap-3 p-4 rounded-xl border <?= $maintenance ? 'border-amber-300 bg-amber-50' : 'border-slate-200' ?> cursor-pointer">
            <input type="checkbox" name="maintenance" <?= $maintenance ? 'checked' : '' ?> class="w-5 h-5">
            <div>
                <div class="font-semibold text-slate-800"><?= e(__('admin.maintenance_mode')) ?></div>
                <div class="text-sm text-slate-500"><?= e(__('admin.maintenance_hint')) ?></div>
            </div>
        </label>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('admin.announcement')) ?></label>
            <textarea name="announcement" rows="3" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none" placeholder="<?= e(__('admin.announcement_hint')) ?>"><?= e($announcement) ?></textarea>
        </div>

        <button class="w-full px-6 py-3 rounded-xl bg-brand-600 text-white font-semibold hover:bg-brand-700"><?= e(__('common.save')) ?></button>
    </form>
</div>
