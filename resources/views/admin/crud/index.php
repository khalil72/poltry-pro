<?php
$manage = can($r['perm'] . '.manage');
$canCreate = $manage && empty($r['readonly']);
$canEdit = $manage && empty($r['readonly']) && empty($r['no_edit']) && empty($r['custom_form']);
$canDelete = $manage && empty($r['no_delete']) && (empty($r['readonly']) || !empty($r['can_delete']));
$canShow = !empty($r['show_view']) || !empty($r['can_show']);
$canCancel = $manage && !empty($r['cancel']);
$custom = $r['actions'] ?? [];
$hasPay = (bool) array_filter($custom, fn ($a) => !empty($a['pay']) && can($a['perm'] ?? 'x'));
$hasActions = $canEdit || $canDelete || $canShow || $canCancel || $custom;
$hasFilters = !empty($r['search']) || !empty($r['filters']);
$active = array_diff_key($_GET, ['page' => 1, 'sort' => 1, 'dir' => 1]);
$cancelLabel = $r['cancel_label'] ?? 'Cancel';
?>
<div class="page-head">
  <div>
    <h1 class="page-title"><i class="bi <?= e($r['icon']) ?>" aria-hidden="true"></i> <?= e($r['title']) ?></h1>
    <div class="text-body-secondary small"><?= e(num($table->total)) ?> <?= $table->total === 1 ? 'record' : 'records' ?><?= $active ? ' match your filters' : '' ?></div>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <?php foreach ($r['toolbar'] ?? [] as $b): if (!can($b['perm'])) { continue; } ?>
      <a class="btn btn-outline-secondary btn-sm" href="<?= e(url($b['url'])) ?>"><i class="bi <?= e($b['icon']) ?> me-1"></i><?= e($b['label']) ?></a>
    <?php endforeach; ?>
    <?php if ($canCreate): ?><a class="btn btn-primary btn-sm" href="<?= e(url("admin/$slug/create")) ?>"><i class="bi bi-plus-lg me-1"></i>Add <?= e(strtolower($r['singular'])) ?></a><?php endif; ?>
  </div>
</div>
<?php if (!empty($r['note'])): ?><div class="alert alert-light border small py-2"><i class="bi bi-info-circle me-1"></i><?= e($r['note']) ?></div><?php endif; ?>

