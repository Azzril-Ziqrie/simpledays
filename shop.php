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

// Gambar & jumlah per kategori untuk dropdown, dihitung dari seluruh katalog (bukan hasil filter).
$catalog = $isFiltered ? fetchInStockProducts($pdo, $schema, '', '', false) : $products;
$catalog = sortProducts($catalog, 'featured');
$categoryThumb = [];
$categoryCount = [];
foreach ($catalog as $item) {
    $slugs = [(string)($item['category_slug'] ?? '')];
    if (!empty($item['is_new'])) {
        $slugs[] = 'new';   // kategori "new" memuat semua produk bertanda baru
    }
    foreach (array_unique(array_filter($slugs, 'strlen')) as $slug) {
        $categoryCount[$slug] = ($categoryCount[$slug] ?? 0) + 1;
        $categoryThumb[$slug] ??= (string)$item['thumbnail'];
    }
}
$allThumb = $catalog ? (string)$catalog[0]['thumbnail'] : '';

$categoryLabel = '';
foreach ($categories as $cat) {
    if ($category !== '' && $category === (string)$cat['slug']) {
        $categoryLabel = (string)$cat['name'];
    }
}

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
    <h1 class="sr-only">Shop</h1>

    <div class="shop-bar">
      <span class="shop-count" aria-live="polite"><?= count($products) ?> <?= count($products) === 1 ? 'product' : 'products' ?></span>

      <details class="shop-menu shop-menu--category">
        <summary aria-label="Filter by category">
          <span class="shop-menu__label">Category</span>
          <?php if ($categoryLabel !== ''): ?><span class="shop-menu__value"><?= esc($categoryLabel) ?></span><?php endif; ?>
          <svg class="shop-menu__chev" viewBox="0 0 12 12" aria-hidden="true"><path d="M2 4.5l4 4 4-4"/></svg>
        </summary>
        <div class="shop-menu__panel" role="menu">
          <a role="menuitemradio" aria-checked="<?= $category === '' ? 'true' : 'false' ?>"
             class="shop-menu__cat<?= $category === '' ? ' is-active' : '' ?>"
             href="<?= esc(shopUrl($current, ['category' => null])) ?>">
            <span class="shop-menu__thumb"><?php if ($allThumb !== ''): ?><img src="<?= esc($allThumb) ?>" alt="" loading="lazy" decoding="async"><?php endif; ?></span>
            <span class="shop-menu__text">All</span>
            <span class="shop-menu__n"><?= count($catalog) ?></span>
          </a>
          <?php foreach ($categories as $cat):
              $slug     = (string)$cat['slug'];
              $isActive = $category === $slug;
              $thumb    = $categoryThumb[$slug] ?? '';
              if (!$isActive && empty($categoryCount[$slug])) { continue; }   // sembunyikan kategori kosong
          ?>
            <a role="menuitemradio" aria-checked="<?= $isActive ? 'true' : 'false' ?>"
               class="shop-menu__cat<?= $isActive ? ' is-active' : '' ?>"
               href="<?= esc(shopUrl($current, ['category' => $slug])) ?>">
              <span class="shop-menu__thumb"><?php if ($thumb !== ''): ?><img src="<?= esc($thumb) ?>" alt="" loading="lazy" decoding="async"><?php endif; ?></span>
              <span class="shop-menu__text"><?= esc(sentenceCase((string)$cat['name'])) ?></span>
              <span class="shop-menu__n"><?= (int)($categoryCount[$slug] ?? 0) ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      </details>

      <details class="shop-menu shop-menu--sort">
        <summary aria-label="Sort products">
          <span class="shop-menu__label">Sort</span>
          <svg class="shop-menu__plus" viewBox="0 0 12 12" aria-hidden="true"><path d="M6 1v10M1 6h10"/></svg>
        </summary>
        <div class="shop-menu__panel" role="menu">
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
      <div class="product-grid shop-grid" id="shopGrid">
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
