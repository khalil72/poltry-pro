<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= (int) $code ?> | <?= e(config('app.name')) ?></title>
<style>
  body{margin:0;min-height:100vh;display:grid;place-items:center;background:#12382b;color:#fff;font-family:system-ui,sans-serif;padding:24px}
  .box{max-width:480px;text-align:center}
  .code{width:110px;height:138px;margin:0 auto 20px;background:#fff;color:#12382b;border-radius:50% 50% 50% 50%/60% 60% 40% 40%;display:grid;place-items:center;font-size:32px;font-weight:700}
  h1{font-size:22px;margin:0 0 12px;font-weight:600} a{color:#f2c14e}
</style>
</head>
<body><div class="box">
  <div class="code"><?= (int) $code ?></div>
  <h1><?= e($message) ?></h1>
  <p><a href="<?= e(url('')) ?>">Go to the homepage</a><?php if ($code === 403): ?> · <a href="<?= e(url('admin/dashboard')) ?>">Go to the dashboard</a><?php endif; ?></p>
</div></body>
</html>
