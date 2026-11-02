<?php $cats = ['farming_tips' => 'Farming tips', 'bird_health' => 'Bird health', 'vaccination' => 'Vaccination', 'feed_nutrition' => 'Feed and nutrition', 'industry' => 'Industry updates']; ?>
<article class="post-card">
  <a href="<?= e(url('blog/' . $post['slug'])) ?>" tabindex="-1" aria-hidden="true"><img src="<?= e(media($post['image'], 'images/site/hens-fence.jpg')) ?>" alt="" loading="lazy"></a>
  <div class="post-meta"><?= e($cats[$post['category']] ?? '') ?> · <?= e(fdate($post['published_at'])) ?></div>
  <h3><a href="<?= e(url('blog/' . $post['slug'])) ?>"><?= e($post['title']) ?></a></h3>
  <p><?= e($post['excerpt']) ?></p>
</article>
