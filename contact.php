<?php
session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/db_schema.php';
require_once __DIR__ . '/includes/currency_helper.php';
require_once __DIR__ . '/includes/layout.php';

siteHeadOpen([
    'title'       => 'Contact Us',
    'description' => 'Get in touch with ' . BRAND_NAME . ' about an order, sizing or anything else.',
    'css'         => ['assets/css/pages.css'],
]);
siteBodyOpen(['bodyClass' => 'page-contact', 'searchAction' => 'shop.php']);
?>
  <main class="page-main">
    <div class="info-wrap">
      <p class="info-eyebrow">Contact Us</p>
      <h1 class="info-title">We're here to help.</h1>
      <p class="info-lede">Message us about an order, sizing or anything else. Please include your order number if you have one so we can find it quickly.</p>

      <div class="contact-cards">
        <a class="contact-card" href="<?= esc(BRAND_WHATSAPP) ?>" target="_blank" rel="noopener">
          <small>WhatsApp</small>
          <strong>Chat with us</strong>
          <span>Fastest way to reach us.</span>
        </a>
        <?php if (BRAND_EMAIL !== ''): ?>
        <a class="contact-card" href="mailto:<?= esc(BRAND_EMAIL) ?>">
          <small>Email</small>
          <strong><?= esc(BRAND_EMAIL) ?></strong>
          <span>For longer questions and returns.</span>
        </a>
        <?php endif; ?>
        <?php if (BRAND_INSTAGRAM !== ''): ?>
        <a class="contact-card" href="<?= esc(BRAND_INSTAGRAM) ?>" target="_blank" rel="noopener">
          <small>Instagram</small>
          <strong>Follow us</strong>
          <span>New pieces and updates.</span>
        </a>
        <?php endif; ?>
      </div>

      <section class="info-sec" style="margin-top:24px">
        <h2>Before you write</h2>
        <p>Many questions are answered in the <a href="faq.php">FAQ</a> and on the <a href="shipping.php">Shipping &amp; Returns</a> page.</p>
      </section>
    </div>
  </main>
<?php
siteBodyClose();
siteDocClose();
