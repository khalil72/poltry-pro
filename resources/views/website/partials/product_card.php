<?php
$labels = ['in_stock' => 'Available', 'limited' => 'Limited supply', 'on_order' => 'Made to order', 'out_of_stock' => 'Out of stock'];
?>
<article class="product-card">
  <a class="product-egg" href="<?= e(url('products/' . $p['slug'])) ?>" tabindex="-1" aria-hidden="true"><img src="<?= e(media($p['image'])) ?>" alt="" loading="lazy"></a>
  <div class="product-cat"><?= e($p['category_name']) ?></div>
  <h3><a href="<?= e(url('products/' . $p['slug'])) ?>"><?= e($p['name']) ?></a></h3>
  <p><?= e($p['summary']) ?></p>
  <div class="product-meta">
    <span class="avail avail-<?= e($p['availability']) ?>"><?= e($labels[$p['availability']] ?? '') ?></span>
    <?php if ($p['price'] !== null): ?><span class="price"><?= e(money($p['price'])) ?><?= $p['price_unit'] ? ' <small>/ ' . e($p['price_unit']) . '</small>' : '' ?></span><?php endif; ?>
  </div>
  <div class="product-actions">
    <a class="btn btn-pine btn-sm" href="<?= e(url('products/' . $p['slug'])) ?>">View details</a>
    <a class="btn btn-line btn-sm" href="<?= e(url('products/' . $p['slug'] . '#inquiry')) ?>">Ask about this</a>
  </div>
</article>