<div class="card">
  <?php if ($hasFilters): ?>
  <form class="filter-bar" method="get">
    <?php if (!empty($r['search'])): ?>
      <div class="filter-search"><i class="bi bi-search"></i><input class="form-control form-control-sm" type="search" name="q" value="<?= e($table->search) ?>" placeholder="Search" aria-label="Search"></div>
    <?php endif; ?>
    <?php foreach ($r['filters'] ?? [] as $f):
        $val = App\Core\Request::query($f['name'], '');
        $type = $f['type'] ?? 'select'; ?>
      <?php if ($type === 'select'):
          $opts = isset($f['lookup']) ? array_column(App\Services\Lookup::options($f['lookup']), 'label', 'value') : $f['options']; ?>
        <select class="form-select form-select-sm filter-input" name="<?= e($f['name']) ?>" aria-label="<?= e($f['label']) ?>">
          <option value=""><?= e($f['label']) ?>: all</option>
          <?php foreach ($opts as $v => $l): ?><option value="<?= e($v) ?>" <?= (string) $val === (string) $v ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
        </select>
      <?php else: ?>
        <label class="filter-date"><span><?= e($f['label']) ?></span><input class="form-control form-control-sm" type="date" name="<?= e($f['name']) ?>" value="<?= e($val) ?>"></label>
      <?php endif; ?>
    <?php endforeach; ?>
    <button class="btn btn-sm btn-primary">Apply</button>
    <?php if ($active): ?><a class="btn btn-sm btn-link" href="<?= e(url("admin/$slug")) ?>">Clear</a><?php endif; ?>
  </form>
  <?php endif; ?>

  <?php if ($table->rows): ?>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0 data-table">
      <thead><tr>
        <?php foreach ($columns as $c):
            $isSorted = !empty($c['sort']) && App\Core\Request::query('sort') === $c['key'];
            $dir = $isSorted && App\Core\Request::query('dir') === 'asc' ? 'desc' : 'asc'; ?>
          <th class="<?= is_numeric_column($c) ? 'text-end' : '' ?>">
            <?php if (!empty($c['sort'])): ?>
              <a class="sort-link" href="<?= e(list_url(['sort' => $c['key'], 'dir' => $dir, 'page' => null])) ?>"><?= e($c['label']) ?><?= $isSorted ? ' <i class="bi bi-caret-' . ($dir === 'asc' ? 'down' : 'up') . '-fill"></i>' : '' ?></a>
            <?php else: ?><?= e($c['label']) ?><?php endif; ?>
          </th>
        <?php endforeach; ?>
        <?php if ($hasActions): ?><th class="text-end"><span class="visually-hidden">Actions</span></th><?php endif; ?>
      </tr></thead>
      <tbody>
      <?php foreach ($table->rows as $row): $pk = $row[$r['pk'] ?? 'id'] ?? null; ?>
        <tr class="<?= in_array($row['status'] ?? '', ['cancelled', 'voided'], true) ? 'row-muted' : '' ?>">
          <?php foreach ($columns as $c): ?><td class="<?= is_numeric_column($c) ? 'text-end' : '' ?>"><?= render_cell($c, $row) ?></td><?php endforeach; ?>
          <?php if ($hasActions): ?>
          <td class="text-end text-nowrap actions">
            <?php foreach ($custom as $a):
                if (!empty($a['perm']) && !can($a['perm'])) { continue; }
                if (!empty($a['when']) && !$a['when']($row)) { continue; }
                if (!empty($a['pay'])): ?>
                  <button type="button" class="btn btn-sm btn-action" title="<?= e($a['label']) ?>" aria-label="<?= e($a['label']) ?>" data-pay data-doc-type="<?= e($a['pay']) ?>" data-doc-id="<?= (int) $pk ?>" data-due="<?= e($row['balance']) ?>"><i class="bi <?= e($a['icon']) ?>"></i></button>
                <?php else: ?>
                  <a class="btn btn-sm btn-action" href="<?= e(url(fill_placeholders($a['url'], $row))) ?>" title="<?= e($a['label']) ?>" aria-label="<?= e($a['label']) ?>" <?= !empty($a['target']) ? 'target="' . e($a['target']) . '"' : '' ?>><i class="bi <?= e($a['icon']) ?>"></i></a>
                <?php endif;
            endforeach; ?>
            <?php if ($canShow): ?><a class="btn btn-sm btn-action" href="<?= e(url("admin/$slug/$pk")) ?>" title="View" aria-label="View"><i class="bi bi-eye"></i></a><?php endif; ?>
            <?php if ($canEdit): ?><a class="btn btn-sm btn-action" href="<?= e(url("admin/$slug/$pk/edit")) ?>" title="Edit" aria-label="Edit"><i class="bi bi-pencil"></i></a><?php endif; ?>
            <?php if ($canCancel && (isset($r['cancel_when']) ? $r['cancel_when']($row) : ($row['status'] ?? '') === 'posted')): ?>
              <form class="d-inline" method="post" action="<?= e(url("admin/$slug/$pk/cancel")) ?>" data-cancel="<?= e($cancelLabel . ' this ' . strtolower($r['singular']) . '?') ?>" data-cancel-label="<?= e($cancelLabel) ?>"><?= csrf_field() ?><input type="hidden" name="reason"><button class="btn btn-sm btn-action text-warning-emphasis" title="<?= e($cancelLabel) ?>" aria-label="<?= e($cancelLabel) ?>"><i class="bi bi-x-circle"></i></button></form>
            <?php endif; ?>
            <?php if ($canDelete): ?>
              <form class="d-inline" method="post" action="<?= e(url("admin/$slug/$pk/delete")) ?>" data-confirm="Delete this <?= e(strtolower($r['singular'])) ?>?"><?= csrf_field() ?><button class="btn btn-sm btn-action text-danger" title="Delete" aria-label="Delete"><i class="bi bi-trash"></i></button></form>
            <?php endif; ?>
          </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= App\Core\View::capture('components/pagination', ['table' => $table]) ?>
  <?php else: ?>
    <div class="empty-state">
      <i class="bi <?= e($r['icon']) ?>" aria-hidden="true"></i>
      <?php if ($active): ?>
        <p>Nothing matches these filters.</p><a class="btn btn-sm btn-outline-secondary" href="<?= e(url("admin/$slug")) ?>">Clear filters</a>
      <?php else: ?>
        <p>No <?= e(strtolower($r['title'])) ?> yet.</p>
        <?php if ($canCreate): ?><a class="btn btn-sm btn-primary" href="<?= e(url("admin/$slug/create")) ?>">Add the first <?= e(strtolower($r['singular'])) ?></a><?php endif; ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
<?php if ($hasPay): ?><?= App\Core\View::capture('admin/partials/pay_modal', ['accounts' => App\Services\Lookup::options('accounts')]) ?><?php endif; ?>
