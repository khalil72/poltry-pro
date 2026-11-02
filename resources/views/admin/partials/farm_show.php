<?php $kv = fn ($l, $v) => App\Core\View::capture('admin/partials/kv', ['label' => $l, 'value' => $v]); ?>
<div class="kv-grid mb-4">
  <?= $kv('Birds', e(num($stats['birds']))) ?><?= $kv('Capacity', e(num($row['capacity']))) ?><?= $kv('Sheds', e($stats['sheds'])) ?><?= $kv('Active batches', e($stats['active_batches'])) ?>
  <?= $kv('Total mortality', e(num($stats['dead']))) ?><?= $kv('Feed in stock', e(num($stats['feed_kg'])) . ' kg') ?><?= $kv('Eggs in stock', e(num($stats['eggs']))) ?><?= $kv('Employees', e($stats['employees'])) ?>
</div>
<div class="row g-4">
  <div class="col-lg-7"><div class="card h-100">
    <div class="card-header d-flex justify-content-between"><span>Sheds</span><?php if (can('sheds.manage')): ?><a class="small" href="<?= e(url('admin/sheds/create')) ?>">Add shed</a><?php endif; ?></div>
    <div class="table-responsive"><table class="table mb-0 align-middle"><thead><tr><th>No.</th><th>Shed</th><th>Type</th><th class="text-end">Birds</th><th class="text-end">Capacity</th><th>Status</th></tr></thead><tbody>
      <?php foreach ($sheds as $s): ?><tr><td><?= e($s['shed_number']) ?></td><td><a href="<?= e(url('admin/sheds/' . $s['id'])) ?>"><?= e($s['name']) ?></a></td><td><?= e(label_of($s['shed_type'])) ?></td><td class="text-end"><?= e(num($s['current_bird_count'])) ?></td><td class="text-end"><?= e(num($s['max_capacity'])) ?></td><td><?= status_badge($s['status']) ?></td></tr><?php endforeach; ?>
      <?php if (!$sheds): ?><tr><td colspan="6" class="text-body-secondary">No sheds at this farm yet.</td></tr><?php endif; ?>
    </tbody></table></div>
  </div></div>
  <div class="col-lg-5"><div class="card h-100"><div class="card-header">Details</div><div class="card-body">
    <dl class="row mb-0 small">
      <dt class="col-5">Code</dt><dd class="col-7"><?= e($row['code']) ?></dd>
      <dt class="col-5">Location</dt><dd class="col-7"><?= e($row['location']) ?></dd>
      <dt class="col-5">Manager</dt><dd class="col-7"><?= e($row['manager_name']) ?></dd>
      <dt class="col-5">Contact</dt><dd class="col-7"><?= e($row['contact_phone']) ?><br><?= e($row['contact_email']) ?></dd>
      <dt class="col-5">Status</dt><dd class="col-7"><?= status_badge($row['status']) ?></dd>
    </dl>
    <?php if ($pnl): ?>
      <hr><div class="small fw-semibold mb-2">This month</div>
      <dl class="row mb-0 small">
        <dt class="col-7">Revenue</dt><dd class="col-5 text-end"><?= e(money($pnl['revenue'], false)) ?></dd>
        <dt class="col-7">Direct costs</dt><dd class="col-5 text-end"><?= e(money($pnl['cogs'], false)) ?></dd>
        <dt class="col-7">Operating expenses</dt><dd class="col-5 text-end"><?= e(money($pnl['operating_expenses'], false)) ?></dd>
        <dt class="col-7">Net profit</dt><dd class="col-5 text-end fw-bold"><?= e(money($pnl['net_profit'], false)) ?></dd>
      </dl>
    <?php endif; ?>
  </div></div></div>
</div>
