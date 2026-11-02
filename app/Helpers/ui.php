<?php
/** Rendering helpers for admin screens. */

function list_url(array $changes): string
{
    $q = array_merge($_GET, $changes);
    $q = array_filter($q, fn ($v) => $v !== null && $v !== '');
    return '?' . http_build_query($q);
}

function fill_placeholders(string $tpl, array $row): string
{
    return preg_replace_callback('/\{(\w+)\}/', fn ($m) => rawurlencode((string) ($row[$m[1]] ?? '')), $tpl);
}

function render_cell(array $c, array $row): string
{
    $v = $row[$c['key']] ?? null;
    $type = $c['type'] ?? 'text';
    $html = match ($type) {
        'money' => $v === null ? '—' : e(money($v, false)),
        'number' => $v === null ? '—' : e(num($v)),
        'decimal' => $v === null ? '—' : e(num($v, 2)),
        'signed' => '<span class="' . ((float) $v < 0 ? 'text-danger' : 'text-success') . '">' . ((float) $v > 0 ? '+' : '') . e(num($v, $c['decimals'] ?? 0)) . '</span>',
        'date' => e(fdate($v)),
        'datetime' => $v ? e(date('d M Y H:i', strtotime($v))) : '—',
        'badge' => status_badge($v),
        'label' => e(label_of($v)),
        'bool' => $v ? '<i class="bi bi-check-circle-fill text-success" title="Yes"></i><span class="visually-hidden">Yes</span>' : '<i class="bi bi-dash-circle text-body-tertiary" title="No"></i><span class="visually-hidden">No</span>',
        'image' => $v ? '<img class="tbl-thumb" src="' . e(asset($v)) . '" alt="" loading="lazy">' : '<span class="tbl-thumb tbl-thumb-empty"><i class="bi bi-image"></i></span>',
        'progress' => '<div class="d-flex align-items-center gap-2"><div class="progress flex-grow-1" style="height:6px;min-width:48px"><div class="progress-bar ' . ((int) $v >= 95 ? 'bg-danger' : '') . '" style="width:' . min(100, (int) $v) . '%"></div></div><small>' . (int) $v . '%</small></div>',
        'alert' => $v ? '<span class="badge text-bg-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i>' . e($v) . '</span>' : '',
        default => $v === null || $v === '' ? '' : e($v),
    };
    if (!empty($c['strong'])) {
        $html = '<strong>' . $html . '</strong>';
    }
    if (!empty($c['link'])) {
        $html = '<a href="' . e(url(fill_placeholders($c['link'], $row))) . '" class="fw-semibold">' . $html . '</a>';
    }
    return $html;
}

function is_numeric_column(array $c): bool
{
    return in_array($c['type'] ?? 'text', ['money', 'number', 'decimal', 'signed'], true);
}

function nav_allowed(string $perm): bool
{
    if (str_starts_with($perm, 'any:')) {
        foreach (explode(',', substr($perm, 4)) as $p) {
            if (can($p)) {
                return true;
            }
        }
        return false;
    }
    return can($perm);
}
