<?php
$hasFile = (bool) array_filter($fields, fn ($f) => in_array($f['type'] ?? '', ['image', 'file'], true));
$editing = !empty($row);
?>
<div class="page-head">
  <h1 class="page-title"><i class="bi <?= e($r['icon']) ?>" aria-hidden="true"></i> <?= e($title) ?></h1>
  <a class="btn btn-outline-secondary btn-sm" href="<?= e(url("admin/$slug")) ?>"><i class="bi bi-arrow-left me-1"></i>Back to <?= e(strtolower($r['title'])) ?></a>
</div>
<?php if (!empty($r['note'])): ?><div class="alert alert-light border small py-2"><i class="bi bi-info-circle me-1"></i><?= e($r['note']) ?></div><?php endif; ?>
<div class="card"><div class="card-body">
  <form method="post" action="<?= e(url($action)) ?>" <?= $hasFile ? 'enctype="multipart/form-data"' : '' ?> data-once>
    <?= csrf_field() ?>
    <div class="row g-3">
      <?php foreach ($fields as $f): ?>
        <div class="<?= !empty($f['wide']) ? 'col-12' : 'col-md-6' ?>"><?= App\Core\View::capture('components/field', ['f' => $f, 'row' => $row]) ?></div>
      <?php endforeach; ?>
    </div>
    <div class="mt-4 d-flex gap-2">
      <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= $editing ? 'Save changes' : 'Save ' . e(strtolower($r['singular'])) ?></button>
      <a class="btn btn-link" href="<?= e(url("admin/$slug")) ?>">Cancel</a>
    </div>
  </form>
</div></div>
