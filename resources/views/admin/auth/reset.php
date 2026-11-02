<?php if ($valid): ?>
  <form method="post" action="<?= e(url('admin/reset-password')) ?>" data-once>
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <div class="mb-3"><label class="form-label" for="password">New password</label><input class="form-control form-control-lg" type="password" id="password" name="password" minlength="8" required autofocus autocomplete="new-password"><div class="form-text">At least 8 characters.</div></div>
    <div class="mb-4"><label class="form-label" for="password_confirm">Repeat new password</label><input class="form-control form-control-lg" type="password" id="password_confirm" name="password_confirm" minlength="8" required autocomplete="new-password"></div>
    <button class="btn btn-primary btn-lg w-100">Save new password</button>
  </form>
<?php else: ?>
  <p>This reset link has expired or was already used. Links work once, for <?= (int) config('app.password_reset_minutes') ?> minutes.</p>
  <a class="btn btn-primary btn-lg w-100" href="<?= e(url('admin/forgot-password')) ?>">Request a new link</a>
<?php endif; ?>
<a class="d-inline-block mt-4 small" href="<?= e(url('admin/login')) ?>">&larr; Back to sign in</a>
