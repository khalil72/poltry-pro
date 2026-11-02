<?php
use App\Core\View;

$cats = ['farm' => 'Farm', 'sheds' => 'Sheds', 'chickens' => 'Chickens', 'eggs' => 'Egg production', 'activities' => 'Farm activities'];
?>
<?= View::capture('website/partials/page_head', ['title' => 'Gallery', 'lead' => 'A look around our farms.', 'image' => 'images/site/broiler-flock.jpg']) ?>
<section class="sec"><div class="container-xl">
  <div class="cat-filter" data-gallery-filter>
    <button type="button" class="active" data-cat="">All</button>
    <?php foreach ($cats as $k => $l): ?><button type="button" data-cat="<?= e($k) ?>"><?= e($l) ?></button><?php endforeach; ?>
  </div>
  <div class="gallery-grid" id="galleryGrid">
    <?php foreach ($items as $g): $yt = youtube_id($g['video_url']); ?>
      <figure data-cat="<?= e($g['category']) ?>">
        <?php if ($g['media_type'] === 'video' && $yt): ?>
          <div class="ratio ratio-4x3"><iframe src="https://www.youtube-nocookie.com/embed/<?= e($yt) ?>" title="<?= e($g['title']) ?>" loading="lazy" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div>
        <?php elseif ($g['image']): ?>
          <button type="button" class="gallery-open" data-full="<?= e(asset($g['image'])) ?>" data-title="<?= e($g['title']) ?>" aria-label="Enlarge: <?= e($g['title']) ?>"><img src="<?= e(asset($g['image'])) ?>" alt="<?= e($g['title']) ?>" loading="lazy"></button>
        <?php endif; ?>
        <figcaption><?= e($g['title']) ?></figcaption>
      </figure>
    <?php endforeach; ?>
  </div>
</div></section>
<div class="modal fade" id="lightbox" tabindex="-1" aria-hidden="true" aria-label="Enlarged photo"><div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content bg-transparent border-0">
  <button type="button" class="btn-close btn-close-white ms-auto mb-2" data-bs-dismiss="modal" aria-label="Close"></button>
  <img class="img-fluid rounded" id="lightboxImg" alt="">
</div></div></div>
