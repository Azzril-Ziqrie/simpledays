<?php
session_start();
require __DIR__ . '/includes/site_gate.php';

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/db_schema.php';
require_once __DIR__ . '/includes/currency_helper.php';
require_once __DIR__ . '/includes/layout.php';

siteHeadOpen([
    'title'       => 'Terms of Service',
    'description' => 'The terms that apply when you visit or purchase from ' . BRAND_NAME . '.',
    'css'         => ['assets/css/pages.css'],
]);
siteBodyOpen(['bodyClass' => 'page-terms', 'searchAction' => 'shop.php']);
?>
  <main class="page-main">
    <article class="doc-body">
      <header class="doc-head reveal">
        <p class="doc-eyebrow">Terms</p>
        <h1 class="doc-title">Terms of Service</h1>
      </header>

      <section class="doc-sec reveal">
        <h2>General</h2>
        <p>This website is operated by <?= esc(BRAND_NAME) ?>. By visiting our site and/or purchasing from us, you agree to be bound by the following terms.</p>
      </section>
      <section class="doc-sec reveal">
        <h2>Payments and charges</h2>
        <p>All orders are charged in USD. Prices shown in other currencies are estimates. Shipping costs depend on your order total, delivery location and selected shipping method.</p>
      </section>
      <section class="doc-sec reveal">
        <h2>Shipping</h2>
        <p>Orders are prepared for shipment within 1–2 working days after payment confirmation, unless otherwise stated.</p>
      </section>
      <section class="doc-sec reveal">
        <h2>Exchange &amp; returns</h2>
        <p>All sales are final unless the item received is defective or incorrect. Requests must be submitted within 3 days of receiving the order.</p>
      </section>
    </article>
  </main>
<?php
siteBodyClose();
siteDocClose();
