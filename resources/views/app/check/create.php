<?php
/** @var string $kind @var array $companies @var int $selectedCompany @var array $accounts */
use Muh\Core\Session;
use Muh\Core\Translator;
$locale = Translator::instance()->locale();
$errors = Session::get('_form_errors', []);
Session::forget('_form_errors');
$isNote = $kind === 'note';
?>
<div class="max-w-xl">
    <div class="mb-6">
        <a href="<?= e(url($isNote ? '/app/checks/notes' : '/app/checks')) ?>" class="text-sm text-brand-600 hover:underline">← <?= e(__('check.' . ($isNote ? 'notes' : 'checks'))) ?></a>
        <h1 class="text-2xl font-bold text-slate-900 mt-1"><?= e(__('check.new')) ?> — <?= e(__('check.' . ($isNote ? 'type_note' : 'type_check'))) ?></h1>
    </div>

    <form method="post" action="<?= e(url($isNote ? '/app/checks/notes' : '/app/checks')) ?>" class="bg-white border border-slate-200 rounded-2xl p-6 space-y-4">
        <?= csrf_field() ?>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('check.company')) ?> *</label>
                <select name="company_id" required class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                    <?php foreach ($companies as $c): ?><option value="<?= e($c['id']) ?>" <?= (int)$selectedCompany===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('check.account')) ?></label>
                <select name="current_account_id" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                    <option value="">—</option>
                    <?php foreach ($accounts as $a): ?><option value="<?= e($a['id']) ?>"><?= e($a['code']) ?> · <?= e($a['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('check.direction')) ?> *</label>
            <div class="flex gap-4">
                <label class="flex items-center gap-2"><input type="radio" name="direction" value="incoming" checked> <?= e(__('check.direction_incoming')) ?></label>
                <label class="flex items-center gap-2"><input type="radio" name="direction" value="outgoing"> <?= e(__('check.direction_outgoing')) ?></label>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('check.number')) ?></label>
                <input name="number" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('check.bank')) ?></label>
                <input name="bank" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
            </div>
        </div>

        <div class="grid grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('check.issue_date')) ?></label>
                <input type="date" name="issue_date" value="<?= date('Y-m-d') ?>" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('check.due_date')) ?> *</label>
                <input type="date" name="due_date" required class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('check.amount')) ?> *</label>
                <input type="number" step="0.01" name="amount" required class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('check.notes_text')) ?></label>
            <textarea name="notes_text" rows="2" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none"></textarea>
        </div>

        <?php if ($errors): ?><div class="p-3 rounded-lg bg-red-50 border border-red-200 text-sm text-red-700"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>

        <button class="w-full px-6 py-3 rounded-xl bg-brand-600 text-white font-semibold hover:bg-brand-700"><?= e(__('common.save')) ?></button>
    </form>
</div>
