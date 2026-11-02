<p class="text-body-secondary">Enter your account email and we will send you a link to choose a new password.</p>
<?php if (!empty($devLink)): ?>
  <div class="alert alert-warning small" role="status">
    <strong>Local test mode.</strong> No email is sent on this computer, so use this link. It never appears on a live site.
    <a class="d-block text-break mt-1" href="<?= e($devLink) ?>">Open the reset link</a>
  </div>
<?php endif; ?>
<form method="post" action="<?= e(url('admin/forgot-password')) ?>" data-once>
  <?= csrf_field() ?>
  <div class="mb-4"><label class="form-label" for="email">Email</label><input class="form-control form-control-lg" type="email" id="email" name="email" value="<?= e(old('email')) ?>" required autofocus autocomplete="username"></div>
  <button class="btn btn-primary btn-lg w-100">Send reset link</button>
</form>
<a class="d-inline-block mt-4 small" href="<?= e(url('admin/login')) ?>">&larr; Back to sign in</a>
