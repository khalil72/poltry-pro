<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;

function env(string $key, mixed $default = null): mixed
{
    return $_ENV[$key] ?? $default;
}

function config(string $key, mixed $default = null): mixed
{
    static $cache = [];
    [$file, $item] = array_pad(explode('.', $key, 2), 2, null);
    $cache[$file] ??= require BASE_PATH . "/config/$file.php";
    return $item === null ? $cache[$file] : ($cache[$file][$item] ?? $default);
}

function e(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** URL path of the app root: '' on a dedicated host, '/umar/PoultryPro' in a sub-folder. */
function base_path_url(): string
{
    static $base = null;
    if ($base === null) {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $base = rtrim(preg_replace('#(/public)?/index\.php$#', '', $script), '/');
    }
    return $base;
}

function url(string $path = ''): string
{
    return base_path_url() . '/' . ltrim($path, '/');
}

function absolute_url(string $path = ''): string
{
    $scheme = App\Core\Request::isHttps() ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . url($path);
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

/** A stored image path, or a placeholder when none is set. */
function media(?string $path, string $fallback = 'images/site/hen-closeup.jpg'): string
{
    return asset($path ?: $fallback);
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function json_out(array $data): never
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
    exit;
}

function csrf_field(): string
{
    return Csrf::field();
}

function flash(string $type, string $message): void
{
    Session::flash($type, $message);
}

function old(string $key, mixed $default = ''): mixed
{
    return $GLOBALS['_old_input'][$key] ?? $default;
}

function field_error(string $key): ?string
{
    return $GLOBALS['_form_errors'][$key] ?? null;
}

function can(string $perm): bool
{
    return Auth::can($perm);
}

function settings_all(bool $refresh = false): array
{
    static $all = null;
    if ($all === null || $refresh) {
        $all = array_column(Database::all('SELECT setting_key, setting_value FROM website_settings'), 'setting_value', 'setting_key');
    }
    return $all;
}

function setting(string $key, string $default = ''): string
{
    return (string) (settings_all()[$key] ?? $default);
}

function currency(): string
{
    return setting('currency', config('app.currency'));
}

function money(mixed $n, bool $symbol = true): string
{
    return ($symbol ? currency() . ' ' : '') . number_format((float) $n, 2);
}

function num(mixed $n, int $decimals = 0): string
{
    return number_format((float) $n, $decimals);
}

function fdate(?string $d): string
{
    return $d ? date('d M Y', strtotime($d)) : '—';
}

function slugify(string $text): string
{
    $s = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $text), '-'));
    return $s ?: 'item';
}

function label_of(?string $value): string
{
    return ucfirst(str_replace('_', ' ', (string) $value));
}

function status_badge(?string $status): string
{
    $map = [
        'active' => 'success', 'posted' => 'success', 'confirmed' => 'success', 'paid' => 'success', 'published' => 'success',
        'given' => 'success', 'in_stock' => 'success', 'present' => 'success', 'closed' => 'secondary', 'inactive' => 'secondary',
        'voided' => 'secondary', 'resigned' => 'secondary', 'draft' => 'warning', 'partial' => 'warning', 'maintenance' => 'warning',
        'scheduled' => 'info', 'limited' => 'warning', 'on_order' => 'info', 'on_leave' => 'info', 'leave' => 'info', 'half_day' => 'warning',
        'cancelled' => 'danger', 'unpaid' => 'danger', 'missed' => 'danger', 'out_of_stock' => 'danger', 'absent' => 'danger', 'terminated' => 'danger',
    ];
    $c = $map[$status] ?? 'secondary';
    return '<span class="badge rounded-pill text-bg-' . $c . '">' . e(label_of($status)) . '</span>';
}

/** Keeps a safe subset of HTML for CMS articles: scripts, handlers and unknown attributes are removed. */
function sanitize_html(string $html): string
{
    $allowed = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'h2', 'h3', 'h4', 'blockquote', 'a'];
    $drop = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'svg', 'math'];
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8"?><div id="__root">' . $html . '</div>', LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    $root = $doc->getElementById('__root');
    if (!$root) {
        return '';
    }
    $walk = function (DOMNode $node) use (&$walk, $allowed, $drop) {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE) {
                $node->removeChild($child);
            } elseif ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);
                if (in_array($tag, $drop, true)) {
                    $node->removeChild($child);
                    continue;
                }
                $walk($child);
                if (!in_array($tag, $allowed, true)) {
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                    continue;
                }
                foreach (iterator_to_array($child->attributes) as $attr) {
                    $keep = $tag === 'a' && $attr->name === 'href' && preg_match('#^(https?://|mailto:|/)#i', $attr->value);
                    if (!$keep) {
                        $child->removeAttribute($attr->name);
                    }
                }
            }
        }
    };
    $walk($root);
    $out = '';
    foreach ($root->childNodes as $n) {
        $out .= $doc->saveHTML($n);
    }
    return $out;
}

function youtube_id(?string $url): ?string
{
    return $url && preg_match('~(?:youtu\.be/|v=|embed/)([A-Za-z0-9_-]{11})~', $url, $m) ? $m[1] : null;
}

require __DIR__ . '/ui.php';
