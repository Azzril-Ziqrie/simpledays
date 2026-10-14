<?php
session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/db_schema.php';
require_once __DIR__ . '/includes/currency_helper.php';
require_once __DIR__ . '/includes/layout.php';

$copy     = brandAboutCopy();
$headline = array_shift($copy);   // pernyataan brand jadi judul halaman

siteHeadOpen([
    'title'       => 'About',
    'description' => $copy[0] ?? (BRAND_NAME . ' — ' . BRAND_TAGLINE),
    'css'         => ['assets/css/pages.css'],
]);
siteBodyOpen(['bodyClass' => 'page-about', 'searchAction' => 'shop.php']);
?>
  <main class="page-main">
    <article class="doc-body">
      <header class="doc-head reveal">
        <p class="doc-eyebrow">About</p>
        <p class="doc-est">Est. <?= esc(BRAND_EST) ?></p>
        <h1 class="doc-title"><?= esc($headline) ?></h1>
      </header>

      <?php foreach ($copy as $line): ?>
        <p class="doc-text reveal"><?= esc($line) ?></p>
      <?php endforeach; ?>
    </article>
  </main>
<?php
siteBodyClose();
siteDocClose();
