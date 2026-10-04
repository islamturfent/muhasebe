<?php
/** @var array $companies @var int $companyId @var array $periods @var int $periodId */
?>
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900"><?= e(__('nav.reports')) ?></h1>
    <p class="text-slate-500"><?= e(__('reports.export_desc')) ?></p>
</div>

<div class="mb-6 bg-white border border-slate-200 rounded-2xl p-4 flex items-center gap-3">
    <select id="repCompany" class="text-sm border border-slate-200 rounded-lg px-3 py-2 bg-white focus:ring-2 focus:ring-brand-500 outline-none">
        <?php foreach ($companies as $c): ?><option value="<?= e($c['id']) ?>" <?= (int)$companyId===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
    </select>
    <select id="repPeriod" class="text-sm border border-slate-200 rounded-lg px-3 py-2 bg-white focus:ring-2 focus:ring-brand-500 outline-none">
        <?php foreach ($periods as $p): ?><option value="<?= e($p['id']) ?>" <?= (int)$periodId===(int)$p['id']?'selected':'' ?>><?= e($p['name']) ?></option><?php endforeach; ?>
    </select>
</div>

<?php
$reports = [
    ['mizan', 'accounting.trial_balance'],
    ['yevmiye', 'accounting.journal'],
    ['defter', 'report.ledger'],
    ['bilanco', 'accounting.balance_sheet'],
    ['gelir', 'accounting.income_statement'],
    ['cari', 'current_account.title'],
    ['yaslandirma', 'report.aging'],
    ['kdv', 'report.vat_summary'],
    ['kdv-beyanname', 'report.vat_declaration'],
    ['stok', 'report.stock'],
    ['stok-maliyet', 'report.stock_cost'],
    ['satis', 'report.sales'],
    ['alis', 'report.purchases'],
    ['kasa', 'report.cash'],
    ['banka', 'report.bank'],
    ['banka-mutabakat', 'report.bank_reconciliation'],
    ['kdv-detay', 'report.vat_detail'],
    ['efatura', 'report.efatura_status'],
    ['yuklumlulukler', 'report.upcoming_liabilities'],
    ['karlilik', 'report.profitability'],
    ['comparative', 'report.comparative'],
    ['borc-alacak', 'report.receivables_payables'],
];
?>
<div class="grid md:grid-cols-2 gap-4">
    <?php
    $screenable = ['yaslandirma' => true, 'stok' => true, 'karlilik' => true, 'kdv' => true, 'kdv-beyanname' => true, 'yuklumlulukler' => true, 'kasa' => true, 'banka' => true, 'efatura' => true, 'comparative' => true];
    foreach ($reports as [$slug, $label]): ?>
    <div class="bg-white border border-slate-200 rounded-2xl p-5">
        <div class="flex items-center gap-3 mb-4">
            <span class="w-10 h-10 rounded-lg bg-brand-100 text-brand-700 flex items-center justify-center text-sm font-bold">📊</span>
            <h3 class="font-semibold text-slate-900"><?= e(__($label)) ?></h3>
        </div>
        <?php if (isset($screenable[$slug])): ?>
        <a href="<?= e(url('/app/reports/' . $slug . '?company_id=' . $companyId . '&period_id=' . $periodId)) ?>" class="mb-3 block w-full text-center px-3 py-2 rounded-lg bg-brand-50 text-brand-700 text-sm font-medium hover:bg-brand-100"><?= e(__('common.view')) ?> →</a>
        <?php endif; ?>
        <div class="flex gap-2">
            <?php foreach (['pdf' => 'PDF', 'excel' => 'Excel', 'csv' => 'CSV'] as $fmt => $lbl): ?>
            <?php $url = url('/app/reports/' . $slug . '/export?format=' . $fmt . '&company_id=' . $companyId . '&period_id=' . $periodId); ?>
            <a href="<?= e($url) ?>" class="flex-1 text-center px-3 py-2 rounded-lg border border-slate-200 text-sm font-medium text-slate-600 hover:border-brand-300 hover:text-brand-600"><?= e($lbl) ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<script>
document.getElementById('repCompany').addEventListener('change', function(){
  const p = document.getElementById('repPeriod').value;
  const base = window.location.pathname;
  location.href = base + '?company_id=' + this.value + '&period_id=' + p;
});
</script>
