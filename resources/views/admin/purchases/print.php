<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title><?= e($purchase['purchase_no']) ?></title><?= App\Core\View::capture('components/print_head') ?></head>
<body><div class="doc">
<?php if (!empty($print)): ?><div class="no-print"><button onclick="window.print()">Print</button></div><?php endif; ?>
<table class="doc-head"><tr>
  <td><div class="company"><?= e(setting('company_name', config('app.name'))) ?></div><div class="muted"><?= e(setting('contact_address')) ?><br><?= e(setting('contact_phone')) ?> · <?= e(setting('contact_email')) ?></div></td>
  <td><div class="doc-title"><?= $purchase['status'] === 'draft' ? 'PURCHASE ORDER' : 'PURCHASE INVOICE' ?></div><div class="r muted"><?= e($purchase['purchase_no']) ?><br><?= e(fdate($purchase['purchase_date'])) ?></div>
  <?php if ($purchase['status'] === 'cancelled'): ?><div class="r"><span class="stamp">CANCELLED</span></div><?php endif; ?></td>
</tr></table>
<table style="width:100%"><tr>
  <td><strong>Supplier</strong><br><?= e($supplier['name']) ?><br><span class="muted"><?= e($supplier['address']) ?><br><?= e($supplier['contact_number']) ?></span></td>
  <td class="r"><strong>Deliver to</strong><br><?= e($farm['name']) ?><br><span class="muted"><?= e($farm['location']) ?></span></td>
</tr></table>
<table class="items"><thead><tr><th>Item</th><th>Type</th><th class="r">Qty</th><th class="r">Unit price</th><th class="r">Total</th></tr></thead><tbody>
<?php foreach ($items as $i): ?><tr><td><?= e($i['description']) ?><?= $i['lot_no'] ? '<br><span class="muted">Lot ' . e($i['lot_no']) . '</span>' : '' ?></td><td><?= e(label_of($i['item_type'])) ?></td><td class="r"><?= e(rtrim(rtrim(num($i['quantity'], 3), '0'), '.')) ?></td><td class="r"><?= e(money($i['unit_price'], false)) ?></td><td class="r"><?= e(money($i['line_total'], false)) ?></td></tr><?php endforeach; ?>
</tbody></table>
<table class="sum">
  <tr class="total"><td>Total</td><td class="r"><?= e(money($purchase['total_amount'])) ?></td></tr>
  <tr><td>Paid</td><td class="r"><?= e(money($purchase['paid_amount'], false)) ?></td></tr>
  <tr><td><strong>Balance</strong></td><td class="r"><strong><?= e(money((float) $purchase['total_amount'] - (float) $purchase['paid_amount'], false)) ?></strong></td></tr>
</table>
<div class="foot"><?= e($purchase['notes']) ?></div>
</div>
<?php if (!empty($print)): ?><script>window.addEventListener('load',function(){window.print()})</script><?php endif; ?>
</body></html>
