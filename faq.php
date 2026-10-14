<?php
session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/db_schema.php';
require_once __DIR__ . '/includes/currency_helper.php';
require_once __DIR__ . '/includes/layout.php';

siteHeadOpen([
    'title'       => 'FAQ',
    'description' => 'Answers about taxes, getting in touch and product care at ' . BRAND_NAME . '.',
    'css'         => ['assets/css/pages.css'],
]);
siteBodyOpen(['bodyClass' => 'page-faq', 'searchAction' => 'shop.php']);
?>
  <main class="page-main">
    <article class="doc-body">
      <header class="doc-head reveal">
        <h1 class="doc-title">FAQ</h1>
      </header>

      <section class="doc-sec reveal">
        <h2>Taxes</h2>
        <p>Prices do not include taxes. Taxes will be paid by the individual. Rates will vary according to the country the individual is in.</p>
      </section>

      <section class="doc-sec reveal">
        <h2>Contact Us</h2>
        <p>Whether you’re reaching out to say what’s up, give us feedback, or suggest a collaboration, connect with us via
          <?php if (BRAND_EMAIL !== ''): ?><a href="mailto:<?= esc(BRAND_EMAIL) ?>">email</a><?php else: ?>email<?php endif; ?>
          or
          <?php if (BRAND_INSTAGRAM !== ''): ?><a href="<?= esc(BRAND_INSTAGRAM) ?>" target="_blank" rel="noopener">Instagram</a><?php else: ?>Instagram<?php endif; ?>.</p>
      </section>

      <section class="doc-sec reveal">
        <h2>Product Care</h2>
        <p>Otherwise specified, here’s the general suggested washing instruction:</p>
        <ul>
          <li>Do not bleach</li>
          <li>Wash inside out</li>
          <li>Machine wash cold</li>
          <li>Tumble dry low / line dry</li>
        </ul>
      </section>
    </article>
  </main>
<?php
siteBodyClose();
siteDocClose();
