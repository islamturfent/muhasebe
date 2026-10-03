<?php
/** @var array $companies @var int $companyId @var array $periods @var int $periodId */
?>
<div class="mb-6 flex items-center gap-3">
    <select name="company_id" onchange="location.href='<?= e(url('/app/accounting/' . ($active ?? 'journal'))) ?>?company_id='+this.value" class="text-sm border border-slate-200 rounded-lg px-3 py-2 bg-white focus:ring-2 focus:ring-brand-500 outline-none">
        <?php foreach ($companies as $c): ?><option value="<?= e($c['id']) ?>" <?= (int)$companyId===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
    </select>
    <select name="period_id" onchange="location.href='<?= e(url('/app/accounting/' . ($active ?? 'journal'))) ?>?company_id=<?= (int)$companyId ?>&period_id='+this.value" class="text-sm border border-slate-200 rounded-lg px-3 py-2 bg-white focus:ring-2 focus:ring-brand-500 outline-none">
        <?php foreach ($periods as $p): ?><option value="<?= e($p['id']) ?>" <?= (int)$periodId===(int)$p['id']?'selected':'' ?>><?= e($p['name']) ?></option><?php endforeach; ?>
    </select>
</div>
