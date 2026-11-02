<?php
$p = $purchase ?? [];
$action = $purchase ? "admin/purchases/{$purchase['id']}/save" : 'admin/purchases/save';
$oldItems = old('items', null);
$lines = $oldItems ?: array_map(fn ($i) => [
    'item_type' => $i['item_type'],
    'ref_id' => $i['batch_id'] ?? $i['feed_product_id'] ?? $i['medicine_id'] ?? $i['product_id'] ?? '',
    'unit_id' => $i['unit_id'], 'lot_no' => $i['lot_no'], 'expiry_date' => $i['expiry_date'], 'description' => $i['description'],
    'quantity' => (float) $i['quantity'], 'unit_price' => (float) $i['unit_price'],
], $items);
?>
<div class="page-head"><h1 class="page-title"><i class="bi bi-cart-plus"></i> <?= e($title) ?></h1><a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/purchases')) ?>"><i class="bi bi-arrow-left me-1"></i>Back to purchases</a></div>
<form method="post" action="<?= e(url($action)) ?>" data-once id="docForm" data-kind="purchase">
  <?= csrf_field() ?>
  <div class="card mb-4"><div class="card-body"><div class="row g-3">
    <div class="col-md-3"><label class="form-label">Supplier <span class="text-danger">*</span></label><select class="form-select" name="supplier_id" required><option value="">Choose…</option><?php foreach ($lk['suppliers'] as $o): ?><option value="<?= e($o['value']) ?>" <?= (string) old('supplier_id', $p['supplier_id'] ?? '') === (string) $o['value'] ? 'selected' : '' ?>><?= e($o['label']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><label class="form-label">Receiving farm <span class="text-danger">*</span></label><select class="form-select" name="farm_id" id="docFarm" required><option value="">Choose…</option><?php foreach ($lk['farms'] as $o): ?><option value="<?= e($o['value']) ?>" <?= (string) old('farm_id', $p['farm_id'] ?? '') === (string) $o['value'] ? 'selected' : '' ?>><?= e($o['label']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><label class="form-label">Date <span class="text-danger">*</span></label><input class="form-control" type="date" name="purchase_date" value="<?= e(old('purchase_date', $p['purchase_date'] ?? date('Y-m-d'))) ?>" required></div>
    <div class="col-md-3"><label class="form-label">Supplier invoice no.</label><input class="form-control" name="supplier_invoice_no" maxlength="60" value="<?= e(old('supplier_invoice_no', $p['supplier_invoice_no'] ?? '')) ?>"></div>
  </div></div></div>
  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center"><span>Items</span><button class="btn btn-sm btn-outline-primary" type="button" id="addLine"><i class="bi bi-plus-lg me-1"></i>Add item</button></div>
    <div class="table-responsive"><table class="table mb-0 align-middle line-table"><thead><tr><th style="width:150px">Buying</th><th style="min-width:280px">Item</th><th style="width:120px">Quantity</th><th style="width:140px">Unit price</th><th style="width:140px" class="text-end">Line total</th><th style="width:44px"></th></tr></thead><tbody id="lines"></tbody></table></div>
  </div>
  <div class="row g-4">
    <div class="col-lg-7"><div class="card"><div class="card-body"><div class="row g-3">
      <div class="col-sm-4"><label class="form-label">Paid now</label><input class="form-control" type="number" min="0" step="0.01" name="paid_amount" id="docPaid" value="<?= e(old('paid_amount', 0)) ?>"></div>
      <div class="col-sm-4"><label class="form-label">From account</label><select class="form-select" name="account_id"><option value="">Choose…</option><?php foreach ($lk['accounts'] as $o): ?><option value="<?= e($o['value']) ?>"><?= e($o['label']) ?></option><?php endforeach; ?></select></div>
      <div class="col-sm-4"><label class="form-label">Payment method</label><select class="form-select" name="method"><option value="cash">Cash</option><option value="bank_transfer">Bank transfer</option><option value="cheque">Cheque</option><option value="mobile_wallet">Mobile wallet</option></select></div>
      <div class="col-12"><label class="form-label">Notes</label><input class="form-control" name="notes" maxlength="500" value="<?= e(old('notes', $p['notes'] ?? '')) ?>"></div>
    </div></div></div></div>
    <div class="col-lg-5"><div class="card"><div class="card-body">
      <table class="table table-sm mb-3 totals">
        <tr class="fw-bold fs-5"><td>Total</td><td class="text-end" id="tGrand">0.00</td></tr>
        <tr><td>Remaining balance</td><td class="text-end" id="tDue">0.00</td></tr>
      </table>
      <div class="d-grid gap-2">
        <button class="btn btn-primary" name="save_as" value="confirm"><i class="bi bi-check2-circle me-1"></i>Confirm and receive stock</button>
        <button class="btn btn-outline-secondary" name="save_as" value="draft">Save as purchase order</button>
      </div>
      <div class="form-text mt-2">A purchase order changes nothing until you confirm it. Confirming adds the items to stock and records what you owe the supplier.</div>
    </div></div></div>
  </div>
</form>
<script id="docData" type="application/json"><?= json_encode(['lk' => $lk, 'lines' => array_values((array) $lines)], JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= e(asset('js/documents.js')) ?>" defer></script>
