<section class="page-hero" style="background-image:linear-gradient(rgba(13,42,32,.78),rgba(13,42,32,.78)),url('<?= e(asset($image ?? 'images/site/hens-field.jpg')) ?>')">
  <div class="container-xl">
    <h1><?= e($title) ?></h1>
    <?php if (!empty($lead)): ?><p><?= e($lead) ?></p><?php endif; ?>
  </div>
</section>
