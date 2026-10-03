<?php
/** @var array $tenant @var array $companies @var array $users @var array|null $sub */
?>
<div class="max-w-4xl mx-auto space-y-6">
    <a href="<?= e(url('/admin/tenants')) ?>" class="text-sm text-brand-600 hover:underline">← <?= e(__('admin.tenants')) ?></a>

    <div class="bg-white border border-slate-200 rounded-2xl p-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-900"><?= e($tenant['name']) ?></h1>
                <p class="text-sm text-slate-500"><?= e($tenant['email']) ?> · <?= e($tenant['locale']) ?> / <?= e($tenant['currency']) ?></p>
            </div>
            <span class="px-3 py-1 rounded-full text-xs font-medium <?= $tenant['status']==='active'?'bg-emerald-50 text-emerald-700':'bg-red-50 text-red-600' ?>"><?= e(__('admin.status_' . $tenant['status'])) ?></span>
        </div>
        <?php if ($sub): ?><div class="mt-3 text-sm text-slate-600"><?= e(__('admin.subscription')) ?>: <strong><?= e($sub['plan_code']) ?></strong> (<?= e(__('admin.status_' . $sub['status'])) ?>)</div><?php endif; ?>
        <form method="post" action="<?= e(url('/admin/tenants/' . $tenant['id'] . '/impersonate')) ?>" class="mt-4">
            <?= csrf_field() ?>
            <button class="px-4 py-2 rounded-lg bg-amber-600 text-white text-sm font-medium hover:bg-amber-700">👁 <?= e(__('admin.impersonate_btn')) ?></button>
        </form>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 font-semibold text-slate-800 text-sm"><?= e(__('admin.firms')) ?> (<?= count($companies) ?>)</div>
        <div class="divide-y divide-slate-100">
            <?php foreach ($companies as $c): ?>
            <div class="px-5 py-3 flex items-center justify-between">
                <div><div class="font-medium text-slate-800 text-sm"><?= e($c['name']) ?></div><div class="text-xs text-slate-400"><?= e($c['tax_number'] ?? '') ?> · <?= e($c['tax_office'] ?? '') ?></div></div>
                <span class="text-xs text-slate-400"><?= e($c['currency']) ?></span>
            </div>
            <?php endforeach; ?>
            <?php if (!$companies): ?><div class="px-5 py-4 text-sm text-slate-400">—</div><?php endif; ?>
        </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 font-semibold text-slate-800 text-sm"><?= e(__('admin.members')) ?> (<?= count($users) ?>)</div>
        <div class="divide-y divide-slate-100">
            <?php foreach ($users as $u): ?>
            <div class="px-5 py-3 flex items-center justify-between">
                <div><div class="font-medium text-slate-800 text-sm"><?= e($u['name']) ?> <?= $u['is_owner'] ? '⭐' : '' ?></div><div class="text-xs text-slate-400"><?= e($u['email']) ?></div></div>
                <span class="text-xs text-slate-500"><?= e(__('admin.status_' . $u['status'])) ?></span>
            </div>
            <?php endforeach; ?>
            <?php if (!$users): ?><div class="px-5 py-4 text-sm text-slate-400">—</div><?php endif; ?>
        </div>
    </div>
</div>
