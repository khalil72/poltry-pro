<div class="page-head"><h1 class="page-title"><i class="bi bi-shield-lock"></i> <?= e($title) ?></h1><a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/roles')) ?>"><i class="bi bi-arrow-left me-1"></i>Back</a></div>
<form method="post" action="<?= e(url('admin/roles/' . $role['id'])) ?>" data-once><?= csrf_field() ?>
  <div class="card mb-4"><div class="card-body"><div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" id="allFarms" name="all_farms" value="1" <?= $role['all_farms'] ? 'checked' : '' ?>><label class="form-check-label" for="allFarms">Can see all farms. When off, users only see the farms assigned to them.</label></div></div></div>
  <div class="row g-3">
    <?php foreach ($grouped as $module => $perms): ?>
      <div class="col-md-6 col-xl-4"><div class="card h-100"><div class="card-header"><?= e(label_of($module)) ?></div><div class="card-body">
        <?php foreach ($perms as $p): ?><div class="form-check"><input class="form-check-input" type="checkbox" id="p<?= (int) $p['id'] ?>" name="permissions[]" value="<?= (int) $p['id'] ?>" <?= isset($granted[$p['id']]) ? 'checked' : '' ?>><label class="form-check-label" for="p<?= (int) $p['id'] ?>"><?= e($p['label']) ?></label></div><?php endforeach; ?>
      </div></div></div>
    <?php endforeach; ?>
  </div>
  <button class="btn btn-primary mt-4"><i class="bi bi-check-lg me-1"></i>Save permissions</button>
</form>
