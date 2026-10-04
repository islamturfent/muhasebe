<?php
/** @var string $activeTab @var array $policy */
?>
<div class="max-w-2xl mx-auto space-y-6">
    <h1 class="text-2xl font-bold text-slate-900"><?= e(__('admin.settings_title')) ?></h1>
    <p class="text-slate-500 -mt-4"><?= e(__('admin.settings_subtitle')) ?></p>

    <?= $this->partial('admin._settings_nav', ['activeTab' => $activeTab]) ?>

    <form method="post" action="<?= e(url('/admin/settings/security')) ?>" class="bg-white border border-slate-200 rounded-2xl p-6 space-y-3">
        <?= csrf_field() ?>

        <label class="flex items-center gap-3 p-4 rounded-xl border border-slate-200 cursor-pointer">
            <input type="checkbox" name="require_email_verify" value="1" <?= !empty($policy['require_email_verify']) ? 'checked' : '' ?> class="w-5 h-5">
            <div>
                <div class="font-semibold text-slate-800"><?= e(__('admin.policy_verify')) ?></div>
                <div class="text-sm text-slate-500"><?= e(__('admin.policy_verify_hint')) ?></div>
            </div>
        </label>

        <label class="flex items-center gap-3 p-4 rounded-xl border border-slate-200 cursor-pointer">
            <input type="checkbox" name="require_2fa" value="1" <?= !empty($policy['require_2fa']) ? 'checked' : '' ?> class="w-5 h-5">
            <div>
                <div class="font-semibold text-slate-800"><?= e(__('admin.policy_2fa')) ?></div>
                <div class="text-sm text-slate-500"><?= e(__('admin.policy_2fa_hint')) ?></div>
            </div>
        </label>

        <label class="flex items-center gap-3 p-4 rounded-xl border border-slate-200 cursor-pointer">
            <input type="checkbox" name="rate_limit_webhooks" value="1" <?= !empty($policy['rate_limit_webhooks']) ? 'checked' : '' ?> class="w-5 h-5">
            <div>
                <div class="font-semibold text-slate-800"><?= e(__('admin.policy_webhook')) ?></div>
                <div class="text-sm text-slate-500"><?= e(__('admin.policy_webhook_hint')) ?></div>
            </div>
        </label>

        <button class="w-full px-6 py-3 rounded-xl bg-brand-600 text-white font-semibold hover:bg-brand-700"><?= e(__('common.save')) ?></button>
    </form>
</div>
