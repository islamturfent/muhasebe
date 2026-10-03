<?php
/** @var array $notifications */
use Muh\Core\Translator;
use Muh\Core\Auth;
$locale = Translator::instance()->locale();
$levels = ['info'=>'bg-brand-50 text-brand-700','warning'=>'bg-amber-50 text-amber-700','success'=>'bg-emerald-50 text-emerald-700','danger'=>'bg-red-50 text-red-700'];
function timeAgo($dt, $locale): string {
    if (!$dt) return '';
    $secs = time() - strtotime($dt);
    if ($secs < 60) return __('notification.now');
    if ($secs < 3600) return __('notification.minutes_ago', ['n' => (int) floor($secs / 60)]);
    if ($secs < 86400) return __('notification.hours_ago', ['n' => (int) floor($secs / 3600)]);
    return __('notification.days_ago', ['n' => (int) floor($secs / 86400)]);
}
?>
<div class="mb-6 flex items-center justify-between">
    <h1 class="text-2xl font-bold text-slate-900"><?= e(__('notification.title')) ?></h1>
    <form method="post" action="<?= e(url('/app/notifications/mark-all-read')) ?>">
        <?= csrf_field() ?>
        <button class="px-4 py-2 rounded-lg border border-slate-200 text-sm text-slate-600 hover:bg-slate-50"><?= e(__('notification.mark_all_read')) ?></button>
    </form>
</div>

<div class="space-y-3">
    <?php if (!$notifications): ?><div class="bg-white border border-slate-200 rounded-2xl p-10 text-center text-slate-400"><?= e(__('notification.empty')) ?></div><?php endif; ?>
    <?php foreach ($notifications as $n): ?>
    <div class="flex items-start gap-3 bg-white border border-slate-200 rounded-2xl p-4 <?= $n['is_read'] ? 'opacity-70' : '' ?>">
        <span class="w-9 h-9 rounded-lg flex items-center justify-center text-xs font-bold <?= $levels[$n['level']] ?? $levels['info'] ?>"><?= e(mb_strtoupper(mb_substr($n['type'], 0, 2))) ?></span>
        <div class="flex-1 min-w-0">
            <div class="font-semibold text-slate-800 text-sm"><?= e($n['title']) ?></div>
            <?php if ($n['body']): ?><p class="text-sm text-slate-500 mt-0.5"><?= e($n['body']) ?></p><?php endif; ?>
            <div class="text-xs text-slate-400 mt-1">
                <?= e(timeAgo($n['created_at'], $locale)) ?>
                <?php if ($n['action_url']): ?> · <a href="<?= e(url($n['action_url'])) ?>" class="text-brand-600"><?= e(__('common.details')) ?></a><?php endif; ?>
            </div>
        </div>
        <?php if (!$n['is_read']): ?>
        <form method="post" action="<?= e(url('/app/notifications/' . $n['id'] . '/read')) ?>">
            <?= csrf_field() ?>
            <button class="text-xs text-brand-600"><?= e(__('notification.mark_read')) ?></button>
        </form>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
