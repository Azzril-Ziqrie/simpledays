<?php
session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/db_schema.php';
require_once __DIR__ . '/includes/currency_helper.php';
require_once __DIR__ . '/includes/pricing.php';
require_once __DIR__ . '/includes/layout.php';

const SHOP_SORTS = [
    'featured'   => 'Featured',
    'newest'     => 'Newest',
    'price_asc'  => 'Price: low to high',
    'price_desc' => 'Price: high to low',
];

/* ── helpers ─────────────────────────────────────────── */
/** URL halaman shop dengan filter aktif; $override menimpa/menghapus parameter (null = hapus). */
function shopUrl(array $current, array $override = []): string
{
    $params = array_filter(
        array_merge($current, $override),
        static fn($v) => $v !== null && $v !== '' && $v !== 'featured'
    );
    return 'shop.php' . ($params ? '?' . http_build_query($params) : '');
}

/** Urutkan produk menurut pilihan sort; "featured" mempertahankan urutan dari katalog. */
function sortProducts(array $products, string $sort): array
{
    $finalPrice = static fn(array $p): float => (float)productPriceView($p)['final'];

    switch ($sort) {
        case 'newest':
            usort($products, static fn($a, $b) => (int)$b['id'] <=> (int)$a['id']);
            break;
        case 'price_asc':
            usort($products, static fn($a, $b) => $finalPrice($a) <=> $finalPrice($b));
            break;
        case 'price_desc':
            usort($products, static fn($a, $b) => $finalPrice($b) <=> $finalPrice($a));
            break;
    }
    return $products;
}

/* ── request ─────────────────────────────────────────── */
$search   = trim((string)($_GET['search'] ?? ''));
$category = trim((string)($_GET['category'] ?? ''));
$sort     = (string)($_GET['sort'] ?? 'featured');
if (!isset(SHOP_SORTS[$sort])) {
    $sort = 'featured';
}
$isFiltered = $search !== '' || $category !== '';
$current    = ['search' => $search, 'category' => $category, 'sort' => $sort];

/* ── data ────────────────────────────────────────────── */
$schema     = catalogSchema($pdo);
$categories = fetchCategories($pdo, $schema);
// Shop menampilkan juga produk habis (diberi label "Sold out")
$products   = sortProducts(fetchInStockProducts($pdo, $schema, $search, $category, false), $sort);

/* ── render ──────────────────────────────────────────── */
siteHeadOpen([
    'title'       => 'Shop',
    'description' => 'Shop ' . BRAND_NAME . ' — ' . BRAND_TAGLINE,
    'css'         => ['assets/css/shop.css'],
]);
siteBodyOpen([
    'bodyClass'    => 'page-shop',
    'searchAction' => 'shop.php',
    'categoryBase' => 'shop.php',
    'search'       => $search,
    'category'     => $category,
]);
?>
<main class="page-main">
  <section class="shop" aria-label="Shop">
    <h1 class="shop-title">Shop</h1>

    <nav class="shop-cats" aria-label="Categories">
      <a href="<?= esc(shopUrl($current, ['category' => null])) ?>" class="<?= $category === '' ? 'active' : '' ?>">All</a>
      <?php foreach ($categories as $cat): ?>
        <a href="<?= esc(shopUrl($current, ['category' => (string)$cat['slug']])) ?>" class="<?= $category === (string)$cat['slug'] ? 'active' : '' ?>"><?= esc($cat['name']) ?></a>
      <?php endforeach; ?>
    </nav>

    <div class="shop-bar">
      <div class="shop-view" role="group" aria-label="Grid view">
        <span class="shop-view__label">View:</span>
        <button type="button" class="shop-view__btn" data-view="a" aria-pressed="false" aria-label="Large grid">A</button>
        <button type="button" class="shop-view__btn" data-view="b" aria-pressed="true" aria-label="Compact grid">B</button>
      </div>

      <span class="shop-count" aria-live="polite"><?= count($products) ?> <?= count($products) === 1 ? 'product' : 'products' ?></span>

      <details class="shop-sort">
        <summary>
          <span>Sort</span>
          <svg class="shop-sort__plus" viewBox="0 0 12 12" aria-hidden="true"><path d="M6 1v10M1 6h10"/></svg>
        </summary>
        <div class="shop-sort__panel" role="menu">
          <?php foreach (SHOP_SORTS as $key => $label): ?>
            <a role="menuitemradio" aria-checked="<?= $key === $sort ? 'true' : 'false' ?>"
               class="<?= $key === $sort ? 'is-active' : '' ?>"
               href="<?= esc(shopUrl($current, ['sort' => $key])) ?>"><?= esc($label) ?></a>
          <?php endforeach; ?>
        </div>
      </details>
    </div>

    <?php if ($search !== ''): ?>
      <p class="shop-filter">
        Search: <strong><?= esc($search) ?></strong>
        <a class="link-u" href="<?= esc(shopUrl($current, ['search' => null])) ?>">Clear</a>
      </p>
    <?php endif; ?>

    <?php if (!$products): ?>
      <p class="empty-text"><?= $isFiltered ? 'No products match your filters.' : 'No active products yet.' ?></p>
    <?php else: ?>
      <div class="product-grid shop-grid" id="shopGrid" data-view="b">
        <?php foreach ($products as $product): ?>
          <?= renderProductCard($product) ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</main>
<?php
siteBodyClose(['scripts' => ['assets/js/shop.js']]);
siteDocClose();
