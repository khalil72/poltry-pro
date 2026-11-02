<?php $due = (float) $sale['grand_total'] - (float) $sale['paid_amount']; ?>
<div class="page-head">
  <div><h1 class="page-title"><i class="bi bi-receipt"></i> <?= e($sale['invoice_no']) ?></h1><div class="d-flex gap-2 mt-1"><?= status_badge($sale['status']) ?><?= status_badge($sale['payment_status']) ?></div></div>
  <div class="d-flex flex-wrap gap-2">
    <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/sales')) ?>"><i class="bi bi-arrow-left me-1"></i>Back</a>
    <a class="btn btn-outline-secondary btn-sm" href="<?= e(url("admin/sales/{$sale['id']}/print")) ?>" target="_blank" rel="noopener"><i class="bi bi-printer me-1"></i>Print</a>
    <a class="btn btn-outline-secondary btn-sm" href="<?= e(url("admin/sales/{$sale['id']}/pdf")) ?>"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>
    <?php if (can('sales.manage') && $sale['status'] === 'draft'): ?>
      <a class="btn btn-outline-secondary btn-sm" href="<?= e(url("admin/sales/{$sale['id']}/edit")) ?>"><i class="bi bi-pencil me-1"></i>Edit</a>
      <form method="post" action="<?= e(url("admin/sales/{$sale['id']}/confirm")) ?>" data-confirm="Confirm this invoice and take the items out of stock?" data-confirm-label="Confirm invoice"><?= csrf_field() ?><button class="btn btn-primary btn-sm"><i class="bi bi-check2-circle me-1"></i>Confirm</button></form>
    <?php endif; ?>
    <?php if (can('payments.manage') && $sale['status'] === 'confirmed' && $due > 0): ?>
      <button class="btn btn-primary btn-sm" data-pay data-doc-type="sale" data-doc-id="<?= (int) $sale['id'] ?>" data-due="<?= e($due) ?>"><i class="bi bi-cash-coin me-1"></i>Record payment</button>
    <?php endif; ?>
    <?php if (can('sales.manage') && $sale['status'] !== 'cancelled'): ?>
      <form method="post" action="<?= e(url("admin/sales/{$sale['id']}/cancel")) ?>" data-cancel="Cancel this invoice? Stock returns and any payments are voided." data-cancel-label="Cancel invoice"><?= csrf_field() ?><input type="hidden" name="reason"><button class="btn btn-outline-danger btn-sm"><i class="bi bi-x-circle me-1"></i>Cancel invoice</button></form>
    <?php endif; ?>
  </div>
</div>
<?php if ($sale['status'] === 'cancelled'): ?><div class="alert alert-danger">Cancelled on <?= e(fdate($sale['cancelled_at'])) ?><?= $sale['cancel_reason'] ? ': ' . e($sale['cancel_reason']) : '' ?>. Its stock and money effects were reversed.</div><?php endif; ?>
<div class="row g-4">
  <div class="col-lg-8"><div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Item</th><th class="text-end">Qty</th><th class="text-end">Unit price</th><th class="text-end">Total</th></tr></thead><tbody>
    <?php foreach ($items as $i): ?><tr><td><?= e($i['description']) ?></td><td class="text-end"><?= e(rtrim(rtrim(num($i['quantity'], 3), '0'), '.')) ?></td><td class="text-end"><?= e(money($i['unit_price'], false)) ?></td><td class="text-end"><?= e(money($i['line_total'], false)) ?></td></tr><?php endforeach; ?>
  </tbody></table></div></div></div>
  <div class="col-lg-4"><div class="card"><div class="card-body">
    <dl class="row small mb-3"><dt class="col-5">Customer</dt><dd class="col-7"><a href="<?= e(url('admin/customers/' . $customer['id'])) ?>"><?= e($customer['name']) ?></a></dd><dt class="col-5">Farm</dt><dd class="col-7"><?= e($farm['name']) ?></dd><dt class="col-5">Date</dt><dd class="col-7"><?= e(fdate($sale['sale_date'])) ?></dd></dl>
    <table class="table table-sm mb-0">
      <tr><td>Subtotal</td><td class="text-end"><?= e(money($sale['subtotal'], false)) ?></td></tr>
      <tr><td>Discount</td><td class="text-end"><?= e(money($sale['discount'], false)) ?></td></tr>
      <tr><td>Tax (<?= e((float) $sale['tax_rate']) ?>%)</td><td class="text-end"><?= e(money($sale['tax_amount'], false)) ?></td></tr>
      <tr class="fw-bold"><td>Grand total</td><td class="text-end"><?= e(money($sale['grand_total'])) ?></td></tr>
      <tr><td>Paid</td><td class="text-end"><?= e(money($sale['paid_amount'], false)) ?></td></tr>
      <tr class="fw-bold"><td>Remaining</td><td class="text-end"><?= e(money($due, false)) ?></td></tr>
    </table>
  </div></div></div>
</div>
<?= App\Core\View::capture('admin/partials/doc_payments', ['payments' => $payments]) ?>
<?= App\Core\View::capture('admin/partials/pay_modal', ['accounts' => $accounts]) ?>
