<?php
/** @var array $items @var array $companies @var int $overdue @var int $upcoming @var int $year @var string|null $status @var string|null $type */
use Muh\Core\Auth;
use Muh\Core\Session;
$flashS = Session::get('success'); Session::forget('success');
$flashE = Session::get('error'); Session::forget('error');
?>
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900"><?= e(__('tax.title')) ?></h1>
    <p class="text-slate-500"><?= e(__('tax.subtitle')) ?></p>
</div>

<?php if ($flashS): ?><div class="mb-4 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm"><?= e($flashS) ?></div><?php endif; ?>
<?php if ($flashE): ?><div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm"><?= e($flashE) ?></div><?php endif; ?>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="rounded-2xl bg-white border border-red-200 p-5">
        <div class="text-xs text-slate-400 uppercase"><?= e(__('tax.overdue')) ?></div>
        <div class="text-2xl font-bold text-red-600"><?= (int) $overdue ?></div>
    </div>
    <div class="rounded-2xl bg-white border border-amber-200 p-5">
        <div class="text-xs text-slate-400 uppercase"><?= e(__('tax.upcoming')) ?></div>
        <div class="text-2xl font-bold text-amber-600"><?= (int) $upcoming ?></div>
    </div>
    <div class="rounded-2xl bg-white border border-slate-200 p-5">
        <div class="text-xs text-slate-400 uppercase"><?= e(__('tax.status_pending')) ?></div>
        <div class="text-2xl font-bold text-slate-800"><?= (int) $total ?></div>
    </div>
    <div class="rounded-2xl bg-white border border-slate-200 p-5">
        <div class="text-xs text-slate-400 uppercase"><?= e(__('common.total')) ?></div>
        <div class="text-2xl font-bold text-slate-800"><?= (int) count($items) ?></div>
    </div>
</div>

<div class="grid lg:grid-cols-3 gap-6 mb-6">
    <!-- Generate -->
    <div class="bg-white border border-slate-200 rounded-2xl p-5">
        <h3 class="font-semibold text-slate-800 mb-3"><?= e(__('tax.generate')) ?></h3>
        <form method="post" action="<?= e(url('/app/tax-calendar/generate')) ?>" class="space-y-2">
            <?= csrf_field() ?>
            <input type="number" name="year" value="<?= (int) $year ?>" min="2000" max="2100" class="w-full px-3 py-2 rounded-lg border border-slate-200">
            <select name="company_id" class="w-full px-3 py-2 rounded-lg border border-slate-200">
                <option value=""><?= e(__('tax.company')) ?> — (<?= e(__('common.all')) ?>)</option>
                <?php foreach ($companies as $c): ?><option value="<?= e($c['id']) ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
            </select>
            <button class="w-full px-4 py-2 rounded-lg bg-brand-600 text-white text-sm font-semibold"><?= e(__('tax.generate')) ?></button>
        </form>
    </div>

    <!-- Add -->
    <div class="bg-white border border-slate-200 rounded-2xl p-5">
        <h3 class="font-semibold text-slate-800 mb-3"><?= e(__('tax.new')) ?></h3>
        <form method="post" action="<?= e(url('/app/tax-calendar')) ?>" class="space-y-2">
            <?= csrf_field() ?>
            <input name="name" placeholder="<?= e(__('tax.name')) ?> *" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm">
            <div class="grid grid-cols-2 gap-2">
                <select name="obligation_type" class="px-3 py-2 rounded-lg border border-slate-200 text-sm">
                    <?php foreach (['kdv','muhtasar','gecici','annual','other'] as $t): ?><option value="<?= e($t) ?>"><?= e(__('tax.type_' . $t)) ?></option><?php endforeach; ?>
                </select>
                <input type="date" name="due_date" required class="px-3 py-2 rounded-lg border border-slate-200 text-sm">
            </div>
            <div class="grid grid-cols-2 gap-2">
                <input name="period_label" placeholder="<?= e(__('tax.period')) ?>" class="px-3 py-2 rounded-lg border border-slate-200 text-sm">
                <input type="number" name="amount" step="0.01" value="0" class="px-3 py-2 rounded-lg border border-slate-200 text-sm">
            </div>
            <input name="company_id" type="hidden" value="0">
            <button class="w-full px-4 py-2 rounded-lg bg-brand-600 text-white text-sm font-semibold"><?= e(__('tax.new')) ?></button>
        </form>
    </div>

    <!-- Filters -->
    <div class="bg-white border border-slate-200 rounded-2xl p-5">
        <h3 class="font-semibold text-slate-800 mb-3"><?= e(__('common.filter')) ?></h3>
        <form method="get" action="<?= e(url('/app/tax-calendar')) ?>" class="space-y-2">
            <input type="number" name="year" value="<?= (int) $year ?>" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm">
            <select name="status" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm">
                <option value=""><?= e(__('tax.status')) ?> — <?= e(__('common.all')) ?></option>
                <option value="pending" <?= $status==='pending'?'selected':'' ?>><?= e(__('tax.status_pending')) ?></option>
                <option value="done" <?= $status==='done'?'selected':'' ?>><?= e(__('tax.status_done')) ?></option>
            </select>
            <button class="w-full px-4 py-2 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium"><?= e(__('common.filter')) ?></button>
        </form>
    </div>
