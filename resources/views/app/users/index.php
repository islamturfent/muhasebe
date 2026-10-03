<?php
/** @var array $users @var array $invites @var array $links @var int $userCount @var int|null $planUsers */
use Muh\Core\Session;
$errors = Session::get('_form_errors', []);
$roleLabel = fn (string $key) => __('user.role_' . $key);
$locale = \Muh\Core\Translator::instance()->locale();
?>
<div class="max-w-5xl mx-auto p-6 space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-slate-900"><?= e(__('user.title')) ?></h1>
            <p class="text-sm text-slate-500"><?= e(__('user.active_users')) ?>: <strong><?= (int) $userCount ?></strong>
                <?php if ($planUsers !== null): ?> / <?= (int) $planUsers ?> <span class="text-slate-400">(<?= e(__('user.plan_users')) ?>)</span><?php endif; ?>
            </p>
        </div>
        <a href="<?= e(url('/app/users/invite')) ?>" class="px-4 py-2 rounded-lg bg-brand-600 text-white text-sm font-medium hover:bg-brand-700">+ <?= e(__('user.invite_new')) ?></a>
    </div>

    <?php if ($planUsers !== null && $userCount >= $planUsers): ?>
        <div class="px-4 py-3 rounded-lg bg-amber-50 border border-amber-200 text-amber-700 text-sm"><?= e(__('user.plan_limit_reached')) ?></div>
    <?php endif; ?>

    <?php if ($errors): ?><div class="px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>

    <!-- Active users -->
    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 text-sm font-semibold text-slate-700"><?= e(__('user.active_users')) ?></div>
        <?php if (!$users): ?>
            <div class="p-5 text-sm text-slate-500"><?= e(__('user.no_users')) ?></div>
        <?php else: ?>
        <table class="w-full text-sm">
            <thead class="text-left text-xs text-slate-400 uppercase border-b border-slate-100">
                <tr>
                    <th class="px-5 py-2.5 font-medium"><?= e(__('user.name')) ?></th>
                    <th class="px-5 py-2.5 font-medium"><?= e(__('user.email')) ?></th>
                    <th class="px-5 py-2.5 font-medium"><?= e(__('user.role')) ?></th>
                    <th class="px-5 py-2.5 font-medium"><?= e(__('user.companies')) ?></th>
                    <th class="px-5 py-2.5 font-medium"><?= e(__('user.actions')) ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($users as $u): ?>
                <tr>
                    <td class="px-5 py-3 font-medium text-slate-800"><?= e($u['name']) ?><?= !empty($u['is_owner']) ? ' <span class="text-xs text-brand-600">(owner)</span>' : '' ?></td>
                    <td class="px-5 py-3 text-slate-500"><?= e($u['email']) ?></td>
                    <td class="px-5 py-3">
                        <?php $rlist = $u['roles'] ? explode(',', $u['roles']) : []; ?>
                        <?php foreach ($rlist as $rk): ?><span class="inline-block px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 text-xs mr-1"><?= e($roleLabel(trim($rk))) ?></span><?php endforeach; ?>
                    </td>
                    <td class="px-5 py-3 text-slate-500"><?= (int) ($links[(int) $u['id']] ?? 0) ?> <?= e(__('user.company_colon')) ?></td>
                    <td class="px-5 py-3"><a href="<?= e(url('/app/users/' . $u['id'])) ?>" class="text-brand-600 hover:underline"><?= e(__('user.view')) ?></a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Pending invitations -->
    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 text-sm font-semibold text-slate-700"><?= e(__('user.pending_invites')) ?></div>
        <?php if (!$invites): ?>
            <div class="p-5 text-sm text-slate-500"><?= e(__('user.no_invites')) ?></div>
        <?php else: ?>
        <table class="w-full text-sm">
            <thead class="text-left text-xs text-slate-400 uppercase border-b border-slate-100">
                <tr>
                    <th class="px-5 py-2.5 font-medium"><?= e(__('user.email')) ?></th>
                    <th class="px-5 py-2.5 font-medium"><?= e(__('user.role')) ?></th>
                    <th class="px-5 py-2.5 font-medium"><?= e(__('user.companies')) ?></th>
                    <th class="px-5 py-2.5 font-medium"><?= e(__('user.status')) ?></th>
                    <th class="px-5 py-2.5 font-medium"><?= e(__('user.invited_at')) ?></th>
                    <th class="px-5 py-2.5 font-medium"><?= e(__('user.actions')) ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($invites as $iv): ?>
                <?php $isPending = $iv['status'] === 'pending'; ?>
                <tr>
                    <td class="px-5 py-3 text-slate-800"><?= e($iv['email']) ?></td>
                    <td class="px-5 py-3 text-slate-500"><?= e($roleLabel((string) $iv['role_key'])) ?></td>
                    <td class="px-5 py-3 text-slate-500"><?= (int) $iv['company_count'] ?></td>
                    <td class="px-5 py-3">
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $iv['status'] === 'pending' ? 'bg-amber-50 text-amber-700' : ($iv['status'] === 'accepted' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500') ?>">
                            <?= e(__('user.status_' . $iv['status'])) ?>
                        </span>
                    </td>
                    <td class="px-5 py-3 text-slate-500"><?= e(\format_date($iv['created_at'])) ?></td>
                    <td class="px-5 py-3">
                        <?php if ($isPending): ?>
                        <form method="post" action="<?= e(url('/app/users/invites/' . $iv['id'] . '/cancel')) ?>" onsubmit="return confirm('<?= e(__('user.cancel')) ?>?')">
                            <?= csrf_field() ?>
                            <button class="text-red-600 hover:underline text-xs"><?= e(__('user.cancel')) ?></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
