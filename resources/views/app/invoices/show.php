<?php
/** @var array $invoice @var array $items */
use Muh\Core\Auth;
use Muh\Core\Translator;
$locale = Translator::instance()->locale();
?>
<div class="max-w-3xl mx-auto">
    <div class="flex items-center justify-between mb-4 gap-3">
        <a href="<?= e(url('/app/invoices')) ?>" class="text-sm text-brand-600 hover:underline">← <?= e(__('invoice.title')) ?></a>
        <div class="flex items-center gap-2 flex-wrap">
            <?php if (in_array($invoice['type'], ['sales', 'purchase'], true) && in_array($invoice['efatura_status'], ['draft', 'error', 'rejected'], true)): ?>
            <form method="post" action="<?= e(url('/app/invoices/' . $invoice['id'] . '/efatura')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="doc_type" value="invoice">
                <button class="px-4 py-2 rounded-lg bg-brand-600 text-white text-sm font-medium hover:bg-brand-700">⚡ <?= e(__('efatura.send')) ?></button>
            </form>
            <form method="post" action="<?= e(url('/app/invoices/' . $invoice['id'] . '/efatura')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="doc_type" value="archive">
                <button class="px-4 py-2 rounded-lg border border-brand-600 text-brand-600 text-sm font-medium hover:bg-brand-50">🗄 <?= e(__('efatura.send_archive')) ?></button>
            </form>
            <?php endif; ?>
            <span class="px-2 py-1 rounded-full text-xs font-semibold <?= $invoice['efatura_status'] === 'accepted' ? 'bg-emerald-50 text-emerald-700' : (in_array($invoice['efatura_status'], ['error','rejected'], true) ? 'bg-red-50 text-red-600' : 'bg-slate-100 text-slate-600') ?>" title="<?= !empty($invoice['efatura_envelope_id']) ? e(__('efatura.envelope_id') . ': ' . $invoice['efatura_envelope_id']) : '' ?>">
                <?= e(($invoice['efatura_doc_type'] === 'archive' ? __('efatura.type_archive') : __('efatura.title'))) ?>: <?= e(__('efatura.st_' . $invoice['efatura_status'])) ?>
                <?php if (!empty($invoice['efatura_envelope_id'])): ?><br><span class="font-normal"><?= e(__('efatura.envelope_id') . ': ' . $invoice['efatura_envelope_id']) ?></span><?php endif; ?>
            </span>
            <a href="<?= e(url('/app/invoices/' . $invoice['id'] . '/print')) ?>" target="_blank" class="px-4 py-2 rounded-lg border border-slate-200 text-sm font-medium text-slate-600 hover:bg-slate-50">🖨 <?= e(__('invoice.print')) ?></a>
            <form method="post" action="<?= e(url('/app/invoices/' . $invoice['id'] . '/email')) ?>">
                <?= csrf_field() ?>
                <button class="px-4 py-2 rounded-lg border border-slate-200 text-sm font-medium text-slate-600 hover:bg-slate-50">✉ <?= e(__('invoice.send_email')) ?></button>
            </form>
        </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl p-8 print:p-0 print:border-0">
        <!-- Header -->
        <div class="flex items-start justify-between border-b border-slate-100 pb-6">
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <span class="w-9 h-9 rounded-lg bg-gradient-to-br from-brand-600 to-brand-500 flex items-center justify-center text-white font-black"><?= e(mb_strtoupper(mb_substr($invoice['company_name'], 0, 1))) ?></span>
                    <h1 class="text-xl font-bold text-slate-900"><?= e($invoice['company_name']) ?></h1>
                </div>
                <div class="text-sm text-slate-500 space-y-0.5">
                    <div><?= e($invoice['trade_name'] ?? '') ?></div>
                    <div><?= e(__('invoice.document_no')) ?>: <?= e($invoice['tax_number'] ?? '—') ?></div>
                    <div><?= e($invoice['address'] ?? '') ?></div>
                    <div><?= e($invoice['company_email'] ?? '') ?></div>
                </div>
            </div>
            <div class="text-right">
                <div class="text-3xl font-extrabold text-slate-900"><?= e(__('invoice.type_' . $invoice['type'])) ?></div>
                <div class="font-mono text-brand-600 mt-1"><?= e($invoice['number']) ?></div>
                <div class="text-sm text-slate-500 mt-1"><?= e(__('invoice.date')) ?>: <?= e(format_date($invoice['date'])) ?></div>
                <div class="text-sm text-slate-500"><?= e(__('invoice.due_date')) ?>: <?= e(format_date($invoice['due_date'])) ?></div>
            </div>
        </div>

        <!-- Bill to -->
        <div class="py-6 border-b border-slate-100">
            <div class="text-xs uppercase text-slate-400 mb-1"><?= e(__('invoice.customer')) ?></div>
            <div class="font-semibold text-slate-800"><?= e($invoice['account_name']) ?></div>
            <div class="text-sm text-slate-500"><?= e($invoice['account_tax'] ?? '') ?></div>
            <div class="text-sm text-slate-500"><?= e($invoice['account_address'] ?? '') ?></div>
        </div>

        <!-- Items -->
        <table class="w-full text-sm mt-6">
            <thead>
                <tr class="text-left text-xs text-slate-400 uppercase border-b border-slate-100">
                    <th class="py-2"><?= e(__('invoice.line_description')) ?></th>
                    <th class="py-2 text-right"><?= e(__('invoice.line_qty')) ?></th>
                    <th class="py-2 text-right"><?= e(__('invoice.line_price')) ?></th>
                    <th class="py-2 text-right">% <?= e(__('invoice.line_vat')) ?></th>
                    <th class="py-2 text-right">% <?= e(__('invoice.line_withholding')) ?></th>
                    <th class="py-2 text-right"><?= e(__('invoice.total')) ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php foreach ($items as $it): ?>
                <tr>
                    <td class="py-2.5 text-slate-700"><?= e($it['product_name'] ?? $it['description'] ?? '—') ?></td>
                    <td class="py-2.5 text-right text-slate-600"><?= e((float)$it['quantity']) ?></td>
                    <td class="py-2.5 text-right text-slate-600"><?= e(money($it['unit_price'])) ?></td>
                    <td class="py-2.5 text-right text-slate-600">%<?= e((float)$it['tax_rate']) ?></td>
                    <td class="py-2.5 text-right text-slate-600">%<?= e((float)($it['withholding_rate'] ?? 0)) ?></td>
                    <td class="py-2.5 text-right font-medium text-slate-800"><?= e(money($it['total'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Totals -->
        <div class="ml-auto max-w-xs mt-6 space-y-1 text-sm">
            <div class="flex justify-between text-slate-500"><span><?= e(__('invoice.subtotal')) ?></span><span><?= e(money($invoice['subtotal'])) ?></span></div>
            <div class="flex justify-between text-slate-500"><span><?= e(__('invoice.discount')) ?></span><span>-<?= e(money($invoice['discount'])) ?></span></div>
            <div class="flex justify-between text-slate-500"><span><?= e(__('invoice.tax')) ?></span><span><?= e(money($invoice['tax'])) ?></span></div>
            <?php $w = (float)($invoice['withholding'] ?? 0); if ($w): ?>
            <div class="flex justify-between text-slate-500"><span><?= e(__('report.vat_withholding')) ?></span><span>-<?= e(money($w)) ?></span></div>
            <div class="flex justify-between text-slate-500"><span><?= e(__('invoice.net_vat')) ?></span><span><?= e(money((float)$invoice['tax'] - $w)) ?></span></div>
            <?php endif; ?>
            <div class="flex justify-between text-lg font-bold text-slate-900 border-t border-slate-200 pt-2"><span><?= e(__('invoice.total')) ?></span><span><?= e(money($invoice['total'])) ?></span></div>
        </div>

        <?php if ($invoice['notes']): ?><div class="mt-6 p-4 rounded-lg bg-slate-50 text-sm text-slate-600"><?= nl2br(e($invoice['notes'])) ?></div><?php endif; ?>
    </div>
</div>

<?php if (Auth::can('document.read') || Auth::can('document.create')): ?>
<!-- İlişkili belgeler -->
<div class="mt-6 bg-white border border-slate-200 rounded-2xl p-5">
    <div class="flex items-center justify-between mb-3">
        <h3 class="font-semibold text-slate-800"><?= e(__('nav.documents')) ?> <span class="text-xs text-slate-400 font-normal">(<?= count($documents ?? []) ?>)</span></h3>
        <?php if (Auth::can('document.create')): ?>
        <details class="relative">
            <summary class="cursor-pointer px-3 py-1.5 rounded-lg bg-brand-50 text-brand-700 text-sm font-medium hover:bg-brand-100">+ <?= e(__('document.upload')) ?></summary>
            <form method="post" action="<?= e(url('/app/documents')) ?>" enctype="multipart/form-data" class="absolute right-0 mt-2 w-72 bg-white border border-slate-200 rounded-2xl shadow-lg p-4 space-y-2 z-10">
                <?= csrf_field() ?>
                <input type="hidden" name="company_id" value="<?= (int)$invoice['company_id'] ?>">
                <input type="hidden" name="invoice_id" value="<?= (int)$invoice['id'] ?>">
                <input type="hidden" name="redirect" value="<?= e(url('/app/invoices/' . (int)$invoice['id'])) ?>">
                <select name="category" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm">
                    <?php foreach (['invoice','receipt','contract','bank_statement','other'] as $c): ?>
                    <option value="<?= $c ?>"><?= e(__('document.cat_' . $c)) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="file" name="file" required class="w-full text-sm border border-slate-200 rounded-lg px-2 py-2 bg-white">
                <input name="notes" placeholder="<?= e(__('document.notes')) ?>" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm">
                <button class="w-full px-3 py-2 rounded-lg bg-brand-600 text-white text-sm font-semibold"><?= e(__('document.upload')) ?></button>
            </form>
        </details>
        <?php endif; ?>
    </div>
    <?php if (!empty($documents)): ?>
    <ul class="divide-y divide-slate-50">
        <?php foreach ($documents as $d): ?>
        <li class="py-2 flex items-center justify-between text-sm">
            <div class="flex items-center gap-2 min-w-0">
                <span class="text-slate-400">📄</span>
                <span class="text-slate-700 truncate"><?= e($d['original_name']) ?></span>
                <span class="text-xs text-slate-400">(<?= e(__('document.cat_' . $d['category'])) ?>)</span>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs text-slate-400"><?= e(format_bytes($d['size'])) ?></span>
                <a href="<?= e(url('/app/documents/' . (int)$d['id'] . '/download')) ?>" class="text-brand-600 hover:underline text-xs"><?= e(__('common.download')) ?></a>
                <?php if (Auth::can('document.delete')): ?>
                <form method="post" action="<?= e(url('/app/documents/' . (int)$d['id'] . '/delete')) ?>" class="inline" onsubmit="return confirm('<?= e(__('common.delete')) ?>?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="redirect" value="<?= e(url('/app/invoices/' . (int)$invoice['id'])) ?>">
                    <button class="text-slate-400 hover:text-red-500 text-xs"><?= e(__('common.delete')) ?></button>
                </form>
                <?php endif; ?>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php else: ?>
    <div class="text-sm text-slate-400 py-2"><?= e(__('document.no_documents')) ?></div>
    <?php endif; ?>
</div>
<?php endif; ?>

<style>@media print{ body{background:white} aside,header,main>div>div:first-child{display:none !important} .rounded-2xl{box-shadow:none} }</style>
