<form method="post" action="<?= e(url('contact')) ?>" class="contact-form">
  <?= csrf_field() ?>
  <input type="hidden" name="message_type" value="<?= e($type ?? 'contact') ?>">
  <?php if (!empty($productId)): ?><input type="hidden" name="product_id" value="<?= (int) $productId ?>"><?php endif; ?>
  <div class="hp" aria-hidden="true"><label>Leave this empty<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label" for="cf_name">Your name</label><input class="form-control" id="cf_name" name="name" value="<?= e(old('name')) ?>" required maxlength="120" autocomplete="name"></div>
    <div class="col-md-6"><label class="form-label" for="cf_phone">Phone</label><input class="form-control" id="cf_phone" type="tel" name="phone" value="<?= e(old('phone')) ?>" autocomplete="tel"></div>
    <div class="col-md-6"><label class="form-label" for="cf_email">Email</label><input class="form-control" id="cf_email" type="email" name="email" value="<?= e(old('email')) ?>" autocomplete="email"></div>
    <div class="col-md-6"><label class="form-label" for="cf_subject">Subject</label><input class="form-control" id="cf_subject" name="subject" value="<?= e(old('subject', $subject ?? '')) ?>" maxlength="200"></div>
    <?php if (!empty($products)): ?>
      <div class="col-12"><label class="form-label" for="cf_product">Product</label><select class="form-select" id="cf_product" name="product_id"><option value="">Not sure yet</option><?php foreach ($products as $p): ?><option value="<?= (int) $p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?></select></div>
    <?php endif; ?>
    <div class="col-12"><label class="form-label" for="cf_message"><?= e($messageLabel ?? 'Your message') ?></label><textarea class="form-control" id="cf_message" name="message" rows="5" required maxlength="5000"><?= e(old('message')) ?></textarea></div>
  </div>
  <button class="btn btn-pine mt-3"><?= e($button ?? 'Send message') ?></button>
</form>
