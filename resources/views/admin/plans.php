<?php
/** @var array $plans @var array $errors */
use Muh\Core\Translator;
$locale = Translator::instance()->locale();
$decode = function ($json) use ($locale) { $a = json_decode((string) $json, true); return is_array($a) ? ($a[$locale] ?? $a['en'] ?? $a['tr'] ?? $json) : $json; };
$feat = function ($features) { $f = json_decode((string) $features, true); return is_array($f) ? $f : []; };
$featureFields = ['companies' => __('admin.f_companies'), 'users' => __('admin.f_users'), 'warehouses' => __('admin.f_warehouses'), 'invoices' => __('admin.f_invoices'), 'storage_mb' => __('admin.f_storage')];
?>
<div class="max-w-5xl mx-auto space-y-6">
    <h1 class="text-2xl font-bold text-slate-900"><?= e(__('admin.plans')) ?></h1>

    <?php if ($errors): ?><div class="px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>

    <!-- Create plan -->
    <form method="post" action="<?= e(url('/admin/plans')) ?>" class="bg-white border border-slate-200 rounded-2xl p-5">
        <?= csrf_field() ?>
        <h2 class="font-semibold text-slate-900 mb-3" style="--tw-text-opacity:1">+ <?= e(__('admin.add_plan')) ?></h2>
        <div class="grid md:grid-cols-2 gap-3">
            <input name="code" required placeholder="<?= e(__('admin.plan_code')) ?>" class="px-3 py-2 rounded-lg border border-slate-200 text-sm">
            <input name="name" required placeholder="<?= e(__('admin.plan_name')) ?>" class="px-3 py-2 rounded-lg border border-slate-200 text-sm">
            <input name="price_monthly" type="number" step="0.01" placeholder="<?= e(__('admin.price_monthly')) ?>" class="px-3 py-2 rounded-lg border border-slate-200 text-sm">
            <input name="price_yearly" type="number" step="0.01" placeholder="<?= e(__('admin.price_yearly')) ?>" class="px-3 py-2 rounded-lg border border-slate-200 text-sm">
            <input name="stripe_price_monthly_id" placeholder="Stripe price_ (monthly)" class="px-3 py-2 rounded-lg border border-slate-200 text-sm">
            <input name="stripe_price_yearly_id" placeholder="Stripe price_ (yearly)" class="px-3 py-2 rounded-lg border border-slate-200 text-sm">
        </div>
        <div class="grid md:grid-cols-5 gap-2 mt-3">
            <?php foreach ($featureFields as $f => $lbl): ?><input name="features[<?= $f ?>]" placeholder="<?= e($lbl) ?>" class="px-2 py-1.5 rounded-lg border border-slate-200 text-xs"><?php endforeach; ?>
        </div>
        <label class="flex items-center gap-2 text-xs mt-2 text-slate-600"><input type="checkbox" name="features[efatura]"> <?= e(__('admin.einvoice')) ?></label>
        <button class="mt-3 px-4 py-2 rounded-lg bg-brand-600 text-white text-sm font-medium"><?= e(__('admin.create')) ?></button>
    </form>

    <!-- Plans list -->
    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 font-semibold text-slate-800 text-sm"><?= e(__('admin.plans')) ?> (<?= count($plans) ?>)</div>
        <?php foreach ($plans as $p): $ff = $feat($p['features']); ?>
        <form method="post" action="<?= e(url('/admin/plans/' . $p['id'])) ?>" class="px-5 py-4 border-b border-slate-100">
            <?= csrf_field() ?>
            <div class="flex items-center justify-between mb-2">
                <span class="font-semibold text-slate-800 text-sm"><?= e($p['code']) ?> — <?= e($decode($p['name'])) ?></span>
                <div class="flex gap-2">
                    <span class="text-xs text-slate-400"><?= e(money((float) $p['price_monthly'], $p['currency'])) ?>/ay</span>
                    <button formaction="<?= e(url('/admin/plans/' . $p['id'] . '/delete')) ?>" class="text-xs text-red-600 hover:underline" onclick="return confirm('<?= e(__('admin.delete')) ?>?')"><?= e(__('admin.delete')) ?></button>
                </div>
            </div>
            <div class="grid md:grid-cols-5 gap-2">
                <input name="name" value="<?= e($decode($p['name'])) ?>" class="px-2 py-1.5 rounded-lg border border-slate-200 text-sm">
                <input name="price_monthly" type="number" step="0.01" value="<?= e($p['price_monthly']) ?>" class="px-2 py-1.5 rounded-lg border border-slate-200 text-sm">
                <input name="price_yearly" type="number" step="0.01" value="<?= e($p['price_yearly']) ?>" class="px-2 py-1.5 rounded-lg border border-slate-200 text-sm">
                <input name="stripe_price_monthly_id" value="<?= e($p['stripe_price_monthly_id'] ?? '') ?>" placeholder="price_ (aylık)" class="px-2 py-1.5 rounded-lg border border-slate-200 text-sm">
                <input name="stripe_price_yearly_id" value="<?= e($p['stripe_price_yearly_id'] ?? '') ?>" placeholder="price_ (yıllık)" class="px-2 py-1.5 rounded-lg border border-slate-200 text-sm">
                <input name="features[companies]" value="<?= e($ff['companies'] ?? 1) ?>" class="px-2 py-1.5 rounded-lg border border-slate-200 text-sm">
                <input name="features[invoices]" value="<?= e($ff['invoices'] ?? 0) ?>" class="px-2 py-1.5 rounded-lg border border-slate-200 text-sm">
            </div>
            <div class="flex items-center gap-2 mt-2">
                <input name="features[users]" value="<?= e($ff['users'] ?? 1) ?>" placeholder="<?= e(__('admin.f_users')) ?>" class="px-2 py-1.5 rounded-lg border border-slate-200 text-xs w-28">
                <input name="features[warehouses]" value="<?= e($ff['warehouses'] ?? 1) ?>" placeholder="<?= e(__('admin.f_warehouses')) ?>" class="px-2 py-1.5 rounded-lg border border-slate-200 text-xs w-28">
                <input name="features[storage_mb]" value="<?= e($ff['storage_mb'] ?? 0) ?>" placeholder="<?= e(__('admin.f_storage')) ?>" class="px-2 py-1.5 rounded-lg border border-slate-200 text-xs w-28">
                <label class="flex items-center gap-1 text-xs"><input type="checkbox" name="features[efatura]" <?= !empty($ff['efatura'])?'checked':'' ?>> <?= e(__('admin.einvoice')) ?></label>
                <button class="ml-auto px-3 py-1.5 rounded-lg bg-slate-100 text-slate-700 text-xs font-medium hover:bg-brand-50"><?= e(__('common.save')) ?></button>
            </div>
        </form>
        <?php endforeach; ?>
    </div>
</div>
