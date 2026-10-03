<?php
/** @var array $documents @var array $companies @var int $companyId */
use Muh\Core\Session;
$errors = Session::get('_form_errors', []);
Session::forget('_form_errors');
$cats = ['invoice','receipt','contract','bank_statement','other'];
?>
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-900"><?= e(__('document.title')) ?></h1>
        <p class="text-slate-500"><?= count($documents) ?> belge</p>
    </div>
</div>

<div class="grid lg:grid-cols-3 gap-6">
    <!-- Upload form -->
    <div class="lg:col-span-1 bg-white border border-slate-200 rounded-2xl p-5 h-fit">
        <h3 class="font-semibold text-slate-800 mb-4"><?= e(__('document.new')) ?></h3>
        <form method="post" action="<?= e(url('/app/documents')) ?>" enctype="multipart/form-data" class="space-y-3">
            <?= csrf_field() ?>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1"><?= e(__('document.company')) ?></label>
                <select name="company_id" class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                    <?php foreach ($companies as $c): ?><option value="<?= e($c['id']) ?>" <?= (int)$companyId===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1"><?= e(__('document.category')) ?></label>
                <select name="category" class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
                    <?php foreach ($cats as $c): ?><option value="<?= $c ?>"><?= e(__('document.cat_' . $c)) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1"><?= e(__('document.file')) ?></label>
                <input type="file" name="file" class="w-full text-sm border border-slate-200 rounded-lg px-2 py-2">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1"><?= e(__('document.notes')) ?></label>
                <input name="notes" class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:ring-2 focus:ring-brand-500 outline-none">
            </div>
            <?php if ($errors): ?><div class="p-2 rounded-lg bg-red-50 text-xs text-red-700"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>
            <button class="w-full px-4 py-2 rounded-lg bg-brand-600 text-white text-sm font-semibold"><?= e(__('document.upload')) ?></button>
        </form>
    </div>

    <!-- List -->
    <div class="lg:col-span-2 bg-white border border-slate-200 rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-xs text-slate-400 uppercase bg-slate-50">
                    <tr>
                        <th class="px-5 py-3"><?= e(__('document.name')) ?></th>
                        <th class="px-5 py-3"><?= e(__('document.category')) ?></th>
                        <th class="px-5 py-3"><?= e(__('document.size')) ?></th>
                        <th class="px-5 py-3"><?= e(__('document.date')) ?></th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if (!$documents): ?><tr><td colspan="5" class="px-5 py-8 text-center text-slate-400"><?= e(__('document.no_documents')) ?></td></tr><?php endif; ?>
                    <?php foreach ($documents as $d): ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-5 py-3">
                            <div class="font-medium text-slate-800"><?= e($d['original_name']) ?></div>
                            <div class="text-xs text-slate-400"><?= e($d['uploaded_by_name'] ?? '') ?></div>
                        </td>
                        <td class="px-5 py-3"><span class="px-2 py-0.5 rounded-full text-xs bg-slate-100 text-slate-600"><?= e(__('document.cat_' . $d['category'])) ?></span></td>
                        <td class="px-5 py-3 text-slate-500"><?= e(number_format((float)$d['size'] / 1024, 1, ',', '.') . ' KB') ?></td>
                        <td class="px-5 py-3 text-slate-500"><?= e(format_date($d['created_at'])) ?></td>
                        <td class="px-5 py-3 text-right space-x-2">
                            <a href="<?= e(url('/app/documents/' . $d['id'] . '/download')) ?>" class="text-brand-600 text-xs font-medium"><?= e(__('document.download')) ?></a>
                            <form method="post" action="<?= e(url('/app/documents/' . $d['id'] . '/delete')) ?>" class="inline" onsubmit="return confirm('delete?')">
                                <?= csrf_field() ?>
                                <button class="text-red-500 text-xs"><?= e(__('common.delete')) ?></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
