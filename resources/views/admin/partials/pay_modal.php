<div class="modal fade" id="payModal" tabindex="-1" aria-labelledby="payTitle" aria-hidden="true"><div class="modal-dialog"><form class="modal-content" method="post" action="<?= e(url('admin/payments/record')) ?>" data-once>
  <?= csrf_field() ?>
  <input type="hidden" name="doc_type" id="payDocType"><input type="hidden" name="doc_id" id="payDocId">
  <div class="modal-header"><h2 class="modal-title h5" id="payTitle">Record a payment</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
  <div class="modal-body">
    <div class="mb-3"><label class="form-label">Amount</label><input class="form-control" type="number" step="0.01" min="0.01" name="amount" id="payAmount" required><div class="form-text" id="payDue"></div></div>
    <div class="row g-3 mb-3">
      <div class="col-6"><label class="form-label">Date</label><input class="form-control" type="date" name="payment_date" value="<?= e(date('Y-m-d')) ?>" required></div>
      <div class="col-6"><label class="form-label">Method</label><select class="form-select" name="method"><option value="cash">Cash</option><option value="bank_transfer">Bank transfer</option><option value="cheque">Cheque</option><option value="card">Card</option><option value="mobile_wallet">Mobile wallet</option></select></div>
    </div>
    <div class="mb-3"><label class="form-label">Account</label><select class="form-select" name="account_id" required><option value="">Choose…</option><?php foreach ($accounts as $a): ?><option value="<?= e($a['value']) ?>"><?= e($a['label']) ?></option><?php endforeach; ?></select></div>
    <div class="mb-0"><label class="form-label">Reference</label><input class="form-control" name="reference_no" maxlength="80" placeholder="Cheque or transaction number"></div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-link" data-bs-dismiss="modal">Close</button><button class="btn btn-primary">Record payment</button></div>
</form></div></div>
