<?php
/** @var array $roles @var array $companies @var array $input */
use Muh\Core\Session;
use Muh\Core\Translator;
$errors = Session::get('_form_errors', []);
$locale = Translator::instance()->locale();
$roleLabel = fn (string $key) => __('user.role_' . $key);
?>
<div class="max-w-2xl mx-auto p-6">
    <a href="<?= e(url('/app/users')) ?>" class="text-sm text-brand-600 hover:underline"><?= e(__('user.back_to_users')) ?></a>
    <div class="mt-4 bg-white border border-slate-200 rounded-2xl p-6">
        <h1 class="text-xl font-bold text-slate-900"><?= e(__('user.invite_new')) ?></h1>
        <p class="text-sm text-slate-500 mt-1"><?= e(__('user.invite_company_notice')) ?></p>

        <?php if ($errors): ?>
            <div class="mt-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('/app/users/invite')) ?>" class="mt-6 space-y-5">
            <?= csrf_field() ?>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('user.email')) ?> *</label>
                <input type="email" name="email" required value="<?= e($input['email'] ?? '') ?>" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('user.role')) ?> *</label>
                <select name="role_id" required class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                    <option value=""><?= e(__('user.select_role')) ?></option>
                    <?php foreach ($roles as $r): ?>
                        <?php if ($r['key'] === 'owner') continue; ?>
                        <option value="<?= e($r['id']) ?>" <?= (string) ($input['role_id'] ?? '') === (string) $r['id'] ? 'selected' : '' ?>><?= e($roleLabel((string) $r['key'])) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('user.select_companies')) ?></label>
                <div class="space-y-2 max-h-56 overflow-y-auto border border-slate-200 rounded-lg p-3">
                    <?php if (!$companies): ?><div class="text-sm text-slate-400">—</div><?php endif; ?>
                    <?php foreach ($companies as $c): ?>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="company_ids[]" value="<?= (int) $c['id'] ?>">
                        <?= e($c['name']) ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <button class="w-full px-6 py-3 rounded-xl bg-brand-600 text-white font-semibold hover:bg-brand-700"><?= e(__('user.invite_button')) ?></button>
        </form>
    </div>
</div>
