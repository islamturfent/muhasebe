<?php
/** @var array $admins @var array $promotable @var array $errors */
?>
<div class="max-w-4xl mx-auto space-y-6">
    <h1 class="text-2xl font-bold text-slate-900"><?= e(__('admin.admins')) ?></h1>
    <p class="text-slate-500 -mt-4"><?= e(__('admin.admins_hint')) ?></p>

    <div class="grid md:grid-cols-2 gap-6">
        <!-- Create a new system admin -->
        <div class="bg-white border border-slate-200 rounded-2xl p-6">
            <h2 class="font-semibold text-slate-900 mb-4"><?= e(__('admin.create_admin')) ?></h2>
            <?php if ($errors): ?><div class="mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>
            <form method="post" action="<?= e(url('/admin/admins/create')) ?>" class="space-y-4">
                <?= csrf_field() ?>
                <div><label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('user.name')) ?></label><input name="name" required class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none"></div>
                <div><label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('user.email')) ?></label><input type="email" name="email" required class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none"></div>
                <div><label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('user.set_password')) ?></label><input type="password" name="password" required minlength="8" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none"></div>
                <button class="w-full px-4 py-3 rounded-xl bg-brand-600 text-white font-semibold hover:bg-brand-700"><?= e(__('admin.create_admin_btn')) ?></button>
            </form>
        </div>

        <!-- Promote an existing user -->
        <div class="bg-white border border-slate-200 rounded-2xl p-6">
            <h2 class="font-semibold text-slate-900 mb-4"><?= e(__('admin.promote_admin')) ?></h2>
            <form method="post" action="<?= e(url('/admin/admins/promote')) ?>" class="space-y-4">
                <?= csrf_field() ?>
                <select name="user_id" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                    <option value=""><?= e(__('admin.select_user')) ?></option>
                    <?php foreach ($promotable as $u): ?><option value="<?= (int) $u['id'] ?>"><?= e($u['name']) ?> · <?= e($u['email']) ?></option><?php endforeach; ?>
                </select>
                <button class="w-full px-4 py-3 rounded-xl bg-brand-600 text-white font-semibold hover:bg-brand-700"><?= e(__('admin.promote_btn')) ?></button>
            </form>
        </div>
    </div>

    <!-- Current system admins -->
    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 font-semibold text-slate-800 text-sm"><?= e(__('admin.active_admins')) ?> (<?= count($admins) ?>)</div>
        <div class="divide-y divide-slate-100">
            <?php foreach ($admins as $a): ?>
            <div class="px-5 py-3 flex items-center justify-between">
                <div>
                    <div class="font-medium text-slate-800 text-sm"><?= e($a['name']) ?></div>
                    <div class="text-xs text-slate-400"><?= e($a['email']) ?></div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="px-2 py-0.5 rounded-full bg-brand-50 text-brand-700 text-xs font-medium"><?= e(__('admin.super_admin')) ?></span>
                    <form method="post" action="<?= e(url('/admin/admins/' . $a['id'] . '/revoke')) ?>" onsubmit="return confirm('<?= e(__('admin.revoke_confirm')) ?>?')">
                        <?= csrf_field() ?>
                        <button class="text-xs text-red-600 hover:underline"><?= e(__('admin.revoke')) ?></button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (!$admins): ?><div class="px-5 py-4 text-sm text-slate-400"><?= e(__('admin.no_admins')) ?></div><?php endif; ?>
        </div>
    </div>
</div>
