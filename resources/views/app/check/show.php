<?php
/** @var string $kind @var array $record @var array $statuses */
use Muh\Core\Auth;
$isNote = $kind === 'note';
$statusBadge = [
    'in_portfolio' => 'bg-blue-50 text-blue-700',
    'banked'       => 'bg-amber-50 text-amber-700',
    'collected'    => 'bg-emerald-50 text-emerald-700',
    'endorsed'     => 'bg-violet-50 text-violet-700',
    'returned'     => 'bg-slate-100 text-slate-600',
    'unpaid'       => 'bg-red-50 text-red-600',
    'cancelled'    => 'bg-slate-100 text-slate-400',
];
$due = $record['due_date'] ? strtotime($record['due_date']) : null;
$days = $due ? (int) floor(($due - time()) / 86400) : null;
?>
<div class="max-w-4xl">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <a href="<?= e(url($isNote ? '/app/checks/notes' : '/app/checks')) ?>" class="text-sm text-brand-600 hover:underline">← <?= e(__('check.title')) ?></a>
            <h1 class="text-2xl font-bold text-slate-900 mt-1"><?= e($isNote ? __('check.type_note') : __('check.type_check')) ?> · <?= e($record[$isNote ? 'note_no' : 'check_no'] ?: '—') ?></h1>
            <p class="text-slate-500"><?= e($record['company_name']) ?> · <?= e($record['account_name'] ?? '—') ?></p>
        </div>
        <div class="text-right">
            <div class="text-xs uppercase text-slate-400"><?= e(__('check.amount')) ?></div>
            <div class="text-2xl font-bold text-slate-900"><?= e(money($record['amount'])) ?></div>
        </div>
    </div>
    <?php if (!empty($record['posted_at'])): ?>
    <div class="mb-4 px-4 py-2 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm"><?= e(__('check.fin_posted')) ?> · <?= e(format_date(substr((string) $record['posted_at'], 0, 10))) ?></div>
    <?php endif; ?>

    <div class="mb-6 grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="rounded-2xl bg-white border border-slate-200 p-5">
            <div class="text-xs text-slate-400 uppercase"><?= e(__('check.status')) ?></div>
            <span class="mt-1 inline-block text-xs px-2 py-1 rounded-full <?= e($statusBadge[$record['status']] ?? 'bg-slate-100 text-slate-600') ?>"><?= e(__('check.status_' . $record['status'])) ?></span>
        </div>
        <div class="rounded-2xl bg-white border border-slate-200 p-5">
            <div class="text-xs text-slate-400 uppercase"><?= e(__('check.direction')) ?></div>
            <div class="mt-1 font-semibold <?= $record['direction']==='incoming' ? 'text-emerald-600' : 'text-amber-600' ?>"><?= e(__('check.direction_' . $record['direction'])) ?></div>
        </div>
        <div class="rounded-2xl bg-white border border-slate-200 p-5">
            <div class="text-xs text-slate-400 uppercase"><?= e(__('check.due_date')) ?></div>
            <div class="mt-1 font-semibold text-slate-800"><?= e(format_date($record['due_date'])) ?></div>
            <?php if ($days !== null): ?>
            <div class="mt-1 text-xs <?= $days < 0 ? 'text-red-600' : ($days <= 7 ? 'text-amber-600' : 'text-slate-400') ?>">
                <?php if ($days < 0): ?><?= e(__('check.due_overdue')) ?> (<?= e(abs($days)) ?> <?= e(__('check.due_days')) ?>)<?php elseif ($days <= 1): ?><?= e(__('check.due_today')) ?><?php else: ?><?= e($days) ?> <?= e(__('check.due_days')) ?><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="rounded-2xl bg-white border border-slate-200 p-5">
            <div class="text-xs text-slate-400 uppercase"><?= e(__('check.issue_date')) ?></div>
            <div class="mt-1 font-semibold text-slate-800"><?= e(format_date($record['issue_date'])) ?></div>
        </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1 bg-white border border-slate-200 rounded-2xl p-5 space-y-3 h-fit">
            <h3 class="font-semibold text-slate-800 mb-2"><?= e(__('common.details')) ?></h3>
            <?php foreach ([
                ['check.company', $record['company_name'] ?? '—'],
                ['check.account', trim(($record['account_code'] ?? '') . ' ' . ($record['account_name'] ?? '')) ?: '—'],
                ['check.bank', $record['bank'] ?: '—'],
                ['check.number', $record[$isNote ? 'note_no' : 'check_no'] ?: '—'],
            ] as [$k, $val]): ?>
            <div class="flex justify-between text-sm">
                <span class="text-slate-400"><?= e(__($k)) ?></span>
                <span class="font-medium text-slate-700 text-right"><?= e($val) ?></span>
            </div>
            <?php endforeach; ?>
            <?php if ($record['notes']): ?>
            <div class="pt-2 border-t border-slate-100 text-sm text-slate-500"><?= e($record['notes']) ?></div>
            <?php endif; ?>
        </div>

        <div class="lg:col-span-2 bg-white border border-slate-200 rounded-2xl p-5">
            <h3 class="font-semibold text-slate-800 mb-1"><?= e(__('check.update_status')) ?></h3>
            <p class="text-xs text-slate-400 mb-4"><?= e(__('check.lifecycle_hint')) ?></p>
            <form method="post" action="<?= e(url('/app/checks/' . $record['id'] . ($isNote ? '/note' : '/check') . '/status')) ?>" class="flex flex-wrap items-end gap-2">
                <?= csrf_field() ?>
                <select name="status" class="text-sm border border-slate-200 rounded-lg px-3 py-2 bg-white focus:ring-2 focus:ring-brand-500 outline-none">
                    <?php foreach ($statuses as $s): ?><option value="<?= e($s) ?>" <?= $record['status']===$s?'selected':'' ?>><?= e(__('check.status_' . $s)) ?></option><?php endforeach; ?>
                </select>
                <button class="px-4 py-2 rounded-lg bg-brand-600 text-white text-sm font-semibold hover:bg-brand-700"><?= e(__('common.save')) ?></button>
            </form>
        </div>
    </div>
</div>
