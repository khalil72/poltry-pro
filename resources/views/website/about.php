<?php use App\Core\View; ?>
<?= View::capture('website/partials/page_head', ['title' => setting('about_title', 'About us'), 'lead' => setting('company_tagline'), 'image' => 'images/site/layer-house.jpg']) ?>
<section class="sec"><div class="container-xl"><div class="row g-5 align-items-center">
  <div class="col-lg-5"><div class="egg-frame"><img src="<?= e(asset(setting('about_image', 'images/site/hen-closeup.jpg'))) ?>" alt="A hen on our farm" loading="lazy"></div></div>
  <div class="col-lg-7"><p class="lead-text"><?= e(setting('about_intro')) ?></p><h2 class="mini-h mt-4">Our history</h2><p><?= nl2br(e(setting('about_history'))) ?></p></div>
</div></div></section>
<section class="sec sec-tint"><div class="container-xl"><div class="row g-4">
  <div class="col-md-6 col-lg-3"><h2 class="mini-h">Mission</h2><p><?= e(setting('about_mission')) ?></p></div>
  <div class="col-md-6 col-lg-3"><h2 class="mini-h">Vision</h2><p><?= e(setting('about_vision')) ?></p></div>
  <div class="col-md-6 col-lg-3"><h2 class="mini-h">Farming standards</h2><p><?= e(setting('about_standards')) ?></p></div>
  <div class="col-md-6 col-lg-3"><h2 class="mini-h">Quality assurance</h2><p><?= e(setting('about_quality')) ?></p></div>
</div></div></section>
<?php if ($process): ?><section class="sec"><div class="container-xl"><h2 class="sec-title text-center mb-5">How we farm</h2><?= View::capture('website/partials/process', ['process' => $process]) ?></div></section><?php endif; ?>
