<?php
/** @var array $companies @var int $selectedCompany @var array $accounts @var array $products */
use Muh\Core\Translator;
use Muh\Core\Session;
$locale = Translator::instance()->locale();
$errors = Session::get('_form_errors', []);
?>
<div class="max-w-3xl">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <a href="<?= e(url('/app/invoices')) ?>" class="text-sm text-brand-600 hover:underline">← <?= e(__('invoice.back')) ?></a>
            <h1 class="text-2xl font-bold text-slate-900 mt-1"><?= e(__('invoice.bulk_title')) ?></h1>
            <p class="text-slate-500"><?= e(__('invoice.bulk_hint')) ?></p>
        </div>
    </div>

    <?php if ($errors): ?><div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-sm text-red-700"><?php foreach ($errors as $er): ?><div><?= e(is_array($er) ? implode(' ', $er) : $er) ?></div><?php endforeach; ?></div><?php endif; ?>

    <form method="post" action="<?= e(url('/app/invoices/bulk')) ?>" class="bg-white border border-slate-200 rounded-2xl p-6 space-y-4">
        <?= csrf_field() ?>

        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('invoice.company')) ?> *</label>
                <select name="company_id" required class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                    <?php foreach ($companies as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int)$selectedCompany===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('invoice.type')) ?> *</label>
                <select name="type" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                    <option value="sales"><?= e(__('invoice.sales')) ?></option>
                    <option value="purchase"><?= e(__('invoice.purchase')) ?></option>
                </select>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('invoice.customers')) ?> *</label>
            <div class="max-h-48 overflow-y-auto border border-slate-200 rounded-lg p-3 space-y-1">
                <?php if (!$accounts): ?><p class="text-sm text-slate-400"><?= e(__('invoice.no_accounts')) ?></p><?php endif; ?>
                <?php foreach ($accounts as $a): ?>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="accounts[]" value="<?= (int) $a['id'] ?>" class="rounded"> <?= e($a['code']) ?> · <?= e($a['name']) ?></label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div><label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('invoice.date')) ?> *</label>
                <input type="date" name="date" value="<?= date('Y-m-d') ?>" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none"></div>
            <div><label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('invoice.due_date')) ?></label>
                <input type="date" name="due_date" value="<?= date('Y-m-d', strtotime('+15 days')) ?>" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none"></div>
        </div>

        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
            <div class="text-sm font-medium text-slate-700 mb-2"><?= e(__('invoice.line')) ?></div>
            <div class="grid grid-cols-6 gap-2">
                <label class="col-span-3 text-xs text-slate-500"><?= e(__('invoice.line_product')) ?>
                    <select name="lines[0][product_id]" class="w-full px-2 py-1.5 rounded-lg border border-slate-200 text-sm">
                        <option value="">—</option>
                        <?php foreach ($products as $p): ?><option value="<?= (int) $p['id'] ?>"><?= e($p['code']) ?> · <?= e($p['name']) ?></option><?php endforeach; ?>
                    </select></label>
                <label class="text-xs text-slate-500"><?= e(__('invoice.line_qty')) ?>
                    <input type="number" step="0.01" name="lines[0][qty]" value="1" class="w-full px-2 py-1.5 rounded-lg border border-slate-200 text-sm"></label>
                <label class="text-xs text-slate-500"><?= e(__('invoice.line_price')) ?>
                    <input type="number" step="0.01" name="lines[0][unit_price]" class="w-full px-2 py-1.5 rounded-lg border border-slate-200 text-sm"></label>
                <label class="text-xs text-slate-500">%
                    <input type="number" step="0.01" name="lines[0][vat_rate]" value="20" class="w-full px-2 py-1.5 rounded-lg border border-slate-200 text-sm"></label>
            </div>
            <label class="block text-xs text-slate-500 mt-2"><?= e(__('invoice.line_description')) ?>
                <input name="lines[0][description]" class="w-full px-2 py-1.5 rounded-lg border border-slate-200 text-sm"></label>
        </div>

        <button class="w-full px-4 py-3 rounded-xl bg-brand-600 text-white font-semibold hover:bg-brand-700"><?= e(__('invoice.bulk_submit')) ?></button>
    </form>
</div>
