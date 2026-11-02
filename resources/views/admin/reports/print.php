<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title><?= e($def['title']) ?></title><?= App\Core\View::capture('components/print_head') ?>
<style>table.items td,table.items th{font-size:9px;padding:4px 5px} tfoot td{font-weight:bold;border-top:2px solid #12382b}</style></head>
<body><div class="doc" style="max-width:none">
<?php if (!empty($print)): ?><div class="no-print"><button onclick="window.print()">Print</button></div><?php endif; ?>
<table class="doc-head"><tr><td><div class="company"><?= e(setting('company_name', config('app.name'))) ?></div></td><td><div class="doc-title"><?= e($def['title']) ?></div><div class="r muted"><?= e($subtitle) ?></div></td></tr></table>
<?php if ($result['note']): ?><p class="muted"><?= e($result['note']) ?></p><?php endif; ?>
<table class="items"><thead><tr><?php foreach ($result['columns'] as $c): ?><th class="<?= is_numeric_column($c) ? 'r' : '' ?>"><?= e($c['label']) ?></th><?php endforeach; ?></tr></thead>
<tbody><?php foreach ($result['rows'] as $r): ?><tr <?= !empty($r['bold']) ? 'style="font-weight:bold"' : '' ?>><?php foreach ($result['columns'] as $c):
    $v = $r[$c['key']] ?? null; $t = $c['type'] ?? 'text';
    $txt = match ($t) { 'money' => $v === null ? '' : money($v, false), 'number' => $v === null ? '' : num($v), 'decimal' => $v === null ? '' : num($v, 2), 'date' => fdate($v), 'label' => label_of($v), default => (string) $v }; ?>
  <td class="<?= is_numeric_column($c) ? 'r' : '' ?>"><?= e($txt) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody>
<?php if ($result['totals']): ?><tfoot><tr><?php foreach ($result['columns'] as $i => $c):
    $v = $result['totals'][$c['key']] ?? null; $t = $c['type'] ?? 'text'; ?>
  <td class="<?= is_numeric_column($c) ? 'r' : '' ?>"><?= $v !== null ? e($t === 'money' ? money($v, false) : num($v, $t === 'decimal' ? 2 : 0)) : ($i === 0 ? 'Total' : '') ?></td><?php endforeach; ?></tr></tfoot><?php endif; ?>
</table>
<div class="foot"><?= (int) $result['count'] ?> records. All figures are amounts in <?= e(currency()) ?> where money is shown.</div>
</div>
<?php if (!empty($print)): ?><script>window.addEventListener('load',function(){window.print()})</script><?php endif; ?>
</body></html>
