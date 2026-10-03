<?php
/** @var array $invoice @var array $items @var array|null $brand */
$locale = \Muh\Core\Translator::instance()->locale();
$brand = $brand ?? [
    'name' => $invoice['company_name'] ?? '', 'trade_name' => $invoice['trade_name'] ?? '',
    'logo_url' => null, 'tax_number' => $invoice['tax_number'] ?? '', 'tax_office' => $invoice['tax_office'] ?? '',
    'mersis' => $invoice['mersis'] ?? '', 'address' => $invoice['address'] ?? '',
    'phone' => $invoice['company_phone'] ?? '', 'email' => $invoice['company_email'] ?? '',
    'website' => $invoice['website'] ?? '', 'currency' => $invoice['currency'] ?? 'TRY', 'iban' => '',
];
$symbols = ['TRY' => '₺', 'USD' => '$', 'EUR' => '€', 'GBP' => '£'];
$cur = strtoupper((string) ($brand['currency'] ?: $invoice['currency'] ?? 'TRY'));
$sym = $symbols[$cur] ?? $cur;
$init = mb_strtoupper(mb_substr(trim($brand['name']) ?: 'H', 0, 1));
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
  .head { display:flex; justify-content:space-between; align-items:flex-start; gap:20px; padding-bottom:20px; border-bottom:3px solid #2b55e0; }
  .brand { display:flex; gap:14px; align-items:center; min-width:0; }
  .logo { width:72px; height:72px; border-radius:14px; object-fit:contain; background:#fff; }
  .logo-img { width:72px; height:72px; object-fit:contain; }
  .mono { width:72px; height:72px; border-radius:14px; background:#2b55e0; color:#fff; display:flex; align-items:center; justify-content:center; font-size:34px; font-weight:800; }
  .co { font-size:20px; font-weight:800; color:#0f172a; line-height:1.2; }
  .meta { color:#64748b; font-size:12px; line-height:1.55; }
  .doctitle { text-align:right; flex-shrink:0; }
  .doctitle .t { font-size:24px; font-weight:800; color:#2b55e0; }
  .doctitle .no { font-family:ui-monospace,monospace; color:#334155; font-size:14px; margin-top:4px; }
  .billto { margin:22px 0; display:flex; gap:24px; flex-wrap:wrap; }
  .billto .box { min-width:230px; }
  .billto .lbl { font-size:10px; text-transform:uppercase; letter-spacing:.05em; color:#94a3b8; }
  table { width:100%; border-collapse:collapse; font-size:13px; }
  thead th { text-align:left; border-bottom:2px solid #e2e8f0; padding:8px 4px; color:#94a3b8; font-size:10px; text-transform:uppercase; letter-spacing:.03em; }
  thead th.r, tbody td.r, .totals .r { text-align:right; }
  tbody td { padding:9px 4px; border-bottom:1px solid #f1f5f9; }
  .totals { margin-left:auto; max-width:330px; margin-top:20px; font-size:13px; }
  .totals > div { display:flex; justify-content:space-between; padding:4px 0; color:#475569; }
  .totals .grand { font-size:17px; font-weight:800; color:#2b55e0; border-top:2px solid #e2e8f0; margin-top:6px; padding-top:10px; }
  .notes { margin-top:28px; font-size:12px; color:#64748b; white-space:pre-wrap; }
  .footer { margin-top:36px; border-top:1px solid #e2e8f0; padding-top:16px; font-size:12px; color:#64748b; display:flex; justify-content:space-between; gap:20px; flex-wrap:wrap; }
  .footer strong { color:#334155; }
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
    <!-- Branded header -->
    <div class="head">
      <div class="brand">
        <?php if (!empty($brand['logo_url'])): ?>
          <img class="logo-img" src="<?= e($brand['logo_url']) ?>" alt="">
        <?php else: ?>
          <div class="mono"><?= e($init) ?></div>
        <?php endif; ?>
        <div>
          <div class="co"><?= e($brand['name']) ?></div>
          <?php if (!empty($brand['trade_name'])): ?><div class="meta"><?= e($brand['trade_name']) ?></div><?php endif; ?>
          <div class="meta">
            <?php if (!empty($brand['tax_number'])): ?><div><?= e(__('common.tax_number')) ?>: <?= e($brand['tax_number']) ?><?= !empty($brand['tax_office']) ? ' · ' . e($brand['tax_office']) : '' ?></div><?php endif; ?>
            <?php if (!empty($brand['address'])): ?><div><?= e($brand['address']) ?></div><?php endif; ?>
            <?php if (!empty($brand['phone'])): ?><div>☎ <?= e($brand['phone']) ?></div><?php endif; ?>
            <?php if (!empty($brand['email'])): ?><div>✉ <?= e($brand['email']) ?></div><?php endif; ?>
          </div>
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

    <!-- Bill to / seller -->
    <div class="billto">
      <div class="box">
        <div class="lbl"><?= e(__('invoice.customer')) ?></div>
        <div style="font-weight:700;color:#0f172a;"><?= e($invoice['account_name']) ?></div>
        <div class="meta"><?= e($invoice['account_tax'] ?? '') ?><?= $invoice['account_address'] ? ' · ' . e($invoice['account_address']) : '' ?></div>
      </div>
      <div class="box">
        <div class="lbl"><?= e(__('invoice.status')) ?></div>
        <div class="meta" style="text-transform:capitalize;">
          <?= e(__('invoice.' . $invoice['status'])) ?>
          <?php if (in_array($invoice['type'], ['sales', 'sales_return'], true)): ?> · <?= e(__('invoice.due_date')) ?>: <?= e(format_date($invoice['due_date'])) ?><?php endif; ?>
        </div>
      </div>
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
      <div><span><?= e(__('invoice.subtotal')) ?></span><span><?= e(number_format((float)$invoice['subtotal'],2,',','.')) ?> <?= e($sym) ?></span></div>
      <div><span><?= e(__('invoice.discount')) ?></span><span>-<?= e(number_format((float)$invoice['discount'],2,',','.')) ?> <?= e($sym) ?></span></div>
      <div><span><?= e(__('invoice.tax')) ?></span><span><?= e(number_format((float)$invoice['tax'],2,',','.')) ?> <?= e($sym) ?></span></div>
      <?php $withholding = (float)($invoice['withholding'] ?? 0); if ($withholding): ?>
      <div><span><?= e(__('report.vat_withholding')) ?></span><span>-<?= e(number_format($withholding,2,',','.')) ?> <?= e($sym) ?></span></div>
      <div><span><?= e(__('invoice.net_vat')) ?></span><span><?= e(number_format((float)$invoice['tax'] - $withholding,2,',','.')) ?> <?= e($sym) ?></span></div>
      <?php endif; ?>
      <div class="grand"><span><?= e($sym) ?> <?= e(__('invoice.total')) ?></span><span><?= e(number_format((float)$invoice['total'],2,',','.')) ?> <?= e($sym) ?></span></div>
    </div>

    <?php if ($invoice['notes']): ?>
    <div class="notes"><?= e($invoice['notes']) ?></div>
    <?php endif; ?>

    <!-- Footer -->
    <div class="footer">
      <div>
        <div style="font-weight:700;color:#334155;margin-bottom:4px;"><?= e($brand['name']) ?></div>
        <?php if (!empty($brand['mersis'])): ?><div>MERSİS: <?= e($brand['mersis']) ?></div><?php endif; ?>
        <?php if (!empty($brand['website'])): ?><div><?= e($brand['website']) ?></div><?php endif; ?>
      </div>
      <div style="text-align:right;">
        <?php if (!empty($brand['iban'])): ?>
          <div><strong>IBAN</strong>: <?= e($brand['iban']) ?></div>
        <?php endif; ?>
        <div style="margin-top:4px;"><?= e(__('invoice.thanks')) ?></div>
      </div>
    </div>
  </div>
</body>
</html>
