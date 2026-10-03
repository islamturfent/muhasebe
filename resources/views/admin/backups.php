<?php
/** @var array $files @var string $backupDir @var array $health */
use Muh\Core\Auth;
?>
<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900"><?= e(__('admin.backups')) ?></h1>
            <p class="text-slate-500 text-sm mt-1"><?= e(__('admin.backups_hint')) ?></p>
        </div>
        <form method="post" action="<?= e(url('/admin/backups/run')) ?>">
            <?= csrf_field() ?>
            <button class="px-4 py-2 rounded-lg bg-brand-600 text-white text-sm font-semibold hover:bg-brand-700">⬇ <?= e(__('admin.run_backup')) ?></button>
        </form>
    </div>

    <?php if (!empty($health)): ?>
    <div class="bg-white border border-slate-200 rounded-2xl p-5">
        <h3 class="font-semibold text-slate-800 mb-4"><?= e(__('admin.system_health')) ?></h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div><div class="text-xs text-slate-400"><?= e(__('admin.db_driver')) ?></div><div class="font-semibold text-slate-800 mt-1"><?= e(ucfirst($health['driver'])) ?> <span class="text-slate-400 text-xs"><?= e($health['version']) ?></span></div></div>
            <div><div class="text-xs text-slate-400"><?= e(__('admin.db_size')) ?></div><div class="font-semibold text-slate-800 mt-1"><?= e(number_format($health['db_size'], 2, ',', '.')) ?> MB</div></div>
            <div><div class="text-xs text-slate-400"><?= e(__('admin.table_count')) ?></div><div class="font-semibold text-slate-800 mt-1"><?= e((int) $health['tables']) ?></div></div>
            <div><div class="text-xs text-slate-400"><?= e(__('admin.backup_dir')) ?></div><div class="font-semibold text-slate-800 mt-1 text-xs break-all"><?= e($backupDir) ?></div></div>
        </div>
    </div>
    <?php endif; ?>

    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 font-semibold text-slate-800 text-sm"><?= e(__('admin.backups')) ?></div>
        <table class="w-full text-sm">
            <thead class="text-left text-xs text-slate-400 uppercase bg-slate-50">
                <tr>
                    <th class="px-5 py-3"><?= e(__('admin.file')) ?></th>
                    <th class="px-5 py-3"><?= e(__('admin.created_at')) ?></th>
                    <th class="px-5 py-3 text-right"><?= e(__('admin.size')) ?></th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (!$files): ?>
                <tr><td colspan="4" class="px-5 py-8 text-center text-slate-400"><?= e(__('admin.no_backups')) ?></td></tr>
                <?php endif; ?>
                <?php foreach ($files as $f): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-5 py-3 font-mono text-brand-600 text-xs"><?= e($f['name']) ?></td>
                    <td class="px-5 py-3 text-slate-500"><?= e($f['date']) ?></td>
                    <td class="px-5 py-3 text-right text-slate-600"><?= e(number_format($f['size'] / 1024, 1, ',', '.')) ?> KB</td>
                    <td class="px-5 py-3 text-right">
                        <a href="<?= e(url('/admin/backups/' . rawurlencode($f['name']) . '/download')) ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 text-xs font-medium text-slate-600 hover:border-brand-300 hover:text-brand-600"><?= e(__('admin.download')) ?></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
