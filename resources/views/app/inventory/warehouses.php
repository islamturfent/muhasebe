<?php
/** @var array $warehouses */
?>
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-900"><?= e(__('inventory.warehouses')) ?></h1>
        <p class="text-slate-500"><?= count($warehouses) ?> depo</p>
    </div>
    <a href="<?= e(url('/app/inventory/warehouses/create')) ?>" class="px-4 py-2 rounded-lg bg-brand-600 text-white text-sm font-semibold hover:bg-brand-700">+ <?= e(__('inventory.new_warehouse')) ?></a>
</div>

<div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
    <?php if (!$warehouses): ?><div class="col-span-full bg-white border border-slate-200 rounded-2xl p-8 text-center text-slate-400"><?= e(__('inventory.no_warehouses')) ?></div><?php endif; ?>
    <?php foreach ($warehouses as $w): ?>
    <div class="bg-white border border-slate-200 rounded-2xl p-5">
        <div class="flex items-center gap-3">
            <span class="w-10 h-10 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center font-bold"><?= e(mb_strtoupper(mb_substr($w['name'], 0, 1))) ?></span>
            <div>
                <h3 class="font-semibold text-slate-900"><?= e($w['name']) ?></h3>
                <p class="text-xs text-slate-400"><?= e($w['code']) ?> · <?= e($w['company_name']) ?></p>
            </div>
            <?php if ($w['is_default']): ?><span class="ml-auto px-2 py-0.5 rounded-full text-[11px] bg-brand-50 text-brand-600"><?= e(__('inventory.default_warehouse')) ?></span><?php endif; ?>
        </div>
        <?php if ($w['address']): ?><p class="mt-3 text-sm text-slate-500"><?= e($w['address']) ?></p><?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
