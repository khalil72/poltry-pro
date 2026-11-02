<?php use App\Core\View; ?>
<?= View::capture('website/partials/page_head', ['title' => 'Our products', 'lead' => 'Birds, chicks, eggs and feed from our own farms.', 'image' => 'images/site/eggs-tray.jpg']) ?>
<section class="sec"><div class="container-xl">
  <div class="cat-filter" role="navigation" aria-label="Product categories">
    <a class="<?= !$category ? 'active' : '' ?>" href="<?= e(url('products')) ?>">All</a>
    <?php foreach ($categories as $c): ?><a class="<?= $category === $c['slug'] ? 'active' : '' ?>" href="<?= e(url('products?category=' . urlencode($c['slug']))) ?>"><?= e($c['name']) ?></a><?php endforeach; ?>
  </div>
  <div class="product-grid"><?php foreach ($products as $p): ?><?= View::capture('website/partials/product_card', ['p' => $p]) ?><?php endforeach; ?></div>
  <?php if (!$products): ?><p>Nothing in this category right now. <a href="<?= e(url('contact')) ?>">Ask us</a> what is coming up.</p><?php endif; ?>
</div></section>
