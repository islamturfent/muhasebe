<?php
/** @var array $products */
?>
<div class="line grid grid-cols-12 gap-2 items-end mb-2">
    <div class="col-span-4">
        <label class="block text-xs font-medium text-slate-500 mb-1"><?= e(__('invoice.line_product')) ?></label>
        <select name="lines[][product_id]" onchange="onProductChange(this)" class="w-full sm:text-sm border border-slate-200 rounded-lg px-2 py-2 focus:ring-2 focus:ring-brand-500 outline-none">
            <option value="">—</option>
            <?php foreach ($products as $p): ?><option value="<?= e($p['id']) ?>"><?= e($p['code']) ?> · <?= e($p['name']) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="col-span-1">
        <label class="block text-xs font-medium text-slate-500 mb-1"><?= e(__('invoice.line_qty')) ?></label>
        <input type="number" name="lines[][qty]" value="1" step="0.01" class="w-full sm:text-sm border border-slate-200 rounded-lg px-2 py-2 focus:ring-2 focus:ring-brand-500 outline-none">
    </div>
    <div class="col-span-2">
        <label class="block text-xs font-medium text-slate-500 mb-1"><?= e(__('invoice.line_price')) ?></label>
        <input type="number" name="lines[][unit_price]" step="0.01" class="w-full sm:text-sm border border-slate-200 rounded-lg px-2 py-2 focus:ring-2 focus:ring-brand-500 outline-none">
    </div>
    <div class="col-span-1">
        <label class="block text-xs font-medium text-slate-500 mb-1">% <?= e(__('invoice.line_discount')) ?></label>
        <input type="number" name="lines[][discount]" value="0" class="w-full sm:text-sm border border-slate-200 rounded-lg px-2 py-2 focus:ring-2 focus:ring-brand-500 outline-none">
    </div>
    <div class="col-span-1">
        <label class="block text-xs font-medium text-slate-500 mb-1">% <?= e(__('invoice.line_vat')) ?></label>
        <input type="number" name="lines[][vat_rate]" value="20" class="w-full sm:text-sm border border-slate-200 rounded-lg px-2 py-2 focus:ring-2 focus:ring-brand-500 outline-none">
    </div>
    <div class="col-span-2">
        <label class="block text-xs font-medium text-slate-500 mb-1"><?= e(__('invoice.line_description')) ?></label>
        <input name="lines[][description]" class="w-full sm:text-sm border border-slate-200 rounded-lg px-2 py-2 focus:ring-2 focus:ring-brand-500 outline-none">
    </div>
    <div class="col-span-1 text-right">
        <button type="button" onclick="this.closest('.line').remove(); recalc();" class="px-2 py-2 rounded-lg text-red-500 hover:bg-red-50">✕</button>
    </div>
</div>
