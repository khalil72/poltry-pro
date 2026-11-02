<?php $flash = App\Core\Session::pull('_flash', []); ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title><?= e($title) ?> | <?= e(setting('company_name', config('app.name'))) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Young+Serif&family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/css/bootstrap.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/bi/bootstrap-icons.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="login-page">
  <div class="login-photo" style="background-image:url('<?= e(asset('images/site/layer-house.jpg')) ?>')" aria-hidden="true"></div>
  <main class="login-panel">
    <a class="brand brand-dark mb-4" href="<?= e(url('')) ?>"><span class="brand-egg" aria-hidden="true"></span><span class="brand-name"><?= e(setting('company_name', config('app.name'))) ?></span></a>
    <h1 class="login-title"><?= e($heading) ?></h1>
    <?php foreach ($flash as $f): ?>
      <div class="alert alert-<?= ['error' => 'danger', 'success' => 'success'][$f['type']] ?? 'info' ?> py-2" role="alert"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
    <?= $content ?>
  </main>
  <script src="<?= e(asset('js/password-toggle.js')) ?>"></script>
</body>
</html>
