<?php use App\Core\View; ?>
<?= View::capture('website/partials/page_head', ['title' => 'Our services', 'lead' => 'Supply and support for farms and food businesses.', 'image' => 'images/site/chicks-brooder.jpg']) ?>
<section class="sec"><div class="container-xl"><div class="service-list">
  <?php foreach ($services as $s): ?><div class="service-row"><i class="bi <?= e($s['icon'] ?: 'bi-check2') ?>" aria-hidden="true"></i><h2 class="h3"><?= e($s['title']) ?></h2><p><?= e($s['summary']) ?></p></div><?php endforeach; ?>
</div><a class="btn btn-pine mt-4" href="<?= e(url('quote')) ?>">Get a quote</a></div></section>
