<?php
/** @var array $log */
?>
<div class="max-w-3xl">
    <div class="mb-6">
        <a href="<?= e(url('/app/audit')) ?>" class="text-sm text-brand-600 hover:underline">← <?= e(__('audit.title')) ?></a>
        <h1 class="text-2xl font-bold text-slate-900 mt-1">#<?= (int) $log['id'] ?> · <?= e($log['action']) ?></h1>
        <p class="text-slate-500"><?= e(format_datetime($log['created_at'])) ?></p>
    </div>

    <!-- Meta -->
    <div class="bg-white border border-slate-200 rounded-2xl p-5 mb-4">
        <dl class="grid sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
            <div><dt class="text-xs text-slate-400 uppercase"><?= e(__('audit.user')) ?></dt><dd class="font-medium text-slate-800"><?= e($log['user_name'] ?? '—') ?></dd></div>
            <div><dt class="text-xs text-slate-400 uppercase"><?= e(__('audit.company')) ?></dt><dd class="font-medium text-slate-800"><?= e($log['company_name'] ?? '—') ?></dd></div>
            <div><dt class="text-xs text-slate-400 uppercase"><?= e(__('audit.module')) ?></dt><dd class="font-medium text-slate-800"><?= e($log['module'] ?? '') ?></dd></div>
            <div><dt class="text-xs text-slate-400 uppercase"><?= e(__('audit.action')) ?></dt><dd class="font-mono text-brand-600"><?= e($log['action']) ?></dd></div>
            <div><dt class="text-xs text-slate-400 uppercase"><?= e(__('audit.ip')) ?></dt><dd class="font-mono text-slate-600"><?= e($log['ip'] ?? '—') ?></dd></div>
            <div><dt class="text-xs text-slate-400 uppercase"><?= e(__('audit.entity')) ?></dt><dd class="font-mono text-slate-600"><?= e(($log['entity_type'] ?? '') . '#' . ($log['entity_id'] ?? '')) ?></dd></div>
            <div class="sm:col-span-2"><dt class="text-xs text-slate-400 uppercase">User Agent</dt><dd class="text-slate-500 break-all"><?= e($log['user_agent'] ?? '—') ?></dd></div>
        </dl>
    </div>

    <?php $renderValue = function (array $rows, string $title) { ?>
        <?php if ($rows): ?>
        <div class="bg-white border border-slate-200 rounded-2xl mb-4 overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-100 font-semibold text-slate-800 text-sm"><?= $title ?></div>
            <div class="divide-y divide-slate-50 text-sm">
                <?php foreach ($rows as $k => $v): ?>
                <div class="px-5 py-2.5 flex gap-4">
                    <div class="w-40 shrink-0 font-mono text-xs text-slate-400 pt-0.5"><?= e($k) ?></div>
                    <div class="text-slate-700 break-all">
                        <?php if (is_array($v) || is_object($v)): ?>
                            <pre class="bg-slate-50 rounded-lg p-2 text-xs overflow-x-auto"><?= e(json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                        <?php else: ?>
                            <?= e((string) (is_scalar($v) ? $v : json_encode($v))) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="bg-white border border-slate-200 rounded-2xl mb-4 px-5 py-4 text-sm text-slate-400"><?= $title ?> —</div>
        <?php endif; ?>
    <?php }; ?>

    <?php $renderValue($log['old_decoded'] ?? [], __('audit.old_value')); ?>
    <?php $renderValue($log['new_decoded'] ?? [], __('audit.new_value')); ?>
</div>
