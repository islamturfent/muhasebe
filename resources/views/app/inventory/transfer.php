<?php
/** @var array $products @var array $warehouses */
?>
<div class="max-w-2xl mx-auto p-6">
    <h1 class="text-2xl font-bold text-slate-900"><?= e(__('inventory.transfer')) ?></h1>
    <p class="text-slate-500 mb-6 text-sm"><?= e(__('inventory.transfer_hint')) ?></p>

    <?php if ($err = \Muh\Core\Session::getFlash('error')): ?><div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm"><?= e($err) ?></div><?php endif; ?>
    <?php if ($s =\Muh\Core\Session::getFlash('success')): ?><div class="mb-4 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm"><?= e($s) ?></div><?php endif; ?>

    <form method="post" action="<?= e(url('/app/inventory/transfer')) ?>" class="bg-white border border-slate-200 rounded-2xl p-6 space-y-4">
        <?= csrf_field() ?>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('inventory.products')) ?></label>
            <select name="product_id" required class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                <?php foreach ($products as $p): ?>
                <option value="<?= (int) $p['id'] ?>"><?= e($p['code']) ?> — <?= e($p['name']) ?> (<?= (float) $p['stock_quantity'] ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('inventory.transfer_from')) ?></label>
                <select name="from_warehouse" required class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                    <?php foreach ($warehouses as $w): ?><option value="<?= (int) $w['id'] ?>"><?= e($w['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('inventory.transfer_to')) ?></label>
                <select name="to_warehouse" required class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                    <?php foreach ($warehouses as $w): ?><option value="<?= (int) $w['id'] ?>"><?= e($w['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('inventory.qty')) ?></label>
                <input type="number" name="quantity" step="0.01" min="0.01" required class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('common.description')) ?></label>
                <input name="description" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
            </div>
        </div>
        <button class="w-full px-6 py-3 rounded-xl bg-brand-600 text-white font-semibold hover:bg-brand-700"><?= e(__('inventory.transfer')) ?></button>
    </form>
</div>
