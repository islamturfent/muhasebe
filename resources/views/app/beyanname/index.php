<?php
/** @var array $declarations @var array $companies @var array $settings */
$statusBadge = fn ($s) => [
    'draft' => 'bg-slate-100 text-slate-600',
    'packaged' => 'bg-blue-50 text-blue-700',
    'sent' => 'bg-amber-50 text-amber-700',
    'approved' => 'bg-emerald-50 text-emerald-700',
    'error' => 'bg-red-50 text-red-700',
][$s] ?? 'bg-slate-100 text-slate-600';
$statusLabel = fn ($s) => [
    'draft' => __('beyan.status_draft'),
    'packaged' => __('beyan.status_packaged'),
    'sent' => __('beyan.status_sent'),
    'approved' => __('beyan.status_approved'),
    'error' => __('beyan.status_error'),
][$s] ?? $s;
?>
<div class="max-w-5xl">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900"><?= e(__('beyan.title')) ?></h1>
        <p class="text-slate-500 text-sm mt-1"><?= e(__('beyan.subtitle')) ?></p>
    </div>

    <?php if (empty($settings['token']) && empty($settings['username']) && $settings['provider'] === 'rest'): ?>
    <div class="mb-4 rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800"><?= e(__('beyan.settings_hint')) ?></div>
    <?php endif; ?>

    <!-- Prepare -->
    <form method="post" action="<?= e(url('/app/beyanname/prepare')) ?>" class="bg-white border border-slate-200 rounded-2xl p-5 mb-6">
        <?= csrf_field() ?>
        <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-[220px]">
                <label class="block text-xs text-slate-500 mb-1"><?= e(__('beyan.company')) ?></label>
                <select name="company_id" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm">
                    <?php foreach ($companies as $c): ?>
                    <option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?><?= $c['tax_office_code'] ? ' · ' . e($c['tax_office_code']) : ' · (GİB kod yok)' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs text-slate-500 mb-1"><?= e(__('beyan.period')) ?> (YYYY-MM)</label>
                <input name="month" type="month" required value="<?= e(date('Y-m')) ?>" class="px-3 py-2 rounded-lg border border-slate-200 text-sm">
            </div>
            <button class="px-5 py-2 rounded-lg bg-brand-600 text-white text-sm font-semibold hover:bg-brand-700"><?= e(__('beyan.prepare')) ?></button>
        </div>
    </form>

    <!-- List -->
    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 font-semibold text-slate-800 text-sm"><?= e(__('beyan.declarations')) ?></div>
        <?php if (!$declarations): ?>
        <div class="px-5 py-10 text-center text-slate-400 text-sm"><?= e(__('beyan.empty')) ?></div>
        <?php endif; ?>
        <?php foreach ($declarations as $d): $p = json_decode((string) $d['payload'], true) ?: []; $f = $p['figures'] ?? []; ?>
        <div class="px-5 py-4 border-b border-slate-50 flex flex-wrap items-center gap-4">
            <div class="flex-1 min-w-[180px]">
                <div class="flex items-center gap-2">
                    <span class="font-medium text-slate-800"><?= e($d['company_name'] ?? '—') ?></span>
                    <span class="text-xs text-slate-400"><?= e($d['type']) ?> · <?= e($d['period']) ?></span>
                </div>
                <div class="text-xs text-slate-500 mt-0.5">
                    <?= e(__('beyan.out_vat')) ?> <?= money($f['outVat'] ?? 0) ?> · <?= e(__('beyan.in_vat')) ?> <?= money($f['inVat'] ?? 0) ?> ·
                    <?= e(__('beyan.payable')) ?> <?= money($f['payable'] ?? 0) ?>
                </div>
                <?php if ($d['gib_reference']): ?><div class="text-xs text-slate-400 mt-0.5">GİB Ref: <?= e($d['gib_reference']) ?></div><?php endif; ?>
                <?php if ($d['gib_error']): ?><div class="text-xs text-red-600 mt-0.5"><?= e($d['gib_error']) ?></div><?php endif; ?>
            </div>
            <span class="px-2.5 py-1 rounded-full text-xs font-medium <?= $statusBadge($d['status']) ?>"><?= e($statusLabel($d['status'])) ?></span>
            <div class="flex gap-2">
                <?php if (in_array($d['status'], ['draft', 'packaged', 'error'], true)): ?>
                <form method="post" action="<?= e(url('/app/beyanname/' . (int) $d['id'] . '/submit')) ?>"><?= csrf_field() ?>
                    <button class="px-3 py-1.5 rounded-lg bg-amber-600 text-white text-xs font-semibold hover:bg-amber-700"><?= e(__('beyan.send_to_gib')) ?></button>
                </form>
                <?php elseif ($d['status'] === 'sent'): ?>
                <form method="post" action="<?= e(url('/app/beyanname/' . (int) $d['id'] . '/approve')) ?>"><?= csrf_field() ?>
                    <button class="px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-700"><?= e(__('beyan.approve_in_gib')) ?></button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
