<?php use App\Core\View; ?>
<?= View::capture('website/partials/page_head', ['title' => 'Contact us', 'lead' => 'Call, message or write to us. We reply on working days.', 'image' => 'images/site/hen-yard.jpg']) ?>
<section class="sec"><div class="container-xl"><div class="row g-5">
  <div class="col-lg-5">
    <h2 class="mini-h">Reach us directly</h2>
    <ul class="contact-lines contact-lines-dark">
      <?php if ($a = setting('contact_phone')): ?><li><i class="bi bi-telephone"></i><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $a)) ?>"><?= e($a) ?></a></li><?php endif; ?>
      <?php if ($w = preg_replace('/\D/', '', setting('contact_whatsapp'))): ?><li><i class="bi bi-whatsapp"></i><a href="https://wa.me/<?= e($w) ?>" target="_blank" rel="noopener">Message us on WhatsApp</a></li><?php endif; ?>
      <?php if ($a = setting('contact_email')): ?><li><i class="bi bi-envelope"></i><a href="mailto:<?= e($a) ?>"><?= e($a) ?></a></li><?php endif; ?>
      <?php if ($a = setting('contact_address')): ?><li><i class="bi bi-geo-alt"></i><span><?= e($a) ?></span></li><?php endif; ?>
    </ul>
    <?php if ($m = setting('contact_map_embed')): ?><div class="ratio ratio-4x3 map-box"><iframe src="<?= e($m) ?>" title="Map to our farm" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe></div><?php endif; ?>
  </div>
  <div class="col-lg-7"><h2 class="mini-h">Send a message</h2><?= View::capture('website/partials/contact_form', []) ?></div>
</div></div></section>
