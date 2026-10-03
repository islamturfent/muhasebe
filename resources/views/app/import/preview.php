<?php
/** @var string $module @var array $fields @var array $rows @var array $mapping @var int $maxCols @var array $companies @var int $companyId @var string $filename @var bool $hasHeader */
$fieldLabels = [
    'code' => __('current_account.code'),
    'name' => __('common.name'),
    'type' => __('import.field') . ' (Tür)',
    'barcode' => __('import.field_barcode'),
    'tax_number' => __('current_account.tax_number'),
    'phone' => __('common.phone'),
    'email' => __('common.email'),
    'iban' => __('bank.iban'),
    'balance' => __('current_account.balance'),
    'risk_limit' => __('current_account.risk_limit'),
    'purchase_price' => __('import.field_purchase'),
    'sale_price' => __('import.field_sale'),
    'vat_rate' => __('import.field_vat'),
    'stock_quantity' => __('report.stock_qty'),
    'critical_stock' => __('import.field_critical'),
    'description' => __('common.description'),
    'unit' => __('import.field_unit'),
    'subtype' => __('import.field_subtype'),
    'is_header' => __('accounting.account_is_header'),
    'opening_debit' => __('accounting.opening_debit'),
    'opening_credit' => __('accounting.opening_credit'),
    'currency' => __('import.field_currency'),
];
?>
<div class="mb-6">
    <a href="<?= e(url('/app/import')) ?>" class="text-sm text-brand-600 hover:underline">← <?= e(__('import.title')) ?></a>
    <h1 class="text-2xl font-bold text-slate-900 mt-1"><?= e(__('import.preview')) ?></h1>
    <p class="text-slate-500"><?= e($filename) ?> · <?= e(__('import.module_' . $module)) ?></p>
</div>

<form method="post" action="<?= e(url('/app/import/run')) ?>">
    <?= csrf_field() ?>

    <?php if ($module === 'hesap'): ?>
    <div class="bg-white border border-slate-200 rounded-2xl p-6 mb-6">
        <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('accounting.chart_of_accounts')) ?> (<?= e(__('period.title')) ?>) *</label>
        <select name="period_id" required class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
            <?php foreach ($periods as $p): ?><option value="<?= e($p['id']) ?>"><?= e($p['name']) ?></option><?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>

    <div class="bg-white border border-slate-200 rounded-2xl p-6 mb-6">
        <h3 class="font-semibold text-slate-800 mb-4"><?= e(__('import.map_column')) ?></h3>
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-3">
            <?php for ($i = 0; $i < $maxCols; $i++): ?>
            <div>
                <label class="block text-xs text-slate-400 mb-1"><?= e(__('import.field')) ?> <?= $i + 1 ?></label>
                <select name="map_col_<?= $i ?>" class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none text-sm">
                    <option value=""><?= e(__('import.skip')) ?></option>
                    <?php foreach ($fields as $f): ?>
                    <option value="<?= e($f) ?>" <?= ($mapping[$i] ?? '') === $f ? 'selected' : '' ?>><?= e($fieldLabels[$f] ?? $f) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endfor; ?>
        </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl mb-6">
        <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
            <span class="font-semibold text-slate-800 text-sm"><?= e(__('import.preview')) ?></span>
            <span class="text-xs text-slate-400"><?= count($rows) ?> <?= e(__('common.total')) ?></span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-xs text-slate-400 uppercase bg-slate-50">
                    <tr><th class="px-4 py-2">#</th><?php for ($i = 0; $i < $maxCols; $i++): ?><th class="px-4 py-2"><?= $i + 1 ?></th><?php endfor; ?></tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php $shown = 0; foreach ($rows as $r): if ($shown >= 10) break; $shown++; ?>
                    <tr><td class="px-4 py-2 text-slate-400"><?= $shown ?></td><?php for ($i = 0; $i < $maxCols; $i++): ?><td class="px-4 py-2 text-slate-600"><?= e(mb_substr((string) ($r[$i] ?? ''), 0, 40)) ?></td><?php endfor; ?></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <button class="px-6 py-3 rounded-xl bg-brand-600 text-white font-semibold hover:bg-brand-700"><?= e(__('import.run_import')) ?></button>
</form>
