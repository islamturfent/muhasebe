<?php
/** @var array $rows @var array $totals */
?>
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900"><?= e(__('admin.analytics')) ?></h1>
    <p class="text-slate-500"><?= e(__('admin.analytics_hint')) ?></p>
</div>

<div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
    <?php
    $cards = [
        ['admin.a_users', $totals['users']], ['admin.a_companies', $totals['companies']],
        ['admin.a_invoices', $totals['invoices']], ['admin.a_revenue', money((float) $totals['revenue'])],
        ['admin.a_documents', $totals['documents']],
    ];
    foreach ($cards as [$label, $val]): ?>
    <div class="bg-white border border-slate-200 rounded-2xl p-4">
        <div class="text-xs text-slate-400 uppercase"><?= e(__($label)) ?></div>
        <div class="text-2xl font-bold mt-1 text-slate-900"><?= e($val) ?></div>
    </div>
    <?php endforeach; ?>
</div>

<div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
    <div class="px-5 py-3 border-b border-slate-100 font-semibold text-slate-800 text-sm"><?= e(__('admin.all_tenants')) ?></div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-left text-xs text-slate-400 uppercase bg-slate-50">
                <tr>
                    <th class="px-5 py-3"><?= e(__('admin.tenant')) ?></th>
                    <th class="px-5 py-3"><?= e(__('admin.a_users')) ?></th>
                    <th class="px-5 py-3"><?= e(__('admin.a_companies')) ?></th>
                    <th class="px-5 py-3"><?= e(__('admin.a_invoices')) ?></th>
                    <th class="px-5 py-3 text-right"><?= e(__('admin.a_revenue')) ?></th>
                    <th class="px-5 py-3"><?= e(__('admin.sub_status')) ?></th>
                    <th class="px-5 py-3"><?= e(__('admin.a_last_activity')) ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php foreach ($rows as $r): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-5 py-3">
                        <a href="<?= e(url('/admin/tenants/' . $r['id'])) ?>" class="font-medium text-slate-800 hover:text-brand-600"><?= e($r['name']) ?></a>
                        <div class="text-xs text-slate-400"><?= e(__('admin.status_' . $r['status'])) ?></div>
                    </td>
                    <td class="px-5 py-3"><?= (int) $r['users'] ?></td>
                    <td class="px-5 py-3"><?= (int) $r['companies'] ?></td>
                    <td class="px-5 py-3"><?= (int) $r['invoices'] ?></td>
                    <td class="px-5 py-3 text-right"><?= e(money((float) $r['revenue'])) ?></td>
                    <td class="px-5 py-3">
                        <span class="px-2 py-0.5 rounded-full text-xs <?= $r['sub_status']==='active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' ?>"><?= e($r['sub_status']) ?></span>
                        <?php if ($r['sub_ends']): ?><div class="text-[11px] text-slate-400 mt-0.5"><?= e(format_date($r['sub_ends'])) ?></div><?php endif; ?>
                    </td>
                    <td class="px-5 py-3 text-xs text-slate-500"><?= e($r['last_activity'] ? date('d.m.Y', strtotime($r['last_activity'])) : '—') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr><td colspan="7" class="px-5 py-8 text-center text-slate-400"><?= e(__('common.no_data')) ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
