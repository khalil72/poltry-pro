<?php
$s = $sale ?? [];
$action = $sale ? "admin/sales/{$sale['id']}/save" : 'admin/sales/save';
$oldItems = old('items', null);
$lines = $oldItems ?: array_map(fn ($i) => [
    'item_type' => $i['item_type'],
    'ref_id' => match ($i['item_type']) { 'bird' => $i['batch_id'] . ':' . $i['shed_id'], 'egg' => $i['batch_id'], 'feed' => $i['feed_product_id'], default => $i['product_id'] },
    'quantity' => (float) $i['quantity'], 'unit_price' => (float) $i['unit_price'],
], $items);
?>
<div class="page-head"><h1 class="page-title"><i class="bi bi-receipt"></i> <?= e($title) ?></h1><a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/sales')) ?>"><i class="bi bi-arrow-left me-1"></i>Back to sales</a></div>
<form method="post" action="<?= e(url($action)) ?>" data-once id="docForm" data-kind="sale">
  <?= csrf_field() ?>
  <div class="card mb-4"><div class="card-body"><div class="row g-3">
    <div class="col-md-4"><label class="form-label">Customer <span class="text-danger">*</span></label><select class="form-select" name="customer_id" required><option value="">Choose…</option><?php foreach ($lk['customers'] as $o): ?><option value="<?= e($o['value']) ?>" <?= (string) old('customer_id', $s['customer_id'] ?? '') === (string) $o['value'] ? 'selected' : '' ?>><?= e($o['label']) ?></option><?php endforeach; ?></select><div class="form-text"><?php if (can('customers.manage')): ?><a href="<?= e(url('admin/customers/create')) ?>" target="_blank" rel="noopener">Add a new customer</a><?php endif; ?></div></div>
    <div class="col-md-4"><label class="form-label">Selling farm <span class="text-danger">*</span></label><select class="form-select" name="farm_id" id="docFarm" required><option value="">Choose…</option><?php foreach ($lk['farms'] as $o): ?><option value="<?= e($o['value']) ?>" <?= (string) old('farm_id', $s['farm_id'] ?? '') === (string) $o['value'] ? 'selected' : '' ?>><?= e($o['label']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-4"><label class="form-label">Invoice date <span class="text-danger">*</span></label><input class="form-control" type="date" name="sale_date" value="<?= e(old('sale_date', $s['sale_date'] ?? date('Y-m-d'))) ?>" required></div>
  </div></div></div>
  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center"><span>Items</span><button class="btn btn-sm btn-outline-primary" type="button" id="addLine"><i class="bi bi-plus-lg me-1"></i>Add item</button></div>
    <div class="table-responsive"><table class="table mb-0 align-middle line-table"><thead><tr><th style="width:150px">Sell</th><th>Item</th><th style="width:120px">Quantity</th><th style="width:140px">Unit price</th><th style="width:140px" class="text-end">Line total</th><th style="width:44px"></th></tr></thead><tbody id="lines"></tbody></table></div>
  </div>
  <div class="row g-4">
    <div class="col-lg-7"><div class="card"><div class="card-body">
      <div class="row g-3">
        <div class="col-sm-6"><label class="form-label">Discount</label><input class="form-control" type="number" min="0" step="0.01" name="discount" id="docDiscount" value="<?= e(old('discount', $s['discount'] ?? 0)) ?>"></div>
        <div class="col-sm-6"><label class="form-label">Tax rate (%)</label><input class="form-control" type="number" min="0" step="0.001" name="tax_rate" id="docTax" value="<?= e(old('tax_rate', $s['tax_rate'] ?? setting('default_tax_rate', '0'))) ?>"></div>
        <div class="col-sm-4"><label class="form-label">Paid now</label><input class="form-control" type="number" min="0" step="0.01" name="paid_amount" id="docPaid" value="<?= e(old('paid_amount', 0)) ?>"></div>
        <div class="col-sm-4"><label class="form-label">Into account</label><select class="form-select" name="account_id"><option value="">Choose…</option><?php foreach ($lk['accounts'] as $o): ?><option value="<?= e($o['value']) ?>" <?= (string) old('account_id') === (string) $o['value'] ? 'selected' : '' ?>><?= e($o['label']) ?></option><?php endforeach; ?></select></div>
        <div class="col-sm-4"><label class="form-label">Payment method</label><select class="form-select" name="method"><option value="cash">Cash</option><option value="bank_transfer">Bank transfer</option><option value="cheque">Cheque</option><option value="card">Card</option><option value="mobile_wallet">Mobile wallet</option></select></div>
        <div class="col-12"><label class="form-label">Notes</label><input class="form-control" name="notes" maxlength="500" value="<?= e(old('notes', $s['notes'] ?? '')) ?>"></div>
      </div>
    </div></div></div>
    <div class="col-lg-5"><div class="card"><div class="card-body">
      <table class="table table-sm mb-3 totals">
        <tr><td>Subtotal</td><td class="text-end" id="tSub">0.00</td></tr>
        <tr><td>Discount</td><td class="text-end" id="tDisc">0.00</td></tr>
        <tr><td>Tax</td><td class="text-end" id="tTax">0.00</td></tr>
        <tr class="fw-bold fs-5"><td>Grand total</td><td class="text-end" id="tGrand">0.00</td></tr>
        <tr><td>Remaining balance</td><td class="text-end" id="tDue">0.00</td></tr>
      </table>
      <div class="d-grid gap-2">
        <button class="btn btn-primary" name="save_as" value="confirm"><i class="bi bi-check2-circle me-1"></i>Confirm invoice</button>
        <button class="btn btn-outline-secondary" name="save_as" value="draft">Save as draft</button>
      </div>
      <div class="form-text mt-2">Confirming takes the items out of stock. A draft changes nothing, and the amount paid is ignored until it is confirmed.</div>
    </div></div></div>
  </div>
</form>
<script id="docData" type="application/json"><?= json_encode(['lk' => $lk, 'lines' => array_values((array) $lines)], JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= e(asset('js/documents.js')) ?>" defer></script>
