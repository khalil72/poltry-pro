<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Services\AlertService;

$user = Auth::user();
$flash = App\Core\Session::pull('_flash', []);
$alerts = AlertService::all();
$current = '/' . trim(App\Core\Request::path(), '/');
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
<title><?= e($title ?? 'Admin') ?> | <?= e(setting('company_name', config('app.name'))) ?></title>
<script>try{document.documentElement.setAttribute('data-bs-theme',localStorage.getItem('pp-theme')||'light')}catch(e){}</script>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Young+Serif&family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/css/bootstrap.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/bi/bootstrap-icons.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
<?php if ($favicon = setting('favicon')): ?><link rel="icon" href="<?= e(asset($favicon)) ?>"><?php endif; ?>
</head>
<body>
<div class="app-shell" id="appShell">
  <aside class="sidebar" id="sidebar" aria-label="Main navigation">
    <a class="brand" href="<?= e(url('admin/dashboard')) ?>">
      <span class="brand-egg" aria-hidden="true"></span>
      <span class="brand-name"><?= e(setting('company_name', config('app.name'))) ?></span>
    </a>
    <nav class="side-nav">
      <?php foreach (config('navigation') as $group):
          $items = array_filter($group['items'], fn ($i) => nav_allowed($i[3]));
          if (!$items) { continue; } ?>
        <div class="nav-group-label"><?= e($group['label']) ?></div>
        <?php foreach ($items as [$label, $href, $icon]):
            $active = $current === '/' . $href || str_starts_with($current, '/' . $href . '/'); ?>
          <a class="side-link <?= $active ? 'active' : '' ?>" href="<?= e(url($href)) ?>" title="<?= e($label) ?>" <?= $active ? 'aria-current="page"' : '' ?>>
            <i class="bi <?= e($icon) ?>" aria-hidden="true"></i><span><?= e($label) ?></span>
          </a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>
  </aside>
  <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

  <div class="main">
    <header class="topbar">
      <button class="btn btn-icon" id="sidebarToggle" type="button" aria-label="Toggle navigation"><i class="bi bi-list"></i></button>
      <div class="topbar-title"><?= e($title ?? '') ?></div>
      <div class="ms-auto d-flex align-items-center gap-1">
        <a class="btn btn-icon d-none d-sm-inline-flex" href="<?= e(url('')) ?>" target="_blank" rel="noopener" title="View website" aria-label="View website"><i class="bi bi-box-arrow-up-right"></i></a>
        <button class="btn btn-icon" id="themeToggle" type="button" aria-label="Switch between light and dark mode"><i class="bi bi-moon-stars"></i></button>
        <div class="dropdown">
          <button class="btn btn-icon position-relative" data-bs-toggle="dropdown" type="button" aria-label="Alerts">
            <i class="bi bi-bell"></i>
            <?php if ($alerts): ?><span class="bell-dot"><?= count($alerts) > 9 ? '9+' : count($alerts) ?></span><?php endif; ?>
          </button>
          <div class="dropdown-menu dropdown-menu-end alert-menu">
            <div class="dropdown-header">Alerts</div>
            <?php foreach (array_slice($alerts, 0, 12) as $a): ?>
              <a class="dropdown-item alert-item" href="<?= e(url($a['url'])) ?>">
                <i class="bi <?= e($a['icon']) ?> text-<?= e($a['level']) ?>"></i><span><?= e($a['text']) ?></span>
              </a>
            <?php endforeach; ?>
            <?php if (!$alerts): ?><div class="px-3 py-2 text-body-secondary small">Nothing needs attention right now.</div><?php endif; ?>
          </div>
        </div>
        <div class="dropdown">
          <button class="btn user-chip" data-bs-toggle="dropdown" type="button">
            <span class="avatar"><?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></span>
            <span class="d-none d-md-inline text-start lh-sm"><span class="d-block fw-semibold small"><?= e($user['name']) ?></span><span class="d-block text-body-secondary" style="font-size:.72rem"><?= e($user['role_name']) ?></span></span>
          </button>
          <div class="dropdown-menu dropdown-menu-end">
            <a class="dropdown-item" href="<?= e(url('admin/profile')) ?>"><i class="bi bi-person me-2"></i>My profile</a>
            <form method="post" action="<?= e(url('admin/logout')) ?>"><?= csrf_field() ?><button class="dropdown-item"><i class="bi bi-box-arrow-right me-2"></i>Sign out</button></form>
          </div>
        </div>
      </div>
    </header>
    <main class="content">
      <?php if (!empty($user['must_change_password'])): ?>
        <div class="alert alert-warning d-flex align-items-center gap-2"><i class="bi bi-shield-exclamation"></i><div>You are using the default password. <a href="<?= e(url('admin/profile')) ?>" class="alert-link">Change it now</a> before going live.</div></div>
      <?php endif; ?>
      <?= $content ?>
    </main>
  </div>
</div>
<div class="toast-container position-fixed top-0 end-0 p-3" id="toasts" style="z-index:1090" aria-live="polite"></div>
<script id="flashData" type="application/json"><?= json_encode($flash, JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= e(asset('vendor/bootstrap/js/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('vendor/swal/sweetalert2.all.min.js')) ?>"></script>
<script src="<?= e(asset('js/admin.js')) ?>"></script>
<script src="<?= e(asset('js/password-toggle.js')) ?>"></script>
<?php foreach ($scripts ?? [] as $s): ?><script src="<?= e(asset($s)) ?>"></script><?php endforeach; ?>
</body>
</html>
