<?php use App\Core\View; ?>
<?= View::capture('website/partials/page_head', ['title' => 'Our farms', 'lead' => 'Where our birds are raised.', 'image' => 'images/site/hero-shed.jpg']) ?>
<section class="sec"><div class="container-xl">
  <?php foreach ($farms as $i => $f): ?>
    <div class="row g-5 align-items-center mb-5 <?= $i % 2 ? 'flex-lg-row-reverse' : '' ?>">
      <div class="col-lg-5"><div class="egg-frame"><img src="<?= e(media($f['image'], 'images/site/broiler-flock.jpg')) ?>" alt="<?= e($f['name']) ?>" loading="lazy"></div></div>
      <div class="col-lg-7"><h2 class="sec-title"><?= e($f['name']) ?></h2><p class="mini-h"><i class="bi bi-geo-alt me-1"></i><?= e($f['location']) ?></p><p class="lead-text"><?= e($f['description']) ?></p><?php if ($f['capacity']): ?><p>Capacity for <?= e(num($f['capacity'])) ?> birds.</p><?php endif; ?></div>
    </div>
  <?php endforeach; ?>
  <?php if (!$farms): ?><p>Farm details will appear here soon.</p><?php endif; ?>
</div></section>
