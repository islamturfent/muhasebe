<?php
/** @var array $accounts */
?>
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-900"><?= e(__('bank.title')) ?></h1>
        <p class="text-slate-500"><?= count($accounts) ?> hesap</p>
    </div>
    <a href="<?= e(url('/app/bank/create')) ?>" class="px-4 py-2 rounded-lg bg-brand-600 text-white text-sm font-semibold hover:bg-brand-700">+ <?= e(__('bank.new_account')) ?></a>
</div>

<div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
    <?php if (!$accounts): ?><div class="col-span-full bg-white border border-slate-200 rounded-2xl p-8 text-center text-slate-400"><?= e(__('bank.no_accounts')) ?></div><?php endif; ?>
    <?php foreach ($accounts as $a): ?>
    <a href="<?= e(url('/app/bank/' . $a['id'])) ?>" class="bg-white border border-slate-200 rounded-2xl p-5 hover:shadow-lg hover:border-brand-200 transition">
        <div class="flex items-center justify-between">
            <span class="w-10 h-10 rounded-lg bg-sky-100 text-sky-700 flex items-center justify-center font-bold">🏦</span>
            <span class="text-xs text-slate-400 italic"><?= e($a['bank_name']) ?></span>
        </div>
        <h3 class="mt-3 font-semibold text-slate-900"><?= e($a['account_name'] ?? $a['bank_name']) ?></h3>
        <p class="text-xs text-slate-400"><?= e($a['company_name']) ?> · <?= e($a['iban'] ?? '') ?></p>
        <div class="mt-3 text-lg font-bold <?= $a['balance'] < 0 ? 'text-red-600' : 'text-slate-800' ?>"><?= e(money($a['balance'], $a['currency'])) ?></div>
    </a>
    <?php endforeach; ?>
</div>
