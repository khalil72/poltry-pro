<?php $slugs = array_map('slugify', array_keys($groups)); $values = settings_all(); $manage = can(($preview ? 'cms' : 'settings') . '.manage'); ?>
<div class="page-head"><h1 class="page-title"><i class="bi bi-window-stack"></i> <?= e($title) ?></h1>
  <?php if ($preview): ?><a class="btn btn-outline-secondary btn-sm" href="<?= e(url('')) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1"></i>View website</a><?php endif; ?></div>
<form method="post" action="<?= e(url($action)) ?>" enctype="multipart/form-data" data-once><?= csrf_field() ?>
  <div class="card">
    <div class="card-header pb-0"><ul class="nav nav-tabs card-header-tabs" role="tablist">
      <?php $i = 0; foreach ($groups as $name => $_): ?><li class="nav-item" role="presentation"><button class="nav-link <?= $i++ === 0 ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#t-<?= e(slugify($name)) ?>" type="button" role="tab"><?= e($name) ?></button></li><?php endforeach; ?>
    </ul></div>
    <div class="card-body tab-content">
      <?php $i = 0; foreach ($groups as $name => $fields): ?>
        <div class="tab-pane fade <?= $i++ === 0 ? 'show active' : '' ?>" id="t-<?= e(slugify($name)) ?>" role="tabpanel"><div class="row g-3">
          <?php foreach ($fields as $key => $def):
              [$label, $type] = $def; $help = $def[2] ?? null; $val = $values[$key] ?? ''; $err = field_error($key); ?>
            <div class="<?= $type === 'textarea' ? 'col-12' : 'col-md-6' ?>">
              <?php if ($type === 'toggle'): ?>
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="s_<?= e($key) ?>" name="<?= e($key) ?>" value="1" <?= $val === '1' ? 'checked' : '' ?>><label class="form-check-label" for="s_<?= e($key) ?>"><?= e($label) ?></label></div>
              <?php else: ?>
                <label class="form-label" for="s_<?= e($key) ?>"><?= e($label) ?></label>
                <?php if ($type === 'textarea'): ?><textarea class="form-control" id="s_<?= e($key) ?>" name="<?= e($key) ?>" rows="3"><?= e(old($key, $val)) ?></textarea>
                <?php elseif ($type === 'image'): ?>
                  <input class="form-control" type="file" id="s_<?= e($key) ?>" name="<?= e($key) ?>" accept="image/jpeg,image/png,image/webp" data-preview="#pv_<?= e($key) ?>">
                  <img id="pv_<?= e($key) ?>" class="img-preview <?= $val ? '' : 'd-none' ?>" src="<?= $val ? e(asset($val)) : '' ?>" alt="Preview">
                  <?php if ($val): ?><div class="form-check mt-1"><input class="form-check-input" type="checkbox" id="rm_<?= e($key) ?>" name="_remove_<?= e($key) ?>" value="1"><label class="form-check-label small" for="rm_<?= e($key) ?>">Remove this image</label></div><?php endif; ?>
                <?php else: ?><input class="form-control <?= $err ? 'is-invalid' : '' ?>" type="<?= $type === 'number' ? 'number' : 'text' ?>" <?= $type === 'number' ? 'step="any"' : '' ?> id="s_<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e(old($key, $val)) ?>"><?php endif; ?>
                <?php if ($err): ?><div class="invalid-feedback d-block"><?= e($err) ?></div><?php endif; ?>
                <?php if ($help): ?><div class="form-text"><?= e($help) ?></div><?php endif; ?>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div></div>
      <?php endforeach; ?>
    </div>
    <?php if ($manage): ?><div class="card-footer"><button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save changes</button></div><?php endif; ?>
  </div>
</form>
