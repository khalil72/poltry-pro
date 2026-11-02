<?php use App\Core\View; ?>
<article class="sec article"><div class="container-xl"><div class="row justify-content-center"><div class="col-lg-8">
  <p class="crumbs"><a href="<?= e(url('blog')) ?>">Blog</a> / <?= e(label_of($post['category'])) ?></p>
  <h1 class="article-title"><?= e($post['title']) ?></h1>
  <p class="post-meta"><?= e(fdate($post['published_at'])) ?><?= $post['author_name'] ? ' · ' . e($post['author_name']) : '' ?></p>
  <?php if ($post['image']): ?><img class="article-img" src="<?= e(asset($post['image'])) ?>" alt=""><?php endif; ?>
  <div class="article-body"><?= sanitize_html($post['body']) ?></div>
</div></div></div></article>
<?php if ($more): ?><section class="sec sec-tint"><div class="container-xl"><h2 class="sec-title mb-4">Keep reading</h2><div class="row g-4"><?php foreach ($more as $m): ?><div class="col-md-4"><a class="more-link" href="<?= e(url('blog/' . $m['slug'])) ?>"><img src="<?= e(media($m['image'], 'images/site/hens-fence.jpg')) ?>" alt="" loading="lazy"><span><?= e($m['title']) ?></span></a></div><?php endforeach; ?></div></div></section><?php endif; ?>
