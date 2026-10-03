<?php
/** @var array $kpis @var array $recentInvoices @var array $recentCompanies @var array $upcoming */
use Muh\Core\Auth;
use Muh\Core\Translator;
$user = Auth::user();
?>
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-900"><?= e(__('dashboard.welcome', ['name' => $user['name'] ?? ''])) ?></h1>
        <p class="text-slate-500"><?= e(__('dashboard.office_overview')) ?></p>
    </div>
    <?php if ($activeCompanyId = \Muh\Services\SessionContext::companyId()): ?>
    <a href="<?= e(url('/app/company')) ?>" class="px-4 py-2 rounded-lg bg-brand-600 text-white text-sm font-medium hover:bg-brand-700"><?= e(__('dashboard.firm_dashboard')) ?></a>
    <?php endif; ?>
</div>

<!-- KPI Cards -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    <?php $cards = [
        ['dashboard.total_companies', $kpis['total_companies'], 'bg-brand-600', 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10'],
        ['dashboard.active_customers', $kpis['active_customers'], 'bg-emerald-600', 'M17 20h5v-2a3 3 0 00-5.36-1.86M7 20H2v-2a3 3 0 015.36-1.86M16 8a4 4 0 11-8 0 4 4 0 018 0z'],
        ['dashboard.pending_invoices', $kpis['pending_invoices'], 'bg-amber-500', 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['dashboard.overdue', $kpis['overdue'], 'bg-red-600', 'M12 9v2m0 4h.01m-6.9 5h13.8a1 1 0 00.9-1.45l-6.9-12a1 1 0 00-1.72 0l-6.9 12a1 1 0 00.9 1.45z'],
    ]; foreach ($cards as [$label, $value, $color, $icon]): ?>
    <div class="rounded-2xl p-5 text-white <?= $color ?> shadow">
        <div class="flex items-center gap-3">
            <svg class="w-8 h-8 opacity-90" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="<?= $icon ?>"/></svg>
            <div>
                <div class="text-2xl font-bold leading-none"><?= (int) $value ?></div>
                <div class="text-xs mt-1 opacity-90"><?= e(__($label)) ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if (!empty($chart)): ?>
<?php
    $cSales = array_map('floatval', $chart['sales'] ?? []);
    $cPurch = array_map('floatval', $chart['purchase'] ?? []);
    $cColl = array_map('floatval', $chart['collection'] ?? []);
    $cPay = array_map('floatval', $chart['payment'] ?? []);
    $maxA = max(array_merge($cSales, $cPurch, [1]));
    $maxB = max(array_merge($cColl, $cPay, [1]));
    $ef = $efaturaCounts ?? [];
?>
<!-- Aylık hareketler + e-Fatura durumu -->
<div class="grid lg:grid-cols-2 gap-4 mt-6">
    <div class="bg-white border border-slate-200 rounded-2xl p-5">
        <div class="font-semibold text-slate-800 mb-4"><?= e(__('dashboard.monthly_flow')) ?></div>
        <div class="text-xs text-slate-400 mb-2"><?= e(__('dashboard.sales')) ?> vs <?= e(__('dashboard.purchases')) ?></div>
        <div class="flex items-end gap-2 h-28">
            <?php foreach ($chart['labels'] as $i => $lbl): ?>
            <div class="flex-1 flex flex-col items-center gap-1">
                <div class="w-full flex gap-0.5 items-end justify-center" style="height:95px">
                    <div class="w-1/2 rounded-t bg-brand-500" style="height:<?= round(($cSales[$i] / $maxA) * 100) ?>%"></div>
                    <div class="w-1/2 rounded-t bg-rose-400" style="height:<?= round(($cPurch[$i] / $maxA) * 100) ?>%"></div>
                </div>
                <span class="text-[10px] text-slate-400"><?= e($lbl) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="text-xs text-slate-400 mt-4 mb-2"><?= e(__('dashboard.collections')) ?> vs <?= e(__('dashboard.payments')) ?></div>
        <div class="flex items-end gap-2 h-28">
            <?php foreach ($chart['labels'] as $i => $lbl): ?>
            <div class="flex-1 flex flex-col items-center gap-1">
                <div class="w-full flex gap-0.5 items-end justify-center" style="height:95px">
                    <div class="w-1/2 rounded-t bg-emerald-500" style="height:<?= round(($cColl[$i] / $maxB) * 100) ?>%"></div>
                    <div class="w-1/2 rounded-t bg-amber-400" style="height:<?= round(($cPay[$i] / $maxB) * 100) ?>%"></div>
                </div>
                <span class="text-[10px] text-slate-400"><?= e($lbl) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="bg-white border border-slate-200 rounded-2xl p-5">
        <div class="font-semibold text-slate-800 mb-4"><?= e(__('efatura.title')) ?> <?= e(__('efatura.status')) ?></div>
        <div class="space-y-2">
            <?php foreach (['draft','sending','sent','accepted','rejected','error'] as $st): if (empty($ef[$st])) continue; ?>
            <div class="flex items-center justify-between text-sm">
                <span class="text-slate-600"><?= e(__('efatura.st_' . $st)) ?></span>
                <span class="px-2 py-0.5 rounded-full text-xs <?= $st==='accepted' ? 'bg-emerald-50 text-emerald-700' : (in_array($st,['error','rejected'],true) ? 'bg-red-50 text-red-600' : 'bg-slate-100 text-slate-600') ?>" style="min-width:30px;text-align:center"><?= (int) $ef[$st] ?></span>
            </div>
            <?php endforeach; ?>
            <?php if (!$ef): ?><div class="text-sm text-slate-400"><?= e(__('common.no_data')) ?></div><?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="grid lg:grid-cols-3 gap-6 mt-6">
    <!-- Recent companies -->
    <div class="lg:col-span-1 bg-white border border-slate-200 rounded-2xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 font-semibold text-slate-800"><?= e(__('dashboard.recent_companies')) ?></div>
        <div class="divide-y divide-slate-100">
            <?php if (!$recentCompanies): ?><div class="p-5 text-sm text-slate-400"><?= e(__('app.no_companies')) ?></div><?php endif; ?>
            <?php foreach ($recentCompanies as $c): ?>
            <form method="post" action="<?= e(url('/app/switch-company')) ?>" class="flex items-center gap-3 px-5 py-3 hover:bg-slate-50">
                <?= csrf_field() ?>
                <input type="hidden" name="company_id" value="<?= e($c['id']) ?>">
                <span class="w-9 h-9 rounded-lg bg-brand-100 text-brand-700 flex items-center justify-center font-bold text-xs"><?= e(mb_strtoupper(mb_substr($c['name'], 0, 1))) ?></span>
                <div class="flex-1 min-w-0">
                    <div class="font-medium text-slate-800 text-sm truncate"><?= e($c['name']) ?></div>
                    <div class="text-xs text-slate-400"><?= e($c['tax_number'] ?? '—') ?></div>
                </div>
                <button class="text-xs text-brand-600 font-medium whitespace-nowrap"><?= e(__('dashboard.view_company')) ?> →</button>
            </form>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Recent invoices -->
    <div class="lg:col-span-2 bg-white border border-slate-200 rounded-2xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 font-semibold text-slate-800"><?= e(__('dashboard.recent_invoices')) ?></div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-xs text-slate-400 uppercase">
                    <tr class="border-b border-slate-100">
                        <th class="px-5 py-2"><?= e(__('dashboard.company')) ?></th>
                        <th class="px-5 py-2"><?= e(__('dashboard.invoice_no')) ?></th>
                        <th class="px-5 py-2"><?= e(__('common.date')) ?></th>
                        <th class="px-5 py-2"><?= e(__('dashboard.due_date')) ?></th>
                        <th class="px-5 py-2 text-right"><?= e(__('common.total')) ?></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if (!$recentInvoices): ?><tr><td colspan="5" class="px-5 py-6 text-slate-400"><?= e(__('dashboard.no_invoices_yet')) ?></td></tr><?php endif; ?>
                    <?php foreach ($recentInvoices as $inv): ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-5 py-3 text-slate-700"><?= e($inv['company_name'] ?? '') ?></td>
                        <td class="px-5 py-3 text-slate-500"><?= e($inv['number']) ?></td>
                        <td class="px-5 py-3 text-slate-500"><?= e(format_date($inv['date'])) ?></td>
                        <td class="px-5 py-3 text-slate-500"><?= e(format_date($inv['due_date'])) ?></td>
                        <td class="px-5 py-3 text-right font-medium text-slate-800"><?= e(money($inv['total'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Upcoming dues -->
<div class="mt-6 bg-white border border-slate-200 rounded-2xl overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100 font-semibold text-slate-800"><?= e(__('dashboard.upcoming_due_dates')) ?></div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-left text-xs text-slate-400 uppercase">
                <tr class="border-b border-slate-100">
                    <th class="px-5 py-2"><?= e(__('dashboard.company')) ?></th>
                    <th class="px-5 py-2"><?= e(__('dashboard.invoice_no')) ?></th>
                    <th class="px-5 py-2"><?= e(__('dashboard.due_date')) ?></th>
                    <th class="px-5 py-2 text-right"><?= e(__('common.amount')) ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php if (!$upcoming): ?><tr><td colspan="4" class="px-5 py-6 text-slate-400"><?= e(__('dashboard.no_invoices_yet')) ?></td></tr><?php endif; ?>
                <?php foreach ($upcoming as $inv): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-5 py-3 text-slate-700"><?= e($inv['company_name'] ?? '') ?></td>
                    <td class="px-5 py-3 text-slate-500"><?= e($inv['number']) ?></td>
                    <td class="px-5 py-3">
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-amber-50 text-amber-700 border border-amber-200"><?= e(format_date($inv['due_date'])) ?></span>
                    </td>
                    <td class="px-5 py-3 text-right font-medium text-slate-800"><?= e(money($inv['total'] - $inv['paid'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
