<?php
/** @var array $sections */
?>
<div class="max-w-4xl mx-auto p-6">
    <h1 class="text-2xl font-bold text-slate-900"><?= e(__('nav.settings')) ?></h1>
    <p class="text-slate-500 mb-6"><?= e(__('app.settings_desc')) ?></p>

    <div class="grid md:grid-cols-2 gap-4">
        <?php foreach ($sections as [$path, $label, $icon]): ?>
        <a href="<?= e(url($path)) ?>" class="flex items-center gap-4 bg-white border border-slate-200 rounded-2xl p-5 hover:border-brand-300 hover:shadow-sm transition">
            <span class="w-11 h-11 rounded-xl bg-brand-100 text-brand-700 flex items-center justify-center text-xl"><?= $icon ?></span>
            <div>
                <div class="font-semibold text-slate-900"><?= e(__($label)) ?></div>
                <div class="text-sm text-brand-600"><?= e(__('common.view')) ?> →</div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</div>
