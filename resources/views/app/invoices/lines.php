<?php
/**
 * @var array $products
 *
 * Each field uses a `__LINE__` placeholder that is replaced with a concrete
 * line index (0,1,2,…) when rendered. This is REQUIRED: HTML `lines[][field]`
 * is mis-parsed by PHP (each `[]` becomes its own array element), so we always
 * emit explicit indices like `lines[0][qty]`.
 */
$idx = '__LINE__';
?>
<div class="line grid grid-cols-12 gap-2 items-end mb-2">
    <div class="col-span-3">
        <label class="block text-xs font-medium text-slate-500 mb-1"><?= e(__('invoice.line_product')) ?></label>
        <select name="lines[<?= $idx ?>][product_id]" onchange="onProductChange(this)" class="w-full sm:text-sm border border-slate-200 rounded-lg px-2 py-2 focus:ring-2 focus:ring-brand-500 outline-none">
            <option value="">—</option>
            <?php foreach ($products as $p): ?><option value="<?= e($p['id']) ?>"><?= e($p['code']) ?> · <?= e($p['name']) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="col-span-1">
        <label class="block text-xs font-medium text-slate-500 mb-1"><?= e(__('invoice.line_qty')) ?></label>
        <input type="number" name="lines[<?= $idx ?>][qty]" value="1" step="0.01" class="w-full sm:text-sm border border-slate-200 rounded-lg px-2 py-2 focus:ring-2 focus:ring-brand-500 outline-none">
    </div>
    <div class="col-span-2">
        <label class="block text-xs font-medium text-slate-500 mb-1"><?= e(__('invoice.line_price')) ?></label>
        <input type="number" name="lines[<?= $idx ?>][unit_price]" step="0.01" class="w-full sm:text-sm border border-slate-200 rounded-lg px-2 py-2 focus:ring-2 focus:ring-brand-500 outline-none">
    </div>
    <div class="col-span-1">
        <label class="block text-xs font-medium text-slate-500 mb-1">% <?= e(__('invoice.line_discount')) ?></label>
        <input type="number" name="lines[<?= $idx ?>][discount]" value="0" class="w-full sm:text-sm border border-slate-200 rounded-lg px-2 py-2 focus:ring-2 focus:ring-brand-500 outline-none">
    </div>
    <div class="col-span-1">
        <label class="block text-xs font-medium text-slate-500 mb-1">% <?= e(__('invoice.line_vat')) ?></label>
        <input type="number" name="lines[<?= $idx ?>][vat_rate]" value="20" class="w-full sm:text-sm border border-slate-200 rounded-lg px-2 py-2 focus:ring-2 focus:ring-brand-500 outline-none">
    </div>
    <div class="col-span-1">
        <label class="block text-xs font-medium text-slate-500 mb-1" title="<?= e(__('invoice.line_withholding_hint')) ?>">% <?= e(__('invoice.line_withholding')) ?></label>
        <input type="number" name="lines[<?= $idx ?>][withholding_rate]" value="0" min="0" max="100" class="w-full sm:text-sm border border-slate-200 rounded-lg px-2 py-2 focus:ring-2 focus:ring-brand-500 outline-none">
    </div>
    <div class="col-span-2">
        <label class="block text-xs font-medium text-slate-500 mb-1"><?= e(__('invoice.line_description')) ?></label>
        <input name="lines[<?= $idx ?>][description]" class="w-full sm:text-sm border border-slate-200 rounded-lg px-2 py-2 focus:ring-2 focus:ring-brand-500 outline-none">
    </div>
    <div class="col-span-1 text-right">
        <button type="button" onclick="this.closest('.line').remove(); recalc();" class="px-2 py-2 rounded-lg text-red-500 hover:bg-red-50">✕</button>
    </div>
</div>
