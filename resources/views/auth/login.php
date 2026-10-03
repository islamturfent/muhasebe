<?php use Muh\Core\Translator; ?>
<div class="max-w-md mx-auto py-14 px-4">
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-8">
        <div class="text-center mb-6">
            <img src="<?= e(asset('assets/img/logo-mark.svg')) ?>" alt="Hesap360" class="w-14 h-14 mx-auto mb-3">
            <h1 class="text-2xl font-bold text-slate-900"><?= e(__('auth.login_title')) ?></h1>
            <p class="text-sm text-slate-500 mt-1"><?= e(__('auth.login_subtitle')) ?></p>
        </div>

        <form method="post" action="<?= e(url('/login')) ?>" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="locale" value="<?= e(Translator::instance()->locale()) ?>">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('auth.email')) ?></label>
                <input type="email" name="email" required class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('auth.password')) ?></label>
                <input type="password" name="password" required class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
            </div>
            <div class="flex items-center justify-between text-sm">
                <label class="flex items-center gap-2 text-slate-600"><input type="checkbox" name="remember" class="rounded"> <?= e(__('auth.remember_me')) ?></label>
                <a href="#" class="text-brand-600 hover:underline"><?= e(__('auth.forgot_password')) ?></a>
            </div>
            <button type="submit" class="w-full px-6 py-3 rounded-xl bg-brand-600 text-white font-semibold hover:bg-brand-700"><?= e(__('auth.login')) ?></button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-500">
            <?= e(__('auth.no_account')) ?> <a href="<?= e(url('/register')) ?>" class="text-brand-600 font-medium hover:underline"><?= e(__('auth.register')) ?></a>
        </p>
    </div>
    <p class="mt-4 text-center text-xs text-slate-400"><?= e(__('common.tagline')) ?></p>
</div>
