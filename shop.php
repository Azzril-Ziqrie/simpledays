<?php
session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/db_schema.php';
require_once __DIR__ . '/includes/currency_helper.php';
require_once __DIR__ . '/includes/pricing.php';
require_once __DIR__ . '/includes/layout.php';

/* ── request ─────────────────────────────────────────── */
$search     = trim((string)($_GET['search'] ?? ''));
$category   = trim((string)($_GET['category'] ?? ''));
$isFiltered = $search !== '' || $category !== '';

/* ── data ────────────────────────────────────────────── */
$schema     = catalogSchema($pdo);
$categories = fetchCategories($pdo, $schema);
$products   = fetchInStockProducts($pdo, $schema, $search, $category, false);   // Shop menampilkan juga produk habis (diberi label "Sold out")

/* ── render ──────────────────────────────────────────── */
siteHeadOpen([
    'title'       => 'Shop',
    'description' => 'Shop ' . BRAND_NAME . ' — ' . BRAND_TAGLINE,
]);
?>
<style>
  .shop-head{padding:clamp(24px,4vw,48px) 0 8px}
  .shop-head .page-title{margin-bottom:20px}
  .shop-filter{margin-top:14px;color:var(--mute)}
  .shop-filter strong{color:var(--ink);font-weight:500}
  .shop-filter a{margin-left:10px;color:var(--ink)}
  .shop-grid{padding-top:20px}
</style>
<?php
siteBodyOpen([
    'bodyClass'    => 'page-shop',
    'searchAction' => 'shop.php',
    'categoryBase' => 'shop.php',
    'search'       => $search,
    'category'     => $category,
]);
?>
<main class="page-main">
  <section class="shelf" aria-label="Shop">
    <div class="shop-head">
      <h1 class="page-title">Shop</h1>
      <nav class="category-nav" aria-label="Categories">
        <a href="shop.php" class="<?= !$isFiltered ? 'active' : '' ?>">All</a>
        <?php foreach ($categories as $cat): ?>
          <a href="shop.php?category=<?= urlencode((string)$cat['slug']) ?>" class="<?= $category === (string)$cat['slug'] ? 'active' : '' ?>"><?= esc($cat['name']) ?></a>
        <?php endforeach; ?>
      </nav>

      <?php if ($search !== ''): ?>
        <div class="shop-filter">
          Search: <strong><?= esc($search) ?></strong>
          <a class="link-u" href="shop.php<?= $category !== '' ? '?category=' . urlencode($category) : '' ?>">Clear</a>
        </div>
      <?php endif; ?>
    </div>

    <div class="shop-grid">
      <?php if (!$products): ?>
        <p class="empty-text">No active products yet.</p>
      <?php else: ?>
        <div class="product-grid">
          <?php foreach ($products as $product): ?>
            <?= renderProductCard($product) ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>
</main>
<?php
siteBodyClose();
siteDocClose();
