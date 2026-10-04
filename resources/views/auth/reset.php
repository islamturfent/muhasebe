<?php
/** @var string $token @var string $email */
?>
<div class="max-w-md w-full mx-auto mt-10 p-8 bg-white border border-slate-200 rounded-2xl shadow-sm">
    <h1 class="text-xl font-bold text-slate-900 mb-1"><?= e(__('auth.reset_title')) ?></h1>
    <p class="text-sm text-slate-500 mb-6"><?= e(__('auth.reset_subtitle')) ?></p>

    <?php if ($err = \Muh\Core\Session::getFlash('error')): ?><div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm"><?= e($err) ?></div><?php endif; ?>

    <form method="post" action="<?= e(url('/reset-password')) ?>" class="space-y-4">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <input type="hidden" name="email" value="<?= e($email) ?>">
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('auth.new_password')) ?></label>
            <input type="password" name="password" required class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('auth.confirm_password')) ?></label>
            <input type="password" name="password_confirmation" required class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
        </div>
        <button type="submit" class="w-full px-6 py-3 rounded-xl bg-brand-600 text-white font-semibold hover:bg-brand-700"><?= e(__('auth.reset_button')) ?></button>
    </form>
</div>
