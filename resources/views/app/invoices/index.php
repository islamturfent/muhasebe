<?php
/** @var array $invoices @var array $companies @var int $companyId @var string|null $type */
use Muh\Core\Translator;
$locale = Translator::instance()->locale();
?>
<div class="mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-slate-900"><?= e(__('invoice.title')) ?></h1>
        <p class="text-slate-500"><?= count($invoices) ?> <?= e(__('common.records')) ?></p>
    </div>
    <a href="<?= e(url('/app/invoices/create')) ?>" class="px-4 py-2 rounded-lg bg-brand-600 text-white text-sm font-semibold hover:bg-brand-700">+ <?= e(__('invoice.new')) ?></a>
</div>

<form method="get" action="<?= e(url('/app/invoices')) ?>" class="mb-6 bg-white border border-slate-200 rounded-2xl p-4 flex gap-3">
    <select name="company_id" class="text-sm border border-slate-200 rounded-lg px-3 py-2 bg-white focus:ring-2 focus:ring-brand-500 outline-none">
        <option value=""><?= e(__('invoice.company')) ?> (<?= e(__('common.all')) ?>)</option>
        <?php foreach ($companies as $c): ?><option value="<?= e($c['id']) ?>" <?= (int)$companyId===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
    </select>
    <select name="type" class="text-sm border border-slate-200 rounded-lg px-3 py-2 bg-white focus:ring-2 focus:ring-brand-500 outline-none">
        <option value=""><?= e(__('invoice.title')) ?></option>
        <option value="sales" <?= $type==='sales'?'selected':'' ?>><?= e(__('invoice.type_sales')) ?></option>
        <option value="purchase" <?= $type==='purchase'?'selected':'' ?>><?= e(__('invoice.type_purchase')) ?></option>
    </select>
    <a href="<?= e(url('/app/invoices')) ?>" class="self-center text-sm text-slate-500 hover:text-brand-600"><?= e(__('common.cancel')) ?></a>
</form>

<div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-left text-xs text-slate-400 uppercase bg-slate-50">
                <tr>
                    <th class="px-5 py-3"><?= e(__('invoice.invoice_no')) ?></th>
                    <th class="px-5 py-3"><?= e(__('invoice.company')) ?></th>
                    <th class="px-5 py-3"><?= e(__('invoice.customer')) ?></th>
                    <th class="px-5 py-3"><?= e(__('invoice.type')) ?></th>
                    <th class="px-5 py-3"><?= e(__('invoice.date')) ?></th>
                    <th class="px-5 py-3"><?= e(__('invoice.status')) ?></th>
                    <th class="px-5 py-3 text-right"><?= e(__('invoice.total')) ?></th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php if (!$invoices): ?><tr><td colspan="8" class="px-5 py-8 text-center text-slate-400"><?= e(__('invoice.no_invoices')) ?></td></tr><?php endif; ?>
                <?php foreach ($invoices as $inv): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-5 py-3 font-mono text-brand-600"><?= e($inv['number']) ?></td>
                    <td class="px-5 py-3 text-slate-700"><?= e($inv['company_name']) ?></td>
                    <td class="px-5 py-3 text-slate-500"><?= e($inv['account_name'] ?? '—') ?></td>
                    <td class="px-5 py-3"><span class="px-2 py-0.5 rounded-full text-xs bg-slate-100 text-slate-600"><?= e(__('invoice.type_' . $inv['type'])) ?></span></td>
                    <td class="px-5 py-3 text-slate-500"><?= e(format_date($inv['date'])) ?></td>
                    <td class="px-5 py-3"><span class="px-2 py-0.5 rounded-full text-xs bg-emerald-50 text-emerald-700"><?= e(__('invoice.posted')) ?></span></td>
                    <td class="px-5 py-3 text-right font-semibold text-slate-800"><?= e(money($inv['total'])) ?></td>
                    <td class="px-5 py-3 text-right"><a href="<?= e(url('/app/invoices/' . $inv['id'])) ?>" class="text-xs text-brand-600 font-medium"><?= e(__('common.details')) ?></a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
