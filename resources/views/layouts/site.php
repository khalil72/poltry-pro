<?php
$company = setting('company_name', config('app.name'));
$siteTitle = setting('seo_title', $company);
$fullTitle = isset($pageTitle) ? "$pageTitle | $company" : $siteTitle;
$desc = $metaDescription ?? setting('seo_description');
$flash = App\Core\Session::pull('_flash', []);
$path = '/' . trim(App\Core\Request::path(), '/');
$nav = [['Home', ''], ['About Us', 'about'], ['Our Farms', 'farms'], ['Products', 'products'], ['Services', 'services'], ['Facilities', 'facilities'], ['Gallery', 'gallery'], ['Blog', 'blog'], ['Contact', 'contact']];
$wa = preg_replace('/\D/', '', setting('contact_whatsapp'));
$logo = setting('logo');
$social = array_filter(['facebook' => setting('social_facebook'), 'instagram' => setting('social_instagram'), 'youtube' => setting('social_youtube'), 'linkedin' => setting('social_linkedin')]);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($fullTitle) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<?php if ($k = setting('seo_keywords')): ?><meta name="keywords" content="<?= e($k) ?>"><?php endif; ?>
<link rel="canonical" href="<?= e(absolute_url(ltrim($path, '/'))) ?>">
<meta property="og:title" content="<?= e($fullTitle) ?>"><meta property="og:description" content="<?= e($desc) ?>"><meta property="og:type" content="website">
<meta property="og:image" content="<?= e(absolute_url('assets/' . (($ogImage ?? null) ?: setting('hero_image', 'images/site/hero-shed.jpg')))) ?>">
<?php if ($favicon = setting('favicon')): ?><link rel="icon" href="<?= e(asset($favicon)) ?>"><?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Young+Serif&family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/css/bootstrap.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/bi/bootstrap-icons.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">
</head>
<body class="<?= !empty($navHome) ? 'on-home' : '' ?>">
<a class="visually-hidden-focusable skip" href="#main">Skip to content</a>
<header class="site-head" id="siteHead">
  <nav class="navbar navbar-expand-xl" aria-label="Main">
    <div class="container-xl">
      <a class="navbar-brand site-brand" href="<?= e(url('')) ?>">
        <?php if ($logo): ?><img src="<?= e(asset($logo)) ?>" alt="" height="36"><?php else: ?><span class="egg-mark" aria-hidden="true"></span><?php endif; ?>
        <span><?= e($company) ?></span>
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Open menu"><i class="bi bi-list"></i></button>
      <div class="collapse navbar-collapse" id="mainNav">
        <ul class="navbar-nav mx-auto">
          <?php foreach ($nav as [$label, $href]):
              $active = $href === '' ? $path === '/' : str_starts_with($path, '/' . $href); ?>
            <li class="nav-item"><a class="nav-link <?= $active ? 'active' : '' ?>" <?= $active ? 'aria-current="page"' : '' ?> href="<?= e(url($href)) ?>"><?= e($label) ?></a></li>
          <?php endforeach; ?>
        </ul>
        <div class="d-flex align-items-center gap-2 nav-actions">
          <?php if ($wa): ?><a class="btn-wa" href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener" aria-label="Message us on WhatsApp"><i class="bi bi-whatsapp"></i></a><?php endif; ?>
          <a class="btn btn-yolk" href="<?= e(url('quote')) ?>">Get a Quote</a>
        </div>
      </div>
    </div>
  </nav>
</header>

<?php if ($flash): ?>
<div class="site-flash" role="status">
  <?php foreach ($flash as $f): ?><div class="alert alert-<?= $f['type'] === 'error' ? 'danger' : 'success' ?> alert-dismissible mb-2"><?= e($f['message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endforeach; ?>
</div>
<?php endif; ?>

<main id="main"><?= $content ?></main>

<footer class="site-foot">
  <div class="container-xl">
    <div class="row g-5">
      <div class="col-lg-4">
        <div class="site-brand mb-3"><span class="egg-mark" aria-hidden="true"></span><span><?= e($company) ?></span></div>
        <p class="foot-about"><?= e(setting('footer_about')) ?></p>
        <?php if ($social): ?><div class="d-flex gap-2"><?php foreach ($social as $net => $link): ?><a class="foot-social" href="<?= e($link) ?>" target="_blank" rel="noopener" aria-label="<?= e(ucfirst($net)) ?>"><i class="bi bi-<?= e($net) ?>"></i></a><?php endforeach; ?></div><?php endif; ?>
      </div>
      <div class="col-6 col-lg-2"><h2 class="foot-h">Quick links</h2><ul class="foot-list"><li><a href="<?= e(url('about')) ?>">About us</a></li><li><a href="<?= e(url('farms')) ?>">Our farms</a></li><li><a href="<?= e(url('facilities')) ?>">Facilities</a></li><li><a href="<?= e(url('gallery')) ?>">Gallery</a></li><li><a href="<?= e(url('blog')) ?>">Blog</a></li><li><a href="<?= e(url('contact')) ?>">Contact</a></li></ul></div>
      <div class="col-6 col-lg-2"><h2 class="foot-h">Products</h2><ul class="foot-list"><?php foreach (App\Core\Database::all('SELECT name, slug FROM products WHERE is_published=1 AND deleted_at IS NULL ORDER BY sort_order LIMIT 6') as $p): ?><li><a href="<?= e(url('products/' . $p['slug'])) ?>"><?= e($p['name']) ?></a></li><?php endforeach; ?></ul></div>
      <div class="col-lg-4">
        <h2 class="foot-h">Reach us</h2>
        <ul class="foot-list mb-4">
          <?php if ($a = setting('contact_address')): ?><li><?= e($a) ?></li><?php endif; ?>
          <?php if ($a = setting('contact_phone')): ?><li><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $a)) ?>"><?= e($a) ?></a></li><?php endif; ?>
          <?php if ($a = setting('contact_email')): ?><li><a href="mailto:<?= e($a) ?>"><?= e($a) ?></a></li><?php endif; ?>
        </ul>
        <h2 class="foot-h">Newsletter</h2>
        <form class="foot-news" method="post" action="<?= e(url('newsletter')) ?>"><?= csrf_field() ?>
          <input class="form-control" type="email" name="email" placeholder="Your email" aria-label="Your email" required>
          <button class="btn btn-yolk">Subscribe</button>
        </form>
      </div>
    </div>
    <div class="foot-base"><span><?= e(str_replace('{year}', date('Y'), setting('footer_copyright', '© {year} ' . $company))) ?></span><a href="<?= e(url('admin/login')) ?>">Staff sign-in</a></div>
  </div>
</footer>
<?php if ($wa): ?><a class="wa-float" href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener" aria-label="Message us on WhatsApp"><i class="bi bi-whatsapp"></i></a><?php endif; ?>
<script src="<?= e(asset('vendor/bootstrap/js/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('js/site.js')) ?>"></script>
</body>
</html>
