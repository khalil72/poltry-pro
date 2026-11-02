<?php $kv = fn ($l, $v) => App\Core\View::capture('admin/partials/kv', ['label' => $l, 'value' => $v]); ?>
<div class="kv-grid mb-4">
  <?= $kv('Current balance', '<strong>' . e(money($balance)) . '</strong>') ?><?= $kv('Opening balance', e(money($row['opening_balance']))) ?><?= $kv('Type', e(label_of($row['account_type']))) ?><?= $kv('Number', e($row['account_number'] ?: '—')) ?>
</div>
<div class="card"><div class="card-header">Latest transactions</div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Date</th><th>Description</th><th>Type</th><th class="text-end">In</th><th class="text-end">Out</th></tr></thead><tbody>
  <?php foreach ($rows as $t):
      $in = in_array($t['entry_type'], ['receipt', 'transfer_in'], true);
      $amt = (float) $t['amount']; ?>
    <tr class="<?= $t['reversal_of'] ? 'row-muted' : '' ?>"><td><?= e(fdate($t['txn_date'])) ?></td><td><?= e($t['description']) ?></td><td><?= e(label_of($t['entry_type'])) ?></td><td class="text-end"><?= $in ? e(money($amt, false)) : '' ?></td><td class="text-end"><?= !$in ? e(money($amt, false)) : '' ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="5" class="text-body-secondary">No transactions yet.</td></tr><?php endif; ?>
</tbody></table></div></div>
