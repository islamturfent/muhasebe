<?php
/** @var array $companies @var int $selectedCompany @var array $accounts @var array $products */
use Muh\Core\Session;
use Muh\Core\Translator;
$locale = Translator::instance()->locale();
$errors = Session::get('_form_errors', []);
Session::forget('_form_errors');
$isSales = true;
?>
<div class="max-w-4xl">
    <div class="mb-6">
        <a href="<?= e(url('/app/invoices')) ?>" class="text-sm text-brand-600 hover:underline">← <?= e(__('invoice.title')) ?></a>
        <h1 class="text-2xl font-bold text-slate-900 mt-1"><?= e(__('invoice.new')) ?></h1>
    </div>

    <form method="post" action="<?= e(url('/app/invoices')) ?>" id="invoiceForm">
        <?= csrf_field() ?>

        <div class="bg-white border border-slate-200 rounded-2xl p-6 space-y-4">
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('invoice.type')) ?> *</label>
                    <select name="type" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                        <option value="sales"><?= e(__('invoice.sales')) ?></option>
                        <option value="purchase"><?= e(__('invoice.purchase')) ?></option>
                        <option value="sales_return"><?= e(__('invoice.sales_return')) ?></option>
                        <option value="purchase_return"><?= e(__('invoice.purchase_return')) ?></option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('invoice.company')) ?> *</label>
                    <select name="company_id" id="companySelect" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                        <?php foreach ($companies as $c): ?><option value="<?= e($c['id']) ?>" <?= (int)$selectedCompany===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('invoice.customer')) ?> *</label>
                    <select name="current_account_id" id="accountSelect" required class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                        <option value="">— <?= e(__('current_account.title')) ?> —</option>
                        <?php foreach ($accounts as $a): ?><option value="<?= e($a['id']) ?>"><?= e($a['code']) ?> · <?= e($a['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('invoice.date')) ?> *</label>
                        <input type="date" name="date" value="<?= date('Y-m-d') ?>" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('invoice.due_date')) ?></label>
                        <input type="date" name="due_date" value="<?= date('Y-m-d', strtotime('+15 days')) ?>" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                    </div>
                </div>
            </div>
        </div>

        <!-- Lines -->
        <div class="bg-white border border-slate-200 rounded-2xl mt-4 p-6">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-semibold text-slate-800"><?= e(__('invoice.lines')) ?></h2>
                <button type="button" onclick="addLine()" class="px-3 py-1.5 rounded-lg border border-brand-200 text-brand-600 text-sm font-medium hover:bg-brand-50">+ <?= e(__('invoice.add_line')) ?></button>
            </div>
            <div id="lines">
                <?= str_replace('__LINE__', '0', $this->partial('app.invoices.lines', ['products' => $products])) ?>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl mt-4 p-6">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('invoice.notes')) ?></label>
                <textarea name="notes" rows="2" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none"></textarea>
            </div>

            <div class="mt-4 ml-auto max-w-xs space-y-1 text-sm">
                <div class="flex justify-between text-slate-500"><span><?= e(__('invoice.subtotal')) ?></span><span id="sumSubtotal">0.00</span></div>
                <div class="flex justify-between text-slate-500"><span><?= e(__('invoice.discount')) ?></span><span id="sumDiscount">0.00</span></div>
                <div class="flex justify-between text-slate-500"><span><?= e(__('invoice.tax')) ?></span><span id="sumTax">0.00</span></div>
                <div class="flex justify-between text-base font-bold text-slate-900 border-t border-slate-100 pt-2"><span><?= e(__('invoice.total')) ?></span><span id="sumTotal">0.00</span></div>
            </div>
        </div>

        <?php if ($errors): ?><div class="mt-4 p-3 rounded-lg bg-red-50 border border-red-200 text-sm text-red-700"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>

        <div class="flex gap-3 mt-6">
            <button class="px-6 py-3 rounded-xl bg-brand-600 text-white font-semibold hover:bg-brand-700"><?= e(__('common.save')) ?></button>
            <a href="<?= e(url('/app/invoices')) ?>" class="px-6 py-3 rounded-xl border border-slate-200 text-slate-600 font-medium"><?= e(__('common.cancel')) ?></a>
        </div>
    </form>
</div>

<script>
const PRODUCTS = <?= json_encode($products, JSON_UNESCAPED_UNICODE) ?>;
const lineTpl = <?= json_encode(str_replace(["\n","\r"], ['',''], $this->partial('app.invoices.lines', ['products' => $products])), JSON_UNESCAPED_UNICODE) ?>;
function addLine(){ const i = document.querySelectorAll('#lines .line').length; const el = document.createElement('div'); el.innerHTML = lineTpl.split('__LINE__').join(String(i)); document.getElementById('lines').appendChild(el.firstElementChild); recalc(); }
function onProductChange(sel){ const id = sel.value; const p = PRODUCTS.find(x => String(x.id)===id); if(!p) return; const row = sel.closest('.line'); row.querySelector('[name="unit_price"]').value = p.type==='service' ? p.sale_price : p.sale_price; row.querySelector('[name="vat_rate"]').value = p.vat_rate; recalc(); }
function recalc(){
  let sub=0, disc=0, tax=0, tot=0;
  document.querySelectorAll('.line').forEach(r=>{
    const q=parseFloat(r.querySelector('[name="qty"]').value)||0;
    const p=parseFloat(r.querySelector('[name="unit_price"]').value)||0;
    const d=parseFloat(r.querySelector('[name="discount"]').value)||0;
    const v=parseFloat(r.querySelector('[name="vat_rate"]').value)||0;
    const net=q*p; const da=net*d/100; const nt=net-da; const t=nt*v/100;
    sub+=net; disc+=da; tax+=t; tot+=nt+t;
  });
  const fmt=x=>x.toLocaleString('<?= $locale === 'tr' ? 'tr-TR' : 'en-US' ?>',{minimumFractionDigits:2,maximumFractionDigits:2});
  document.getElementById('sumSubtotal').textContent=fmt(sub);
  document.getElementById('sumDiscount').textContent=fmt(disc);
  document.getElementById('sumTax').textContent=fmt(tax);
  document.getElementById('sumTotal').textContent=fmt(tot);
}
document.addEventListener('input', recalc);
recalc();
</script>
