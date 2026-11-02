<?php
$manage = can($r['perm'] . '.manage');
$canEdit = $manage && empty($r['readonly']) && empty($r['no_edit']) && empty($r['custom_form']);
?>
<div class="page-head">
  <h1 class="page-title"><i class="bi <?= e($r['icon']) ?>" aria-hidden="true"></i> <?= e($title) ?></h1>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary btn-sm" href="<?= e(url("admin/$slug")) ?>"><i class="bi bi-arrow-left me-1"></i>Back</a>
    <?php if ($canEdit): ?><a class="btn btn-primary btn-sm" href="<?= e(url("admin/$slug/{$row['id']}/edit")) ?>"><i class="bi bi-pencil me-1"></i>Edit</a><?php endif; ?>
  </div>
</div>
<?php if (!empty($r['show_view'])): ?>
  <?= App\Core\View::capture($r['show_view'], ['row' => $row, 'r' => $r, ...$extra]) ?>
<?php else: ?>
  <div class="card"><div class="card-body">
    <dl class="row mb-0">
      <?php foreach ($r['fields'] as $f): if (in_array($f['type'] ?? '', ['password', 'multicheck', 'image', 'file'], true)) { continue; } ?>
        <dt class="col-sm-3"><?= e($f['label']) ?></dt><dd class="col-sm-9" style="white-space:pre-wrap"><?= e($row[$f['name']] ?? '—') ?></dd>
      <?php endforeach; ?>
    </dl>
  </div></div>
<?php endif; ?>
