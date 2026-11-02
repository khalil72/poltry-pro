<?php
use App\Core\View;

$show = fn ($k) => setting("show_$k", '1') === '1';
$hero = setting('hero_image', 'images/site/hero-shed.jpg');
$stats = [['stat_birds', 'Birds in our care'], ['stat_farms', 'Farms'], ['stat_customers', 'Customers served'], ['stat_years', 'Years of experience']];
$about = setting('about_image', 'images/site/hen-closeup.jpg');
?>
<section class="hero" style="background-image:url('<?= e(asset($hero)) ?>')">
  <div class="hero-shade" aria-hidden="true"></div>
  <div class="container-xl hero-inner">
    <h1 class="hero-title"><?= e(setting('hero_title')) ?></h1>
    <p class="hero-text"><?= e(setting('hero_text')) ?></p>
    <div class="hero-cta">
      <a class="btn btn-yolk btn-lg" href="<?= e(url('products')) ?>"><?= e(setting('hero_cta_primary', 'Explore Our Products')) ?></a>
      <a class="btn btn-ghost btn-lg" href="<?= e(url('contact')) ?>"><?= e(setting('hero_cta_secondary', 'Contact Our Team')) ?></a>
    </div>
  </div>
  <?php if ($show('stats')): ?>
  <div class="hero-stats"><div class="container-xl"><dl class="stats-row">
    <?php foreach ($stats as [$key, $label]): ?>
      <div class="stat-item"><dt><?= e($label) ?></dt><dd data-count="<?= e((int) setting($key, '0')) ?>"><?= e(num(setting($key, '0'))) ?></dd></div>
    <?php endforeach; ?>
  </dl></div></div>
  <?php endif; ?>
</section>

<?php if ($show('about')): ?>
<section class="sec about-sec">
  <div class="container-xl"><div class="row g-5 align-items-center">
    <div class="col-lg-5"><div class="egg-frame" data-reveal><img src="<?= e(asset($about)) ?>" alt="A hen on our farm" loading="lazy"></div></div>
    <div class="col-lg-7" data-reveal>
      <h2 class="sec-title"><?= e(setting('about_title')) ?></h2>
      <p class="lead-text"><?= e(setting('about_intro')) ?></p>
      <div class="row g-4 mt-1">
        <div class="col-md-6"><h3 class="mini-h">Our mission</h3><p><?= e(setting('about_mission')) ?></p></div>
        <div class="col-md-6"><h3 class="mini-h">Our vision</h3><p><?= e(setting('about_vision')) ?></p></div>
      </div>
      <a class="btn btn-pine mt-2" href="<?= e(url('about')) ?>">Read more</a>
    </div>
  </div></div>
</section>
<?php endif; ?>

<?php if ($show('products') && $products): ?>
<section class="sec sec-tint">
  <div class="container-xl">
    <div class="sec-head"><h2 class="sec-title">Our products</h2><a class="link-more" href="<?= e(url('products')) ?>">All products <i class="bi bi-arrow-right"></i></a></div>
    <div class="product-grid">
      <?php foreach ($products as $p): ?><?= View::capture('website/partials/product_card', ['p' => $p]) ?><?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($show('services') && $services): ?>
<section class="sec">
  <div class="container-xl">
    <div class="sec-head"><h2 class="sec-title">What we do</h2><a class="link-more" href="<?= e(url('services')) ?>">Our services <i class="bi bi-arrow-right"></i></a></div>
    <div class="service-list">
      <?php foreach ($services as $s): ?>
        <div class="service-row" data-reveal><i class="bi <?= e($s['icon'] ?: 'bi-check2') ?>" aria-hidden="true"></i><h3><?= e($s['title']) ?></h3><p><?= e($s['summary']) ?></p></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($show('facilities') && $facilities): ?>
<section class="sec sec-pine">
  <div class="container-xl">
    <div class="row g-5">
      <div class="col-lg-4"><h2 class="sec-title">Inside our farms</h2><p class="lead-text">The systems that keep every shed clean, comfortable and safe for the birds.</p><a class="btn btn-ghost mt-2" href="<?= e(url('facilities')) ?>">See the facilities</a></div>
      <div class="col-lg-8"><ul class="facility-grid">
        <?php foreach ($facilities as $f): ?><li><i class="bi <?= e($f['icon'] ?: 'bi-check2') ?>" aria-hidden="true"></i><div><h3><?= e($f['title']) ?></h3><p><?= e($f['summary']) ?></p></div></li><?php endforeach; ?>
      </ul></div>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($show('why') && $why): ?>
