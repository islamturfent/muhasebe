<?php
/** @var string $type @var int $imported @var array $errors */
?>
<div class="max-w-3xl">
    <div class="mb-6">
        <a href="<?= e(url('/app/import')) ?>" class="text-sm text-brand-600 hover:underline">← <?= e(__('import.back')) ?></a>
        <h1 class="text-2xl font-bold text-slate-900 mt-1"><?= e(__('import.import')) ?> — <?= e(__('import.type_' . $type)) ?></h1>
    </div>

    <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-5 mb-6">
        <div class="text-2xl font-bold text-emerald-700"><?= e(__('import.imported', ['count' => $imported])) ?></div>
    </div>

    <?php if ($errors): ?>
    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
        <div class="px-5 py-3 border-b border-red-100 bg-red-50 font-semibold text-red-700"><?= e(__('import.errors_title', ['count' => count($errors)])) ?></div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-xs text-slate-400 uppercase bg-slate-50">
                    <tr><th class="px-5 py-2"><?= e(__('import.line')) ?></th><th class="px-5 py-2"><?= e(__('import.select_column')) /* kod */ ?></th><th class="px-5 py-2"><?= e(__('import.message')) ?></th></tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php foreach ($errors as $er): ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-5 py-2 text-slate-500"><?= (int) $er['line'] ?></td>
                        <td class="px-5 py-2 text-slate-700"><?= e($er['code']) ?> · <?= e($er['name']) ?></td>
                        <td class="px-5 py-2 text-red-600 text-xs"><?= e($er['message']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
    <div class="bg-white border border-slate-200 rounded-2xl p-8 text-center text-emerald-600 font-semibold">✓ <?= e(__('import.imported', ['count' => $imported])) ?></div>
    <?php endif; ?>
</div>
