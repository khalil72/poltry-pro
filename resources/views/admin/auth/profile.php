<div class="row g-4">
  <div class="col-lg-6"><div class="card"><div class="card-body">
    <h2 class="h5 mb-3">Your details</h2>
    <form method="post" action="<?= e(url('admin/profile')) ?>" data-once>
      <?= csrf_field() ?>
      <div class="mb-3"><label class="form-label">Name</label><input class="form-control" name="name" value="<?= e(old('name', $user['name'])) ?>" required></div>
      <div class="mb-3"><label class="form-label">Email</label><input class="form-control" value="<?= e($user['email']) ?>" disabled></div>
      <div class="mb-3"><label class="form-label">Phone</label><input class="form-control" name="phone" value="<?= e(old('phone', $user['phone'])) ?>"></div>
      <div class="mb-3"><label class="form-label">Role</label><input class="form-control" value="<?= e($user['role_name']) ?>" disabled></div>
      <button class="btn btn-primary">Save changes</button>
    </form>
  </div></div></div>
  <div class="col-lg-6"><div class="card"><div class="card-body">
    <h2 class="h5 mb-3">Change password</h2>
    <form method="post" action="<?= e(url('admin/profile/password')) ?>" data-once>
      <?= csrf_field() ?>
      <div class="mb-3"><label class="form-label">Current password</label><input class="form-control" type="password" name="current_password" required autocomplete="current-password"></div>
      <div class="mb-3"><label class="form-label">New password</label><input class="form-control" type="password" name="password" minlength="8" required autocomplete="new-password"><div class="form-text">At least 8 characters.</div></div>
      <div class="mb-3"><label class="form-label">Repeat new password</label><input class="form-control" type="password" name="password_confirm" minlength="8" required autocomplete="new-password"></div>
      <button class="btn btn-primary">Change password</button>
    </form>
  </div></div></div>
</div>
