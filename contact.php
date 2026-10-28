<?php
session_start();
require __DIR__ . '/includes/site_gate.php';

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
    <article class="doc-body">
      <header class="doc-head reveal">
        <p class="doc-eyebrow">Contact</p>
        <h1 class="doc-title">Contact Us</h1>
      </header>

      <p class="doc-text reveal">For any questions, inquiries, and custom works / collaborations, or if you wanna just share and connect, hit up via instagram or email:</p>
      <p class="doc-text reveal">Simple Days<br><a href="mailto:contact@thesimpledays.co">contact@thesimpledays.co</a></p>
      <?php if (BRAND_INSTAGRAM !== ''): ?>
      <p class="doc-text reveal">Instagram<br><a href="<?= esc(BRAND_INSTAGRAM) ?>" target="_blank" rel="noopener">Follow us on Instagram</a></p>
      <?php endif; ?>
    </article>
  </main>
<?php
siteBodyClose();
siteDocClose();
