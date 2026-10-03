<?php
/** @var string $title @var string $subtitle @var array $headers @var array $rows @var string $exportSlug @var array $companies @var int $companyId @var array $periods @var int $periodId */
?>
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-900"><?= e($title) ?></h1>
        <p class="text-slate-500"><?= e($subtitle ?? '') ?></p>
    </div>
    <div class="flex gap-2">
        <?php foreach (['pdf' => 'PDF', 'excel' => 'Excel', 'csv' => 'CSV'] as $fmt => $lbl): ?>
        <a href="<?= e(url('/app/reports/' . $exportSlug . '/export?format=' . $fmt . '&company_id=' . $companyId . '&period_id=' . $periodId)) ?>" class="px-3 py-2 rounded-lg border border-slate-200 text-sm font-medium text-slate-600 hover:border-brand-300 hover:text-brand-600"><?= e($lbl) ?></a>
        <?php endforeach; ?>
    </div>
</div>

<div class="mb-4 bg-white border border-slate-200 rounded-2xl p-4 flex items-center gap-3 text-sm">
    <select onchange="location.href='<?= e(url('/app/reports/' . $exportSlug)) ?>?company_id='+this.value+'&period_id=<?= (int)$periodId ?>'" class="border border-slate-200 rounded-lg px-3 py-2 bg-white">
        <?php foreach ($companies as $c): ?><option value="<?= e($c['id']) ?>" <?= (int)$companyId===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
    </select>
    <?php if (!empty($periods)): ?>
    <select onchange="location.href='<?= e(url('/app/reports/' . $exportSlug)) ?>?company_id=<?= (int)$companyId ?>&period_id='+this.value" class="border border-slate-200 rounded-lg px-3 py-2 bg-white">
        <?php foreach ($periods as $p): ?><option value="<?= e($p['id']) ?>" <?= (int)$periodId===(int)$p['id']?'selected':'' ?>><?= e($p['name']) ?></option><?php endforeach; ?>
    </select>
    <?php endif; ?>
</div>

<div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-left text-xs text-slate-400 uppercase bg-slate-50">
                <tr><?php foreach ($headers as $h): ?><th class="px-4 py-2 <?= (strpos($h, '0,00') !== false) ? '' : '' ?>"><?= e($h) ?></th><?php endforeach; ?></tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php foreach ($rows as $r): ?>
                <tr class="hover:bg-slate-50">
                    <?php foreach ($r as $cell): ?><td class="px-4 py-2.5 text-slate-700"><?= e($cell) ?></td><?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
