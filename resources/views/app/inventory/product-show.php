<?php
/** @var array $product @var array $company @var array $movements */
use Muh\Core\Auth;
use Muh\Core\Translator;
$locale = Translator::instance()->locale();
?>
<div class="mb-6">
    <a href="<?= e(url('/app/inventory')) ?>" class="text-sm text-brand-600 hover:underline">← <?= e(__('inventory.products')) ?></a>
    <div class="mt-2 flex items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <span class="w-12 h-12 rounded-xl bg-brand-100 text-brand-700 flex items-center justify-center text-lg font-bold"><?= e(mb_strtoupper(mb_substr($product['name'], 0, 1))) ?></span>
            <div>
                <h1 class="text-2xl font-bold text-slate-900"><?= e($product['name']) ?></h1>
                <p class="text-sm text-slate-500"><?= e($product['code']) ?> · <?= e($company['name'] ?? '') ?></p>
            </div>
        </div>
        <?php if (Auth::can('inventory.update')): ?><a href="<?= e(url('/app/inventory/' . $product['id'] . '/edit')) ?>" class="px-4 py-2 rounded-lg border border-slate-200 text-sm font-medium text-slate-600 hover:bg-slate-50"><?= e(__('common.edit')) ?></a><?php endif; ?>
    </div>
</div>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="rounded-2xl bg-white border border-slate-200 p-5"><div class="text-xs text-slate-400 uppercase"><?= e(__('inventory.stock')) ?></div><div class="text-xl font-bold <?= $product['stock_quantity'] <= $product['critical_stock'] && $product['type']!=='service' ? 'text-red-600' : 'text-slate-800' ?>"><?= e((float)$product['stock_quantity']) ?> <?= e($product['unit_abbr'] ?? '') ?></div></div>
    <div class="rounded-2xl bg-white border border-slate-200 p-5"><div class="text-xs text-slate-400 uppercase"><?= e(__('inventory.purchase_price')) ?></div><div class="text-xl font-bold text-slate-800"><?= e(money($product['purchase_price'])) ?></div></div>
    <div class="rounded-2xl bg-white border border-slate-200 p-5"><div class="text-xs text-slate-400 uppercase"><?= e(__('inventory.sale_price')) ?></div><div class="text-xl font-bold text-slate-800"><?= e(money($product['sale_price'])) ?></div></div>
    <div class="rounded-2xl bg-white border border-slate-200 p-5"><div class="text-xs text-slate-400 uppercase"><?= e(__('common.tax')) ?></div><div class="text-xl font-bold text-slate-800">%<?= e((float)$product['vat_rate']) ?></div></div>
</div>

<div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
    <div class="px-5 py-3 border-b border-slate-100 font-semibold text-slate-800 text-sm"><?= e(__('inventory.movements')) ?></div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-left text-xs text-slate-400 uppercase bg-slate-50">
                <tr>
                    <th class="px-5 py-2"><?= e(__('inventory.date')) ?></th>
                    <th class="px-5 py-2"><?= e(__('inventory.type_movement')) ?></th>
                    <th class="px-5 py-2"><?= e(__('inventory.warehouse')) ?></th>
                    <th class="px-5 py-2 text-right"><?= e(__('inventory.qty')) ?></th>
                    <th class="px-5 py-2 text-right"><?= e(__('inventory.total')) ?></th>
                    <th class="px-5 py-2"><?= e(__('common.description')) ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php if (!$movements): ?><tr><td colspan="6" class="px-5 py-6 text-center text-slate-400"><?= e(__('inventory.no_movements')) ?></td></tr><?php endif; ?>
                <?php foreach ($movements as $m): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-5 py-2.5 text-slate-500"><?= e(format_date($m['date'])) ?></td>
                    <td class="px-5 py-2.5"><span class="text-xs px-2 py-0.5 rounded-full <?= $m['quantity']>=0 ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-600' ?>"><?= e($m['type']) ?></span></td>
                    <td class="px-5 py-2.5 text-slate-500"><?= e($m['warehouse_name'] ?? '—') ?></td>
                    <td class="px-5 py-2.5 text-right font-medium <?= $m['quantity']>=0 ? 'text-emerald-600' : 'text-red-600' ?>"><?= e((float)$m['quantity']) ?></td>
                    <td class="px-5 py-2.5 text-right text-slate-700"><?= e(money($m['total'])) ?></td>
                    <td class="px-5 py-2.5 text-slate-600"><?= e($m['description'] ?: '—') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
