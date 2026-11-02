<?php use App\Core\View; ?>
<?= View::capture('website/partials/page_head', ['title' => 'Blog and news', 'lead' => 'Practical notes on bird health, feed, vaccination and the poultry trade.', 'image' => 'images/site/hens-fence.jpg']) ?>
<section class="sec"><div class="container-xl">
  <div class="row g-4"><?php foreach ($posts as $post): ?><div class="col-md-6 col-lg-4"><?= View::capture('website/partials/post_card', ['post' => $post]) ?></div><?php endforeach; ?></div>
  <?php if (!$posts): ?><p>The first articles are on their way.</p><?php endif; ?>
  <?php if ($pages > 1): ?><nav class="mt-5" aria-label="Pages"><ul class="pagination justify-content-center"><?php for ($i = 1; $i <= $pages; $i++): ?><li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="<?= e(url('blog?page=' . $i)) ?>"><?= $i ?></a></li><?php endfor; ?></ul></nav><?php endif; ?>
</div></section>
