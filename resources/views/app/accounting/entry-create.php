<?php
/** @var array $companies @var int $companyId @var array $periods @var int $periodId @var array $accounts @var string $voucherType */
use Muh\Core\Session;
use Muh\Core\Translator;
$locale = Translator::instance()->locale();
$errors = Session::get('_form_errors', []);
Session::forget('_form_errors');
?>
<div class="max-w-5xl">
    <div class="mb-6">
        <a href="<?= e(url('/app/accounting/journal')) ?>" class="text-sm text-brand-600 hover:underline">← <?= e(__('accounting.journal')) ?></a>
        <h1 class="text-2xl font-bold text-slate-900 mt-1"><?= e(__('accounting.new_entry')) ?></h1>
    </div>

    <form method="post" action="<?= e(url('/app/accounting/entry')) ?>" id="entryForm">
        <?= csrf_field() ?>

        <div class="bg-white border border-slate-200 rounded-2xl p-6 space-y-4">
            <div class="grid md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('accounting.voucher_type')) ?> *</label>
                    <select name="voucher_type" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                        <?php foreach (['journal','transfer','opening','closing','carry_forward'] as $vt): ?>
                        <option value="<?= e($vt) ?>" <?= ($voucherType ?? 'journal') === $vt ? 'selected' : '' ?>><?= e(__('accounting.type_' . $vt)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="hidden md:block"></div>
                <div class="hidden md:block"></div>
            </div>
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('accounting.title')) ?> / <?= e(__('common.company')) ?> *</label>
                    <select name="company_id" required class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                        <?php foreach ($companies as $c): ?><option value="<?= e($c['id']) ?>" <?= (int)$companyId===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('accounting.title')) ?> / <?= e(__('accounting.chart_of_accounts')) ?></label>
                    <select name="period_id" required class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                        <?php foreach ($periods as $p): ?><option value="<?= e($p['id']) ?>" <?= (int)$periodId===(int)$p['id']?'selected':'' ?>><?= e($p['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('accounting.date')) ?> *</label>
                    <input type="date" name="date" value="<?= date('Y-m-d') ?>" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('accounting.description')) ?></label>
                    <input name="description" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                </div>
            </div>
        </div>

        <!-- Lines -->
        <div class="bg-white border border-slate-200 rounded-2xl mt-4 p-6">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-semibold text-slate-800"><?= e(__('accounting.lines')) ?></h2>
                <button type="button" onclick="addLine()" class="px-3 py-1.5 rounded-lg border border-brand-200 text-brand-600 text-sm font-medium hover:bg-brand-50">+ <?= e(__('accounting.add_line')) ?></button>
            </div>
            <datalist id="accountList">
                <?php foreach ($accounts as $a): ?><option value="<?= e($a['code']) ?>"><?= e($a['code']) ?> · <?= e($a['name']) ?></option><?php endforeach; ?>
            </datalist>
            <div id="lines" class="space-y-2"></div>
            <div id="balanceStatus" class="mt-4 p-3 rounded-lg text-sm font-medium bg-green-50 border border-green-200 text-green-700"><?= e(__('accounting.not_balanced')) ?></div>
        </div>

        <div class="flex gap-3 mt-6">
            <button class="px-6 py-3 rounded-xl bg-brand-600 text-white font-semibold hover:bg-brand-700"><?= e(__('common.save')) ?></button>
            <a href="<?= e(url('/app/accounting/journal')) ?>" class="px-6 py-3 rounded-xl border border-slate-200 text-slate-600 font-medium"><?= e(__('common.cancel')) ?></a>
        </div>

        <?php if ($errors): ?><div class="mt-4 p-3 rounded-lg bg-red-50 border border-red-200 text-sm text-red-700"><?php foreach ($errors as $er): ?><div>• <?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>
    </form>
</div>

<script>
const hasAccounts = <?= json_encode(!empty($accounts)) ?>;
let lineCount = 0;
function addLine(){
    const i = lineCount++;
    const div = document.createElement('div');
    div.className = 'line grid grid-cols-12 gap-2 items-center bg-slate-50 border border-slate-100 rounded-xl p-2';
    div.innerHTML = `
        <div class="col-span-5">
            <input list="accountList" name="lines[${i}][account_code]" placeholder="${hasAccounts ? '' : '<?= e(__('accounting.account_code')) ?> *'}" class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none text-sm">
        </div>
        <div class="col-span-2">
            <input type="number" name="lines[${i}][debit]" step="0.01" min="0" value="0" class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none text-sm">
        </div>
        <div class="col-span-2">
            <input type="number" name="lines[${i}][credit]" step="0.01" min="0" value="0" class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none text-sm">
        </div>
        <div class="col-span-2 text-right text-sm text-slate-500 line-balance">0.00</div>
        <div class="col-span-1 text-right">
            <button type="button" onclick="this.closest('.line').remove(); recalc();" class="text-slate-400 hover:text-red-500" title="×">✕</button>
        </div>`;
    document.getElementById('lines').appendChild(div);
    recalc();
}
function recalc(){
    let debit=0, credit=0;
    document.querySelectorAll('.line').forEach(r=>{
        const d=parseFloat(r.querySelector('[name$="[debit]"]').value)||0;
        const c=parseFloat(r.querySelector('[name$="[credit]"]').value)||0;
        debit+=d; credit+=c;
        const balEl=r.querySelector('.line-balance');
        if(balEl) balEl.textContent = (d-c).toLocaleString('<?= $locale === 'tr' ? 'tr-TR' : 'en-US' ?>',{minimumFractionDigits:2,maximumFractionDigits:2});
    });
    const fmt=x=>x.toLocaleString('<?= $locale === 'tr' ? 'tr-TR' : 'en-US' ?>',{minimumFractionDigits:2,maximumFractionDigits:2});
    const diff = Math.abs(debit-credit);
    const status = document.getElementById('balanceStatus');
    if(debit>0 && diff<0.009){
        status.className = 'mt-4 p-3 rounded-lg text-sm font-medium bg-green-50 border border-green-200 text-green-700';
        status.textContent = '<?= e(__('accounting.balance')) ?> 0.00 · ' + fmt(debit) + ' / ' + fmt(credit);
    } else {
        status.className = 'mt-4 p-3 rounded-lg text-sm font-medium bg-red-50 border border-red-200 text-red-700';
        status.textContent = '<?= e(__('accounting.not_balanced')) ?> · ' + fmt(debit) + ' / ' + fmt(credit) + ' (' + (formatDiff(diff)) + ')';
    }
    function formatDiff(x){ return x.toLocaleString('<?= $locale === 'tr' ? 'tr-TR' : 'en-US' ?>',{minimumFractionDigits:2,maximumFractionDigits:2}); }
}
document.addEventListener('input', recalc);
addLine(); addLine();
</script>
<EOF>
