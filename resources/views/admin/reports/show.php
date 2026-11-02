<?php
use App\Core\Request;
use App\Services\Lookup;

$select = function (string $name, string $label, string $lookup) {
    $v = (string) Request::query($name, '');
    $h = '<select class="form-select form-select-sm filter-input" name="' . e($name) . '" aria-label="' . e($label) . '"><option value="">' . e($label) . ': all</option>';
    foreach (Lookup::options($lookup) as $o) {
        $h .= '<option value="' . e($o['value']) . '"' . ($v === (string) $o['value'] ? ' selected' : '') . '>' . e($o['label']) . '</option>';
    }
    return $h . '</select>';
};
$has = fn ($x) => in_array($x, $def['filters'], true);
$exportUrl = fn ($type) => list_url(['export' => $type, 'page' => null]);
?>
<div class="page-head">
  <div><h1 class="page-title"><i class="bi <?= e($def['icon']) ?>"></i> <?= e($def['title']) ?></h1><div class="small text-body-secondary"><?= e($subtitle) ?></div></div>
  <div class="d-flex flex-wrap gap-2">
    <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/reports')) ?>"><i class="bi bi-arrow-left me-1"></i>All reports</a>
    <a class="btn btn-outline-secondary btn-sm" href="<?= e($exportUrl('print')) ?>" target="_blank" rel="noopener"><i class="bi bi-printer me-1"></i>Print</a>
    <a class="btn btn-outline-secondary btn-sm" href="<?= e($exportUrl('pdf')) ?>"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>
    <a class="btn btn-outline-secondary btn-sm" href="<?= e($exportUrl('xlsx')) ?>"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
  </div>
</div>
<?php if ($result['note']): ?><div class="alert alert-light border small py-2"><?= e($result['note']) ?></div><?php endif; ?>
<div class="card">
  <form class="filter-bar" method="get">
    <?php if (empty($def['no_search'])): ?><div class="filter-search"><i class="bi bi-search"></i><input class="form-control form-control-sm" type="search" name="q" value="<?= e(Request::query('q', '')) ?>" placeholder="Search" aria-label="Search"></div><?php endif; ?>
    <?php if ($has('date')): ?>
      <label class="filter-date"><span>From</span><input class="form-control form-control-sm" type="date" name="from" value="<?= e($f['from']) ?>"></label>
      <label class="filter-date"><span>To</span><input class="form-control form-control-sm" type="date" name="to" value="<?= e($f['to']) ?>"></label>
    <?php endif; ?>
    <?php if ($has('farm')) { echo $select('farm_id', 'Farm', 'farms'); } ?>
    <?php if ($has('batch')) { echo $select('batch_id', 'Batch', 'batches_all'); } ?>
    <?php if ($has('customer')) { echo $select('customer_id', 'Customer', 'customers'); } ?>
    <?php if ($has('supplier')) { echo $select('supplier_id', 'Supplier', 'suppliers'); } ?>
    <button class="btn btn-sm btn-primary">Apply</button>
    <a class="btn btn-sm btn-link" href="<?= e(url('admin/reports/' . $key)) ?>">Clear</a>
  </form>
  <?php if ($result['rows']): ?>
  <div class="table-responsive"><table class="table table-hover mb-0 data-table">
    <thead><tr><?php foreach ($result['columns'] as $c): ?><th class="<?= is_numeric_column($c) ? 'text-end' : '' ?>"><?= e($c['label']) ?></th><?php endforeach; ?></tr></thead>
    <tbody>
      <?php foreach ($result['rows'] as $r): ?>
        <tr class="<?= !empty($r['bold']) ? 'fw-bold table-active' : '' ?>"><?php foreach ($result['columns'] as $c): ?><td class="<?= is_numeric_column($c) ? 'text-end' : '' ?>" <?= $result['statement'] && $c['key'] === 'line' ? 'style="white-space:pre"' : '' ?>><?= render_cell($c, $r) ?></td><?php endforeach; ?></tr>
      <?php endforeach; ?>
    </tbody>
    <?php if ($result['totals']): ?>
      <tfoot><tr class="fw-bold"><?php foreach ($result['columns'] as $i => $c): ?><td class="<?= is_numeric_column($c) ? 'text-end' : '' ?>"><?= isset($result['totals'][$c['key']]) ? render_cell($c, $result['totals']) : ($i === 0 ? 'Total' : '') ?></td><?php endforeach; ?></tr></tfoot>
    <?php endif; ?>
  </table></div>
  <?= App\Core\View::capture('components/pagination', ['table' => $table]) ?>
  <?php else: ?>
    <div class="empty-state"><i class="bi <?= e($def['icon']) ?>"></i><p>Nothing to report for these filters. Try a wider date range.</p></div>
  <?php endif; ?>
</div>
