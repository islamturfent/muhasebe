<?php
/** @var array $user @var array $roles @var array $roleIds @var array $companies @var array $ownedCompanyIds @var array $available */
use Muh\Core\Translator;
$locale = Translator::instance()->locale();
$roleLabel = fn (string $key) => __('user.role_' . $key);
?>
<div class="max-w-3xl mx-auto p-6 space-y-6">
    <a href="<?= e(url('/app/users')) ?>" class="text-sm text-brand-600 hover:underline">← <?= e(__('user.title')) ?></a>

    <div class="bg-white border border-slate-200 rounded-2xl p-6">
        <h1 class="text-xl font-bold text-slate-900"><?= e(__('user.user_detail')) ?></h1>
        <div class="mt-4 grid md:grid-cols-3 gap-4 text-sm">
            <div><div class="text-slate-400"><?= e(__('user.name')) ?></div><div class="font-medium text-slate-800"><?= e($user['name']) ?></div></div>
            <div><div class="text-slate-400"><?= e(__('user.email')) ?></div><div class="font-medium text-slate-800"><?= e($user['email']) ?></div></div>
            <div><div class="text-slate-400"><?= e(__('user.status')) ?></div><div class="font-medium text-emerald-600"><?= e(__('user.status_active')) ?></div></div>
        </div>

        <div class="mt-5">
            <div class="text-sm font-semibold text-slate-700 mb-2"><?= e(__('user.roles')) ?></div>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($roles as $r): ?>
                    <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-700 text-xs font-medium"><?= e($roleLabel((string) $r['key'])) ?></span>
                <?php endforeach; ?>
                <?php if (!$roles): ?><span class="text-sm text-slate-400">—</span><?php endif; ?>
            </div>
        </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl p-6">
        <div class="text-sm font-semibold text-slate-700 mb-3"><?= e(__('user.assigned_companies')) ?></div>
        <?php if (!$companies): ?>
            <div class="text-sm text-slate-400"><?= e(__('user.no_assigned_companies')) ?></div>
        <?php else: ?>
        <ul class="divide-y divide-slate-100">
            <?php foreach ($companies as $c): ?>
            <li class="flex items-center justify-between py-2">
                <span class="text-sm text-slate-700"><?= e($c['name']) ?></span>
                <form method="post" action="<?= e(url('/app/users/' . $user['id'] . '/company/' . $c['id'] . '/remove')) ?>">
                    <?= csrf_field() ?>
                    <button class="text-xs text-red-600 hover:underline"><?= e(__('user.remove')) ?></button>
                </form>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <?php $addable = array_values(array_filter($available, fn ($c) => !in_array((int) $c['id'], $ownedCompanyIds, true))); ?>
        <?php if ($addable): ?>
        <form method="post" action="<?= e(url('/app/users/' . $user['id'] . '/company')) ?>" class="mt-4 flex items-center gap-2">
            <?= csrf_field() ?>
            <select name="company_id" class="flex-1 px-3 py-2 rounded-lg border border-slate-200 text-sm focus:ring-2 focus:ring-brand-500 outline-none">
                <?php foreach ($addable as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
            </select>
            <button class="px-4 py-2 rounded-lg bg-brand-600 text-white text-sm font-medium hover:bg-brand-700"><?= e(__('user.add_company')) ?></button>
        </form>
        <?php endif; ?>
    </div>
</div>
