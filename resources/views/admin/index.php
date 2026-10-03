<?php
/** @var array $stats @var array $recentTenants */
?>
<div class="max-w-6xl mx-auto space-y-6">
    <h1 class="text-2xl font-bold text-slate-900"><?= e(__('admin.dashboard')) ?></h1>

    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <?php
        $cards = [
            ['admin.t_tenants', $stats['tenants']],
            ['admin.t_active', $stats['active_tenants']],
            ['admin.t_companies', $stats['companies']],
            ['admin.t_users', $stats['users']],
            ['admin.t_invoices', $stats['invoices']],
            ['admin.t_admins', $stats['system_admins']],
        ];
        foreach ($cards as [$label, $val]): ?>
        <div class="bg-white border border-slate-200 rounded-2xl p-4">
            <div class="text-2xl font-bold text-slate-900"><?= (int) $val ?></div>
            <div class="text-xs text-slate-500 mt-1"><?= e(__($label)) ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if (!empty($subStats)): ?>
    <?php $statuses = ['active','trial','past_due','cancelled','expired']; ?>
    <div class="bg-white border border-slate-200 rounded-2xl p-5">
        <div class="font-semibold text-slate-800 mb-3"><?= e(__('admin.sub_platform')) ?> <span class="text-slate-400 font-normal text-sm">(<?= e((int) ($subStats['total'] ?? 0)) ?>)</span></div>
        <div class="flex flex-wrap gap-3 text-sm">
            <?php foreach ($statuses as $st): if (empty($subStats['byStatus'][$st])) continue; ?>
            <span class="px-3 py-1.5 rounded-lg bg-slate-50 border border-slate-200 text-slate-600"><?= e(__('admin.status_' . $st)) ?>: <b><?= e((int) $subStats['byStatus'][$st]) ?></b></span>
            <?php endforeach; ?>
            <?php foreach (($subStats['byPlan'] ?? []) as $name => $c): ?>
            <span class="px-3 py-1.5 rounded-lg bg-brand-50 border border-brand-200 text-brand-700"><?= e($name) ?>: <b><?= e((int) $c) ?></b></span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 font-semibold text-slate-800 text-sm"><?= e(__('admin.recent_tenants')) ?></div>
        <div class="divide-y divide-slate-100">
            <?php foreach ($recentTenants as $t): ?>
            <a href="<?= e(url('/admin/tenants/' . $t['id'])) ?>" class="px-5 py-3 flex items-center justify-between hover:bg-slate-50">
                <div>
                    <div class="font-medium text-slate-800 text-sm"><?= e($t['name']) ?></div>
                    <div class="text-xs text-slate-400"><?= e($t['email']) ?></div>
                </div>
                <span class="px-2 py-0.5 rounded-full text-xs <?= $t['status']==='active' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-600' ?>">
                    <?= e(__('admin.status_' . $t['status'])) ?>
                </span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
