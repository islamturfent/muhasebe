<?php
/** @var array $companies @var int $companyId @var array $docs */
$statusBadge = fn ($s) => $s === 'converted' ? 'bg-emerald-50 text-emerald-700' : 'bg-blue-50 text-blue-700';
?>
<div class="max-w-5xl">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900"><?= e(__('express.title')) ?></h1>
        <p class="text-slate-500 text-sm mt-1"><?= e(__('express.subtitle')) ?></p>
    </div>

    <!-- Pull -->
    <form method="post" action="<?= e(url('/app/express/pull')) ?>" class="bg-white border border-slate-200 rounded-2xl p-5 mb-6">
        <?= csrf_field() ?>
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs text-slate-500 mb-1"><?= e(__('express.company')) ?></label>
                <select name="company_id" class="px-3 py-2 rounded-lg border border-slate-200 text-sm">
                    <?php foreach ($companies as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $c['id'] == $companyId ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs text-slate-500 mb-1"><?= e(__('express.doc_type')) ?></label>
                <select name="doc_type" class="px-3 py-2 rounded-lg border border-slate-200 text-sm">
                    <option value="e-fatura">e-Fatura</option>
                    <option value="e-arsiv">e-Arşiv</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-slate-500 mb-1"><?= e(__('express.from')) ?></label>
                <input name="from" type="date" required value="<?= e(date('Y-m-01')) ?>" class="px-3 py-2 rounded-lg border border-slate-200 text-sm">
            </div>
            <div>
                <label class="block text-xs text-slate-500 mb-1"><?= e(__('express.to')) ?></label>
                <input name="to" type="date" required value="<?= e(date('Y-m-d')) ?>" class="px-3 py-2 rounded-lg border border-slate-200 text-sm">
            </div>
            <button class="px-5 py-2 rounded-lg bg-brand-600 text-white text-sm font-semibold hover:bg-brand-700"><?= e(__('express.download')) ?></button>
        </div>
        <p class="text-xs text-slate-400 mt-3"><?= e(__('express.source_note')) ?></p>
    </form>

    <!-- Documents -->
    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 font-semibold text-slate-800 text-sm"><?= e(__('express.documents')) ?></div>
        <?php if (!$docs): ?>
        <div class="px-5 py-10 text-center text-slate-400 text-sm"><?= e(__('express.empty')) ?></div>
        <?php endif; ?>
        <?php foreach ($docs as $d): ?>
        <div class="px-5 py-4 border-b border-slate-50 flex flex-wrap items-center gap-4">
            <div class="flex-1 min-w-[200px]">
                <div class="flex items-center gap-2">
                    <span class="font-medium text-slate-800"><?= e($d['document_number']) ?></span>
                    <span class="px-2 py-0.5 rounded-full text-[11px] <?= $statusBadge($d['status']) ?>"><?= $d['status'] === 'converted' ? e(__('express.converted_status')) : e(__('express.downloaded_status')) ?></span>
                </div>
                <div class="text-xs text-slate-500 mt-0.5"><?= e($d['supplier_name'] ?? '') ?> · <?= e($d['doc_date']) ?> · <?= e(__('express.base')) ?> <?= money($d['base']) ?> · <?= e(__('express.vat')) ?> <?= money($d['vat']) ?> · <?= e(__('express.total')) ?> <?= money($d['total']) ?></div>
                <?php if ($d['entry_id']): ?><div class="text-xs text-slate-400 mt-0.5"><?= e(__('express.entry')) ?> #<?= (int) $d['entry_id'] ?></div><?php endif; ?>
            </div>
            <?php if ($d['status'] === 'downloaded'): ?>
            <form method="post" action="<?= e(url('/app/express/' . (int) $d['id'] . '/convert')) ?>" class="flex flex-wrap items-end gap-2">
                <?= csrf_field() ?>
                <input type="hidden" name="company_id" value="<?= (int) $companyId ?>">
                <div><label class="block text-[11px] text-slate-400"><?= e(__('express.inventory_code')) ?></label><input name="inventory" value="153" class="w-16 px-2 py-1.5 rounded-lg border border-slate-200 text-xs"></div>
                <div><label class="block text-[11px] text-slate-400"><?= e(__('express.vat_code')) ?></label><input name="vat" value="191" class="w-16 px-2 py-1.5 rounded-lg border border-slate-200 text-xs"></div>
                <div><label class="block text-[11px] text-slate-400"><?= e(__('express.supplier_code')) ?></label><input name="supplier" value="320" class="w-16 px-2 py-1.5 rounded-lg border border-slate-200 text-xs"></div>
                <button class="px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-700"><?= e(__('express.prepare_save')) ?></button>
            </form>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
