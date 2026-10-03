<?php
/** @var array $companies @var array $stats */
use Muh\Core\Translator;
$locale = Translator::instance()->locale();
?>
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-900"><?= e(__('nav.companies')) ?></h1>
        <p class="text-slate-500"><?= e(__('dashboard.office_overview')) ?></p>
    </div>
    <a href="<?= e(url('/app/companies/create')) ?>" class="px-4 py-2 rounded-lg bg-brand-600 text-white text-sm font-semibold hover:bg-brand-700">+ <?= e(__('common.create')) ?></a>
</div>

<div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
    <?php if (!$companies): ?>
        <div class="col-span-full bg-white border border-slate-200 rounded-2xl p-8 text-center text-slate-500">
            <?= e(__('app.no_companies')) ?>
        </div>
    <?php endif; ?>
    <?php foreach ($companies as $c):
        $s = $stats[(int) $c['id']] ?? ['periods'=>0,'invoices'=>0,'balance'=>0];
    ?>
    <a href="<?= e(url('/app/companies/' . $c['id'])) ?>" class="bg-white border border-slate-200 rounded-2xl p-5 hover:shadow-lg hover:border-brand-200 transition group">
        <div class="flex items-start gap-3">
            <span class="w-11 h-11 rounded-xl bg-brand-100 text-brand-700 flex items-center justify-center font-bold"><?= e(mb_strtoupper(mb_substr($c['name'], 0, 1))) ?></span>
            <div class="flex-1 min-w-0">
                <h3 class="font-semibold text-slate-900 group-hover:text-brand-600"><?= e($c['name']) ?></h3>
                <p class="text-xs text-slate-400"><?= e($c['tax_number'] ?? __('common.no_data')) ?></p>
            </div>
            <span class="px-2 py-1 rounded-full text-xs <?= ($c['status']??'active')==='active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' ?>"><?= e(__('common.' . ($c['status'] ?? 'active'))) ?></span>
        </div>
        <div class="mt-4 grid grid-cols-3 gap-2 text-center">
            <div class="rounded-lg bg-slate-50 py-2">
                <div class="text-lg font-bold text-slate-800"><?= (int) $s['periods'] ?></div>
                <div class="text-[11px] text-slate-400"><?= e(__('onboarding.step_period')) ?></div>
            </div>
            <div class="rounded-lg bg-slate-50 py-2">
                <div class="text-lg font-bold text-slate-800"><?= (int) $s['invoices'] ?></div>
                <div class="text-[11px] text-slate-400"><?= e(__('nav.invoices')) ?></div>
            </div>
            <div class="rounded-lg bg-slate-50 py-2">
                <div class="text-sm font-bold text-slate-800"><?= e(money($s['balance'])) ?></div>
                <div class="text-[11px] text-slate-400"><?= e(__('nav.current_accounts')) ?></div>
            </div>
        </div>
    </a>
    <?php endforeach; ?>
</div>

<?= $this->partial('partials.pagination', ['page' => $page ?? 1, 'lastPage' => $lastPage ?? 1, 'total' => $total ?? null]) ?>
