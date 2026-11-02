<?php $kv = fn ($l, $v) => App\Core\View::capture('admin/partials/kv', ['label' => $l, 'value' => $v]);
$isCustomer = $kind === 'customer'; ?>
<div class="kv-grid mb-4">
  <?= $kv('Outstanding balance', '<strong>' . e(money($outstanding)) . '</strong>') ?>
  <?= $kv($isCustomer ? 'Phone' : 'Contact', e(($isCustomer ? $row['phone'] : $row['contact_number']) ?: '—')) ?>
  <?= $kv('Email', e($row['email'] ?: '—')) ?>
  <?= $kv($isCustomer ? 'Business' : 'Supplies', e(($isCustomer ? $row['business_name'] : $row['supplied_products']) ?: '—')) ?>
</div>
<div class="card">
  <div class="card-header"><?= $isCustomer ? 'Sales and payment history' : 'Purchase and payment history' ?></div>
  <div class="table-responsive"><table class="table mb-0"><thead><tr><th>Date</th><th>Reference</th><th>Type</th><th class="text-end"><?= $isCustomer ? 'Invoiced' : 'Purchased' ?></th><th class="text-end">Paid</th><th class="text-end">Balance</th></tr></thead><tbody>
    <?php foreach (array_reverse($ledger['rows']) as $l): ?>
      <tr><td><?= e(fdate($l['d'])) ?></td>
        <td><?php if ($l['kind'] !== 'Payment' && $l['doc_id']): ?><a href="<?= e(url(($isCustomer ? 'admin/sales/' : 'admin/purchases/') . $l['doc_id'])) ?>"><?= e($l['ref']) ?></a><?php else: ?><?= e($l['ref']) ?><?php endif; ?></td>
        <td><?= e($l['kind']) ?></td><td class="text-end"><?= $l['debit'] > 0 ? e(money($l['debit'], false)) : '' ?></td><td class="text-end"><?= $l['credit'] > 0 ? e(money($l['credit'], false)) : '' ?></td><td class="text-end"><?= e(money($l['balance'], false)) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$ledger['rows']): ?><tr><td colspan="6" class="text-body-secondary">No history yet.</td></tr><?php endif; ?>
  </tbody></table></div>
</div>
