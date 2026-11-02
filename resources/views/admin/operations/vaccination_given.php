<div class="page-head"><h1 class="page-title"><i class="bi bi-shield-plus"></i> <?= e($title) ?></h1><a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/vaccination-records')) ?>"><i class="bi bi-arrow-left me-1"></i>Back</a></div>
<div class="card"><div class="card-body">
  <p class="mb-4"><strong><?= e($rec['vaccine_name']) ?></strong> for batch <?= e($rec['batch_code']) ?>, due <?= e(fdate($rec['scheduled_date'])) ?>. <?= e(num($birds)) ?> birds in the batch.</p>
  <form method="post" action="<?= e(url("admin/vaccination-records/{$rec['id']}/given")) ?>" data-once><?= csrf_field() ?>
    <div class="row g-3">
      <div class="col-md-6"><label class="form-label">Date given</label><input class="form-control" type="date" name="given_date" value="<?= e(date('Y-m-d')) ?>" required></div>
      <div class="col-md-6"><label class="form-label">Veterinarian</label><input class="form-control" name="veterinarian" maxlength="120" value="<?= e($rec['veterinarian']) ?>"></div>
      <?php if ($rec['medicine_id']): ?>
      <div class="col-md-6"><label class="form-label">Doses used (<?= e($rec['unit']) ?> of <?= e($rec['medicine_name']) ?>)</label><input class="form-control" type="number" step="0.001" min="0" name="doses" value="<?= e($birds) ?>"><div class="form-text">Taken out of medicine stock, using the lot that expires first.</div></div>
      <?php endif; ?>
      <div class="col-12"><label class="form-label">Notes</label><input class="form-control" name="notes" maxlength="255"></div>
    </div>
    <button class="btn btn-primary mt-4"><i class="bi bi-check2-circle me-1"></i>Mark as given</button>
  </form>
</div></div>
