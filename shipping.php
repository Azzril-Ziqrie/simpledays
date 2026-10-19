<?php
session_start();
require __DIR__ . '/includes/site_gate.php';

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/db_schema.php';
require_once __DIR__ . '/includes/currency_helper.php';
require_once __DIR__ . '/includes/layout.php';

siteHeadOpen([
    'title'       => 'Shipping & Returns',
    'description' => 'Shipping rates, the free shipping threshold and the return and exchange policy of ' . BRAND_NAME . '.',
    'css'         => ['assets/css/pages.css'],
]);
siteBodyOpen(['bodyClass' => 'page-shipping', 'searchAction' => 'shop.php']);
?>
  <main class="page-main">
    <article class="doc-body">
      <header class="doc-head reveal">
        <h1 class="doc-title">Shipping &amp; Returns</h1>
      </header>

      <section class="doc-group reveal">
        <h2 class="doc-eyebrow">Shipping</h2>

        <h3 class="doc-label">International Shipping</h3>
        <p class="doc-text">Flat fee - $25</p>
        <p class="doc-text">FREE with purchase of $150 or more.</p>
        <p class="doc-text">*Use CODE: 150/DAYS</p>

        <h3 class="doc-label">Indonesia Domestic Shipping</h3>
        <p class="doc-text">Free Shipping</p>

        <p class="doc-text">*Learn more about taxes on global orders</p>
      </section>

      <section class="doc-group reveal">
        <h2 class="doc-eyebrow">Returns &amp; Exchanges</h2>

        <h3 class="doc-label">7 Days to Inquire Return</h3>
        <h3 class="doc-label">No Refunds</h3>

        <h3 class="doc-label">Return Cost</h3>
        <p class="doc-text">In the case of defective product(s), wrong item(s), sizing and/or color, you are entitled to return them to us for an exchange within 7 days of receipt. Return shipping fees will be covered by <?= esc(BRAND_NAME) ?>.</p>
        <p class="doc-text">If you have received the correct item but want to change color/size, you can inquire an exchange within 7 days of receipt. Shipping fees both ways will be covered by the individual. Return fee to the individual will be charged accordingly (rate is measured by weight), and not with the flat $25 fee.</p>
        <p class="doc-text">Do contact us to inquire the availability of your choice of size/color and return shipping rate to your location.</p>
        <p class="doc-text">Purchased item must not have been used/worn and is still in perfect original saleable condition, in the original packaging and with all tags attached.</p>
      </section>
    </article>
  </main>
<?php
siteBodyClose();
siteDocClose();
