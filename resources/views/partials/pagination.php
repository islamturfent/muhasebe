<?php
/**
 * Reusable pagination bar. Renders Previous / "page of total" / Next links
 * that preserve current GET filters via route_query().
 *
 * Expected variables: $page (int), $lastPage (int), $total (int, optional).
 * $caption (optional) is a "N kayıt" text shown on the left.
 */
$page = (int) ($page ?? 1);
$lastPage = (int) ($lastPage ?? 1);
$total = isset($total) ? (int) $total : null;
$caption = $caption ?? (($total !== null) ? ((string) $total . ' ' . __('common.records')) : '');
?>
<?php if ($lastPage > 1 || $caption !== ''): ?>
<div class="flex flex-col sm:flex-row items-center justify-between gap-2 px-5 py-3 border-t border-slate-100 text-sm">
    <?php if ($caption !== ''): ?><span class="text-slate-500"><?= e($caption) ?></span><?php endif; ?>
    <div class="flex items-center gap-3">
        <?php if ($page > 1): ?>
            <a href="<?= e(url(route_query(['page' => $page - 1]))) ?>" class="px-3 py-1 rounded-lg border border-slate-200 text-slate-600 hover:border-brand-300">← <?= e(__('common.previous')) ?></a>
        <?php endif; ?>
        <span class="text-slate-500"><?= (int) $page ?> / <?= (int) $lastPage ?></span>
        <?php if ($page < $lastPage): ?>
            <a href="<?= e(url(route_query(['page' => $page + 1]))) ?>" class="px-3 py-1 rounded-lg border border-slate-200 text-slate-600 hover:border-brand-300"><?= e(__('common.next')) ?> →</a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
