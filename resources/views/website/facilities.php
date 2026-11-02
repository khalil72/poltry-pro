<?php use App\Core\View; ?>
<?= View::capture('website/partials/page_head', ['title' => 'Farm facilities', 'lead' => 'The systems behind healthy birds.', 'image' => 'images/site/layer-house.jpg']) ?>
<section class="sec"><div class="container-xl"><div class="facility-cards">
  <?php foreach ($facilities as $f): ?>
    <article class="facility-card"><?php if ($f['image']): ?><img src="<?= e(asset($f['image'])) ?>" alt="" loading="lazy"><?php endif; ?><div class="p-4"><i class="bi <?= e($f['icon'] ?: 'bi-check2') ?> fs-3" aria-hidden="true"></i><h2 class="h4 mt-2"><?= e($f['title']) ?></h2><p class="mb-0"><?= e($f['summary']) ?></p></div></article>
  <?php endforeach; ?>
</div></div></section>
