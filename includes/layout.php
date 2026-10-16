<?php
/**
 * Kerangka halaman storefront Simple Days.
 *
 * Pemakaian di setiap halaman:
 *
 *   require_once __DIR__ . '/includes/layout.php';
 *   siteHeadOpen(['title' => 'Shop', 'css' => ['assets/css/xyz.css']]);
 *   echo '<style>…CSS khusus halaman…</style>';
 *   siteBodyOpen(['bodyClass' => 'page-shop', 'searchAction' => 'shop.php']);
 *   …isi halaman (di dalam <main class="page-main">)…
 *   siteBodyClose(['scripts' => ['assets/js/xyz.js']]);
 *   // <script> khusus halaman (boleh memakai window.Site) …
 *   siteDocClose();
 *
 * Membutuhkan $pdo (config/database.php) dan includes/currency_helper.php.
 */

require_once __DIR__ . '/brand.php';
require_once __DIR__ . '/db_schema.php';
require_once __DIR__ . '/catalog.php';
require_once __DIR__ . '/currency_helper.php';   // partials/menu.php memanggil ddDisplayCurrency()
require_once __DIR__ . '/pricing.php';

if (!function_exists('esc')) {
    function esc($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

/** Sentence case: huruf pertama kapital, sisanya kecil ("new" / "NEW" → "New", "SHORT SLEEVE" → "Short sleeve"). */
if (!function_exists('sentenceCase')) {
    function sentenceCase(string $text): string
    {
        $text = trim($text);
        return mb_strtoupper(mb_substr($text, 0, 1)) . mb_strtolower(mb_substr($text, 1));
    }
}

/** Path aset + ?v=mtime supaya cache browser ikut berganti saat file berubah. */
function siteAsset(string $path): string
{
    $absolute = dirname(__DIR__) . '/' . $path;
    return $path . (is_file($absolute) ? '?v=' . filemtime($absolute) : '');
}

/** Konteks yang dipakai partial (navbar, menu, dst). */
function siteContext(?array $override = null): array
{
    static $ctx = null;
    if ($override !== null) {
        $ctx = $override;
    }
    return $ctx ?? [];
}

/** <!doctype> … <head>; head dibiarkan terbuka agar halaman bisa menambah <style>/<link>. */
function siteHeadOpen(array $o = []): void
{
    $title       = isset($o['title']) && $o['title'] !== '' ? $o['title'] . ' — ' . BRAND_NAME : BRAND_NAME;
    $description = $o['description'] ?? (BRAND_NAME . ' — ' . BRAND_TAGLINE);
    $origin      = 'https://' . ($_SERVER['HTTP_HOST'] ?? '');
    $image       = $o['image'] ?? BRAND_ICON;
    $imageUrl    = preg_match('#^https?://#', $image) ? $image : $origin . '/' . ltrim($image, '/');
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title><?= esc($title) ?></title>
  <meta name="description" content="<?= esc($description) ?>">
  <?php if (!empty($o['noindex'])): ?><meta name="robots" content="noindex,nofollow"><?php endif; ?>
  <link rel="icon" type="image/png" href="<?= esc(BRAND_ICON) ?>">
  <meta property="og:type" content="website">
  <meta property="og:url" content="<?= esc($origin . ($_SERVER['REQUEST_URI'] ?? '/')) ?>">
  <meta property="og:title" content="<?= esc($title) ?>">
  <meta property="og:description" content="<?= esc($description) ?>">
  <meta property="og:image" content="<?= esc($imageUrl) ?>">
  <meta property="og:site_name" content="<?= esc(BRAND_NAME) ?>">
  <meta name="twitter:card" content="summary_large_image">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:ital,wght@0,100..800;1,100..800&family=Roboto+Mono:ital,wght@0,100..700;1,100..700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= esc(siteAsset('assets/css/site.css')) ?>">
  <?php foreach (($o['css'] ?? []) as $css): ?>
  <link rel="stylesheet" href="<?= esc(siteAsset($css)) ?>">
  <?php endforeach; ?>
  <?php if (function_exists('ddDisplayJs')): ?><script><?= ddDisplayJs() ?></script><?php endif; ?>
  <script>
    // Tema: pilihan tersimpan, jika belum ada default terang. Dipasang sebelum render agar tidak berkedip.
    (function () {
      var t = null;
      try { t = localStorage.getItem('sdTheme'); } catch (e) {}
      if (t !== 'dark' && t !== 'light') t = 'light';
      document.documentElement.setAttribute('data-theme', t);
    })();
  </script>
  <script>
    // Animasi masuk hanya bila pengguna tidak minta reduced-motion; dicabut lagi bila Motion gagal dimuat.
    if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) document.documentElement.classList.add('js');
    <?php if (!empty($o['currencyGate'])): ?>
    try {
      if (location.search.indexOf('choose_currency') === -1 && sessionStorage.getItem('ddCurrencyPicked') === '1')
        document.documentElement.classList.add('cg-skip');
    } catch (e) {}
    <?php endif; ?>
  </script>
<?php
}

/**
 * Tutup <head>, buka <body> dan cetak chrome bersama: loader, (pop-up mata uang), overlay,
 * toast, panel menu & keranjang, modal akun, navbar.
 *
 * Opsi: bodyClass, overlay(bool: navbar transparan di atas hero), searchAction, categoryBase,
 *       search, category, currencyGate(array dari currencyGateModel()), beforeNavbar(callable)
 */
function siteBodyOpen(array $o = []): void
{
    global $pdo;

    $search   = (string)($o['search']   ?? trim((string)($_GET['search']   ?? '')));
    $category = (string)($o['category'] ?? trim((string)($_GET['category'] ?? '')));
    $categories = [];
    if (isset($pdo) && $pdo instanceof PDO) {
        $categories = fetchCategories($pdo, catalogSchema($pdo));
    }

    $ctx = siteContext([
        'overlay'      => !empty($o['overlay']),
        'search'       => $search,
        'category'     => $category,
        'searchAction' => $o['searchAction'] ?? 'shop.php',
        'categoryBase' => $o['categoryBase'] ?? ($o['searchAction'] ?? 'shop.php'),
        'categories'   => $categories,
    ]);
    ?>
</head>
<body class="<?= esc($o['bodyClass'] ?? '') ?>">
  <?php if (is_file(dirname(__DIR__) . '/loader.php')) include_once dirname(__DIR__) . '/loader.php'; ?>

  <?php if (!empty($o['currencyGate'])) { $gate = $o['currencyGate']; include __DIR__ . '/partials/currency_gate.php'; } ?>

  <div class="overlay" id="overlay"></div>
  <div class="toast-container" id="toastContainer" aria-live="polite"></div>

  <?php
    include __DIR__ . '/partials/menu.php';
    include __DIR__ . '/partials/cart.php';
    include __DIR__ . '/partials/account.php';
    if (isset($o['beforeNavbar']) && is_callable($o['beforeNavbar'])) { ($o['beforeNavbar'])(); }
    include dirname(__DIR__) . '/navbar.php';
}

/** Footer + script bersama. <body> dibiarkan terbuka untuk script halaman. */
function siteBodyClose(array $o = []): void
{
    include dirname(__DIR__) . '/footer.php';
    ?>
  <script src="<?= esc(siteAsset('assets/js/mini-cart.js')) ?>"></script>
  <script src="<?= esc(siteAsset('assets/js/vendor/motion.js')) ?>"></script>
  <script src="<?= esc(siteAsset('assets/js/site.js')) ?>"></script>
  <?php foreach (($o['scripts'] ?? []) as $script): ?>
  <script src="<?= esc(siteAsset($script)) ?>"></script>
  <?php endforeach; ?>
<?php
}

function siteDocClose(): void
{
    echo "</body>\n</html>\n";
}

/* ── helper tampilan yang dipakai banyak halaman ─────────── */

/** Harga + diskon untuk kartu produk: ['original','final','hasDiscount','discountPct']. */
function productPriceView(array $product): array
{
    $original = ddProductBasePrice($product);
    $final    = productFinalPrice($product);
    $has      = $final < $original;
    return [
        'original'    => $original,
        'final'       => $final,
        'hasDiscount' => $has,
        'discountPct' => $has ? (int)round((1 - $final / $original) * 100) : 0,
    ];
}

/** Satu kartu produk (grid beranda & shop). $stagger dipakai untuk urutan reveal. */
function renderProductCard(array $product): string
{
    $price = productPriceView($product);
    $name  = (string)$product['name'];
    $link  = 'product.php?slug=' . urlencode((string)$product['slug']);

    $soldOut = isset($product['is_in_stock']) && (int)$product['is_in_stock'] !== 1;

    $badges = '';
    if ($soldOut)                     { $badges .= '<span class="product-badge">Sold out</span>'; }
    elseif (!empty($product['is_new'])) { $badges .= '<span class="product-badge">New</span>'; }
    if ($price['hasDiscount'] && !$soldOut) { $badges .= '<span class="product-badge product-badge--sale">−' . $price['discountPct'] . '%</span>'; }

    $html  = '<a class="product-card reveal' . ($soldOut ? ' is-sold-out' : '') . '" href="' . esc($link) . '"><div class="product-tile">';
    $html .= $badges !== '' ? '<div class="product-badges">' . $badges . '</div>' : '';
    $html .= '<img src="' . esc($product['thumbnail']) . '" alt="' . esc($name) . '" loading="lazy" decoding="async">';
    if (!empty($product['hover_image'])) {
        $html .= '<img class="alt" src="' . esc($product['hover_image']) . '" alt="" loading="lazy" decoding="async">';
    }
    $html .= '</div><div class="product-info"><div class="product-name">' . esc($name) . '</div><div class="product-price">';
    if ($price['hasDiscount'] && !$soldOut) {
        $html .= '<span class="was">' . esc(ddFormatDisplay($price['original'])) . '</span>';
    }
    $html .= '<span class="now">' . esc(ddFormatDisplay($price['final'])) . '</span></div></div></a>';
    return $html;
}
