<?php
/** @var array $settings @var array $logLines */
use Muh\Core\Session;
$s = Session::get('success'); Session::forget('success');
$e = Session::get('error'); Session::forget('error');
?>
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900"><?= e(__('mail.title')) ?></h1>
    <p class="text-slate-500"><?= e(__('mail.subtitle')) ?></p>
</div>

<?php if ($s): ?><div class="mb-4 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm"><?= e($s) ?></div><?php endif; ?>
<?php if ($e): ?><div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm"><?= e($e) ?></div><?php endif; ?>

<form method="post" action="<?= e(url('/app/settings/email')) ?>" class="bg-white border border-slate-200 rounded-2xl p-6 space-y-4 max-w-2xl">
    <?= csrf_field() ?>

    <label class="flex items-center gap-3 text-sm text-slate-700 font-medium">
        <input type="checkbox" name="enabled" value="1" <?= !empty($settings['enabled']) ? 'checked' : '' ?> class="w-4 h-4 text-brand-600"> <?= e(__('mail.enabled')) ?>
    </label>

    <div class="pt-2 border-t border-slate-100">
        <h3 class="font-semibold text-slate-800 text-sm mb-3"><?= e(__('mail.smtp')) ?></h3>
        <div class="grid grid-cols-3 gap-3">
            <div class="col-span-2"><label class="block text-xs text-slate-500 mb-1"><?= e(__('mail.host')) ?></label>
                <input name="host" value="<?= e($settings['host']) ?>" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm"></div>
            <div><label class="block text-xs text-slate-500 mb-1"><?= e(__('mail.port')) ?></label>
                <input name="port" value="<?= e($settings['port']) ?>" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm"></div>
        </div>
        <div class="grid grid-cols-2 gap-3 mt-3">
            <div><label class="block text-xs text-slate-500 mb-1"><?= e(__('mail.username')) ?></label>
                <input name="username" value="<?= e($settings['username']) ?>" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm"></div>
            <div><label class="block text-xs text-slate-500 mb-1"><?= e(__('mail.password')) ?></label>
                <input type="password" name="password" value="" autocomplete="new-password" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm">
                <p class="text-[11px] text-slate-400 mt-0.5"><?= e(__('mail.password_help')) ?></p></div>
        </div>
        <div class="grid grid-cols-3 gap-3 mt-3">
            <div><label class="block text-xs text-slate-500 mb-1"><?= e(__('mail.encryption')) ?></label>
                <select name="encryption" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm">
                    <?php foreach (['tls', 'ssl', 'none'] as $enc): ?><option value="<?= $enc ?>" <?= $settings['encryption'] === $enc ? 'selected' : '' ?>><?= e($enc) ?></option><?php endforeach; ?>
                </select></div>
            <div><label class="block text-xs text-slate-500 mb-1"><?= e(__('mail.from_email')) ?></label>
                <input name="from_email" value="<?= e($settings['from_email']) ?>" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm"></div>
            <div><label class="block text-xs text-slate-500 mb-1"><?= e(__('mail.from_name')) ?></label>
                <input name="from_name" value="<?= e($settings['from_name']) ?>" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm"></div>
        </div>
    </div>

    <div class="pt-2 border-t border-slate-100">
        <h3 class="font-semibold text-slate-800 text-sm mb-2"><?= e(__('mail.notify_types')) ?></h3>
        <?php foreach (['notify_due','notify_stock','notify_efatura','notify_user'] as $t): ?>
        <label class="flex items-center gap-2 text-sm text-slate-600 py-0.5">
            <input type="checkbox" name="<?= $t ?>" value="1" <?= !empty($settings[$t]) ? 'checked' : '' ?> class="w-4 h-4 text-brand-600"> <?= e(__('mail.' . $t)) ?>
        </label>
        <?php endforeach; ?>
    </div>

    <button class="px-6 py-2.5 rounded-xl bg-brand-600 text-white font-semibold"><?= e(__('mail.save')) ?></button>
</form>

<div class="max-w-2xl mt-6">
    <div class="bg-white border border-slate-200 rounded-2xl p-6">
        <h3 class="font-semibold text-slate-800 mb-3"><?= e(__('mail.send_test')) ?></h3>
        <form method="post" action="<?= e(url('/app/settings/email/test')) ?>" class="flex items-center gap-2">
            <?= csrf_field() ?>
            <input name="test_email" placeholder="<?= e(__('mail.test_email')) ?>" class="flex-1 px-3 py-2 rounded-lg border border-slate-200 text-sm">
            <button class="px-4 py-2 rounded-lg bg-brand-50 text-brand-700 text-sm font-medium hover:bg-brand-100"><?= e(__('mail.send_test')) ?></button>
        </form>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl mt-4 overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 font-semibold text-slate-800 text-sm"><?= e(__('mail.log')) ?></div>
        <?php if ($logLines): ?>
        <pre class="p-4 text-xs text-slate-500 overflow-auto max-h-64 whitespace-pre-wrap"><?= e(implode("\n", $logLines)) ?></pre>
        <?php else: ?>
        <div class="px-5 py-4 text-sm text-slate-400"><?= e(__('mail.no_log')) ?></div>
        <?php endif; ?>
    </div>
</div>
