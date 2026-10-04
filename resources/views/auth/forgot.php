<?php ?>
<div class="max-w-md w-full mx-auto mt-10 p-8 bg-white border border-slate-200 rounded-2xl shadow-sm">
    <h1 class="text-xl font-bold text-slate-900 mb-1"><?= e(__('auth.forgot_title')) ?></h1>
    <p class="text-sm text-slate-500 mb-6"><?= e(__('auth.forgot_subtitle')) ?></p>

    <?php if ($err = \Muh\Core\Session::getFlash('error')): ?><div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm"><?= e($err) ?></div><?php endif; ?>
    <?php if ($s = \Muh\Core\Session::getFlash('success')): ?><div class="mb-4 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm"><?= e($s) ?></div><?php endif; ?>

    <form method="post" action="<?= e(url('/forgot-password')) ?>" class="space-y-4">
        <?= csrf_field() ?>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('auth.email')) ?></label>
            <input type="email" name="email" required class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
        </div>
        <button type="submit" class="w-full px-6 py-3 rounded-xl bg-brand-600 text-white font-semibold hover:bg-brand-700"><?= e(__('auth.send_reset_link')) ?></button>
    </form>
    <p class="mt-4 text-center text-sm text-slate-500">
        <a href="<?= e(url('/login')) ?>" class="text-brand-600 font-medium hover:underline">← <?= e(__('auth.login')) ?></a>
    </p>
</div>
