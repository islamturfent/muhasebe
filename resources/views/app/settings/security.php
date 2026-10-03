<?php
/** @var bool $two_factor_enabled @var string $two_factor_secret @var string $two_factor_qr @var string $email @var array $recent_audit */
use Muh\Core\Auth;
use Muh\Core\Session;
$user = Auth::user();
$qr = Session::get('mfa_pending_qr');
$secret = Session::get('mfa_pending_secret');
?>
<div class="max-w-4xl">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900"><?= e(__('security.title')) ?></h1>
    </div>

    <!-- MFA -->
    <div class="bg-white border border-slate-200 rounded-2xl p-6 mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-slate-800"><?= e(__('security.mfa')) ?></h2>
                <p class="text-sm text-slate-500"><?= e(__('security.mfa_desc')) ?></p>
                <span class="inline-flex mt-2 px-2 py-0.5 rounded-full text-xs <?= $two_factor_enabled ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' ?>">
                    <?= e($two_factor_enabled ? __('security.enabled') : __('security.disabled')) ?>
                </span>
            </div>
            <?php if ($two_factor_enabled): ?>
            <form method="post" action="<?= e(url('/app/settings/security/mfa/disable')) ?>">
                <?= csrf_field() ?>
                <button class="px-4 py-2 rounded-lg border border-red-200 text-red-600 text-sm"><?= e(__('security.disable')) ?></button>
            </form>
            <?php else: ?>
            <form method="post" action="<?= e(url('/app/settings/security/mfa/enable')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="email" value="<?= e($email) ?>">
                <button class="px-4 py-2 rounded-lg bg-brand-600 text-white text-sm"><?= e(__('security.enable')) ?></button>
            </form>
            <?php endif; ?>
        </div>

        <?php if (!$two_factor_enabled && $qr): ?>
        <div class="mt-6 border-t border-slate-100 pt-6 grid md:grid-cols-2 gap-6">
            <div>
                <p class="text-sm text-slate-600 mb-1"><?= e(__('security.secret')) ?></p>
                <code class="block bg-slate-50 border border-slate-200 rounded-lg p-3 text-sm break-all mb-3"><?= e($secret ?? '') ?></code>
                <p class="text-sm text-slate-600 mb-1">otpauth://</p>
                <code class="block bg-slate-50 border border-slate-200 rounded-lg p-3 text-xs break-all"><?= e($qr ?? '') ?></code>
            </div>
            <div>
                <p class="text-sm text-slate-600 mb-2"><?= e(__('security.code')) ?></p>
                <form method="post" action="<?= e(url('/app/settings/security/mfa/verify')) ?>" class="flex gap-2">
                    <?= csrf_field() ?>
                    <input name="code" placeholder="000000" class="flex-1 px-3 py-2 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                    <button class="px-4 py-2 rounded-lg bg-brand-600 text-white text-sm"><?= e(__('security.verify')) ?></button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Recent audit -->
    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
            <span class="font-semibold text-slate-800 text-sm"><?= e(__('security.recent_audit')) ?></span>
            <a href="<?= e(url('/app/audit')) ?>" class="text-xs text-brand-600"><?= e(__('security.audit_link')) ?></a>
        </div>
        <table class="w-full text-sm">
            <thead class="text-left text-xs text-slate-400 uppercase bg-slate-50">
                <tr><th class="px-5 py-2"><?= e(__('audit.when')) ?></th><th class="px-5 py-2"><?= e(__('audit.user')) ?></th><th class="px-5 py-2"><?= e(__('audit.action')) ?></th><th class="px-5 py-2"><?= e(__('audit.module')) ?></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php foreach ($recent_audit as $a): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-5 py-2 text-slate-500"><?= e(format_datetime($a['created_at'])) ?></td>
                    <td class="px-5 py-2 text-slate-700"><?= e($a['user_name'] ?? '—') ?></td>
                    <td class="px-5 py-2 font-mono text-xs text-brand-600"><?= e($a['action']) ?></td>
                    <td class="px-5 py-2 text-slate-500"><?= e($a['module'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
