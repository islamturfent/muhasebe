<?php
/** @var array $tenants @var string $search */
use Muh\Core\Translator;
?>
<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-slate-900"><?= e(__('admin.tenants')) ?></h1>
        <form method="get" action="<?= e(url('/admin/tenants')) ?>" class="flex gap-2">
            <input name="search" value="<?= e($search) ?>" placeholder="<?= e(__('admin.search_tenants')) ?>" class="px-3 py-2 rounded-lg border border-slate-200 text-sm focus:ring-2 focus:ring-brand-500 outline-none">
            <button class="px-4 py-2 rounded-lg bg-brand-600 text-white text-sm font-medium"><?= e(__('common.search')) ?></button>
        </form>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
        <table class="w-full text-sm">
            <thead class="text-left text-xs text-slate-400 uppercase border-b border-slate-100">
                <tr>
                    <th class="px-5 py-2.5"><?= e(__('admin.tenant')) ?></th>
                    <th class="px-5 py-2.5"><?= e(__('admin.email')) ?></th>
                    <th class="px-5 py-2.5"><?= e(__('admin.firms')) ?></th>
                    <th class="px-5 py-2.5"><?= e(__('admin.members')) ?></th>
                    <th class="px-5 py-2.5"><?= e(__('admin.status')) ?></th>
                    <th class="px-5 py-2.5"><?= e(__('admin.actions')) ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($tenants as $t): ?>
                <tr>
                    <td class="px-5 py-3 font-medium text-slate-800"><?= e($t['name']) ?></td>
                    <td class="px-5 py-3 text-slate-500"><?= e($t['email']) ?></td>
                    <td class="px-5 py-3 text-slate-600"><?= (int) $t['companies'] ?></td>
                    <td class="px-5 py-3 text-slate-600"><?= (int) $t['members'] ?></td>
                    <td class="px-5 py-3"><span class="px-2 py-0.5 rounded-full text-xs <?= $t['status']==='active'?'bg-emerald-50 text-emerald-700':'bg-red-50 text-red-600' ?>"><?= e(__('admin.status_' . $t['status'])) ?></span></td>
                    <td class="px-5 py-3 whitespace-nowrap">
                        <a href="<?= e(url('/admin/tenants/' . $t['id'])) ?>" class="text-brand-600 hover:underline mr-3"><?= e(__('admin.view')) ?></a>
                        <form method="post" action="<?= e(url('/admin/tenants/' . $t['id'] . '/toggle')) ?>" class="inline">
                            <?= csrf_field() ?>
                            <button class="text-xs <?= $t['status']==='active' ? 'text-red-600' : 'text-emerald-600' ?> hover:underline"><?= e(__('admin.' . ($t['status']==='active' ? 'suspend' : 'activate'))) ?></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$tenants): ?><tr><td colspan="6" class="px-5 py-8 text-center text-slate-400"><?= e(__('admin.no_tenants')) ?></td></tr><?php endif; ?>
            </tbody>
        </table>
        <?= $this->partial('partials.pagination', ['page' => $page ?? 1, 'lastPage' => $lastPage ?? 1, 'total' => $total ?? null]) ?>
    </div>
</div>
