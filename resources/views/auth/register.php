<?php
use Muh\Core\Session;
use Muh\Core\Translator;
$errors = Session::get('_form_errors', []);
Session::forget('_form_errors');
?>
<div class="max-w-lg mx-auto py-14 px-4">
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-8">
        <div class="text-center mb-6">
            <img src="<?= e(asset('assets/img/logo-mark.svg')) ?>" alt="Hesap360" class="w-14 h-14 mx-auto mb-3">
            <h1 class="text-2xl font-bold text-slate-900"><?= e(__('auth.register_title')) ?></h1>
            <p class="text-sm text-slate-500 mt-1"><?= e(__('auth.register_subtitle')) ?></p>
        </div>

        <form method="post" action="<?= e(url('/register')) ?>" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="locale" value="<?= e(Translator::instance()->locale()) ?>">
            <input type="hidden" name="country" value="TR">

            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('auth.name')) ?></label>
                    <input name="name" required value="<?= e($request->input('name') ?? '') ?>" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('auth.office_name')) ?></label>
                    <input name="office_name" required value="<?= e($request->input('office_name') ?? '') ?>" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                </div>
            </div>

            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('auth.email')) ?></label>
                    <input type="email" name="email" required value="<?= e($request->input('email') ?? '') ?>" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('auth.phone')) ?></label>
                    <input name="phone" value="<?= e($request->input('phone') ?? '') ?>" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                </div>
            </div>

            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('auth.password')) ?></label>
                    <input type="password" name="password" required class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('auth.currency')) ?></label>
                    <select name="currency" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                        <option value="TRY">₺ TRY</option><option value="USD">$ USD</option><option value="EUR">€ EUR</option>
                    </select>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <span class="text-sm text-slate-600"><?= e(__('auth.language')) ?>:</span>
                <label class="flex items-center gap-1.5 text-sm"><input type="radio" name="locale" value="tr" <?= Translator::instance()->locale()==='tr'?'checked':'' ?>> Türkçe</label>
                <label class="flex items-center gap-1.5 text-sm"><input type="radio" name="locale" value="en" <?= Translator::instance()->locale()==='en'?'checked':'' ?>> English</label>
            </div>

            <?php if ($errors): ?>
                <div class="p-3 rounded-lg bg-red-50 border border-red-200 text-sm text-red-700">
                    <?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?>
                </div>
            <?php endif; ?>

            <button type="submit" class="w-full px-6 py-3 rounded-xl bg-brand-600 text-white font-semibold hover:bg-brand-700"><?= e(__('auth.start_free')) ?></button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-500">
            <?= e(__('auth.have_account')) ?> <a href="<?= e(url('/login')) ?>" class="text-brand-600 font-medium hover:underline"><?= e(__('auth.login')) ?></a>
        </p>
    </div>
</div>
