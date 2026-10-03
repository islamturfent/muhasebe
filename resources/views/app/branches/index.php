<?php
/** @var array $branches @var array $companies @var int $companyId */
use Muh\Core\Session;
$errors = Session::get('_form_errors', []);
?>
<div class="max-w-4xl mx-auto p-6 space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold text-slate-900"><?= e(__('branch.title')) ?></h1>
        <div class="flex items-center gap-2">
            <form method="get" action="<?= e(url('/app/branches')) ?>" class="flex items-center gap-2">
                <select name="company_id" onchange="this.form.submit()" class="px-3 py-2 rounded-lg border border-slate-200 text-sm bg-white focus:ring-2 focus:ring-brand-500 outline-none">
                    <option value=""><?= e(__('branch.all_companies')) ?></option>
                    <?php foreach ($companies as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) $companyId === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
                </select>
            </form>
            <a href="<?= e(url('/app/branches/create?company_id=' . $companyId)) ?>" class="px-4 py-2 rounded-lg bg-brand-600 text-white text-sm font-medium hover:bg-brand-700"><?= e(__('branch.new')) ?></a>
        </div>
    </div>

    <?php if ($errors): ?><div class="px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>

    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
        <?php if (!$branches): ?>
            <div class="p-6 text-sm text-slate-500"><?= e(__('branch.no_branches')) ?></div>
        <?php else: ?>
        <table class="w-full text-sm">
            <thead class="text-left text-xs text-slate-400 uppercase border-b border-slate-100">
                <tr>
                    <th class="px-5 py-2.5 font-medium"><?= e(__('branch.name')) ?></th>
                    <th class="px-5 py-2.5 font-medium"><?= e(__('branch.company')) ?></th>
                    <th class="px-5 py-2.5 font-medium"><?= e(__('branch.city')) ?></th>
                    <th class="px-5 py-2.5 font-medium"><?= e(__('branch.phone')) ?></th>
                    <th class="px-5 py-2.5 font-medium"><?= e(__('branch.actions')) ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($branches as $b): ?>
                <tr>
                    <td class="px-5 py-3 font-medium text-slate-800"><?= e($b['name']) ?></td>
                    <td class="px-5 py-3">
                        <a href="<?= e(url('/app/companies/' . $b['company_id'])) ?>" class="text-brand-600 hover:underline"><?= e($b['company_name']) ?></a>
                    </td>
                    <td class="px-5 py-3 text-slate-500"><?= e($b['city'] ?? '—') ?></td>
                    <td class="px-5 py-3 text-slate-500"><?= e($b['phone'] ?? '—') ?></td>
                    <td class="px-5 py-3">
                        <form method="post" action="<?= e(url('/app/branches/' . $b['id'] . '/delete')) ?>" onsubmit="return confirm('<?= e(__('branch.delete')) ?>?')">
                            <?= csrf_field() ?>
                            <button class="text-xs text-red-600 hover:underline"><?= e(__('branch.delete')) ?></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
