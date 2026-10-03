<?php
/** @var array $user @var array $roles @var bool $mfaEnabled @var array $errors */
use Muh\Core\Session;
$roleLabel = fn (string $key) => __('user.role_' . $key);
?>
<div class="max-w-2xl mx-auto p-6">
    <h1 class="text-2xl font-bold text-slate-900"><?= e(__('profile.title')) ?></h1>

    <!-- Account summary -->
    <div class="mt-4 bg-white border border-slate-200 rounded-2xl p-6 flex items-center gap-4">
        <span class="w-14 h-14 rounded-xl bg-brand-100 text-brand-700 flex items-center justify-center text-2xl font-bold"><?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></span>
        <div>
            <div class="text-lg font-bold text-slate-900"><?= e($user['name']) ?></div>
            <div class="text-sm text-slate-500"><?= e($user['email']) ?></div>
            <div class="mt-1 flex flex-wrap gap-1">
                <?php foreach ($roles as $r): ?><span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 text-xs"><?= e($roleLabel((string) $r['key'])) ?></span><?php endforeach; ?>
                <?php if ($mfaEnabled): ?><span class="px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-xs">2FA ✅</span><?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($errors): ?><div class="mt-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>

    <!-- Edit form -->
    <form method="post" action="<?= e(url('/app/profile')) ?>" class="mt-6 bg-white border border-slate-200 rounded-2xl p-6 space-y-5">
        <?= csrf_field() ?>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('profile.full_name')) ?> *</label>
            <input name="name" required value="<?= e($user['name']) ?>" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('profile.phone')) ?></label>
            <input name="phone" value="<?= e($user['phone'] ?? '') ?>" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
        </div>

        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('profile.language')) ?></label>
                <select name="locale" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                    <option value="tr" <?= $user['locale']==='tr'?'selected':'' ?>>Türkçe</option>
                    <option value="en" <?= $user['locale']==='en'?'selected':'' ?>>English</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('profile.currency')) ?></label>
                <select name="currency" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                    <option value="TRY" <?= $user['currency']==='TRY'?'selected':'' ?>>₺ TRY</option>
                    <option value="USD" <?= $user['currency']==='USD'?'selected':'' ?>>$ USD</option>
                    <option value="EUR" <?= $user['currency']==='EUR'?'selected':'' ?>>€ EUR</option>
                    <option value="GBP" <?= $user['currency']==='GBP'?'selected':'' ?>>£ GBP</option>
                </select>
            </div>
        </div>

        <div class="text-sm text-slate-500">
            <span class="font-medium text-slate-700"><?= e(__('profile.mfa')) ?>:</span>
            <?php if ($mfaEnabled): ?><?= e(__('profile.mfa_on')) ?><?php else: ?><?= e(__('profile.mfa_off')) ?><?php endif; ?>
            <a href="<?= e(url('/app/settings/security')) ?>" class="text-brand-600 hover:underline ml-1"><?= e(__('profile.mfa_manage')) ?></a>
        </div>

        <button class="w-full px-6 py-3 rounded-xl bg-brand-600 text-white font-semibold hover:bg-brand-700"><?= e(__('common.save')) ?></button>
    </form>
</div>
