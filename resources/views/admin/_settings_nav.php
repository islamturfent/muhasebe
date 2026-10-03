<?php
/** @var string $activeTab */
$tabs = [
    'general' => ['label' => __('admin.general_settings'), 'url' => '/admin/settings'],
    'session' => ['label' => __('admin.session_security'), 'url' => '/admin/settings/session'],
];
?>
<div class="flex flex-wrap gap-2 mb-6">
    <?php foreach ($tabs as $key => $tab):
        $active = ($activeTab === $key)
            ? 'text-brand-600 bg-brand-50 border-brand-200'
            : 'text-slate-600 hover:bg-slate-100 border-transparent';
    ?>
    <a href="<?= e(url($tab['url'])) ?>" class="px-4 py-2 rounded-lg border text-sm font-medium <?= $active ?>"><?= e($tab['label']) ?></a>
    <?php endforeach; ?>
</div>
