<?php
/** @var array $companies @var int $companyId @var array $periods @var int $periodId @var array $accountOptions @var int $accountId @var array $lines */
use Muh\Core\Auth;
$active = 'ledger';
$running = [];
?>
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-900"><?= e(__('accounting.ledger')) ?></h1>
        <p class="text-slate-500"><?= e(__('accounting.title')) ?></p>
    </div>
    <?php if (Auth::can('report.export')): ?>
    <div class="flex gap-2">
        <?php foreach (['pdf'=>'PDF','excel'=>'Excel','csv'=>'CSV'] as $fmt=>$lbl): ?>
        <a href="<?= e(url('/app/accounting/ledger/export?format=' . $fmt . '&company_id=' . $companyId . '&period_id=' . $periodId . '&account_id=' . $accountId)) ?>" class="px-3 py-2 rounded-lg border border-slate-200 text-sm font-medium text-slate-600 hover:border-brand-300 hover:text-brand-600"><?= e($lbl) ?></a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?= $this->partial('app.accounting._context', ['companies'=>$companies,'companyId'=>$companyId,'periods'=>$periods,'periodId'=>$periodId,'active'=>$active]) ?>
<?= $this->partial('app.accounting._subnav', ['active'=>$active,'companyId'=>$companyId,'periodId'=>$periodId]) ?>

<div class="mb-4 bg-white border border-slate-200 rounded-2xl p-4 flex items-center gap-3">
    <select onchange="location.href='<?= e(url('/app/accounting/ledger')) ?>?company_id=<?= (int)$companyId ?>&period_id=<?= (int)$periodId ?>&account_id='+this.value" class="text-sm border border-slate-200 rounded-lg px-3 py-2 bg-white focus:ring-2 focus:ring-brand-500 outline-none">
        <option value="0"><?= e(__('accounting.all_accounts')) ?></option>
        <?php foreach ($accountOptions as $a): ?><option value="<?= e($a['id']) ?>" <?= (int)$accountId===(int)$a['id']?'selected':'' ?>><?= e($a['code']) ?> · <?= e($a['name']) ?></option><?php endforeach; ?>
    </select>
</div>

<div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-left text-xs text-slate-400 uppercase bg-slate-50">
                <tr>
                    <th class="px-5 py-2"><?= e(__('accounting.account_code')) ?></th>
                    <th class="px-5 py-2"><?= e(__('accounting.number')) ?></th>
                    <th class="px-5 py-2"><?= e(__('accounting.date')) ?></th>
                    <th class="px-5 py-2"><?= e(__('accounting.description')) ?></th>
                    <th class="px-5 py-2 text-right"><?= e(__('common.debit')) ?></th>
                    <th class="px-5 py-2 text-right"><?= e(__('common.credit')) ?></th>
                    <th class="px-5 py-2 text-right"><?= e(__('accounting.balance')) ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php if (!$lines): ?><tr><td colspan="7" class="px-5 py-8 text-center text-slate-400"><?= e(__('accounting.no_entries')) ?></td></tr><?php endif; ?>
                <?php
                $prevAccount = null;
                foreach ($lines as $ln):
                    $k = $ln['account_code'];
                    if ($prevAccount !== null && $prevAccount !== $k) { $running = []; }
                    if (!isset($running[$k])) { $running[$k] = (float) $ln['opening_debit'] - (float) $ln['opening_credit']; }
                    $running[$k] += (float) $ln['debit'] - (float) $ln['credit'];
                    $prevAccount = $k;
                ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-5 py-2.5 font-mono text-brand-600"><?= e($ln['account_code']) ?><div class="text-xs text-slate-400"><?= e($ln['account_name']) ?></div></td>
                    <td class="px-5 py-2.5 text-slate-600"><?= e($ln['number']) ?></td>
                    <td class="px-5 py-2.5 text-slate-500"><?= e(format_date($ln['date'])) ?></td>
                    <td class="px-5 py-2.5 text-slate-700"><?= e($ln['description'] ?? '') ?></td>
                    <td class="px-5 py-2.5 text-right text-slate-700"><?= e(money($ln['debit'])) ?></td>
                    <td class="px-5 py-2.5 text-right text-slate-700"><?= e(money($ln['credit'])) ?></td>
                    <td class="px-5 py-2.5 text-right font-medium <?= $running[$k] < 0 ? 'text-red-600' : 'text-slate-800' ?>"><?= e(money($running[$k])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
