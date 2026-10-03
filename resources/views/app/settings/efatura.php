<?php
/** @var array $cfg */
?>
<div class="max-w-2xl mx-auto p-6">
    <h1 class="text-2xl font-bold text-slate-900"><?= e(__('efatura.settings_title')) ?></h1>
    <p class="text-slate-500 mb-6 text-sm"><?= e(__('efatura.settings_hint')) ?></p>

    <form method="post" action="<?= e(url('/app/settings/efatura')) ?>" class="bg-white border border-slate-200 rounded-2xl p-6 space-y-4">
        <?= csrf_field() ?>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('efatura.provider')) ?></label>
            <select name="provider" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                <option value="simulated" <?= ($cfg['provider'] ?? '') === 'simulated' ? 'selected' : '' ?>><?= e(__('efatura.provider_simulated')) ?></option>
                <option value="rest" <?= in_array($cfg['provider'] ?? '', ['rest', 'entegrator'], true) ? 'selected' : '' ?>><?= e(__('efatura.provider_rest')) ?></option>
            </select>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('efatura.mode')) ?></label>
                <select name="mode" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                    <option value="test" <?= ($cfg['mode'] ?? '') === 'test' ? 'selected' : '' ?>><?= e(__('efatura.mode_test')) ?></option>
                    <option value="production" <?= ($cfg['mode'] ?? '') === 'production' ? 'selected' : '' ?>><?= e(__('efatura.mode_production')) ?></option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('efatura.username')) ?></label>
                <input name="username" value="<?= e($cfg['username'] ?? '') ?>" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('efatura.test_url')) ?></label>
            <input name="test_url" value="<?= e($cfg['test_url'] ?? '') ?>" placeholder="https://entegrator.example.test/v1" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('efatura.production_url')) ?></label>
            <input name="production_url" value="<?= e($cfg['production_url'] ?? '') ?>" placeholder="https://entegrator.example.com/v1" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('efatura.password')) ?></label>
            <input type="password" name="password" value="<?= e($cfg['password'] ?? '') ?>" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
        </div>

        <button class="w-full px-6 py-3 rounded-xl bg-brand-600 text-white font-semibold hover:bg-brand-700"><?= e(__('common.save')) ?></button>
    </form>
</div>
