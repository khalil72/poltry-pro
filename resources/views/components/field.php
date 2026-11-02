<?php
use App\Services\Lookup;

$n = $f['name'];
$type = $f['type'] ?? 'text';
$default = $f['default'] ?? '';
if ($default === 'today') { $default = date('Y-m-d'); }
if ($default === 'this_month') { $default = date('Y-m'); }
$value = old($n, $row[$n] ?? $default);
$err = field_error($n);
$required = str_contains($f['rules'] ?? '', 'required');
$cls = $err ? ' is-invalid' : '';
$id = 'f_' . $n;
?>
<?php if ($type === 'checkbox'):
    $checked = array_key_exists($n, $GLOBALS['_old_input'] ?? []) ? true : (int) ($row[$n] ?? $default) === 1; ?>
  <div class="form-check form-switch mt-md-4 pt-md-2">
    <input class="form-check-input" type="checkbox" role="switch" id="<?= e($id) ?>" name="<?= e($n) ?>" value="1" <?= $checked ? 'checked' : '' ?>>
    <label class="form-check-label" for="<?= e($id) ?>"><?= e($f['label']) ?></label>
  </div>
<?php elseif ($type === 'multicheck'):
    $sel = array_map('strval', (array) old($n, $row[$n] ?? [])); ?>
  <label class="form-label"><?= e($f['label']) ?></label>
  <div class="d-flex flex-wrap gap-3">
    <?php foreach (Lookup::options($f['lookup']) as $o): ?>
      <div class="form-check"><input class="form-check-input" type="checkbox" id="<?= e($id . $o['value']) ?>" name="<?= e($n) ?>[]" value="<?= e($o['value']) ?>" <?= in_array((string) $o['value'], $sel, true) ? 'checked' : '' ?>><label class="form-check-label" for="<?= e($id . $o['value']) ?>"><?= e($o['label']) ?></label></div>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <label class="form-label" for="<?= e($id) ?>"><?= e($f['label']) ?><?= $required ? ' <span class="text-danger" aria-hidden="true">*</span>' : '' ?></label>
  <?php if ($type === 'textarea'): ?>
    <textarea class="form-control<?= $cls ?>" id="<?= e($id) ?>" name="<?= e($n) ?>" rows="<?= (int) ($f['rows'] ?? 4) ?>" <?= $required ? 'required' : '' ?>><?= e($value) ?></textarea>
  <?php elseif ($type === 'select'): ?>
    <select class="form-select<?= $cls ?>" id="<?= e($id) ?>" name="<?= e($n) ?>" <?= $required ? 'required' : '' ?> <?= !empty($f['parent']) ? 'data-parent-field="' . e($f['parent']) . '"' : '' ?>>
      <option value="">Choose…</option>
      <?php if (isset($f['lookup'])): foreach (Lookup::options($f['lookup']) as $o): ?>
        <option value="<?= e($o['value']) ?>" <?= (string) $value === (string) $o['value'] ? 'selected' : '' ?> <?= isset($o['parent']) ? 'data-parent="' . e($o['parent']) . '"' : '' ?>><?= e($o['label']) ?></option>
      <?php endforeach; else: foreach ($f['options'] as $v => $l): ?>
        <option value="<?= e($v) ?>" <?= (string) $value === (string) $v ? 'selected' : '' ?>><?= e($l) ?></option>
      <?php endforeach; endif; ?>
    </select>
  <?php elseif ($type === 'image' || $type === 'file'):
      $accept = $type === 'image' ? 'image/jpeg,image/png,image/webp' : 'image/jpeg,image/png,image/webp,application/pdf'; ?>
    <input class="form-control<?= $cls ?>" type="file" id="<?= e($id) ?>" name="<?= e($n) ?>" accept="<?= e($accept) ?>" <?= $type === 'image' ? 'data-preview="#p_' . e($n) . '"' : '' ?>>
    <?php if ($type === 'image'): ?>
      <img id="p_<?= e($n) ?>" class="img-preview <?= !empty($row[$n]) ? '' : 'd-none' ?>" src="<?= !empty($row[$n]) ? e(asset($row[$n])) : '' ?>" alt="Preview">
      <?php if (!empty($row[$n])): ?><div class="form-check mt-1"><input class="form-check-input" type="checkbox" id="rm_<?= e($n) ?>" name="_remove_<?= e($n) ?>" value="1"><label class="form-check-label small" for="rm_<?= e($n) ?>">Remove this image</label></div><?php endif; ?>
    <?php endif; ?>
  <?php else:
      $inputType = in_array($type, ['number', 'date', 'email', 'tel', 'password', 'month'], true) ? $type : 'text';
      $value = $type === 'password' ? '' : $value; ?>
    <input class="form-control<?= $cls ?>" type="<?= e($inputType) ?>" id="<?= e($id) ?>" name="<?= e($n) ?>" value="<?= e($value) ?>" <?= $required ? 'required' : '' ?> <?= isset($f['step']) ? 'step="' . e($f['step']) . '"' : '' ?> <?= $type === 'password' ? 'autocomplete="new-password"' : '' ?>>
  <?php endif; ?>
<?php endif; ?>
<?php if ($err): ?><div class="invalid-feedback d-block"><?= e($err) ?></div><?php endif; ?>
<?php if (!empty($f['help'])): ?><div class="form-text"><?= e($f['help']) ?></div><?php endif; ?>
