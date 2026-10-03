<?php
/** @var int $step @var array $steps */
use Muh\Core\Translator;
$locale = Translator::instance()->locale();
$labels = ['1' => __('onboarding.step_office'), '2' => __('onboarding.step_first_company'), '3' => __('onboarding.step_invite'), '4' => __('onboarding.step_done')];
?>
<div class="max-w-2xl mx-auto py-12 px-4">
    <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-slate-900"><?= e(__('onboarding.title')) ?></h1>
        <p class="text-slate-500 mt-2">Hesap360</p>
    </div>

    <!-- Stepper -->
    <div class="flex items-center justify-between mb-10 max-w-md mx-auto">
        <?php foreach ([1,2,3,4] as $s): ?>
        <div class="flex items-center">
            <div class="w-9 h-9 rounded-full flex items-center justify-center font-semibold text-sm <?= $s <= $step ? 'bg-brand-600 text-white' : 'bg-slate-200 text-slate-500' ?>"><?= $s ?></div>
            <?php if ($s < 4): ?><div class="w-12 h-0.5 <?= $s < $step ? 'bg-brand-600' : 'bg-slate-200' ?>"></div><?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-8">

        <?php if ($step === 1): ?>
            <h2 class="text-xl font-semibold text-slate-900 text-center"><?= e(__('onboarding.step_office')) ?></h2>
            <p class="text-sm text-slate-500 text-center mt-2"><?= e(__('common.welcome')) ?></p>
            <form method="get" action="<?= e(url('/onboarding')) ?>" class="mt-8">
                <input type="hidden" name="step" value="2">
                <button class="w-full px-6 py-3 rounded-xl bg-brand-600 text-white font-semibold hover:bg-brand-700"><?= e(__('common.next')) ?></button>
            </form>

        <?php elseif ($step === 2): ?>
            <h2 class="text-xl font-semibold text-slate-900"><?= e(__('onboarding.step_company')) ?></h2>
            <p class="text-sm text-slate-500 mt-1"><?= e(__('onboarding.step_first_company')) ?></p>
            <form method="post" action="<?= e(url('/onboarding/company')) ?>" class="mt-6 space-y-4">
                <?= csrf_field() ?>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('onboarding.company_name')) ?></label>
                    <input name="company_name" required class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                </div>
                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('onboarding.trade_name')) ?></label>
                        <input name="trade_name" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('onboarding.tax_number')) ?></label>
                        <input name="tax_number" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                    </div>
                </div>
                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('onboarding.tax_office')) ?></label>
                        <input name="tax_office" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1"><?= e(__('onboarding.fiscal_year')) ?></label>
                        <input name="fiscal_year" value="<?= date('Y') ?>" class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                    </div>
                </div>
                <button class="w-full px-6 py-3 rounded-xl bg-brand-600 text-white font-semibold hover:bg-brand-700"><?= e(__('onboarding.create_company')) ?></button>
            </form>

        <?php elseif ($step === 3): ?>
            <h2 class="text-xl font-semibold text-slate-900 text-center"><?= e(__('onboarding.step_invite')) ?></h2>
            <p class="text-sm text-slate-500 text-center mt-2"><?= e(__('onboarding.complete')) ?></p>
            <div class="mt-8">
                <a href="<?= e(url('/onboarding/complete')) ?>" class="block w-full text-center px-6 py-3 rounded-xl bg-brand-600 text-white font-semibold hover:bg-brand-700"><?= e(__('onboarding.step_done')) ?></a>
            </div>

        <?php else: ?>
            <h2 class="text-xl font-semibold text-slate-900 text-center"><?= e(__('onboarding.complete')) ?></h2>
            <div class="mt-8">
                <a href="<?= e(url('/app/dashboard')) ?>" class="block w-full text-center px-6 py-3 rounded-xl bg-brand-600 text-white font-semibold hover:bg-brand-700"><?= e(__('nav.dashboard')) ?></a>
            </div>
        <?php endif; ?>
    </div>
</div>
