<div class="page-head"><h1 class="page-title"><i class="bi bi-file-earmark-bar-graph"></i> Reports</h1></div>
<div class="row g-3">
  <?php foreach ($defs as $key => $d): ?>
    <div class="col-sm-6 col-lg-4 col-xl-3"><a class="card h-100 text-decoration-none report-tile" href="<?= e(url("admin/reports/$key")) ?>"><div class="card-body d-flex align-items-center gap-3"><i class="bi <?= e($d['icon']) ?> fs-4"></i><span class="fw-semibold"><?= e($d['title']) ?></span></div></a></div>
  <?php endforeach; ?>
</div>
