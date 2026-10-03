<?php
/** @var array $logs @var array $modules @var string|null $module @var string|null $action @var int $page @var int $lastPage @var int $total */
?>
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900"><?= e(__('audit.title')) ?></h1>
    <p class="text-slate-500"><?= (int) $total ?> <?= e(__('common.records')) ?></p>
</div>

<form method="get" action="<?= e(url('/app/audit')) ?>" class="mb-4 bg-white border border-slate-200 rounded-2xl p-3 flex gap-2">
    <select name="module" class="text-sm border border-slate-200 rounded-lg px-3 py-2 bg-white focus:ring-2 focus:ring-brand-500 outline-none">
        <option value=""><?= e(__('audit.filter_module')) ?></option>
        <?php foreach ($modules as $m): ?><option value="<?= e($m) ?>" <?= $module===$m?'selected':'' ?>><?= e($m) ?></option><?php endforeach; ?>
    </select>
    <input name="action" value="<?= e($action ?? '') ?>" placeholder="<?= e(__('audit.filter_action')) ?>" class="flex-1 text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-brand-500 outline-none">
    <button class="px-4 py-2 rounded-lg bg-brand-600 text-white text-sm font-semibold"><?= e(__('common.search')) ?></button>
</form>

<div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-left text-xs text-slate-400 uppercase bg-slate-50">
                <tr>
                    <th class="px-5 py-2"><?= e(__('audit.when')) ?></th>
                    <th class="px-5 py-2"><?= e(__('audit.user')) ?></th>
                    <th class="px-5 py-2"><?= e(__('audit.company')) ?></th>
                    <th class="px-5 py-2"><?= e(__('audit.action')) ?></th>
                    <th class="px-5 py-2"><?= e(__('audit.module')) ?></th>
                    <th class="px-5 py-2"><?= e(__('audit.ip')) ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php if (!$logs): ?><tr><td colspan="6" class="px-5 py-8 text-center text-slate-400"><?= e(__('audit.empty')) ?></td></tr><?php endif; ?>
                <?php foreach ($logs as $l): ?>
                <tr class="hover:bg-slate-50 align-top">
                    <td class="px-5 py-2.5 text-slate-500 whitespace-nowrap"><?= e(format_datetime($l['created_at'])) ?></td>
                    <td class="px-5 py-2.5 text-slate-700"><?= e($l['user_name'] ?? '—') ?></td>
                    <td class="px-5 py-2.5 text-slate-500"><?= e($l['company_name'] ?? '—') ?></td>
                    <td class="px-5 py-2.5 font-mono text-xs text-brand-600"><?= e($l['action']) ?></td>
                    <td class="px-5 py-2.5 text-slate-500"><?= e($l['module'] ?? '') ?></td>
                    <td class="px-5 py-2.5 text-slate-400"><?= e($l['ip'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="6" class="px-5 py-3">
                        <div class="flex items-center justify-center gap-3 text-sm">
                            <?php if ($page > 1): ?>
                                <a href="<?= e(url('/app/audit?module=' . rawurlencode((string) ($module ?? '')) . '&action=' . rawurlencode((string) ($action ?? '')) . '&page=' . ($page - 1))) ?>" class="px-3 py-1 rounded-lg border border-slate-200 text-slate-600 hover:border-brand-300">←</a>
                            <?php endif; ?>
                            <span class="text-slate-500"><?= (int) $page ?> / <?= (int) $lastPage ?></span>
                            <?php if ($page < $lastPage): ?>
                                <a href="<?= e(url('/app/audit?module=' . rawurlencode((string) ($module ?? '')) . '&action=' . rawurlencode((string) ($action ?? '')) . '&page=' . ($page + 1))) ?>" class="px-3 py-1 rounded-lg border border-slate-200 text-slate-600 hover:border-brand-300">→</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
