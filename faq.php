<?php
session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/db_schema.php';
require_once __DIR__ . '/includes/currency_helper.php';
require_once __DIR__ . '/includes/layout.php';

siteHeadOpen([
    'title'       => 'FAQ',
    'description' => 'Answers about ordering, payment, shipping and returns at ' . BRAND_NAME . '.',
    'css'         => ['assets/css/pages.css'],
]);
siteBodyOpen(['bodyClass' => 'page-faq', 'searchAction' => 'shop.php']);
?>
  <main class="page-main">
    <div class="info-wrap">
      <p class="info-eyebrow">FAQ</p>
      <h1 class="info-title">Frequently asked questions</h1>
      <p class="info-lede">Quick answers about ordering, payment, shipping and returns. Can't find yours? <a href="contact.php" style="text-decoration:underline;text-underline-offset:3px">Contact us</a>.</p>

      <section class="faq-group">
        <h2>Ordering</h2>
        <details class="faq-item"><summary>Do I need an account to order?</summary><div class="faq-a">No. You can check out as a guest. An account lets you track orders and see your order history.</div></details>
        <details class="faq-item"><summary>How do I place an order?</summary><div class="faq-a">Pick a product, choose your size, add it to the cart and continue to checkout.</div></details>
        <details class="faq-item"><summary>Can I use a promo code?</summary><div class="faq-a">Yes. If you have a code, enter it at checkout before paying. Minimum spend and validity dates may apply.</div></details>
      </section>
      <section class="faq-group">
        <h2>Payment</h2>
        <details class="faq-item"><summary>Which currency am I charged in?</summary><div class="faq-a">All orders are charged in USD. Prices shown in another currency are estimates based on current exchange rates, and your bank may apply its own rate.</div></details>
        <details class="faq-item"><summary>Which payment methods do you accept?</summary><div class="faq-a">PayPal, including card payments through PayPal, and Apple Pay or Google Pay where your device supports them.</div></details>
      </section>
      <section class="faq-group">
        <h2>Shipping</h2>
        <details class="faq-item"><summary>How long does delivery take?</summary><div class="faq-a">Orders are processed in 1–3 business days. Delivery then takes 1–3 business days within Indonesia and 3–10 business days internationally, depending on the destination. See <a href="shipping.php">Shipping &amp; Returns</a> for details.</div></details>
        <details class="faq-item"><summary>How can I check my order status?</summary><div class="faq-a">Once payment is confirmed you will receive an invoice by email, and a shipping confirmation with a tracking number when your order is dispatched.</div></details>
      </section>
      <section class="faq-group">
        <h2>Returns</h2>
        <details class="faq-item"><summary>Do you accept returns or exchanges?</summary><div class="faq-a">Our policy is covered in full on the <a href="shipping.php">Shipping &amp; Returns</a> page. If something arrived defective or incorrect, <a href="contact.php">contact us</a> with your order number.</div></details>
      </section>
    </div>
  </main>
<?php
siteBodyClose();
siteDocClose();
