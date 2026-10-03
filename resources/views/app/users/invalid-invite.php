<?php /** @var array $errors */ ?>
<div class="max-w-md mx-auto text-center">
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-10">
        <div class="text-4xl mb-3">🗝️</div>
        <h1 class="text-xl font-bold text-slate-900"><?= e(__('user.invalid_title')) ?></h1>
        <p class="text-sm text-slate-500 mt-2"><?= e(__('user.invalid_text')) ?></p>
        <a href="<?= e(url('/')) ?>" class="inline-block mt-5 px-6 py-3 rounded-xl bg-brand-600 text-white font-semibold hover:bg-brand-700"><?= e(__('user.go_home')) ?></a>
    </div>
</div>
