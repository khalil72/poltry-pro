<?php
use App\Services\Lookup;
use App\Services\ReportService;

$kv = fn ($l, $v) => App\Core\View::capture('admin/partials/kv', ['label' => $l, 'value' => $v]);
$cash = array_sum(array_map(fn ($a) => $a['account_type'] === 'cash' ? $a['balance'] : 0, $accounts));
$bank = array_sum(array_map(fn ($a) => $a['account_type'] === 'bank' ? $a['balance'] : 0, $accounts));
?>
<div class="page-head"><div><h1 class="page-title"><i class="bi bi-graph-up-arrow"></i> Financial overview</h1><div class="small text-body-secondary">Worked out from recorded transactions. Nothing here is typed in by hand.</div></div>
  <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/reports/profit-loss?from=' . $from . '&to=' . $to)) ?>"><i class="bi bi-file-earmark-text me-1"></i>Profit and loss report</a></div>
<form class="card mb-4" method="get"><div class="filter-bar border-0">
  <label class="filter-date"><span>From</span><input class="form-control form-control-sm" type="date" name="from" value="<?= e($from) ?>"></label>
  <label class="filter-date"><span>To</span><input class="form-control form-control-sm" type="date" name="to" value="<?= e($to) ?>"></label>
  <select class="form-select form-select-sm filter-input" name="farm_id" aria-label="Farm"><option value="">Farm: all</option><?php foreach (Lookup::options('farms') as $o): ?><option value="<?= e($o['value']) ?>" <?= (string) $farm === (string) $o['value'] ? 'selected' : '' ?>><?= e($o['label']) ?></option><?php endforeach; ?></select>
  <button class="btn btn-sm btn-primary">Apply</button>
</div></form>
<div class="kv-grid mb-4">
  <?= $kv('Revenue', e(money($pl['revenue']))) ?><?= $kv('Gross profit', e(money($pl['gross_profit']))) ?><?= $kv('Operating expenses', e(money($pl['operating_expenses']))) ?><?= $kv('Net profit', '<span class="' . ($pl['net_profit'] < 0 ? 'text-danger' : 'text-success') . '">' . e(money($pl['net_profit'])) . '</span>') ?>
  <?= $kv('Customer receivables', e(money($receivables))) ?><?= $kv('Supplier payables', e(money($payables))) ?><?= $kv('Cash in hand', e(money($cash))) ?><?= $kv('Bank balance', e(money($bank))) ?>
</div>
<div class="row g-4">
  <div class="col-lg-6"><div class="card h-100"><div class="card-header">Income and expense statement</div><div class="table-responsive"><table class="table mb-0">
    <tr class="fw-bold"><td>Revenue</td><td class="text-end"><?= e(money($pl['revenue'], false)) ?></td></tr>
    <?php foreach ($pl['cogs_lines'] as $c): ?><tr><td class="ps-4"><?= e(ReportService::costLabel($c['sub_type'])) ?></td><td class="text-end">(<?= e(money($c['amt'], false)) ?>)</td></tr><?php endforeach; ?>
    <tr class="fw-bold table-active"><td>Gross profit</td><td class="text-end"><?= e(money($pl['gross_profit'], false)) ?></td></tr>
    <?php foreach ($pl['opex_lines'] as $c): ?><tr><td class="ps-4"><?= e($c['name']) ?></td><td class="text-end">(<?= e(money($c['amt'], false)) ?>)</td></tr><?php endforeach; ?>
    <tr class="fw-bold table-active"><td>Net profit</td><td class="text-end"><?= e(money($pl['net_profit'], false)) ?></td></tr>
  </table></div></div></div>
  <div class="col-lg-6">
    <div class="card mb-4"><div class="card-header">Counted separately, not in profit</div><div class="card-body small">
      <table class="table table-sm mb-2">
        <tr><td>Stock purchased</td><td class="text-end"><?= e(money($pl['inventory_purchases'], false)) ?></td></tr>
        <tr><td>Capital spending</td><td class="text-end"><?= e(money($pl['capex'], false)) ?></td></tr>
        <tr><td>Sales tax collected</td><td class="text-end"><?= e(money($pl['tax_collected'], false)) ?></td></tr>
        <tr><td>Expenses not yet paid</td><td class="text-end"><?= e(money($unpaid, false)) ?></td></tr>
      </table>
      <div class="text-body-secondary">Stock purchases reach profit only when the stock is consumed, sold or lost, so they are not counted twice.</div>
    </div></div>
    <div class="card"><div class="card-header">Accounts</div><div class="table-responsive"><table class="table mb-0"><?php foreach ($accounts as $a): ?><tr><td><a href="<?= e(url('admin/accounts/' . $a['id'])) ?>"><?= e($a['name']) ?></a></td><td><?= e(label_of($a['account_type'])) ?></td><td class="text-end"><?= e(money($a['balance'], false)) ?></td></tr><?php endforeach; ?></table></div></div>
  </div>
</div>
<div class="card mt-4"><div class="card-header">Latest financial transactions</div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Date</th><th>Type</th><th>Description</th><th>Account</th><th class="text-end">Amount</th></tr></thead><tbody>
  <?php foreach ($recent as $t): ?><tr class="<?= $t['reversal_of'] ? 'row-muted' : '' ?>"><td><?= e(fdate($t['txn_date'])) ?></td><td><?= e(label_of($t['entry_type'])) ?></td><td><?= e($t['description']) ?></td><td><?= e($t['account_name']) ?></td><td class="text-end"><?= e(money($t['amount'], false)) ?></td></tr><?php endforeach; ?>
  <?php if (!$recent): ?><tr><td colspan="5" class="text-body-secondary">No transactions in this period.</td></tr><?php endif; ?>
</tbody></table></div></div>
