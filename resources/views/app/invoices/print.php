<?php
/** @var array $invoice @var array $items */
$locale = \Muh\Core\Translator::instance()->locale();
?>
<!DOCTYPE html>
<html lang="<?= $locale ?>">
<head>
<meta charset="UTF-8">
<title><?= e(__('invoice.title')) ?> · <?= e($invoice['number']) ?></title>
<style>
  * { box-sizing: border-box; }
  body { font-family: -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color:#1e293b; margin:0; padding:32px; }
  .wrap { max-width:760px; margin:0 auto; }
  .head { display:flex; justify-content:space-between; align-items:flex-start; border-bottom:2px solid #e2e8f0; padding-bottom:20px; }
  .co { font-size:20px; font-weight:800; }
  .meta { color:#64748b; font-size:13px; line-height:1.6; }
  .doctitle { text-align:right; }
  .doctitle .t { font-size:26px; font-weight:800; }
  .doctitle .no { font-family:ui-monospace,monospace; color:#2b55e0; font-size:14px; margin-top:4px; }
  .billto { margin:20px 0; }
  .billto .lbl { font-size:11px; text-transform:uppercase; color:#94a3b8; }
  table { width:100%; border-collapse:collapse; font-size:13px; }
  thead th { text-align:left; border-bottom:1px solid #e2e8f0; padding:8px 4px; color:#94a3b8; font-size:11px; text-transform:uppercase; }
  thead th.r, tbody td.r, .totals .r { text-align:right; }
  tbody td { padding:9px 4px; border-bottom:1px solid #f1f5f9; }
  .totals { margin-left:auto; max-width:320px; margin-top:20px; font-size:13px; }
  .totals > div { display:flex; justify-content:space-between; padding:4px 0; color:#475569; }
  .totals .grand { font-size:17px; font-weight:800; color:#0f172a; border-top:2px solid #e2e8f0; margin-top:6px; padding-top:10px; }
  .notes { margin-top:28px; font-size:12px; color:#64748b; white-space:pre-wrap; }
  @media print { body { padding:0; } .noprint { display:none; } }
  .toolbar { max-width:760px; margin:0 auto 16px; display:flex; gap:8px; }
  .toolbar button { padding:8px 16px; border-radius:8px; border:1px solid #cbd5e1; background:#fff; cursor:pointer; font-size:13px; }
  .toolbar button.primary { background:#2b55e0; color:#fff; border-color:#2b55e0; }
</style>
</head>
<body>
  <div class="toolbar noprint">
    <button class="primary" onclick="window.print()">🖨 <?= e(__('invoice.print')) ?></button>
    <button onclick="window.close()"><?= e(__('common.close')) ?></button>
  </div>
  <div class="wrap">
    <!-- Header -->
    <div class="head">
      <div>
        <div class="co"><?= e($invoice['company_name']) ?></div>
        <div class="meta">
          <?php if (!empty($invoice['trade_name'])): ?><div><?= e($invoice['trade_name']) ?></div><?php endif; ?>
          <?php if (!empty($invoice['tax_number'])): ?><div><?= e(__('invoice.document_no')) ?> / <?= e(__('common.tax_number')) ?>: <?= e($invoice['tax_number']) ?></div><?php endif; ?>
          <?php if (!empty($invoice['address'])): ?><div><?= e($invoice['address']) ?></div><?php endif; ?>
          <?php if (!empty($invoice['company_phone'])): ?><div><?= e($invoice['company_phone']) ?></div><?php endif; ?>
        </div>
      </div>
      <div class="doctitle">
        <div class="t"><?= e(__('invoice.type_' . $invoice['type'])) ?></div>
        <div class="no"><?= e($invoice['number']) ?></div>
        <div class="meta">
          <div><?= e(__('invoice.date')) ?>: <?= e(format_date($invoice['date'])) ?></div>
          <div><?= e(__('invoice.due_date')) ?>: <?= e(format_date($invoice['due_date'])) ?></div>
        </div>
      </div>
    </div>

    <!-- Bill to -->
    <div class="billto">
      <div class="lbl"><?= e(__('invoice.customer')) ?></div>
      <div style="font-weight:600;"><?= e($invoice['account_name']) ?></div>
      <div class="meta"><?= e($invoice['account_tax'] ?? '') ?><?= $invoice['account_address'] ? ' · ' . e($invoice['account_address']) : '' ?></div>
    </div>

    <!-- Items -->
    <table>
      <thead>
        <tr>
          <th><?= e(__('invoice.line_description')) ?></th>
          <th class="r"><?= e(__('invoice.line_qty')) ?></th>
          <th class="r"><?= e(__('invoice.line_price')) ?></th>
          <th class="r">% <?= e(__('invoice.line_vat')) ?></th>
          <th class="r">% <?= e(__('invoice.line_withholding')) ?></th>
          <th class="r"><?= e(__('invoice.total')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $it): ?>
        <tr>
          <td><?= e($it['product_name'] ?? $it['description'] ?? '—') ?></td>
          <td class="r"><?= e((float)$it['quantity']) ?></td>
          <td class="r"><?= e(number_format((float)$it['unit_price'],2,',','.')) ?></td>
          <td class="r">%<?= e((float)$it['tax_rate']) ?></td>
          <td class="r">%<?= e((float)($it['withholding_rate'] ?? 0)) ?></td>
          <td class="r"><?= e(number_format((float)$it['total'],2,',','.')) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <!-- Totals -->
    <div class="totals">
      <div><span><?= e(__('invoice.subtotal')) ?></span><span><?= e(number_format((float)$invoice['subtotal'],2,',','.')) ?></span></div>
      <div><span><?= e(__('invoice.discount')) ?></span><span>-<?= e(number_format((float)$invoice['discount'],2,',','.')) ?></span></div>
      <div><span><?= e(__('invoice.tax')) ?></span><span><?= e(number_format((float)$invoice['tax'],2,',','.')) ?></span></div>
      <?php $withholding = (float)($invoice['withholding'] ?? 0); if ($withholding): ?>
      <div><span><?= e(__('report.vat_withholding')) ?></span><span>-<?= e(number_format($withholding,2,',','.')) ?></span></div>
      <div><span><?= e(__('invoice.net_vat')) ?></span><span><?= e(number_format((float)$invoice['tax'] - $withholding,2,',','.')) ?></span></div>
      <?php endif; ?>
      <div class="grand"><span><?= e(__('invoice.total')) ?></span><span><?= e(number_format((float)$invoice['total'],2,',','.')) ?></span></div>
    </div>

    <?php if ($invoice['notes']): ?>
    <div class="notes"><?= e($invoice['notes']) ?></div>
    <?php endif; ?>
  </div>
</body>
</html>
