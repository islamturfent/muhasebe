<?php
/** @var array $settings */
?>
<div class="max-w-2xl">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900"><?= e(__('beyan.settings_title')) ?></h1>
        <p class="text-slate-500 text-sm mt-1"><?= e(__('beyan.settings_subtitle')) ?></p>
    </div>
    <form method="post" action="<?= e(url('/app/settings/gib')) ?>" class="bg-white border border-slate-200 rounded-2xl p-6 space-y-4">
        <?= csrf_field() ?>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs text-slate-500 mb-1"><?= e(__('beyan.provider')) ?></label>
                <select name="provider" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm">
                    <option value="simulated" <?= ($settings['provider'] ?? 'simulated') === 'simulated' ? 'selected' : '' ?>>Simulated (test)</option>
                    <option value="rest" <?= ($settings['provider'] ?? '') === 'rest' ? 'selected' : '' ?>>REST (GİB / entegratör)</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-slate-500 mb-1"><?= e(__('beyan.mode')) ?></label>
                <select name="mode" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm">
                    <option value="test" <?= ($settings['mode'] ?? 'test') === 'test' ? 'selected' : '' ?>>Test</option>
                    <option value="prod" <?= ($settings['mode'] ?? '') === 'prod' ? 'selected' : '' ?>>Prod</option>
                </select>
            </div>
        </div>
        <div>
            <label class="block text-xs text-slate-500 mb-1"><?= e(__('beyan.token')) ?> (e-Beyan entegrasyon anahtarı)</label>
            <input name="token" value="<?= e($settings['token'] ?? '') ?>" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm">
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs text-slate-500 mb-1"><?= e(__('beyan.username')) ?></label>
                <input name="username" value="<?= e($settings['username'] ?? '') ?>" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm">
            </div>
            <div>
                <label class="block text-xs text-slate-500 mb-1"><?= e(__('beyan.password')) ?></label>
                <input name="password" type="password" placeholder="••••••" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm">
            </div>
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs text-slate-500 mb-1">Test URL</label>
                <input name="test_url" value="<?= e($settings['test_url'] ?? '') ?>" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm">
            </div>
            <div>
                <label class="block text-xs text-slate-500 mb-1">Production URL</label>
                <input name="production_url" value="<?= e($settings['production_url'] ?? '') ?>" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm">
            </div>
        </div>
        <p class="text-xs text-slate-400"><?= e(__('beyan.settings_note')) ?></p>
        <button class="px-6 py-2.5 rounded-xl bg-brand-600 text-white font-semibold"><?= e(__('common.save')) ?></button>
    </form>
</div>
