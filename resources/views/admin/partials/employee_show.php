<?php $kv = fn ($l, $v) => App\Core\View::capture('admin/partials/kv', ['label' => $l, 'value' => $v]); ?>
<div class="kv-grid mb-4">
  <?= $kv('Employee ID', e($row['employee_code'])) ?><?= $kv('Designation', e($row['designation'] ?: '—')) ?><?= $kv('Farm', e($row['farm_name'] ?: '—')) ?><?= $kv('Status', status_badge($row['status'])) ?>
  <?= $kv('Phone', e($row['phone'] ?: '—')) ?><?= $kv('Joined', e(fdate($row['joining_date']))) ?><?= $kv('Present this month', e($month['present'] ?? 0) . ' days') ?><?= $kv('Absent this month', e($month['absent'] ?? 0) . ' days') ?>
</div>
<div class="row g-4">
  <div class="col-lg-6"><div class="card"><div class="card-header">Recent attendance</div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Date</th><th>Status</th><th>Notes</th></tr></thead><tbody>
    <?php foreach ($attendance as $a): ?><tr><td><?= e(fdate($a['attendance_date'])) ?></td><td><?= status_badge($a['status']) ?></td><td><?= e($a['notes']) ?></td></tr><?php endforeach; ?>
    <?php if (!$attendance): ?><tr><td colspan="3" class="text-body-secondary">No attendance recorded yet.</td></tr><?php endif; ?>
  </tbody></table></div></div></div>
  <?php if (can('salaries.view')): ?>
  <div class="col-lg-6"><div class="card"><div class="card-header">Salary payments</div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Month</th><th class="text-end">Net paid</th><th>Paid on</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($salaries as $s): ?><tr><td><?= e($s['period']) ?></td><td class="text-end"><?= e(money($s['net_amount'], false)) ?></td><td><?= e(fdate($s['pay_date'])) ?></td><td><?= status_badge($s['status']) ?></td></tr><?php endforeach; ?>
    <?php if (!$salaries): ?><tr><td colspan="4" class="text-body-secondary">No salary payments yet.</td></tr><?php endif; ?>
  </tbody></table></div></div></div>
  <?php endif; ?>
</div>
