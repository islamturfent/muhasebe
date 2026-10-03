<?php
/** @var string $activeTab @var string $defaultLocale @var array $supportedLocales */
?>
<div class="max-w-2xl mx-auto space-y-6">
    <h1 class="text-2xl font-bold text-slate-900"><?= e(__('admin.settings_title')) ?></h1>
    <p class="text-slate-500 -mt-4"><?= e(__('admin.settings_subtitle')) ?></p>

    <?= $this->partial('admin._settings_nav', ['activeTab' => $activeTab]) ?>

    <form method="post" action="<?= e(url('/admin/settings/localization')) ?>" class="bg-white border border-slate-200 rounded-2xl p-6 space-y-5">
        <?= csrf_field() ?>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('admin.default_locale')) ?></label>
            <select name="default_locale" class="w-full md:w-64 px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                <?php foreach ($supportedLocales as $loc): ?>
                    <option value="<?= e($loc) ?>" <?= $defaultLocale === $loc ? 'selected' : '' ?>><?= e(mb_strtoupper($loc)) ?> — <?= e(__('admin.locale_' . $loc)) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="text-xs text-slate-400 mt-1"><?= e(__('admin.default_locale_hint')) ?></p>
        </div>

        <button class="w-full px-6 py-3 rounded-xl bg-brand-600 text-white font-semibold hover:bg-brand-700"><?= e(__('common.save')) ?></button>
    </form>
</div>
