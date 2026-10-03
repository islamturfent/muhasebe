<?php
/** @var array $companies @var array $units @var int $selectedCompany */
use Muh\Core\Session;
$errors = Session::get('_form_errors', []);
Session::forget('_form_errors');
?>
<div class="max-w-2xl">
    <div class="mb-6">
        <a href="<?= e(url('/app/inventory')) ?>" class="text-sm text-brand-600 hover:underline">← <?= e(__('inventory.products')) ?></a>
        <h1 class="text-2xl font-bold text-slate-900 mt-1"><?= e(__('inventory.new_product')) ?></h1>
    </div>

    <form method="post" action="<?= e(url('/app/inventory')) ?>" class="bg-white border border-slate-200 rounded-2xl p-6 space-y-4">
        <?= csrf_field() ?>

        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('inventory.company')) ?> *</label>
                <select name="company_id" required class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                    <?php foreach ($companies as $c): ?><option value="<?= e($c['id']) ?>" <?= (int)$selectedCompany===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('inventory.code')) ?> *</label>
                <input name="code" required class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('inventory.name')) ?> *</label>
            <input name="name" required class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
            <input name="barcode" placeholder="<?= e(__('inventory.barcode')) ?>" class="w-full mt-3 px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
        </div>

        <div class="grid md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('inventory.type')) ?></label>
                <select name="type" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                    <option value="product"><?= e(__('inventory.type_product')) ?></option>
                    <option value="service"><?= e(__('inventory.type_service')) ?></option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('inventory.unit')) ?></label>
                <select name="unit_id" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                    <option value="">—</option>
                    <?php foreach ($units as $u): ?><option value="<?= e($u['id']) ?>"><?= e($u['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('inventory.vat_rate')) ?></label>
                <input name="vat_rate" value="20" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
            </div>
        </div>

        <div class="grid md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('inventory.purchase_price')) ?></label>
                <input name="purchase_price" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('inventory.sale_price')) ?></label>
                <input name="sale_price" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('inventory.critical_stock')) ?></label>
                <input name="critical_stock" value="0" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
            </div>
        </div>

        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('inventory.opening_stock')) ?></label>
                <input name="opening_stock" value="0" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('common.description')) ?></label>
                <input name="description" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
            </div>
        </div>

        <?php if ($errors): ?><div class="p-3 rounded-lg bg-red-50 border border-red-200 text-sm text-red-700"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>

        <div class="flex gap-3 pt-2">
            <button class="px-6 py-3 rounded-xl bg-brand-600 text-white font-semibold hover:bg-brand-700"><?= e(__('common.save')) ?></button>
            <a href="<?= e(url('/app/inventory')) ?>" class="px-6 py-3 rounded-xl border border-slate-200 text-slate-600 font-medium"><?= e(__('common.cancel')) ?></a>
        </div>
    </form>
</div>
