<form method="post" action="<?= e(url('admin/login')) ?>" data-once>
  <?= csrf_field() ?>
  <div class="mb-3"><label class="form-label" for="email">Email</label><input class="form-control form-control-lg" type="email" id="email" name="email" value="<?= e(old('email')) ?>" required autofocus autocomplete="username"></div>
  <div class="mb-4">
    <div class="d-flex justify-content-between align-items-baseline"><label class="form-label" for="password">Password</label><a class="small" href="<?= e(url('admin/forgot-password')) ?>">Forgot password?</a></div>
    <input class="form-control form-control-lg" type="password" id="password" name="password" required autocomplete="current-password">
  </div>
  <button class="btn btn-primary btn-lg w-100">Sign in</button>
</form>
<a class="d-inline-block mt-4 small" href="<?= e(url('')) ?>">&larr; Back to the website</a>
