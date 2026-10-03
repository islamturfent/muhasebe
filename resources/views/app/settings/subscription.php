<?php
/** @var array|null $plan @var array $usage @var array $features @var array $plans */
use Muh\Core\Translator;
$locale = Translator::instance()->locale();
$subRows = [
    'companies'  => __('subscription.resource_companies'),
    'users'      => __('subscription.resource_users'),
    'warehouses' => __('subscription.resource_warehouses'),
    'invoices'   => __('subscription.resource_invoices'),
];
function subLimit($features, $key): int { return (int) ($features[$key] ?? 0); }
?>
<div class="max-w-4xl">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900"><?= e(__('subscription.title')) ?></h1>
    </div>

    <?php if ($plan): ?>
    <!-- Current plan card -->
    <div class="bg-white border border-slate-200 rounded-2xl p-6 mb-6 flex items-center justify-between">
        <div>
            <?php $pname = json_decode($plan['name'] ?? '{}', true); ?>
            <div class="text-xs uppercase text-slate-400 mb-1"><?= e(__('subscription.current_plan')) ?></div>
            <div class="text-2xl font-bold text-slate-900"><?= e($pname[$locale] ?? $plan['code']) ?></div>
            <div class="text-sm text-slate-500 mt-1">
                <?= e(__('subscription.status')) ?>:
                <span class="px-2 py-0.5 rounded-full text-xs <?= $plan['subscription']['status'] === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' ?>">
                    <?= e(__('subscription.' . $plan['subscription']['status'])) ?>
                </span>
                <?php if ($plan['subscription']['trial_ends_at']): ?> · <?= e(__('subscription.trial_ends')) ?>: <?= e(format_date($plan['subscription']['trial_ends_at'])) ?><?php endif; ?>
            </div>
        </div>
        <div class="text-right">
            <div class="text-3xl font-extrabold text-brand-600"><?= e(money($plan['price_monthly'])) ?>/ay</div>
        </div>
    </div>

    <!-- Usage -->
    <div class="bg-white border border-slate-200 rounded-2xl p-6 mb-6">
        <h2 class="font-semibold text-slate-800 mb-4"><?= e(__('subscription.usage')) ?></h2>
        <div class="grid md:grid-cols-2 gap-3">
            <?php foreach ($subRows as $key => $label):
                $limit = subLimit($features, $key);
                $used = (int) ($usage[$key] ?? 0);
                $pct = $limit > 0 ? min(100, round($used / $limit * 100)) : 0;
                $isUnlimited = $limit >= 99999;
                $limitLabel = $isUnlimited ? __('subscription.unlimited') : __('subscription.used_of', ['used' => $used, 'total' => $limit]);
            ?>
            <div>
                <div class="flex justify-between text-sm mb-1">
                    <span class="text-slate-600"><?= e($label) ?></span>
                    <span class="font-medium"><?= $isUnlimited ? e($limitLabel) : e($limitLabel) ?></span>
                </div>
                <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full <?= $pct >= 90 ? 'bg-red-500' : 'bg-brand-600' ?>" style="width:<?= $isUnlimited ? 0 : (int)$pct ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Plans -->
    <h2 class="font-semibold text-slate-800 mb-4"><?= e(__('subscription.plans')) ?></h2>
    <div class="grid md:grid-cols-4 gap-4">
        <?php foreach ($plans as $p):
            $feats = json_decode($p['features'], true);
            $pname = json_decode($p['name'], true);
            $isCurrent = $plan && (int)$plan['id'] === (int)$p['id'];
        ?>
        <div class="rounded-2xl border <?= $isCurrent ? 'border-brand-600 ring-2 ring-brand-600/20' : 'border-slate-200' ?> bg-white p-5 flex flex-col">
            <h3 class="font-bold text-slate-900"><?= e($pname[$locale] ?? $p['code']) ?></h3>
            <div class="mt-1 text-2xl font-extrabold text-slate-900"><?= $feats['companies'] >= 99999 ? __('subscription.unlimited') . ' ₺' : e(money($p['price_monthly'])) ?><span class="text-xs font-normal text-slate-400">/ay</span></div>
            <ul class="mt-3 space-y-1 text-xs text-slate-500 flex-1">
                <li>✓ <?= e(__('plans.companies', ['count' => $feats['companies'] ?? 0])) ?></li>
                <li>✓ <?= e(__('plans.users', ['count' => $feats['users'] ?? 0])) ?></li>
                <li>✓ <?= e(__('plans.warehouses', ['count' => $feats['warehouses'] ?? 0])) ?></li>
                <li>✓ <?= e(__('plans.invoices', ['count' => $feats['invoices'] ?? 0])) ?></li>
            </ul>
            <form method="post" action="<?= e(url('/app/settings/subscription/subscribe')) ?>" class="mt-4">
                <?= csrf_field() ?>
                <input type="hidden" name="plan" value="<?= e($p['code']) ?>">
                <input type="hidden" name="cycle" value="monthly">
                <button <?= $isCurrent ? 'disabled' : '' ?> class="w-full px-4 py-2 rounded-xl text-sm font-semibold <?= $isCurrent ? 'bg-slate-100 text-slate-400' : 'bg-brand-600 text-white hover:bg-brand-700' ?>">
                    <?= $isCurrent ? __('subscription.current') : __('subscription.subscribe') ?>
                </button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
</div>
