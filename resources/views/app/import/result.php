<?php
/** @var string $module @var int $created @var int $total @var array $errors @var string|null $errorCsvUrl */
?>
<div class="max-w-3xl">
    <div class="mb-6">
        <a href="<?= e(url('/app/import')) ?>" class="text-sm text-brand-600 hover:underline">← <?= e(__('import.title')) ?></a>
        <h1 class="text-2xl font-bold text-slate-900 mt-1"><?= e(__('import.result')) ?></h1>
        <p class="text-slate-500"><?= e(__('import.module_' . $module)) ?></p>
    </div>

    <div class="grid grid-cols-2 gap-4 mb-6">
        <div class="rounded-2xl bg-white border border-emerald-200 p-5">
            <div class="text-xs text-slate-400 uppercase"><?= e(__('import.imported')) ?></div>
            <div class="text-2xl font-bold text-emerald-600"><?= (int) $created ?></div>
            <div class="text-xs text-slate-400">/ <?= (int) $total ?></div>
        </div>
        <div class="rounded-2xl bg-white border <?= $errors ? 'border-red-200' : 'border-slate-200' ?> p-5">
            <div class="text-xs text-slate-400 uppercase"><?= e(__('import.with_errors')) ?></div>
            <div class="text-2xl font-bold <?= $errors ? 'text-red-600' : 'text-emerald-600' ?>"><?= count($errors) ?></div>
        </div>
    </div>

    <?php if ($errors): ?>
    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
            <span class="font-semibold text-slate-800 text-sm"><?= e(__('import.with_errors')) ?></span>
            <?php if ($errorCsvUrl): ?><a href="<?= e($errorCsvUrl) ?>" class="text-xs text-brand-600 hover:underline"><?= e(__('import.errors_download')) ?> ↓</a><?php endif; ?>
        </div>
        <table class="w-full text-sm">
            <thead class="text-left text-xs text-slate-400 uppercase bg-slate-50">
                <tr><th class="px-5 py-2"><?= e(__('import.field')) ?></th><th class="px-5 py-2"><?= e(__('common.message')) ?></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php foreach (array_slice($errors, 0, 20) as $e): ?>
                <tr><td class="px-5 py-2.5 text-slate-500"><?= (int) $e['row'] ?></td><td class="px-5 py-2.5 text-red-600"><?= e($e['message']) ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="px-4 py-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm">✓ <?= e(__('import.no_errors')) ?></div>
    <?php endif; ?>

    <div class="mt-6">
        <a href="<?= e(url('/app/import')) ?>" class="inline-block px-6 py-3 rounded-xl bg-brand-600 text-white font-semibold hover:bg-brand-700"><?= e(__('import.upload')) ?></a>
    </div>
</div>
