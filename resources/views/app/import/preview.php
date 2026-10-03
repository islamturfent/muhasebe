<?php
/** @var string $type @var int $companyId @var array $headers @var array $rows @var int $rowTotal @var array $map @var array $columns */
?>
<div class="max-w-3xl">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <a href="<?= e(url('/app/import')) ?>" class="text-sm text-brand-600 hover:underline">← <?= e(__('import.back')) ?></a>
            <h1 class="text-2xl font-bold text-slate-900 mt-1"><?= e(__('import.mapping')) ?> — <?= e(__('import.type_' . $type)) ?></h1>
            <p class="text-slate-500"><?= (int) $rowTotal ?> <?= e(__('common.records')) ?></p>
        </div>
    </div>

    <form method="post" action="<?= e(url('/app/import/run')) ?>" class="bg-white border border-slate-200 rounded-2xl p-6 space-y-5">
        <?= csrf_field() ?>

        <div>
            <h2 class="font-semibold text-slate-800 mb-3"><?= e(__('import.mapping')) ?></h2>
            <div class="grid md:grid-cols-2 gap-3">
                <?php foreach ($columns as $col): ?>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1"><?= e($col) ?></label>
                    <select name="map[<?= e($col) ?>]" class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                        <option value=""><?= e(__('import.select_column')) ?></option>
                        <?php foreach ($headers as $h): ?>
                        <option value="<?= e($h) ?>" <?= ($map[$col] ?? '') === $h ? 'selected' : '' ?>><?= e($h) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div>
            <h2 class="font-semibold text-slate-800 mb-2"><?= e(__('import.preview')) ?></h2>
            <div class="overflow-x-auto border border-slate-200 rounded-xl">
                <table class="w-full text-xs">
                    <thead class="bg-slate-50 text-left text-slate-400 uppercase">
                        <tr><?php foreach ($headers as $h): ?><th class="px-3 py-2"><?= e($h) ?></th><?php endforeach; ?></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach ($rows as $row): ?>
                        <tr><?php foreach ($headers as $h): ?><td class="px-3 py-1.5 text-slate-600"><?= e($row[$h] ?? '') ?></td><?php endforeach; ?></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <button class="w-full px-6 py-3 rounded-xl bg-brand-600 text-white font-semibold hover:bg-brand-700">⚙ <?= e(__('import.run_import')) ?></button>
    </form>
</div>
