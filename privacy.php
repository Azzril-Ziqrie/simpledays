<?php
session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/db_schema.php';
require_once __DIR__ . '/includes/currency_helper.php';
require_once __DIR__ . '/includes/layout.php';

siteHeadOpen([
    'title'       => 'Privacy Policy',
    'description' => 'How ' . BRAND_NAME . ' collects, uses and protects your personal information.',
    'css'         => ['assets/css/pages.css'],
]);
siteBodyOpen(['bodyClass' => 'page-privacy', 'searchAction' => 'shop.php']);
?>
  <main class="page-main">
    <div class="info-wrap">
      <p class="info-eyebrow">Privacy</p>
      <h1 class="info-title">Privacy Policy</h1>
      <p class="info-lede">This page explains what information <?= esc(BRAND_NAME) ?> collects when you use this website, why, and the choices you have.</p>

      <section class="info-sec" style="margin-top:32px">
        <h2>Information we collect</h2>
        <ul>
          <li><strong>Order details:</strong> your name, email address, phone number, delivery address and the items you order.</li>
          <li><strong>Account details:</strong> if you create an account, your name, email and a password (stored in hashed form, never in plain text).</li>
          <li><strong>Cart and preferences:</strong> your cart and chosen display currency, kept in your browser and session.</li>
          <li><strong>Technical data:</strong> your IP address and browser information, used to run the site and to suggest a display currency for your country.</li>
        </ul>
      </section>
      <section class="info-sec">
        <h2>How we use it</h2>
        <ul>
          <li>To process and deliver your order, and to send your invoice and shipping updates.</li>
          <li>To answer your questions and handle returns.</li>
          <li>To keep the site secure and working properly.</li>
        </ul>
        <p>We do not sell your personal information.</p>
      </section>
      <section class="info-sec">
        <h2>Payments</h2>
        <p>Payments are processed by PayPal. Your card or wallet details are entered with PayPal (or Apple Pay / Google Pay) and are never stored on our servers. PayPal handles that information under its own privacy policy.</p>
      </section>
      <section class="info-sec">
        <h2>Who we share it with</h2>
        <p>Only the services needed to complete your order: PayPal for payment, couriers (such as JNT, JNE and DHL) for delivery, and our email provider for invoices and shipping notices. A currency-detection service receives your IP address to suggest a display currency.</p>
      </section>
      <section class="info-sec">
        <h2>Cookies and local storage</h2>
        <p>We use a session cookie and your browser's local storage to keep you signed in, remember your cart and your chosen currency. We do not use them for advertising.</p>
      </section>
      <section class="info-sec">
        <h2>Retention and your choices</h2>
        <p>We keep order records as long as needed for fulfilment, accounting and legal obligations. You can ask us to access, correct or delete your personal information at any time by <a href="contact.php">contacting us</a>.</p>
      </section>
      <section class="info-sec">
        <h2>Changes</h2>
        <p>We may update this policy from time to time. The latest version is always on this page.</p>
      </section>
      <p class="info-updated">Last updated October 2026.</p>
    </div>
  </main>
<?php
siteBodyClose();
siteDocClose();
