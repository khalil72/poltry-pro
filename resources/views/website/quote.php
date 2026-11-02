<?php use App\Core\View; ?>
<?= View::capture('website/partials/page_head', ['title' => 'Get a quote', 'lead' => 'Tell us what you need and we will come back with availability and a price.', 'image' => 'images/site/eggs-mixed.jpg']) ?>
<section class="sec"><div class="container-xl"><div class="row justify-content-center"><div class="col-lg-8">
  <?= View::capture('website/partials/contact_form', ['type' => 'quote', 'products' => $products, 'messageLabel' => 'What quantity do you need, where, and by when?', 'button' => 'Request a quote', 'subject' => 'Quote request']) ?>
</div></div></div></section>
