<?php $kv = fn ($l, $v) => App\Core\View::capture('admin/partials/kv', ['label' => $l, 'value' => $v]); ?>
<div class="kv-grid mb-4">
  <?= $kv('Farm', e($row['farm_name'])) ?><?= $kv('Type', e(label_of($row['shed_type']))) ?><?= $kv('Birds / capacity', e(num($row['current_bird_count'])) . ' / ' . e(num($row['max_capacity']))) ?><?= $kv('Capacity used', e($row['used_pct']) . '%') ?>
  <?= $kv('Total mortality', e(num($dead))) ?><?= $kv('Feed used', e(num($feed_kg)) . ' kg') ?><?= $kv('Eggs collected', e(num($eggs))) ?><?= $kv('Status', status_badge($row['status'])) ?>
</div>
<div class="card"><div class="card-header">Batches in this shed</div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Batch</th><th>Name</th><th>Type</th><th class="text-end">Birds</th></tr></thead><tbody>
  <?php foreach ($flocks as $f): ?><tr><td><a href="<?= e(url('admin/batches/' . $f['id'])) ?>"><?= e($f['batch_code']) ?></a></td><td><?= e($f['name']) ?></td><td><?= e(label_of($f['batch_type'])) ?></td><td class="text-end"><?= e(num($f['quantity'])) ?></td></tr><?php endforeach; ?>
  <?php if (!$flocks): ?><tr><td colspan="4" class="text-body-secondary">This shed is empty. <?php if (can('batches.manage')): ?><a href="<?= e(url('admin/batches/create')) ?>">Add a batch</a>.<?php endif; ?></td></tr><?php endif; ?>
</tbody></table></div></div>
