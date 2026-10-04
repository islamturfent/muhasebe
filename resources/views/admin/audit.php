<?php
/** @var array $logs @var array $tenants @var array $modules @var int $tenantId @var string|null $module @var int $page @var int $lastPage @var int $total */
use Muh\Core\Translator;
$locale = Translator::instance()->locale();
?>
<div class="max-w-6xl mx-auto space-y-4">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-slate-900"><?= e(__('admin.audit')) ?></h1>
        <span class="text-sm text-slate-500"><?= (int) $total ?> <?= e(__('common.records')) ?></span>
    </div>

    <form method="get" action="<?= e(url('/admin/audit')) ?>" class="bg-white border border-slate-200 rounded-2xl p-3 flex gap-2 flex-wrap">
        <select name="tenant_id" class="text-sm border border-slate-200 rounded-lg px-3 py-2 bg-white focus:ring-2 focus:ring-brand-500 outline-none">
            <option value=""><?= e(__('admin.all_tenants')) ?></option>
            <?php foreach ($tenants as $t): ?><option value="<?= (int) $t['id'] ?>" <?= $tenantId===(int)$t['id']?'selected':'' ?>><?= e($t['name']) ?></option><?php endforeach; ?>
        </select>
        <select name="module" class="text-sm border border-slate-200 rounded-lg px-3 py-2 bg-white focus:ring-2 focus:ring-brand-500 outline-none">
            <option value=""><?= e(__('audit.filter_module')) ?></option>
            <?php foreach ($modules as $m): ?><option value="<?= e($m) ?>" <?= $module===$m?'selected':'' ?>><?= e($m) ?></option><?php endforeach; ?>
        </select>
        <input name="action" value="<?= e($action ?? '') ?>" placeholder="<?= e(__('audit.filter_action')) ?>" class="flex-1 min-w-[9rem] text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-brand-500 outline-none">
        <input type="date" name="from" value="<?= e($from ?? '') ?>" class="text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-brand-500 outline-none">
        <input type="date" name="to" value="<?= e($to ?? '') ?>" class="text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-brand-500 outline-none">
        <button class="px-4 py-2 rounded-lg bg-brand-600 text-white text-sm font-semibold"><?= e(__('common.search')) ?></button>
        <a href="<?= e(url('/admin/audit/export?tenant_id=' . (int) $tenantId . '&module=' . urlencode($module ?? '') . '&action=' . urlencode($action ?? '') . '&from=' . urlencode($from ?? '') . '&to=' . urlencode($to ?? '') . '&format=csv')) ?>" class="px-3 py-2 self-center rounded-lg border border-slate-200 text-sm text-slate-600 hover:border-brand-300">CSV</a>
        <a href="<?= e(url('/admin/audit/export?tenant_id=' . (int) $tenantId . '&module=' . urlencode($module ?? '') . '&action=' . urlencode($action ?? '') . '&from=' . urlencode($from ?? '') . '&to=' . urlencode($to ?? '') . '&format=excel')) ?>" class="px-3 py-2 self-center rounded-lg border border-slate-200 text-sm text-slate-600 hover:border-brand-300">Excel</a>
        <a href="<?= e(url('/admin/audit/export?tenant_id=' . (int) $tenantId . '&module=' . urlencode($module ?? '') . '&action=' . urlencode($action ?? '') . '&from=' . urlencode($from ?? '') . '&to=' . urlencode($to ?? '') . '&format=pdf')) ?>" class="px-3 py-2 self-center rounded-lg border border-slate-200 text-sm text-slate-600 hover:border-brand-300">PDF</a>
    </form>

    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-xs text-slate-400 uppercase bg-slate-50">
                    <tr>
                        <th class="px-5 py-2"><?= e(__('audit.when')) ?></th>
                        <th class="px-5 py-2"><?= e(__('admin.tenant')) ?></th>
                        <th class="px-5 py-2"><?= e(__('audit.user')) ?></th>
                        <th class="px-5 py-2"><?= e(__('audit.action')) ?></th>
                        <th class="px-5 py-2"><?= e(__('audit.module')) ?></th>
                        <th class="px-5 py-2"><?= e(__('audit.ip')) ?></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($logs as $l): ?>
                    <tr>
                        <td class="px-5 py-2.5 text-slate-500 whitespace-nowrap"><?= e(format_datetime($l['created_at'])) ?></td>
                        <td class="px-5 py-2.5"><a href="<?= e(url('/admin/tenants/' . $l['tenant_id'])) ?>" class="text-brand-600 hover:underline"><?= e($l['tenant_name'] ?? '#'. $l['tenant_id']) ?></a></td>
                        <td class="px-5 py-2.5 text-slate-700"><?= e($l['user_name'] ?? '—') ?></td>
                        <td class="px-5 py-2.5 text-slate-700"><?= e($l['action']) ?></td>
                        <td class="px-5 py-2.5"><span class="px-2 py-0.5 rounded-full text-xs bg-slate-100 text-slate-600"><?= e($l['module'] ?? '') ?></span></td>
                        <td class="px-5 py-2.5 text-slate-400"><?= e($l['ip'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$logs): ?><tr><td colspan="6" class="px-5 py-8 text-center text-slate-400"><?= e(__('common.no_data')) ?></td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($lastPage > 1): ?>
        <div class="px-5 py-3 border-t border-slate-100 flex items-center justify-between text-sm">
            <a href="<?= e(url('/admin/audit?tenant_id=' . $tenantId . '&module=' . urlencode($module ?? '') . '&action=' . urlencode($action ?? '') . '&from=' . urlencode($from ?? '') . '&to=' . urlencode($to ?? '') . '&page=' . max(1, $page-1))) ?>" class="text-brand-600 hover:underline">← <?= e(__('admin.prev')) ?></a>
            <span class="text-slate-500"><?= (int) $page ?> / <?= (int) $lastPage ?></span>
            <a href="<?= e(url('/admin/audit?tenant_id=' . $tenantId . '&module=' . urlencode($module ?? '') . '&action=' . urlencode($action ?? '') . '&from=' . urlencode($from ?? '') . '&to=' . urlencode($to ?? '') . '&page=' . min($lastPage, $page+1))) ?>" class="text-brand-600 hover:underline"><?= e(__('admin.next')) ?> →</a>
        </div>
        <?php endif; ?>
    </div>
</div>
