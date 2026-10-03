<?php
/** @var array $company @var array $kpis @var array $upcoming @var int $periodId */
use Muh\Core\Translator;
$locale = Translator::instance()->locale();
?>
<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900"><?= e($company['name']) ?></h1>
            <p class="text-slate-500"><?= e(__('dashboard.firm_dashboard')) ?> · <?= e($company['tax_number'] ?? '') ?></p>
        </div>
        <a href="<?= e(url('/app/companies/' . $company['id'])) ?>" class="text-sm text-brand-600 hover:underline"><?= e(__('dashboard.view_company')) ?> →</a>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <?php
        $cards = [
            ['cash', $kpis['cash']],
            ['bank', $kpis['bank']],
            ['receivable', $kpis['receivable']],
            ['payable', $kpis['payable']],
            ['sales', $kpis['sales']],
            ['purchases', $kpis['purchase']],
            ['stock_value', $kpis['stock']],
            ['profit', $kpis['profit']],
        ];
        foreach ($cards as [$label, $val]):
            $negative = in_array($label, ['payable'], true);
        ?>
        <div class="bg-white border border-slate-200 rounded-2xl p-4">
            <div class="text-xs text-slate-400 uppercase"><?= e(__('dashboard.' . $label)) ?></div>
            <div class="text-xl font-bold mt-1 <?= (float)$val < 0 ? 'text-red-600' : 'text-slate-900' ?>"><?= e(money((float)$val, $company['currency'])) ?></div>
        </div>
        <?php endforeach; ?>
        <div class="bg-white border border-slate-200 rounded-2xl p-4">
            <div class="text-xs text-slate-400 uppercase"><?= e(__('dashboard.products')) ?></div>
            <div class="text-xl font-bold mt-1 text-slate-900"><?= (int) $kpis['products'] ?></div>
        </div>
    </div>

    <?php if (!empty($chart)): ?>
    <?php
        $cSales = array_map('floatval', $chart['sales'] ?? []);
        $cPurch = array_map('floatval', $chart['purchase'] ?? []);
        $cColl = array_map('floatval', $chart['collection'] ?? []);
        $cPay = array_map('floatval', $chart['payment'] ?? []);
        $maxA = max(array_merge($cSales, $cPurch, [1]));
        $maxB = max(array_merge($cColl, $cPay, [1]));
    ?>
    <div class="bg-white border border-slate-200 rounded-2xl p-5">
        <div class="font-semibold text-slate-800 mb-4"><?= e(__('dashboard.monthly_flow')) ?></div>
        <div class="grid md:grid-cols-2 gap-6">
            <div>
                <div class="text-xs text-slate-400 mb-2"><?= e(__('dashboard.sales')) ?> vs <?= e(__('dashboard.purchases')) ?></div>
                <div class="flex items-end gap-2 h-32">
                    <?php foreach ($chart['labels'] as $i => $lbl): ?>
                    <div class="flex-1 flex flex-col items-center gap-1">
                        <div class="w-full flex gap-0.5 items-end justify-center" style="height:110px">
                            <div class="w-1/2 rounded-t bg-brand-500" style="height:<?= round(($cSales[$i] / $maxA) * 100) ?>%"></div>
                            <div class="w-1/2 rounded-t bg-rose-400" style="height:<?= round(($cPurch[$i] / $maxA) * 100) ?>%"></div>
                        </div>
                        <span class="text-[10px] text-slate-400"><?= e($lbl) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div>
                <div class="text-xs text-slate-400 mb-2"><?= e(__('dashboard.collections')) ?> vs <?= e(__('dashboard.payments')) ?></div>
                <div class="flex items-end gap-2 h-32">
                    <?php foreach ($chart['labels'] as $i => $lbl): ?>
                    <div class="flex-1 flex flex-col items-center gap-1">
                        <div class="w-full flex gap-0.5 items-end justify-center" style="height:110px">
                            <div class="w-1/2 rounded-t bg-emerald-500" style="height:<?= round(($cColl[$i] / $maxB) * 100) ?>%"></div>
                            <div class="w-1/2 rounded-t bg-amber-400" style="height:<?= round(($cPay[$i] / $maxB) * 100) ?>%"></div>
                        </div>
                        <span class="text-[10px] text-slate-400"><?= e($lbl) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Recent invoices -->
    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 font-semibold text-slate-800 text-sm"><?= e(__('dashboard.recent_invoices')) ?></div>
        <div class="divide-y divide-slate-100">
            <?php if (!$recentInvoices): ?><div class="px-5 py-6 text-sm text-slate-400 text-center"><?= e(__('common.no_data')) ?></div><?php endif; ?>
            <?php foreach ($recentInvoices as $v): ?>
            <a href="<?= e(url('/app/invoices/' . $v['id'] ?? '')) ?>" class="px-5 py-3 flex items-center justify-between hover:bg-slate-50">
                <div>
                    <div class="font-medium text-slate-800 text-sm"><?= e($v['number']) ?></div>
                    <div class="text-xs text-slate-400"><?= e(format_date($v['date'])) ?></div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-600"><?= e(__('invoice.type_' . $v['type'])) ?></span>
                    <span class="font-semibold text-slate-700"><?= e(money((float) $v['total'], $company['currency'])) ?></span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Recent collections / payments -->
    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 font-semibold text-slate-800 text-sm"><?= e(__('dashboard.recent_collections')) ?></div>
        <div class="divide-y divide-slate-100">
            <?php if (!$recentCollections): ?><div class="px-5 py-6 text-sm text-slate-400 text-center"><?= e(__('common.no_data')) ?></div><?php endif; ?>
            <?php foreach ($recentCollections as $c): $pos = $c['type'] === 'collection'; ?>
            <div class="px-5 py-3 flex items-center justify-between">
                <div>
                    <div class="font-medium text-slate-800 text-sm"><?= e($c['cari'] ?? '—') ?></div>
                    <div class="text-xs text-slate-400"><?= e(format_date($c['date'])) ?></div>
                </div>
                <span class="font-semibold <?= $pos ? 'text-emerald-600' : 'text-red-600' ?>"><?= $pos ? '+' : '-' ?><?= e(money((float) $c['amount'], $company['currency'])) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Upcoming payments -->
    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 font-semibold text-slate-800 text-sm"><?= e(__('dashboard.upcoming_payments')) ?></div>
        <div class="divide-y divide-slate-100">
            <?php foreach ($upcoming as $u): ?>
            <div class="px-5 py-3 flex items-center justify-between">
                <div>
                    <div class="font-medium text-slate-800 text-sm"><?= e($u['number']) ?> · <?= e($u['cari'] ?? '') ?></div>
                    <div class="text-xs text-slate-400"><?= e(__('dashboard.due_date')) ?>: <?= e(format_date($u['due_date'])) ?></div>
                </div>
                <span class="font-semibold text-slate-700"><?= e(money((float) $u['total'] - (float) $u['paid'], $company['currency'])) ?></span>
            </div>
            <?php endforeach; ?>
            <?php if (!$upcoming): ?><div class="px-5 py-6 text-sm text-slate-400 text-center"><?= e(__('common.no_data')) ?></div><?php endif; ?>
        </div>
    </div>
</div>
