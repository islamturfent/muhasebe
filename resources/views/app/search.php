<?php
/** @var string $query @var array $results */
$total = array_sum(array_map(fn ($g) => count($g['items']), $results));
?>
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900"><?= e(__('nav.search')) ?></h1>
    <p class="text-slate-500"><?= $query ? (int) $total . ' ' . e(__('common.records')) : '' ?></p>
</div>

<form method="get" action="<?= e(url('/app/search')) ?>" class="mb-6 bg-white border border-slate-200 rounded-2xl p-4">
    <div class="flex gap-2">
        <input name="q" value="<?= e($query) ?>" placeholder="<?= e(__('nav.search')) ?>" autofocus class="flex-1 px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
        <button class="px-6 py-3 rounded-lg bg-brand-600 text-white font-semibold"><?= e(__('common.search')) ?></button>
    </div>
</form>

<?php if ($query !== '' && !$results): ?>
<div class="bg-white border border-slate-200 rounded-2xl p-10 text-center text-slate-400"><?= e(__('common.no_data')) ?></div>
<?php endif; ?>

<?php foreach ($results as $group): ?>
<div class="mb-6">
    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400 mb-2"><?= e($group['label']) ?></h2>
    <div class="bg-white border border-slate-200 rounded-2xl divide-y divide-slate-50 overflow-hidden">
        <?php foreach ($group['items'] as $item): ?>
        <a href="<?= e(url($item['url'])) ?>" class="flex items-center justify-between px-5 py-3 hover:bg-slate-50">
            <div>
                <div class="font-medium text-slate-800"><?= e($item['title']) ?></div>
                <?php if (!empty($item['sub'])): ?><div class="text-xs text-slate-400"><?= e($item['sub']) ?></div><?php endif; ?>
            </div>
            <span class="text-brand-600 text-sm">→</span>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>
