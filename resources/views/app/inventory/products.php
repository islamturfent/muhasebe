<?php
/** @var array $products @var array $companies @var array $warehouses @var int $companyId @var string|null $search */
use Muh\Core\Translator;
$locale = Translator::instance()->locale();
?>
<div class="mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-slate-900"><?= e(__('inventory.products')) ?></h1>
        <p class="text-slate-500"><?= count($products) ?> <?= e(__('common.records')) ?></p>
    </div>
    <div class="flex gap-2">
        <a href="<?= e(url('/app/inventory/warehouses')) ?>" class="px-4 py-2 rounded-lg border border-slate-200 text-sm font-medium text-slate-600 hover:bg-slate-50"><?= e(__('inventory.warehouses')) ?></a>
        <a href="<?= e(url('/app/inventory/create')) ?>" class="px-4 py-2 rounded-lg bg-brand-600 text-white text-sm font-semibold hover:bg-brand-700">+ <?= e(__('inventory.new_product')) ?></a>
    </div>
</div>

<form method="get" action="<?= e(url('/app/inventory')) ?>" class="mb-6 bg-white border border-slate-200 rounded-2xl p-4 flex flex-col md:flex-row gap-3">
    <select name="company_id" class="text-sm border border-slate-200 rounded-lg px-3 py-2 bg-white focus:ring-2 focus:ring-brand-500 outline-none">
        <option value=""><?= e(__('inventory.company')) ?> (<?= e(__('common.all')) ?>)</option>
        <?php foreach ($companies as $c): ?>
        <option value="<?= e($c['id']) ?>" <?= (int)$companyId===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <input type="text" name="search" value="<?= e($search ?? '') ?>" placeholder="<?= e(__('inventory.search_placeholder')) ?>" class="flex-1 text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-brand-500 outline-none">
    <button class="px-4 py-2 rounded-lg bg-brand-600 text-white text-sm font-semibold"><?= e(__('common.search')) ?></button>
</form>

<div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-left text-xs text-slate-400 uppercase bg-slate-50">
                <tr>
                    <th class="px-5 py-3"><?= e(__('inventory.code')) ?></th>
                    <th class="px-5 py-3"><?= e(__('inventory.name')) ?></th>
                    <th class="px-5 py-3"><?= e(__('inventory.company')) ?></th>
                    <th class="px-5 py-3"><?= e(__('inventory.unit')) ?></th>
                    <th class="px-5 py-3 text-right"><?= e(__('inventory.purchase_price')) ?></th>
                    <th class="px-5 py-3 text-right"><?= e(__('inventory.sale_price')) ?></th>
                    <th class="px-5 py-3 text-right"><?= e(__('inventory.stock')) ?></th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php if (!$products): ?><tr><td colspan="8" class="px-5 py-8 text-center text-slate-400"><?= e(__('inventory.no_products')) ?></td></tr><?php endif; ?>
                <?php foreach ($products as $p): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-5 py-3 font-mono text-brand-600"><?= e($p['code']) ?></td>
                    <td class="px-5 py-3 font-medium text-slate-800">
                        <a href="<?= e(url('/app/inventory/' . $p['id'])) ?>" class="hover:text-brand-600"><?= e($p['name']) ?></a>
                    </td>
                    <td class="px-5 py-3 text-slate-500"><?= e($p['company_name']) ?></td>
                    <td class="px-5 py-3 text-slate-500"><?= e($p['unit_abbr'] ?? '—') ?></td>
                    <td class="px-5 py-3 text-right text-slate-700"><?= e(money($p['purchase_price'])) ?></td>
                    <td class="px-5 py-3 text-right text-slate-700"><?= e(money($p['sale_price'])) ?></td>
                    <td class="px-5 py-3 text-right font-semibold <?= ($p['stock_quantity'] <= $p['critical_stock']) && $p['type']!=='service' ? 'text-red-600' : 'text-slate-800' ?>"><?= e((float)$p['stock_quantity']) ?></td>
                    <td class="px-5 py-3 text-right"><a href="<?= e(url('/app/inventory/' . $p['id'])) ?>" class="text-xs text-brand-600 font-medium"><?= e(__('common.details')) ?></a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
