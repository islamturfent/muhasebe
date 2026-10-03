<?php
/** @var string $kind @var array $records @var array $companies @var int $companyId */
use Muh\Core\Auth;
use Muh\Core\Translator;
$locale = Translator::instance()->locale();
$isNote = $kind === 'note';
$statuses = ['in_portfolio','banked','collected','endorsed','returned','unpaid','cancelled'];
?>
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-900"><?= e(__('check.title')) ?></h1>
        <div class="flex gap-2 mt-2">
            <a href="<?= e(url('/app/checks')) ?>" class="px-3 py-1.5 rounded-lg text-sm font-medium <?= !$isNote ? 'bg-brand-600 text-white' : 'bg-white border border-slate-200 text-slate-600' ?>"><?= e(__('check.checks')) ?></a>
            <a href="<?= e(url('/app/checks/notes')) ?>" class="px-3 py-1.5 rounded-lg text-sm font-medium <?= $isNote ? 'bg-brand-600 text-white' : 'bg-white border border-slate-200 text-slate-600' ?>"><?= e(__('check.notes')) ?></a>
        </div>
    </div>
    <a href="<?= e(url('/app/checks/' . ($isNote ? 'notes/' : '') . 'create')) ?>" class="px-4 py-2 rounded-lg bg-brand-600 text-white text-sm font-semibold hover:bg-brand-700">+ <?= e(__('check.new')) ?></a>
</div>

<form method="get" action="<?= e(url($isNote ? '/app/checks/notes' : '/app/checks')) ?>" class="mb-4 bg-white border border-slate-200 rounded-2xl p-3 flex gap-2">
    <select name="company_id" class="text-sm border border-slate-200 rounded-lg px-3 py-2 bg-white focus:ring-2 focus:ring-brand-500 outline-none">
        <option value=""><?= e(__('check.company')) ?></option>
        <?php foreach ($companies as $c): ?><option value="<?= e($c['id']) ?>" <?= (int)$companyId===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
    </select>
    <button class="px-4 py-2 rounded-lg bg-brand-600 text-white text-sm font-semibold"><?= e(__('common.search')) ?></button>
</form>

<div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-left text-xs text-slate-400 uppercase bg-slate-50">
                <tr>
                    <th class="px-5 py-3"><?= e(__('check.number')) ?></th>
                    <th class="px-5 py-3"><?= e(__('check.company')) ?></th>
                    <th class="px-5 py-3"><?= e(__('check.account')) ?></th>
                    <th class="px-5 py-3"><?= e(__('check.direction')) ?></th>
                    <th class="px-5 py-3"><?= e(__('check.due_date')) ?></th>
                    <th class="px-5 py-3 text-right"><?= e(__('check.amount')) ?></th>
                    <th class="px-5 py-3"><?= e(__('check.status')) ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php if (!$records): ?><tr><td colspan="7" class="px-5 py-8 text-center text-slate-400"><?= e(__('check.no_records')) ?></td></tr><?php endif; ?>
                <?php foreach ($records as $r): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-5 py-3 font-mono text-brand-600"><?= e($r['check_no'] ?? $r['note_no'] ?? '—') ?></td>
                    <td class="px-5 py-3 text-slate-700"><?= e($r['company_name']) ?></td>
                    <td class="px-5 py-3 text-slate-500"><?= e($r['account_name'] ?? '—') ?></td>
                    <td class="px-5 py-3"><span class="text-xs px-2 py-0.5 rounded-full <?= $r['direction']==='incoming' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' ?>"><?= e(__('check.direction_' . $r['direction'])) ?></span></td>
                    <td class="px-5 py-3 text-slate-500"><?= e(format_date($r['due_date'])) ?></td>
                    <td class="px-5 py-3 text-right font-semibold text-slate-800"><?= e(money($r['amount'])) ?></td>
                    <td class="px-5 py-3">
                        <?php if (Auth::can('check.update')): ?>
                        <form method="post" action="<?= e(url('/app/checks/' . $r['id'] . ($isNote ? '/note' : '/check') . '/status')) ?>" class="inline">
                            <?= csrf_field() ?>
                            <select name="status" onchange="this.form.submit()" class="text-xs border border-slate-200 rounded-lg px-2 py-1 bg-white">
                                <?php foreach ($statuses as $s): ?><option value="<?= $s ?>" <?= $r['status']===$s?'selected':'' ?>><?= e(__('check.status_' . $s)) ?></option><?php endforeach; ?>
                            </select>
                        </form>
                        <?php else: ?>
                        <span class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-600"><?= e(__('check.status_' . $r['status'])) ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= $this->partial('partials.pagination', ['page' => $page ?? 1, 'lastPage' => $lastPage ?? 1, 'total' => $total ?? null]) ?>
</div>
