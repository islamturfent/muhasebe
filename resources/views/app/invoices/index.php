<?php
/** @var array $invoices @var array $companies @var int $companyId @var string|null $type */
use Muh\Core\Translator;
$locale = Translator::instance()->locale();
?>
<div class="mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-slate-900"><?= e(__('invoice.title')) ?></h1>
        <p class="text-slate-500"><?= (int) ($total ?? count($invoices)) ?> <?= e(__('common.records')) ?></p>
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
        <option value="sales_return" <?= $type==='sales_return'?'selected':'' ?>><?= e(__('invoice.type_sales_return')) ?></option>
        <option value="purchase_return" <?= $type==='purchase_return'?'selected':'' ?>><?= e(__('invoice.type_purchase_return')) ?></option>
        <option value="proforma" <?= $type==='proforma'?'selected':'' ?>><?= e(__('invoice.proforma')) ?></option>
    </select>
    <select name="efatura" class="text-sm border border-slate-200 rounded-lg px-3 py-2 bg-white focus:ring-2 focus:ring-brand-500 outline-none">
        <option value=""><?= e(__('efatura.title')) ?></option>
        <?php foreach (['draft','sending','sent','accepted','rejected','error'] as $st): ?><option value="<?= $st ?>" <?= ($efatura ?? '')===$st?'selected':'' ?>><?= e(__('efatura.st_' . $st)) ?></option><?php endforeach; ?>
    </select>
    <label class="text-xs text-slate-500 self-center"><?= e(__('common.from')) ?> <input type="date" name="from" value="<?= e($from ?? '') ?>" class="ml-1 text-sm border border-slate-200 rounded-lg px-2 py-2"></label>
    <label class="text-xs text-slate-500 self-center"><?= e(__('common.to')) ?> <input type="date" name="to" value="<?= e($to ?? '') ?>" class="ml-1 text-sm border border-slate-200 rounded-lg px-2 py-2"></label>
    <button class="px-4 py-2 rounded-lg bg-brand-600 text-white text-sm font-semibold self-center"><?= e(__('common.filter')) ?></button>
    <a href="<?= e(url('/app/invoices')) ?>" class="self-center text-sm text-slate-500 hover:text-brand-600"><?= e(__('common.reset')) ?></a>
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
                    <th class="px-5 py-3"><?= e(__('efatura.status')) ?></th>
                    <th class="px-5 py-3 text-right"><?= e(__('invoice.total')) ?></th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php if (!$invoices): ?><tr><td colspan="9" class="px-5 py-8 text-center text-slate-400"><?= e(__('invoice.no_invoices')) ?></td></tr><?php endif; ?>
                <?php foreach ($invoices as $inv): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-5 py-3 font-mono text-brand-600"><?= e($inv['number']) ?></td>
                    <td class="px-5 py-3 text-slate-700"><?= e($inv['company_name']) ?></td>
                    <td class="px-5 py-3 text-slate-500"><?= e($inv['account_name'] ?? '—') ?></td>
                    <td class="px-5 py-3"><span class="px-2 py-0.5 rounded-full text-xs bg-slate-100 text-slate-600"><?= e(__('invoice.type_' . $inv['type'])) ?></span></td>
                    <td class="px-5 py-3 text-slate-500"><?= e(format_date($inv['date'])) ?></td>
                    <td class="px-5 py-3"><span class="px-2 py-0.5 rounded-full text-xs <?= $inv['status']==='draft' ? 'bg-slate-100 text-slate-600' : 'bg-emerald-50 text-emerald-700' ?>"><?= e($inv['status']==='draft' ? __('invoice.draft') : __('invoice.posted')) ?></span></td>
                    <td class="px-5 py-3"><span class="px-2 py-0.5 rounded-full text-xs <?= $inv['efatura_status']==='accepted' ? 'bg-emerald-50 text-emerald-700' : (in_array($inv['efatura_status'],['error','rejected'],true) ? 'bg-red-50 text-red-600' : 'bg-slate-100 text-slate-500') ?>"><?= e(__('efatura.st_' . $inv['efatura_status'])) ?></span></td>
                    <td class="px-5 py-3 text-right font-semibold text-slate-800"><?= e(money($inv['total'])) ?></td>
                    <td class="px-5 py-3 text-right"><a href="<?= e(url('/app/invoices/' . $inv['id'])) ?>" class="text-xs text-brand-600 font-medium"><?= e(__('common.details')) ?></a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= $this->partial('partials.pagination', ['page' => $page ?? 1, 'lastPage' => $lastPage ?? 1, 'total' => $total ?? null]) ?>
</div>
