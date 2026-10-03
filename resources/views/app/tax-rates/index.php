<?php
/** @var array $rates @var string $locale */
use Muh\Core\Session;
$errors = Session::get('_form_errors', []);
$typeLabel = fn (array $r, string $loc) => $r['is_vat'] ? __('taxrate.type_vat') : ($r['is_withholding'] ? __('taxrate.type_withholding') : __('taxrate.type_other'));
?>
<div class="max-w-4xl mx-auto p-6 space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold text-slate-900"><?= e(__('taxrate.title')) ?></h1>
        <a href="<?= e(url('/app/tax-rates/create')) ?>" class="px-4 py-2 rounded-lg bg-brand-600 text-white text-sm font-medium hover:bg-brand-700">+ <?= e(__('taxrate.new')) ?></a>
    </div>

    <?php if ($errors): ?><div class="px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>

    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
        <?php if (!$rates): ?>
            <div class="p-6 text-sm text-slate-500"><?= e(__('taxrate.no_rates')) ?></div>
        <?php else: ?>
        <table class="w-full text-sm">
            <thead class="text-left text-xs text-slate-400 uppercase border-b border-slate-100">
                <tr>
                    <th class="px-5 py-2.5 font-medium"><?= e(__('taxrate.name')) ?></th>
                    <th class="px-5 py-2.5 font-medium"><?= e(__('taxrate.rate')) ?></th>
                    <th class="px-5 py-2.5 font-medium"><?= e(__('taxrate.type')) ?></th>
                    <th class="px-5 py-2.5 font-medium"><?= e(__('taxrate.actions')) ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($rates as $r): ?>
                <tr>
                    <td class="px-5 py-3 font-medium text-slate-800">
                        <?= e($r['label']) ?><?php if ($r['is_default']): ?> <span class="text-[11px] px-2 py-0.5 rounded-full bg-brand-50 text-brand-600"><?= e(__('taxrate.is_default')) ?></span><?php endif; ?>
                    </td>
                    <td class="px-5 py-3 text-slate-600">% <?= e(number_format((float) $r['rate'], 2, ',', '.')) ?></td>
                    <td class="px-5 py-3"><span class="px-2 py-0.5 rounded-full text-xs bg-slate-100 text-slate-600"><?= e($typeLabel($r, $locale)) ?></span></td>
                    <td class="px-5 py-3">
                        <?php if (!$r['is_default']): ?>
                        <form method="post" action="<?= e(url('/app/tax-rates/' . $r['id'] . '/delete')) ?>" onsubmit="return confirm('<?= e(__('taxrate.delete')) ?>?')">
                            <?= csrf_field() ?>
                            <button class="text-xs text-red-600 hover:underline"><?= e(__('taxrate.delete')) ?></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
