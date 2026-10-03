<?php
/** @var array $invite @var array|null $office @var bool $exists @var array $errors */
use Muh\Core\Translator;
use Muh\Services\UserInviteService;
$locale = Translator::instance()->locale();
$roleId = (int) $invite['role_id'];
$roleKey = \Muh\Core\DB::scalar('SELECT `key` FROM roles WHERE id = :id', ['id' => $roleId]) ?: 'viewer';
$companies = (new UserInviteService())->inviteCompanies((int) $invite['id']);
?>
<div class="max-w-md mx-auto">
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-8">
        <div class="flex items-center gap-2 mb-4">
            <img src="<?= e(asset('assets/img/logo-mark.svg')) ?>" alt="Hesap360" class="w-9 h-9">
            <span class="font-bold text-lg text-slate-900">Hesap360</span>
        </div>

        <h1 class="text-xl font-bold text-slate-900"><?= e(__('user.accept_title')) ?></h1>
        <p class="text-sm text-slate-500 mt-1"><?= e(__('user.accept_text', ['office' => $office['name'] ?? ''])); ?></p>

        <div class="mt-4 text-sm text-slate-600 bg-slate-50 border border-slate-100 rounded-lg p-3">
            <div><?= e(__('user.email')) ?>: <strong><?= e($invite['email']) ?></strong></div>
            <div class="mt-1"><?= e(__('user.role')) ?>: <strong><?= e(__('user.role_' . $roleKey)) ?></strong></div>
            <?php if ($companies): ?>
            <div class="mt-1"><?= e(__('user.companies')) ?>:
                <?php foreach ($companies as $c): ?><span class="inline-block px-2 py-0.5 rounded bg-brand-50 text-brand-700 text-xs mr-1"><?= e($c['name']) ?></span><?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($errors): ?><div class="mt-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>

        <form method="post" action="<?= e(url('/invite/accept')) ?>" class="mt-5 space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= e($invite['token']) ?>">

            <?php if (!$exists): ?>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('user.your_name')) ?> *</label>
                    <input name="name" required class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('user.set_password')) ?> *</label>
                    <input type="password" name="password" required minlength="8" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                </div>
            <?php else: ?>
                <p class="text-sm text-slate-500 bg-emerald-50 border border-emerald-100 rounded-lg p-3"><?= e(__('user.account_exists_text')) ?></p>
            <?php endif; ?>

            <button class="w-full px-6 py-3 rounded-xl bg-brand-600 text-white font-semibold hover:bg-brand-700"><?= e(__('user.accept_button')) ?></button>
        </form>
    </div>
</div>
