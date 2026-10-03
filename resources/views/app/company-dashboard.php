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