</div>

<div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
    <table class="w-full text-sm">
        <thead class="text-left text-xs text-slate-400 uppercase bg-slate-50">
            <tr>
                <th class="px-5 py-2"><?= e(__('tax.name')) ?></th>
                <th class="px-5 py-2"><?= e(__('tax.obligation_type')) ?></th>
                <th class="px-5 py-2"><?= e(__('tax.period')) ?></th>
                <th class="px-5 py-2"><?= e(__('tax.company')) ?></th>
                <th class="px-5 py-2"><?= e(__('tax.due_date')) ?></th>
                <th class="px-5 py-2 text-right"><?= e(__('tax.amount')) ?></th>
                <th class="px-5 py-2 text-center"><?= e(__('tax.status')) ?></th>
                <th class="px-5 py-2"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            <?php if (!$items): ?><tr><td colspan="8" class="px-5 py-8 text-center text-slate-400"><?= e(__('tax.no_items')) ?></td></tr><?php endif; ?>
            <?php $today = date('Y-m-d'); foreach ($items as $ob): $isOverdue = $ob['status'] === 'pending' && $ob['due_date'] && $ob['due_date'] < $today; ?>
            <tr class="hover:bg-slate-50 <?= $isOverdue ? 'bg-red-50/40' : '' ?>">
                <td class="px-5 py-2.5 font-medium text-slate-700"><?= e($ob['name']) ?></td>
                <td class="px-5 py-2.5"><span class="px-2 py-0.5 rounded-full text-[11px] bg-slate-100 text-slate-600"><?= e(__('tax.type_' . $ob['obligation_type'])) ?></span></td>
                <td class="px-5 py-2.5 text-slate-500"><?= e($ob['period_label'] ?? '—') ?></td>
                <td class="px-5 py-2.5 text-slate-500"><?= e($ob['company_name'] ?? '—') ?></td>
                <td class="px-5 py-2.5 <?= $isOverdue ? 'text-red-600 font-medium' : 'text-slate-600' ?>"><?= e(format_date($ob['due_date'])) ?></td>
                <td class="px-5 py-2.5 text-right text-slate-700"><?= e(money($ob['amount'])) ?></td>
                <td class="px-5 py-2.5 text-center">
                    <form method="post" action="<?= e(url('/app/tax-calendar/' . (int)$ob['id'] . '/toggle')) ?>" class="inline">
                        <?= csrf_field() ?>
                        <button class="px-2 py-0.5 rounded-full text-[11px] font-medium <?= $ob['status'] === 'done' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' ?>"><?= e(__('tax.status_' . $ob['status'])) ?></button>
                    </form>
                </td>
                <td class="px-5 py-2.5 text-right">
                    <form method="post" action="<?= e(url('/app/tax-calendar/' . (int)$ob['id'] . '/delete')) ?>" class="inline" onsubmit="return confirm('<?= e(__('common.delete')) ?>?')">
                        <?= csrf_field() ?>
                        <button class="text-slate-400 hover:text-red-500 text-xs"><?= e(__('common.delete')) ?></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?= $this->partial('partials.pagination', ['page' => $page ?? 1, 'lastPage' => $lastPage ?? 1, 'total' => $total ?? null]) ?>
