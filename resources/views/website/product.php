<?php
use App\Core\View;

$labels = ['in_stock' => 'Available', 'limited' => 'Limited supply', 'on_order' => 'Made to order', 'out_of_stock' => 'Out of stock'];
?>
<section class="sec product-detail"><div class="container-xl">
  <p class="crumbs"><a href="<?= e(url('products')) ?>">Products</a> / <?= e($p['category_name']) ?></p>
  <div class="row g-5 align-items-center">
    <div class="col-lg-5"><div class="egg-frame"><img src="<?= e(media($p['image'])) ?>" alt="<?= e($p['name']) ?>"></div></div>
    <div class="col-lg-7">
      <h1 class="sec-title"><?= e($p['name']) ?></h1>
      <div class="product-meta mb-3"><span class="avail avail-<?= e($p['availability']) ?>"><?= e($labels[$p['availability']] ?? '') ?></span><?php if ($p['price'] !== null): ?><span class="price"><?= e(money($p['price'])) ?><?= $p['price_unit'] ? ' <small>/ ' . e($p['price_unit']) . '</small>' : '' ?></span><?php endif; ?></div>
      <p class="lead-text"><?= e($p['summary']) ?></p>
      <p><?= nl2br(e($p['description'])) ?></p>
      <a class="btn btn-pine" href="#inquiry">Order or ask about this</a>
    </div>
  </div>
</div></section>
<section class="sec sec-tint" id="inquiry"><div class="container-xl"><div class="row justify-content-center"><div class="col-lg-8">
  <h2 class="sec-title mb-3">Ask about <?= e($p['name']) ?></h2>
  <div class="form-panel"><?= View::capture('website/partials/contact_form', ['type' => 'inquiry', 'productId' => $p['id'], 'subject' => $p['name'], 'messageLabel' => 'What quantity do you need, and when?', 'button' => 'Send inquiry']) ?></div>
</div></div></div></section>
<?php if ($related): ?><section class="sec"><div class="container-xl"><h2 class="sec-title mb-4">Also from our farms</h2><div class="product-grid"><?php foreach ($related as $r): ?><?= View::capture('website/partials/product_card', ['p' => $r]) ?><?php endforeach; ?></div></div></section><?php endif; ?>
