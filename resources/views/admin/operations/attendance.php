<div class="page-head"><h1 class="page-title"><i class="bi bi-calendar-check"></i> <?= e($title) ?></h1><a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/attendance')) ?>"><i class="bi bi-calendar3 me-1"></i>Attendance log</a></div>
<div class="card">
  <form class="filter-bar" method="get">
    <label class="filter-date"><span>Date</span><input class="form-control form-control-sm" type="date" name="date" value="<?= e($date) ?>" max="<?= e(date('Y-m-d')) ?>"></label>
    <select class="form-select form-select-sm filter-input" name="farm_id" aria-label="Farm"><option value="">Farm: all</option><?php foreach (App\Services\Lookup::options('farms') as $o): ?><option value="<?= e($o['value']) ?>" <?= (string) $farmId === (string) $o['value'] ? 'selected' : '' ?>><?= e($o['label']) ?></option><?php endforeach; ?></select>
    <button class="btn btn-sm btn-primary">Load</button>
  </form>
  <form method="post" action="<?= e(url('admin/attendance/mark')) ?>" data-once><?= csrf_field() ?><input type="hidden" name="date" value="<?= e($date) ?>">
    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Employee</th><th>Farm</th><th>Status</th><th>Notes</th></tr></thead><tbody>
      <?php foreach ($rows as $r): $cur = $r['att_status'] ?: 'present'; ?>
        <tr><td><strong><?= e($r['name']) ?></strong><br><small class="text-body-secondary"><?= e($r['employee_code']) ?> · <?= e($r['designation']) ?></small></td><td><?= e($r['farm_name']) ?></td>
          <td class="text-nowrap"><?php foreach (['present' => 'Present', 'absent' => 'Absent', 'leave' => 'Leave', 'half_day' => 'Half day'] as $v => $l): ?><div class="form-check form-check-inline"><input class="form-check-input" type="radio" id="a<?= (int) $r['id'] . $v ?>" name="status[<?= (int) $r['id'] ?>]" value="<?= e($v) ?>" <?= $cur === $v ? 'checked' : '' ?>><label class="form-check-label" for="a<?= (int) $r['id'] . $v ?>"><?= e($l) ?></label></div><?php endforeach; ?></td>
          <td><input class="form-control form-control-sm" name="notes[<?= (int) $r['id'] ?>]" value="<?= e($r['att_notes']) ?>" maxlength="255" aria-label="Notes for <?= e($r['name']) ?>"></td></tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="4" class="text-body-secondary">No active employees here.</td></tr><?php endif; ?>
    </tbody></table></div>
    <?php if ($rows): ?><div class="p-3 border-top"><button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save attendance for <?= e(fdate($date)) ?></button></div><?php endif; ?>
  </form>
</div>
