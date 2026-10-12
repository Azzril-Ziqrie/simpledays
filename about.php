<?php
session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/db_schema.php';
require_once __DIR__ . '/includes/currency_helper.php';
require_once __DIR__ . '/includes/layout.php';

siteHeadOpen([
    'title'       => 'About',
    'description' => BRAND_NAME . ' is built around the days that don\'t need an occasion.',
    'css'         => ['assets/css/pages.css'],
]);
siteBodyOpen(['bodyClass' => 'page-about', 'searchAction' => 'shop.php']);
?>
  <main class="page-main">
    <div class="info-wrap">
      <p class="info-eyebrow">About</p>
      <h1 class="info-title">The days that don't need an occasion.</h1>
      <p class="info-lede"><?= esc(BRAND_NAME) ?> is built around ordinary days. Clean shapes, honest materials and quiet details — pieces made to be worn on repeat, easy to layer and easy to live in.</p>

      <figure class="about-photo">
        <img src="assets/images/home/sd-01.webp" alt="<?= esc(BRAND_NAME) ?>" loading="lazy" decoding="async">
      </figure>

      <div class="about-values">
        <section class="info-sec">
          <h2>Clean shapes</h2>
          <p>Silhouettes that stay out of the way. Nothing more than it needs to be, so each piece works with the rest of what you own.</p>
        </section>
        <section class="info-sec">
          <h2>Honest materials</h2>
          <p>Fabrics chosen for how they feel and how they wear, not for how they look in a photo.</p>
        </section>
        <section class="info-sec">
          <h2>Quiet details</h2>
          <p>Small, deliberate finishing you notice over time rather than at first glance.</p>
        </section>
      </div>

      <p class="info-lede">Questions about an order, sizing or anything else? We're happy to help.</p>
      <a class="info-cta" href="shop.php">Shop all</a>
      <a class="info-cta" href="contact.php" style="margin-left:8px;background:transparent;color:var(--ink);border:1px solid var(--ink)">Contact us</a>
    </div>
  </main>
<?php
siteBodyClose();
siteDocClose();
