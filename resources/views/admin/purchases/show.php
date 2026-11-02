<?php $due = (float) $purchase['total_amount'] - (float) $purchase['paid_amount']; ?>
<div class="page-head">
  <div><h1 class="page-title"><i class="bi bi-cart-plus"></i> <?= e($purchase['purchase_no']) ?></h1><div class="d-flex gap-2 mt-1"><?= $purchase['status'] === 'draft' ? '<span class="badge rounded-pill text-bg-warning">Purchase order</span>' : status_badge($purchase['status']) ?><?= status_badge($purchase['payment_status']) ?></div></div>
  <div class="d-flex flex-wrap gap-2">
    <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/purchases')) ?>"><i class="bi bi-arrow-left me-1"></i>Back</a>
    <a class="btn btn-outline-secondary btn-sm" href="<?= e(url("admin/purchases/{$purchase['id']}/print")) ?>" target="_blank" rel="noopener"><i class="bi bi-printer me-1"></i>Print</a>
    <a class="btn btn-outline-secondary btn-sm" href="<?= e(url("admin/purchases/{$purchase['id']}/pdf")) ?>"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>
    <?php if (can('purchases.manage') && $purchase['status'] === 'draft'): ?>
      <a class="btn btn-outline-secondary btn-sm" href="<?= e(url("admin/purchases/{$purchase['id']}/edit")) ?>"><i class="bi bi-pencil me-1"></i>Edit</a>
      <form method="post" action="<?= e(url("admin/purchases/{$purchase['id']}/confirm")) ?>" data-confirm="Confirm this purchase and add the items to stock?" data-confirm-label="Confirm purchase"><?= csrf_field() ?><button class="btn btn-primary btn-sm"><i class="bi bi-check2-circle me-1"></i>Confirm</button></form>
    <?php endif; ?>
    <?php if (can('payments.manage') && $purchase['status'] === 'confirmed' && $due > 0): ?>
      <button class="btn btn-primary btn-sm" data-pay data-doc-type="purchase" data-doc-id="<?= (int) $purchase['id'] ?>" data-due="<?= e($due) ?>"><i class="bi bi-cash-coin me-1"></i>Record payment</button>
    <?php endif; ?>
    <?php if (can('purchases.manage') && $purchase['status'] !== 'cancelled'): ?>
      <form method="post" action="<?= e(url("admin/purchases/{$purchase['id']}/cancel")) ?>" data-cancel="Cancel this purchase? Stock is removed and any payments are voided." data-cancel-label="Cancel purchase"><?= csrf_field() ?><input type="hidden" name="reason"><button class="btn btn-outline-danger btn-sm"><i class="bi bi-x-circle me-1"></i>Cancel purchase</button></form>
    <?php endif; ?>
  </div>
</div>
<?php if ($purchase['status'] === 'cancelled'): ?><div class="alert alert-danger">Cancelled on <?= e(fdate($purchase['cancelled_at'])) ?><?= $purchase['cancel_reason'] ? ': ' . e($purchase['cancel_reason']) : '' ?>. Its stock and money effects were reversed.</div><?php endif; ?>
<div class="row g-4">
  <div class="col-lg-8"><div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Item</th><th>Type</th><th>Lot / expiry</th><th class="text-end">Qty</th><th class="text-end">Unit price</th><th class="text-end">Total</th></tr></thead><tbody>
    <?php foreach ($items as $i): ?><tr><td><?= e($i['description']) ?></td><td><?= e(label_of($i['item_type'])) ?></td><td class="small"><?= e($i['lot_no']) ?><?= $i['expiry_date'] ? '<br>' . e(fdate($i['expiry_date'])) : '' ?></td><td class="text-end"><?= e(rtrim(rtrim(num($i['quantity'], 3), '0'), '.')) ?></td><td class="text-end"><?= e(money($i['unit_price'], false)) ?></td><td class="text-end"><?= e(money($i['line_total'], false)) ?></td></tr><?php endforeach; ?>
  </tbody></table></div></div></div>
  <div class="col-lg-4"><div class="card"><div class="card-body">
    <dl class="row small mb-3"><dt class="col-5">Supplier</dt><dd class="col-7"><a href="<?= e(url('admin/suppliers/' . $supplier['id'])) ?>"><?= e($supplier['name']) ?></a></dd><dt class="col-5">Farm</dt><dd class="col-7"><?= e($farm['name']) ?></dd><dt class="col-5">Date</dt><dd class="col-7"><?= e(fdate($purchase['purchase_date'])) ?></dd><dt class="col-5">Their invoice</dt><dd class="col-7"><?= e($purchase['supplier_invoice_no'] ?: '—') ?></dd></dl>
    <table class="table table-sm mb-0">
      <tr class="fw-bold"><td>Total</td><td class="text-end"><?= e(money($purchase['total_amount'])) ?></td></tr>
      <tr><td>Paid</td><td class="text-end"><?= e(money($purchase['paid_amount'], false)) ?></td></tr>
      <tr class="fw-bold"><td>Remaining</td><td class="text-end"><?= e(money($due, false)) ?></td></tr>
    </table>
  </div></div></div>
</div>
<?= App\Core\View::capture('admin/partials/doc_payments', ['payments' => $payments]) ?>
<?= App\Core\View::capture('admin/partials/pay_modal', ['accounts' => $accounts]) ?>
