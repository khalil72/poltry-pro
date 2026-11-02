<?php $kv = fn ($l, $v) => App\Core\View::capture('admin/partials/kv', ['label' => $l, 'value' => $v]); $showMoney = can('finance.view'); ?>
<div class="kv-grid mb-4">
  <?= $kv('Batch ID', e($row['batch_code'])) ?><?= $kv('Farm', e($row['farm_name'])) ?><?= $kv('Type / breed', e(label_of($row['batch_type'])) . ($row['breed'] ? ' · ' . e($row['breed']) : '')) ?><?= $kv('Status', status_badge($row['status'])) ?>
  <?= $kv('Age', e($perf['age_days']) . ' days') ?><?= $kv('Current birds', '<strong>' . e(num($perf['current'])) . '</strong>') ?><?= $kv('Mortality', e(num($perf['dead'])) . ' (' . e($perf['mortality_pct']) . '%)') ?><?= $kv('Feed used', e(num($perf['feed_kg'])) . ' kg') ?>
</div>
<div class="row g-4 mb-4">
  <div class="col-lg-5"><div class="card h-100"><div class="card-header">Bird count</div><div class="card-body">
    <table class="table table-sm mb-0">
      <tr><td>Opening birds</td><td class="text-end"><?= e(num($perf['opening'])) ?></td></tr>
      <tr><td>+ Purchases</td><td class="text-end"><?= e(num($perf['purchased'])) ?></td></tr>
      <tr><td>+ Transfers in</td><td class="text-end"><?= e(num($perf['transfers_in'])) ?></td></tr>
      <tr><td>− Transfers out</td><td class="text-end"><?= e(num($perf['transfers_out'])) ?></td></tr>
      <tr><td>− Mortality</td><td class="text-end"><?= e(num($perf['dead'])) ?></td></tr>
      <tr><td>− Sales</td><td class="text-end"><?= e(num($perf['sold'])) ?></td></tr>
      <tr><td>+ Stock increases</td><td class="text-end"><?= e(num($perf['adjust_in'])) ?></td></tr>
      <tr><td>− Approved reductions</td><td class="text-end"><?= e(num($perf['reductions'])) ?></td></tr>
      <tr class="fw-bold"><td>Current birds</td><td class="text-end"><?= e(num($perf['current'])) ?></td></tr>
    </table>
    <?php if ($flocks): ?><div class="small text-body-secondary mt-3">Now in: <?= e(implode(', ', array_map(fn ($f) => $f['shed_name'] . ' (' . num($f['quantity']) . ')', $flocks))) ?></div><?php endif; ?>
  </div></div></div>
  <?php if ($showMoney): ?>
  <div class="col-lg-7"><div class="card h-100"><div class="card-header">Batch profit and loss</div><div class="card-body">
    <table class="table table-sm mb-0">
      <tr><td>Revenue<?= $perf['eggs_good'] ? ' (birds and eggs)' : '' ?></td><td class="text-end"><?= e(money($perf['revenue'])) ?></td></tr>
      <tr><td>− Bird purchase cost</td><td class="text-end"><?= e(money($perf['bird_cost'])) ?></td></tr>
      <tr><td>− Feed consumed</td><td class="text-end"><?= e(money($perf['feed_cost'])) ?></td></tr>
      <tr><td>− Medicine and vaccines</td><td class="text-end"><?= e(money($perf['medicine_cost'])) ?></td></tr>
      <tr><td>− Expenses charged to this batch</td><td class="text-end"><?= e(money($perf['other_cost'])) ?></td></tr>
      <tr class="fw-bold <?= $perf['profit'] < 0 ? 'text-danger' : 'text-success' ?>"><td><?= $perf['profit'] < 0 ? 'Loss' : 'Profit' ?> to date</td><td class="text-end"><?= e(money($perf['profit'])) ?></td></tr>
    </table>
    <?php if ($perf['eggs_total']): ?><div class="small text-body-secondary mt-3"><?= e(num($perf['eggs_total'])) ?> eggs collected, <?= e(num($perf['eggs_good'])) ?> good.</div><?php endif; ?>
    <?php if ($perf['current'] > 0): ?><div class="small text-body-secondary mt-1">The <?= e(num($perf['current'])) ?> birds still in stock are not valued here, so this improves as they are sold.</div><?php endif; ?>
  </div></div></div>
  <?php endif; ?>
</div>
<div class="row g-4">
  <div class="col-lg-7"><div class="card"><div class="card-header">Recent bird transactions</div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Date</th><th>Type</th><th>Shed</th><th class="text-end">Change</th><th class="text-end">Shed balance</th></tr></thead><tbody>
    <?php foreach ($history as $h): ?><tr><td><?= e(fdate($h['txn_date'])) ?></td><td><?= e(label_of($h['txn_type'])) ?><?= $h['reversal_of'] ? ' (reversal)' : '' ?></td><td><?= e($h['shed_name']) ?></td><td class="text-end <?= $h['quantity'] < 0 ? 'text-danger' : 'text-success' ?>"><?= $h['quantity'] > 0 ? '+' : '' ?><?= e(num($h['quantity'])) ?></td><td class="text-end"><?= e(num($h['balance_after'])) ?></td></tr><?php endforeach; ?>
  </tbody></table></div></div></div>
  <div class="col-lg-5"><div class="card"><div class="card-header">Vaccinations</div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Due</th><th>Vaccination</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($vaccinations as $v): ?><tr><td><?= e(fdate($v['scheduled_date'])) ?></td><td><?= e($v['vaccine_name']) ?></td><td><?= status_badge($v['status']) ?></td></tr><?php endforeach; ?>
    <?php if (!$vaccinations): ?><tr><td colspan="3" class="text-body-secondary">None scheduled.</td></tr><?php endif; ?>
  </tbody></table></div></div></div>
</div>
