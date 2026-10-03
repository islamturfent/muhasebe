<?php
/** @var array $rows @var array $plans @var array $summary */
use Muh\Core\Translator;
$locale = Translator::instance()->locale();
$decode = function ($json) use ($locale) { $a = json_decode((string) $json, true); return is_array($a) ? ($a[$locale] ?? $a['en'] ?? $a['tr'] ?? $json) : $json; };
?>
<div class="max-w-6xl mx-auto space-y-6">
    <h1 class="text-2xl font-bold text-slate-900"><?= e(__('admin.subscriptions')) ?></h1>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <?php foreach ($summary as $code => $count): ?>
        <div class="bg-white border border-slate-200 rounded-2xl p-4">
            <div class="text-2xl font-bold text-slate-900"><?= (int) $count ?></div>
            <div class="text-xs text-slate-500"><?= e($code) ?> abone</div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 font-semibold text-slate-800 text-sm"><?= e(__('admin.subscriptions')) ?></div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-xs text-slate-400 uppercase bg-slate-50">
                    <tr>
                        <th class="px-5 py-2"><?= e(__('admin.tenant')) ?></th>
                        <th class="px-5 py-2"><?= e(__('admin.plan')) ?></th>
                        <th class="px-5 py-2"><?= e(__('admin.cycle')) ?></th>
                        <th class="px-5 py-2"><?= e(__('admin.status')) ?></th>
                        <th class="px-5 py-2"><?= e(__('admin.start')) ?></th>
                        <th class="px-5 py-2"><?= e(__('admin.actions')) ?></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($rows as $s): ?>
                    <tr>
                        <td class="px-5 py-2.5 font-medium text-slate-800"><?= e($s['tenant_name']) ?></td>
                        <td class="px-5 py-2.5 text-slate-600"><?= e($decode($s['plan_name'] ?? '') ?: $s['plan_code']) ?></td>
                        <td class="px-5 py-2.5 text-slate-500"><?= e($s['billing_cycle']) ?></td>
                        <td class="px-5 py-2.5"><span class="px-2 py-0.5 rounded-full text-xs bg-slate-100 text-slate-600"><?= e($s['status']) ?></span></td>
                        <td class="px-5 py-2.5 text-slate-500"><?= e(format_date($s['starts_at'])) ?></td>
                        <td class="px-5 py-2.5">
                            <form method="post" action="<?= e(url('/admin/tenants/' . $s['tenant_id'] . '/plan')) ?>" class="flex gap-1">
                                <?= csrf_field() ?>
                                <select name="plan_id" class="text-xs border border-slate-200 rounded-lg px-2 py-1 bg-white">
                                    <?php foreach ($plans as $p): ?><option value="<?= (int) $p['id'] ?>"><?= e($p['code']) ?></option><?php endforeach; ?>
                                </select>
                                <select name="cycle" class="text-xs border border-slate-200 rounded-lg px-2 py-1 bg-white"><option value="monthly"><?= e(__('admin.monthly')) ?></option><option value="yearly"><?= e(__('admin.yearly')) ?></option></select>
                                <button class="px-3 py-1 rounded-lg bg-brand-600 text-white text-xs font-medium"><?= e(__('admin.set')) ?></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$rows): ?><tr><td colspan="6" class="px-5 py-8 text-center text-slate-400"><?= e(__('admin.no_subs')) ?></td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