<section class="sec">
  <div class="container-xl"><div class="row g-5 align-items-center">
    <div class="col-lg-7">
      <h2 class="sec-title">Why customers choose us</h2>
      <div class="why-grid">
        <?php foreach ($why as $w): ?><div class="why-item" data-reveal><h3><?= e($w['title']) ?></h3><p><?= e($w['summary']) ?></p></div><?php endforeach; ?>
      </div>
    </div>
    <div class="col-lg-5 d-none d-lg-block"><div class="egg-frame egg-frame-alt" data-reveal><img src="<?= e(asset('images/site/eggs-nest.jpg')) ?>" alt="Fresh eggs in a straw nest" loading="lazy"></div></div>
  </div></div>
</section>
<?php endif; ?>

<?php if ($show('process') && $process): ?>
<section class="sec sec-tint">
  <div class="container-xl">
    <h2 class="sec-title text-center mb-5">From chick to your delivery</h2>
    <?= View::capture('website/partials/process', ['process' => $process]) ?>
  </div>
</section>
<?php endif; ?>

<?php if ($show('gallery') && $gallery): ?>
<section class="sec">
  <div class="container-xl">
    <div class="sec-head"><h2 class="sec-title">Life on the farm</h2><a class="link-more" href="<?= e(url('gallery')) ?>">Full gallery <i class="bi bi-arrow-right"></i></a></div>
    <div class="mosaic">
      <?php foreach (array_slice($gallery, 0, 5) as $g): ?><figure><img src="<?= e(asset($g['image'])) ?>" alt="<?= e($g['title']) ?>" loading="lazy"><figcaption><?= e($g['title']) ?></figcaption></figure><?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($show('testimonials') && $testimonials): ?>
<section class="sec sec-tint">
  <div class="container-xl">
    <h2 class="sec-title mb-4">What customers say</h2>
    <div class="row g-4">
      <?php foreach ($testimonials as $t): ?>
        <div class="col-md-4"><figure class="quote-card">
          <div class="stars" role="img" aria-label="<?= (int) $t['rating'] ?> out of 5 stars"><?php for ($i = 1; $i <= 5; $i++): ?><i class="bi bi-star<?= $i <= $t['rating'] ? '-fill' : '' ?>"></i><?php endfor; ?></div>
          <blockquote><?= e($t['feedback']) ?></blockquote>
          <figcaption><?php if ($t['image']): ?><img src="<?= e(asset($t['image'])) ?>" alt="" loading="lazy"><?php endif; ?><span><strong><?= e($t['customer_name']) ?></strong><br><?= e($t['customer_title']) ?></span></figcaption>
        </figure></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($show('blog') && $posts): ?>
<section class="sec">
  <div class="container-xl">
    <div class="sec-head"><h2 class="sec-title">From the farm journal</h2><a class="link-more" href="<?= e(url('blog')) ?>">All articles <i class="bi bi-arrow-right"></i></a></div>
    <div class="row g-4"><?php foreach ($posts as $post): ?><div class="col-md-4"><?= View::capture('website/partials/post_card', ['post' => $post]) ?></div><?php endforeach; ?></div>
  </div>
</section>
<?php endif; ?>

<?php if ($show('contact')): ?>
<section class="sec sec-pine" id="contact">
  <div class="container-xl"><div class="row g-5">
    <div class="col-lg-5">
      <h2 class="sec-title">Talk to our team</h2>
      <p class="lead-text">Tell us what you need and how much. We will reply with availability and pricing.</p>
      <ul class="contact-lines">
        <?php if ($a = setting('contact_phone')): ?><li><i class="bi bi-telephone"></i><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $a)) ?>"><?= e($a) ?></a></li><?php endif; ?>
        <?php if ($a = setting('contact_email')): ?><li><i class="bi bi-envelope"></i><a href="mailto:<?= e($a) ?>"><?= e($a) ?></a></li><?php endif; ?>
        <?php if ($a = setting('contact_address')): ?><li><i class="bi bi-geo-alt"></i><span><?= e($a) ?></span></li><?php endif; ?>
      </ul>
    </div>
    <div class="col-lg-7"><div class="form-panel"><?= View::capture('website/partials/contact_form', []) ?></div></div>
  </div></div>
</section>
<?php endif; ?>
