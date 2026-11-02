<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title><?= e($sale['invoice_no']) ?></title><?= App\Core\View::capture('components/print_head') ?></head>
<body><div class="doc">
<?php if (!empty($print)): ?><div class="no-print"><button onclick="window.print()">Print</button></div><?php endif; ?>
<table class="doc-head"><tr>
  <td><div class="company"><?= e(setting('company_name', config('app.name'))) ?></div><div class="muted"><?= e(setting('contact_address')) ?><br><?= e(setting('contact_phone')) ?> · <?= e(setting('contact_email')) ?></div></td>
  <td><div class="doc-title">INVOICE</div><div class="r muted"><?= e($sale['invoice_no']) ?><br><?= e(fdate($sale['sale_date'])) ?></div>
  <?php if ($sale['status'] === 'cancelled'): ?><div class="r"><span class="stamp">CANCELLED</span></div><?php elseif ($sale['status'] === 'draft'): ?><div class="r"><span class="stamp">DRAFT</span></div><?php endif; ?></td>
</tr></table>
<table style="width:100%"><tr>
  <td><strong>Bill to</strong><br><?= e($customer['name']) ?><?= $customer['business_name'] ? '<br>' . e($customer['business_name']) : '' ?><br><span class="muted"><?= e($customer['address']) ?><br><?= e($customer['phone']) ?></span></td>
  <td class="r"><strong>From farm</strong><br><?= e($farm['name']) ?><br><span class="muted"><?= e($farm['location']) ?></span></td>
</tr></table>
<table class="items"><thead><tr><th>Item</th><th class="r">Qty</th><th class="r">Unit price</th><th class="r">Total</th></tr></thead><tbody>
<?php foreach ($items as $i): ?><tr><td><?= e($i['description']) ?></td><td class="r"><?= e(rtrim(rtrim(num($i['quantity'], 3), '0'), '.')) ?></td><td class="r"><?= e(money($i['unit_price'], false)) ?></td><td class="r"><?= e(money($i['line_total'], false)) ?></td></tr><?php endforeach; ?>
</tbody></table>
<table class="sum">
  <tr><td>Subtotal</td><td class="r"><?= e(money($sale['subtotal'], false)) ?></td></tr>
  <?php if ((float) $sale['discount'] > 0): ?><tr><td>Discount</td><td class="r">-<?= e(money($sale['discount'], false)) ?></td></tr><?php endif; ?>
  <?php if ((float) $sale['tax_amount'] > 0): ?><tr><td>Tax (<?= e((float) $sale['tax_rate']) ?>%)</td><td class="r"><?= e(money($sale['tax_amount'], false)) ?></td></tr><?php endif; ?>
  <tr class="total"><td>Grand total</td><td class="r"><?= e(money($sale['grand_total'])) ?></td></tr>
  <tr><td>Paid</td><td class="r"><?= e(money($sale['paid_amount'], false)) ?></td></tr>
  <tr><td><strong>Balance due</strong></td><td class="r"><strong><?= e(money((float) $sale['grand_total'] - (float) $sale['paid_amount'], false)) ?></strong></td></tr>
</table>
<div class="foot"><?= e($sale['notes']) ?><?= $sale['notes'] ? '<br>' : '' ?>Thank you for your business.</div>
</div>
<?php if (!empty($print)): ?><script>window.addEventListener('load',function(){window.print()})</script><?php endif; ?>
</body></html>
