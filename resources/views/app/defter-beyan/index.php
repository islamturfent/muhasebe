<?php
/** @var array $companies @var int $companyId @var array $records */
$statusBadge = fn ($s) => [
    'draft' => 'bg-slate-100 text-slate-600',
    'sent' => 'bg-emerald-50 text-emerald-700',
    'error' => 'bg-red-50 text-red-700',
][$s] ?? 'bg-slate-100 text-slate-600';
$docTypes = ['e-smm' => __('defter_beyan.doc_e_smm'), 'satis' => __('defter_beyan.doc_satis'), 'gider' => __('defter_beyan.doc_gider'), 'diger' => __('defter_beyan.doc_diger')];
?>
<div class="max-w-5xl">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900"><?= e(__('defter_beyan.title')) ?></h1>
        <p class="text-slate-500 text-sm mt-1"><?= e(__('defter_beyan.subtitle')) ?></p>
    </div>

    <div class="grid md:grid-cols-2 gap-6 mb-6">
        <form method="post" action="<?= e(url('/app/defter-beyan')) ?>" class="bg-white border border-slate-200 rounded-2xl p-5 space-y-3">
            <?= csrf_field() ?>
            <div class="font-semibold text-slate-800 text-sm"><?= e(__('defter_beyan.add_record')) ?></div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="block text-xs text-slate-500 mb-1"><?= e(__('defter_beyan.company')) ?></label>
                    <select name="company_id" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm"><?php foreach ($companies as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $c['id'] == $companyId ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
                <div><label class="block text-xs text-slate-500 mb-1"><?= e(__('defter_beyan.date')) ?></label>
                    <input name="record_date" type="date" required value="<?= e(date('Y-m-d')) ?>" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm"></div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="block text-xs text-slate-500 mb-1"><?= e(__('defter_beyan.doc_type')) ?></label>
                    <select name="doc_type" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm"><?php foreach ($docTypes as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select></div>
                <div><label class="block text-xs text-slate-500 mb-1"><?= e(__('defter_beyan.doc_number')) ?></label>
                    <input name="doc_number" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm"></div>
            </div>
            <div><label class="block text-xs text-slate-500 mb-1"><?= e(__('defter_beyan.description')) ?></label>
                <input name="description" required class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm"></div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="block text-xs text-slate-500 mb-1"><?= e(__('defter_beyan.amount')) ?></label>
                    <input name="amount" type="number" step="0.01" required value="0" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm"></div>
                <div><label class="block text-xs text-slate-500 mb-1"><?= e(__('defter_beyan.vat')) ?></label>
                    <input name="vat" type="number" step="0.01" value="0" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm"></div>
            </div>
            <button class="px-5 py-2 rounded-lg bg-brand-600 text-white text-sm font-semibold hover:bg-brand-700"><?= e(__('defter_beyan.add')) ?></button>
        </form>

        <form method="post" action="<?= e(url('/app/defter-beyan/import')) ?>" class="bg-white border border-slate-200 rounded-2xl p-5 space-y-3">
            <?= csrf_field() ?>
            <div class="font-semibold text-slate-800 text-sm"><?= e(__('defter_beyan.import') )?></div>
            <input type="hidden" name="company_id" value="<?= (int) $companyId ?>">
            <textarea name="csv" rows="5" required placeholder="<?= e(__('defter_beyan.csv_hint')) ?>" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm font-mono"></textarea>
            <div class="text-xs text-slate-400"><?= e(__('defter_beyan.csv_format')) ?></div>
            <button class="px-5 py-2 rounded-lg bg-slate-800 text-white text-sm font-semibold hover:bg-slate-900"><?= e(__('defter_beyan.import_btn')) ?></button>
        </form>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 font-semibold text-slate-800 text-sm"><?= e(__('defter_beyan.records')) ?></div>
        <?php if (!$records): ?><div class="px-5 py-10 text-center text-slate-400 text-sm"><?= e(__('defter_beyan.empty')) ?></div><?php endif; ?>
        <?php foreach ($records as $r): ?>
        <div class="px-5 py-3 border-b border-slate-50 flex flex-wrap items-center gap-4">
            <div class="flex-1 min-w-[200px]">
                <div class="flex items-center gap-2">
                    <span class="font-medium text-slate-800"><?= e($docTypes[$r['doc_type']] ?? $r['doc_type']) ?></span>
                    <span class="text-xs text-slate-400"><?= e($r['record_date']) ?><?= $r['doc_number'] ? ' · ' . e($r['doc_number']) : '' ?></span>
                </div>
                <div class="text-xs text-slate-500 mt-0.5"><?= e($r['description']) ?> · <?= money($r['total']) ?></div>
                <?php if ($r['gib_reference']): ?><div class="text-xs text-slate-400">GİB: <?= e($r['gib_reference']) ?></div><?php endif; ?>
                <?php if ($r['gib_error']): ?><div class="text-xs text-red-600"><?= e($r['gib_error']) ?></div><?php endif; ?>
            </div>
            <span class="px-2.5 py-1 rounded-full text-xs font-medium <?= $statusBadge($r['status']) ?>"><?= e(__('defter_beyan.status_' . $r['status'])) ?></span>
            <?php if (in_array($r['status'], ['draft', 'error'], true)): ?>
            <form method="post" action="<?= e(url('/app/defter-beyan/' . (int) $r['id'] . '/submit')) ?>"><?= csrf_field() ?>
                <input type="hidden" name="company_id" value="<?= (int) $companyId ?>">
                <button class="px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-700"><?= e(__('defter_beyan.send_to_gib')) ?></button>
            </form>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
