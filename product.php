<?php
session_start();
require __DIR__ . '/includes/site_gate.php';

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/db_schema.php';
require_once __DIR__ . '/includes/currency_helper.php';
require_once __DIR__ . '/includes/pricing.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/variants.php';
require_once __DIR__ . '/includes/campaign.php';

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
if ($slug === '') { http_response_code(404); exit('Product not found.'); }

/* ── product query ───────────────────────────────────── */
$hasComparePriceIdr = columnExists($pdo, 'products', 'compare_price_idr');
$hasComparePriceUsd = columnExists($pdo, 'products', 'compare_price_usd');
$hasPriceUsd        = columnExists($pdo, 'products', 'price_usd');
$hasColorColumn     = columnExists($pdo, 'products', 'color');
$hasCategorySize    = columnExists($pdo, 'categories', 'has_size');
// Cek kolom size_chart_image agar aman jika migrasi belum dijalankan
$hasSizeChart       = columnExists($pdo, 'products', 'size_chart_image');

// FIX: Deteksi juga kolom flash-sale discount (sama seperti index.php & shop.php),
// sebelumnya tidak pernah di-select di sini sehingga diskon persen tidak pernah kebawa ke product.php
$hasDiscountPercent = columnExists($pdo, 'products', 'discount_percent');
$hasDiscountStart   = columnExists($pdo, 'products', 'discount_start_at');
$hasDiscountEnd     = columnExists($pdo, 'products', 'discount_end_at');
$hasDiscountActive  = columnExists($pdo, 'products', 'is_discount_active');

// Deteksi juga kolom price_usd & compare_price_usd (sama seperti index.php)
$priceUsdSelect     = $hasPriceUsd        ? 'p.price_usd,'              : 'NULL AS price_usd,';
$compareUsdSelect   = $hasComparePriceUsd ? 'p.compare_price_usd,'      : 'NULL AS compare_price_usd,';
$compareSelect      = $hasComparePriceIdr ? 'p.compare_price_idr,'      : 'NULL AS compare_price_idr,';
$colorSelect        = $hasColorColumn     ? 'p.color,'                  : 'NULL AS color,';
$catSizeSelect      = $hasCategorySize    ? 'c.has_size AS category_has_size' : '0 AS category_has_size';
$sizeChartSelect    = $hasSizeChart       ? 'p.size_chart_image,'       : 'NULL AS size_chart_image,';
$discountPercentSelect = $hasDiscountPercent ? 'p.discount_percent,'    : 'NULL AS discount_percent,';
$discountStartSelect   = $hasDiscountStart   ? 'p.discount_start_at,'   : 'NULL AS discount_start_at,';
$discountEndSelect     = $hasDiscountEnd     ? 'p.discount_end_at,'     : 'NULL AS discount_end_at,';
$discountActiveSelect  = $hasDiscountActive  ? 'p.is_discount_active,'  : '0 AS is_discount_active,';

// UPDATE: Ditambahkan p.composition, p.care, dan size_chart_image
$productStmt = $pdo->prepare(
    "SELECT p.id, p.category_id, p.name, p.slug, {$colorSelect} p.description, p.composition, p.care, p.thumbnail, {$sizeChartSelect}
            p.price_idr, {$priceUsdSelect} {$compareSelect} {$compareUsdSelect}
            {$discountPercentSelect} {$discountStartSelect} {$discountEndSelect} {$discountActiveSelect} p.is_active,
            c.name AS category_name, c.slug AS category_slug, {$catSizeSelect}
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.slug = :slug AND p.is_active = 1
     LIMIT 1"
);
$productStmt->execute(['slug' => $slug]);
$product = $productStmt->fetch(PDO::FETCH_ASSOC);

if (!$product) { http_response_code(404); exit('Product not found.'); }


$categoryName = $product['category_name'] ?: 'Product';
$categorySlug = strtolower((string)($product['category_slug'] ?: ''));

if ($hasCategorySize) {
    $hasSizeSelector = (int)$product['category_has_size'] === 1;
} else {
    $hasSizeSelector = $categorySlug === 'fashion';
}

/* ── gallery images ──────────────────────────────────── */
$imageHasColor = columnExists($pdo, 'product_images', 'color_name');
$imageStmt = $pdo->prepare(
    'SELECT image_path, ' . ($imageHasColor ? 'color_name' : "'' AS color_name") . ' FROM product_images
     WHERE product_id = :product_id
     ORDER BY sort_order ASC, id ASC'
);
$imageStmt->execute(['product_id' => $product['id']]);
$detailRows = $imageStmt->fetchAll(PDO::FETCH_ASSOC);

// galleryItems: foto utama + foto detail, tiap foto punya warna ('' = umum). galleryImages: semua path (untuk SEO).
$galleryItems = [];
$galleryImages = [];
if (!empty($product['thumbnail'])) { $galleryItems[] = ['path' => $product['thumbnail'], 'color' => '']; $galleryImages[] = $product['thumbnail']; }
foreach ($detailRows as $row) {
    $path = (string)$row['image_path'];
    if ($path === '' || in_array($path, $galleryImages, true)) continue;
    $galleryItems[] = ['path' => $path, 'color' => trim((string)$row['color_name'])];
    $galleryImages[] = $path;
}
if (empty($galleryImages)) { $galleryImages[] = 'assets/images/placeholder.png'; $galleryItems[] = ['path' => 'assets/images/placeholder.png', 'color' => '']; }

/* ── sizes ───────────────────────────────────────────── */
$sizeStmt = $pdo->prepare(
    'SELECT ps.id AS product_size_id, s.name, s.sort_order, ' . (ddVariantColumnsReady($pdo) ? 'ps.color_name, ps.color_hex,' : "'' AS color_name, NULL AS color_hex,") . ' ps.stock, ps.is_active
     FROM product_sizes ps INNER JOIN sizes s ON s.id=ps.size_id
     WHERE ps.product_id=:pid ORDER BY s.sort_order ASC, s.name ASC, ps.id ASC'
);
$sizeStmt->execute(['pid' => $product['id']]);
$productSizes = $sizeStmt->fetchAll(PDO::FETCH_ASSOC);

// Urut menurut sort_order dulu (S/M sebelum L/XL), lalu natural (1, 2, 10) untuk yang sama.
usort($productSizes, 'ddCompareSizes'); // S/M sebelum L/XL walau sort_order di admin belum diatur

// Semua ukuran aktif tetap tampil; yang stoknya habis ditandai "Sold out" dan tidak bisa dipilih
$activeSizes    = array_values(array_filter($productSizes, fn($s) => (int)$s['is_active'] === 1));
$availableSizes = array_values(array_filter($activeSizes, fn($s) => (int)$s['stock'] > 0));
$isSoldOut = count($availableSizes) === 0;
// Pilihan ukuran tampil kalau produk punya ukuran nyata (bukan "One size"), walau flag has_size kategorinya mati
foreach ($activeSizes as $v) {
    if (!in_array(strtolower(trim((string)$v['name'])), ['one size', 'onesize', 'default', ''], true)) { $hasSizeSelector = true; break; }
}
// Produk hadiah campaign: tidak bisa di-cart, hanya diklaim di checkout
$giftOnlyMin = campaignGiftOnlyMinimum($pdo, (int)$product['id']);

/* ── warna: tiap baris product_sizes = satu varian (ukuran + warna) dengan stok sendiri ── */
$colors = [];   // key lowercase => ['name','hex','available']
foreach ($productSizes as $row) {
    $cName = trim((string)($row['color_name'] ?? ''));
    if ($cName === '' || (int)$row['is_active'] !== 1) continue;
    $key = mb_strtolower($cName);
    if (!isset($colors[$key])) $colors[$key] = ['name' => $cName, 'hex' => ddNormalizeHex($row['color_hex'] ?? ''), 'available' => false];
    if ((int)$row['stock'] > 0) $colors[$key]['available'] = true;
}
$colors = array_values($colors);
$hasColorChoices = count($colors) > 0;

$selectedColor = '';
foreach ($colors as $c) { if ($c['available']) { $selectedColor = $c['name']; break; } }
$selectedVariant = null;
foreach ($availableSizes as $v) {
    $vColor = trim((string)$v['color_name']);
    if (!$hasColorChoices || $vColor === '' || strcasecmp($vColor, $selectedColor) === 0) { $selectedVariant = $v; break; }
}
if ($selectedVariant === null && !$isSoldOut) $selectedVariant = $availableSizes[0];

$selectedSize = $selectedVariant ? $selectedVariant['name'] : '';
$selectedProductSizeId = $selectedVariant ? (int)$selectedVariant['product_size_id'] : null;
$variantData = array_map(fn($v) => [
    'id' => (int)$v['product_size_id'], 'size' => (string)$v['name'],
    'color' => trim((string)($v['color_name'] ?? '')), 'stock' => (int)$v['stock'],
], $availableSizes);

$relatedProducts = fetchRelatedProducts($pdo, catalogSchema($pdo), (int)$product['id'], (string)($product['category_slug'] ?? ''), 4);

$originalPrice = ddProductBasePrice($product);
$basePrice     = productFinalPrice($product);
$comparePrice  = productComparePrice($product);

$isFlashSaleActive = isDiscountActive($product);
$hasDiscount        = $isFlashSaleActive || ($comparePrice > 0 && $comparePrice > $basePrice);
$comparePriceToShow = $isFlashSaleActive ? $originalPrice : $comparePrice;

$displayPrice        = ddFormatDisplay($basePrice);
$displayComparePrice = $hasDiscount ? ddFormatDisplay($comparePriceToShow) : null;

$hasColor = !$hasColorChoices && $hasColorColumn && trim((string)($product['color'] ?? '')) !== '';
$metaDescription = trim(preg_replace('/\s+/', ' ', (string)$product['description']));
if ($metaDescription !== '') {
    $metaDescription = mb_strlen($metaDescription) > 160 ? mb_substr($metaDescription, 0, 157) . '…' : $metaDescription;
}
$metaImage = !empty($product['thumbnail']) ? $product['thumbnail'] : $galleryImages[0];

$origin     = BRAND_URL;
$productUrl = $origin . '/product?slug=' . rawurlencode((string)$product['slug']);
$absImage   = static fn(string $path): string => preg_match('#^https?://#', $path) ? $path : $origin . '/' . ltrim($path, '/');
$productLd  = [
    '@context'    => 'https://schema.org',
    '@type'       => 'Product',
    'name'        => $product['name'],
    'description' => $metaDescription !== '' ? $metaDescription : (BRAND_NAME . ' — ' . $product['name']),
    'image'       => array_map($absImage, $galleryImages),
    'sku'         => (string)$product['slug'],
    'brand'       => ['@type' => 'Brand', 'name' => BRAND_NAME],
    'category'    => $categoryName,
    'offers'      => [
        '@type'         => 'Offer',
        'url'           => $productUrl,
        'priceCurrency' => 'USD',
        'price'         => number_format($basePrice, 2, '.', ''),
        'availability'  => $isSoldOut ? 'https://schema.org/OutOfStock' : 'https://schema.org/InStock',
        'itemCondition' => 'https://schema.org/NewCondition',
    ],
];
if ($hasColor) { $productLd['color'] = (string)$product['color']; }

siteHeadOpen([
    'title'       => $product['name'],
    'description' => $metaDescription !== '' ? $metaDescription : (BRAND_NAME . ' — ' . $product['name']),
    'image'       => $metaImage,
    'canonical'   => $productUrl,
    'trackProductId' => (int)$product['id'],
    'jsonLd'      => [$productLd],
]);
?>
<style>
/* ── halaman produk ──────────────────────────────────── */
.pdp{
  --pdp-tile:#f7f7f7;
  --pdp-pad:16px;
  --pdp-spring:cubic-bezier(.2,.9,.25,1.05);
  display:grid;grid-template-columns:minmax(0,7fr) minmax(0,4fr);gap:clamp(32px,5vw,80px);
  max-width:1440px;margin:0 auto;padding:0 var(--gutter) clamp(56px,8vw,112px);align-items:start;
}

/* galeri: tile abu penuh, foto bertumpuk di desktop */
.pdp-gallery{display:grid;gap:4px;position:relative}
.pdp-slide{background:var(--pdp-tile);margin:0}
.pdp-slide img{width:100%;height:auto;display:block;mix-blend-mode:multiply;cursor:zoom-in}
.pdp-progress{display:none}

/* info: teks tenang, huruf kapital kecil, tanpa kotak */
.pdp-info{position:sticky;top:calc(var(--nav-h) + 24px);max-width:440px;padding:8px 0 0}
.pdp-title{margin:0 0 6px;font-size:13px;font-weight:400;text-transform:uppercase;letter-spacing:.05em;line-height:1.4}
.pdp-price{display:flex;align-items:baseline;gap:10px;font-size:13px;letter-spacing:.03em;font-variant-numeric:tabular-nums}
.pdp-price .was{color:var(--mute);text-decoration:line-through}

.pdp-field{margin-top:36px}
.pdp-label{display:block;margin-bottom:12px;font-size:12px;letter-spacing:.03em;color:var(--ink-2)}
.pdp-label b{color:var(--ink);font-weight:400}

/* warna: bulatan kecil, yang dipilih diberi cincin */
.pdp-colors{display:flex;flex-wrap:wrap;gap:10px}
.color-btn{
  position:relative;width:26px;height:26px;padding:0;border-radius:50%;border:0;background:var(--sw,#ddd);cursor:pointer;
  box-shadow:inset 0 0 0 1px rgba(0,0,0,.18);
  transition:transform .12s ease-out,box-shadow .2s ease;-webkit-tap-highlight-color:transparent;
}
.color-btn::after{content:'';position:absolute;inset:-5px;border-radius:50%;border:1px solid transparent;transition:border-color .2s ease}
.color-btn:hover{transform:scale(1.08)}
.color-btn:active{transform:scale(.92)}
.color-btn.active::after{border-color:var(--ink)}
.color-btn:focus-visible{outline:2px solid var(--ink);outline-offset:6px}
.color-btn:disabled{cursor:not-allowed;opacity:.35;transform:none}
.color-btn:disabled::before{content:'';position:absolute;left:50%;top:-3px;width:1px;height:32px;background:var(--ink);transform:rotate(45deg)}
.color-btn--text{width:auto;height:auto;padding:6px 0;border-radius:0;background:none;box-shadow:none;font-size:13px;letter-spacing:.05em;color:var(--mute);border-bottom:1px solid transparent}
.color-btn--text::after{display:none}
.color-btn--text.active{color:var(--ink);border-bottom-color:var(--ink)}

/* ukuran: teks polos, yang dipilih bergaris bawah */
.pdp-sizes{display:flex;flex-wrap:wrap;align-items:flex-start;gap:6px 22px}
.size-btn{
  padding:6px 0;border:0;border-bottom:1px solid transparent;background:none;color:var(--mute);
  font-size:13px;letter-spacing:.05em;cursor:pointer;
  transition:color .18s ease,border-color .18s ease,transform .12s ease-out;
  -webkit-tap-highlight-color:transparent;
}
.size-btn:hover{color:var(--ink)}
.size-btn:active{transform:scale(.94)}
.size-btn.active{color:var(--ink);border-bottom-color:var(--ink)}
.size-btn:focus-visible{outline:2px solid var(--ink);outline-offset:3px}
.size-btn__name,.size-btn__note{display:block}
.size-btn__note{margin-top:2px;font-size:9px;letter-spacing:.08em;text-transform:uppercase}
.size-btn--soldout,.size-btn--soldout:hover{color:var(--mute);cursor:not-allowed;transform:none}
.size-btn--soldout .size-btn__name{text-decoration:line-through;opacity:.6}
.pdp-soldout{font-size:12px;letter-spacing:.05em;text-transform:uppercase;color:var(--mute)}

.pdp-desc{margin-top:36px;font-size:12.5px;line-height:1.75;letter-spacing:.01em;color:var(--ink)}

/* tombol beli: hitam penuh, sudut tegas */
.pdp-buy{margin-top:32px}
.pdp-gift-note{margin:12px 0 0;font-size:13px;line-height:1.5;color:var(--mute)}
.pdp-buy .btn{
  width:100%;height:46px;border-radius:0;border:1px solid var(--ink);background:var(--ink);color:var(--bg);
  font-size:12px;font-weight:400;letter-spacing:.1em;text-transform:uppercase;
  transition:transform .12s ease-out,opacity .2s;
}
.pdp-buy .btn:hover:not(:disabled){opacity:.88}
.pdp-buy .btn:active:not(:disabled){transform:scale(.99)}
.pdp-buy .btn:disabled{background:var(--pdp-tile);border-color:var(--pdp-tile);color:var(--mute)}

/* informasi: satu disclosure */
.pdp-more{margin-top:28px}
.pdp-more summary{
  list-style:none;display:inline-flex;align-items:center;gap:8px;min-height:44px;
  font-size:12px;letter-spacing:.05em;cursor:pointer;-webkit-tap-highlight-color:transparent;
}
.pdp-more summary::-webkit-details-marker{display:none}
.pdp-more summary::before{content:'';width:0;height:0;border-left:6px solid currentColor;border-top:4px solid transparent;border-bottom:4px solid transparent;transition:transform .3s var(--pdp-spring)}
.pdp-more[open] summary::before{transform:rotate(90deg)}
.pdp-more summary:focus-visible{outline:2px solid var(--ink);outline-offset:3px}
.pdp-body{padding:8px 0 4px;font-size:12px;line-height:1.75;color:var(--ink-2)}
.pdp-body p+p{margin-top:14px}
.pdp-body strong{color:var(--ink);font-weight:500}
.pdp-body a,.pdp-linkbtn{text-decoration:underline;text-underline-offset:3px;color:var(--ink)}
.pdp-linkbtn{font-size:12px}

/* modal size chart & galeri layar penuh */
.pdp-modal{position:fixed;inset:0;z-index:150;display:flex;align-items:center;justify-content:center;padding:20px;background:rgba(0,0,0,.18);-webkit-backdrop-filter:blur(22px) saturate(140%);backdrop-filter:blur(22px) saturate(140%);opacity:0;visibility:hidden;transition:opacity .3s ease,visibility .3s}
.pdp-modal.active{opacity:1;visibility:visible}
.pdp-chart{position:relative;max-width:min(92vw,720px);max-height:90vh;padding:20px;background:rgba(255,255,255,.55);-webkit-backdrop-filter:blur(30px) saturate(190%);backdrop-filter:blur(30px) saturate(190%);border:1px solid rgba(255,255,255,.6);border-radius:var(--r-lg);box-shadow:0 30px 80px rgba(0,0,0,.22),inset 0 1px 0 rgba(255,255,255,.7)}
.pdp-chart img{max-width:100%;max-height:78vh;margin:0 auto;object-fit:contain}
.pdp-close{position:absolute;top:6px;right:6px;width:44px;height:44px;font-size:24px;font-weight:300;line-height:1;color:var(--ink);z-index:2}
.pdp-fs{padding:0;background:rgba(255,255,255,.5)}
.pdp-fs img{max-width:92%;max-height:86vh;object-fit:contain;mix-blend-mode:multiply}
.pdp-fs-ctrl{position:absolute;left:50%;bottom:24px;transform:translateX(-50%);display:flex;align-items:center;gap:6px;font-size:11px;font-weight:500}
.pdp-fs-ctrl button{width:44px;height:44px;font-size:16px}
.pdp-fs-ctrl span{min-width:44px;text-align:center;color:var(--ink-2)}

/* produk terkait: baris geser dengan kartu berikutnya mengintip (mobile), grid 4 kolom (desktop) */
.pdp-related{max-width:1440px;margin:0 auto;padding:clamp(40px,6vw,72px) 0 clamp(56px,8vw,112px)}
.pdp-related__title{margin:0 0 14px;padding:0 var(--gutter);font-size:12px;font-weight:400;letter-spacing:.05em}
.pdp-related .product-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:4px;padding:0 var(--gutter)}
.pdp-related .product-card{background:var(--bg)}
.pdp-related .product-tile{aspect-ratio:3/4;background:var(--pdp-tile);border-radius:0}
.pdp-related .product-tile img{object-fit:cover;mix-blend-mode:multiply}
.pdp-related .product-info{padding:12px 4px 4px;gap:2px}
.pdp-related .product-name,.pdp-related .product-price{font-size:12px;font-weight:400;letter-spacing:.02em}

/* mobile: galeri geser, penunjuk berupa garis tipis */
@media(max-width:900px){
  .pdp{grid-template-columns:1fr;gap:0;padding:0 0 64px}
  .pdp-wrap{position:relative}
  .pdp-gallery{display:flex;overflow-x:auto;scroll-snap-type:x mandatory;scrollbar-width:none;gap:0;-webkit-overflow-scrolling:touch}
  .pdp-gallery::-webkit-scrollbar{display:none}
  .pdp-slide{flex:0 0 100%;scroll-snap-align:start}
  .pdp-progress{display:block;position:relative;height:1px;margin:14px var(--pdp-pad) 0;background:rgba(0,0,0,.14)}
  .pdp-progress span{position:absolute;left:0;top:-1px;height:3px;background:var(--ink);transition:left .25s var(--pdp-spring)}
  .pdp-info{position:static;max-width:none;padding:44px var(--pdp-pad) 0}
  .pdp-related__title{padding:0 16px}
  .pdp-related .product-grid{display:flex;overflow-x:auto;scroll-snap-type:x mandatory;scrollbar-width:none;padding:0}
  .pdp-related .product-grid::-webkit-scrollbar{display:none}
  .pdp-related .product-card{flex:0 0 46%;scroll-snap-align:start}
  .pdp-related .product-info{padding:12px 14px 6px}
}
@media(min-width:901px){.pdp-wrap{display:contents}}
@media(prefers-reduced-motion:reduce){.pdp-modal,.size-btn,.color-btn,.color-btn::after,.pdp-buy .btn,.pdp-more summary::before,.pdp-progress span{transition:none}}
@media(prefers-reduced-transparency:reduce){.pdp-modal{-webkit-backdrop-filter:none;backdrop-filter:none;background:rgba(0,0,0,.45)}.pdp-chart{background:#fff;-webkit-backdrop-filter:none;backdrop-filter:none}.pdp-fs{background:#fff}}
</style>
<?php
siteBodyOpen(['bodyClass' => 'page-product', 'searchAction' => 'shop.php']);
?>
<main class="page-main">
  <div class="pdp">
    <div class="pdp-wrap">
      <div class="pdp-gallery" id="productImageContainer" aria-label="Product photos">
        <?php
          // Foto sesuai warna terpilih; kalau warna itu tidak punya foto sendiri, pakai foto umum.
          $selColorKey = mb_strtolower($selectedColor);
          $colorHasOwn = false;
          foreach ($galleryItems as $gi) { if ($selColorKey !== '' && mb_strtolower($gi['color']) === $selColorKey) { $colorHasOwn = true; break; } }
          $firstShown = true;
        ?>
        <?php foreach ($galleryItems as $i => $gi): ?>
          <?php
            $gc = mb_strtolower($gi['color']);
            $showSlide = $selColorKey === '' ? true : ($colorHasOwn ? $gc === $selColorKey : $gc === '');
            $eager = $showSlide && $firstShown; if ($showSlide) $firstShown = false;
          ?>
          <figure class="pdp-slide" data-color="<?= esc($gi['color']) ?>"<?= $showSlide ? '' : ' hidden' ?>>
            <img src="<?= esc($gi['path']) ?>" alt="<?= esc($product['name']) ?>" loading="<?= $eager ? 'eager' : 'lazy' ?>" decoding="async">
          </figure>
        <?php endforeach; ?>
      </div>
      <div class="pdp-progress" id="imageProgress" aria-hidden="true"><span id="imageIndicator" style="width:100%"></span></div>
    </div>

    <div class="pdp-info">
      <h1 class="pdp-title"><?= esc($product['name']) ?></h1>

      <div class="pdp-price">
        <?php if ($hasDiscount): ?><span class="was"><?= esc($displayComparePrice) ?></span><?php endif; ?>
        <span><?= esc($displayPrice) ?></span>
      </div>

      <?php if ($hasColor): ?>
        <div class="pdp-field"><span class="pdp-label">Color: <b><?= esc($product['color']) ?></b></span></div>
      <?php endif; ?>

      <?php if ($hasColorChoices && !$isSoldOut): ?>
        <div class="pdp-field">
          <span class="pdp-label">Color: <b id="colorLabel"><?= esc($selectedColor) ?></b></span>
          <div class="pdp-colors" id="colorBtnGroup" role="group" aria-label="Select color">
            <?php foreach ($colors as $c): ?>
              <?php $isSelColor = strcasecmp($c['name'], $selectedColor) === 0; ?>
              <button type="button" class="color-btn<?= $c['hex'] ? '' : ' color-btn--text' ?><?= $isSelColor ? ' active' : '' ?>"
                      data-color="<?= esc($c['name']) ?>"
                      <?= $c['hex'] ? 'style="--sw:' . esc($c['hex']) . '"' : '' ?>
                      aria-label="<?= esc($c['name']) . ($c['available'] ? '' : ' (sold out)') ?>"
                      aria-pressed="<?= $isSelColor ? 'true' : 'false' ?>"
                      <?= $c['available'] ? '' : 'disabled' ?>><?= $c['hex'] ? '' : esc($c['name']) ?></button>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($hasSizeSelector && $activeSizes): ?>
        <div class="pdp-field">
          <span class="sr-only">Size</span>
          <div class="pdp-sizes" id="sizeBtnGroup" role="group" aria-label="Select size">
            <?php foreach ($activeSizes as $size): ?>
              <?php
                $sizeSoldOut = (int)$size['stock'] <= 0;
                $isSelectedOpt = $selectedProductSizeId === (int)$size['product_size_id'];
                $sizeColor = trim((string)($size['color_name'] ?? ''));
                $showSize = !$hasColorChoices || $sizeColor === '' || strcasecmp($sizeColor, $selectedColor) === 0;
              ?>
              <button type="button" class="size-btn<?= $isSelectedOpt ? ' active' : '' ?><?= $sizeSoldOut ? ' size-btn--soldout' : '' ?>"
                      data-id="<?= (int)$size['product_size_id'] ?>"
                      data-size="<?= esc($size['name']) ?>"
                      data-color="<?= esc($sizeColor) ?>"
                      data-stock="<?= (int)$size['stock'] ?>"
                      aria-pressed="<?= $isSelectedOpt ? 'true' : 'false' ?>"
                      <?= $sizeSoldOut ? 'disabled aria-label="' . esc($size['name']) . ', sold out"' : '' ?><?= $showSize ? '' : ' hidden' ?>><span class="size-btn__name"><?= esc($size['name']) ?></span><?php if ($sizeSoldOut): ?><span class="size-btn__note">Sold out</span><?php endif; ?></button>
            <?php endforeach; ?>
          </div>
        </div>
      <?php elseif ($isSoldOut): ?>
        <div class="pdp-field"><span class="pdp-soldout">Sold out</span></div>
      <?php endif; ?>

      <?php if (trim((string)$product['description']) !== ''): ?>
        <p class="pdp-desc"><?= nl2br(esc($product['description'])) ?></p>
      <?php endif; ?>

      <div class="pdp-buy">
        <?php if ($giftOnlyMin !== null): ?>
          <button type="button" class="btn btn--solid btn--block" disabled>
            <span class="cart-btn__label">Free gift — not sold separately</span>
          </button>
          <p class="pdp-gift-note">Spend <?= esc(ddFormatDisplay($giftOnlyMin)) ?> or more and choose this as your free gift at checkout.</p>
        <?php else: ?>
        <button type="button" class="btn btn--solid btn--block" id="addToCartBtn" <?= $isSoldOut ? 'disabled' : '' ?>>
          <span class="cart-btn__label"><?= $isSoldOut ? 'Sold out' : 'Add to cart' ?></span>
        </button>
        <?php endif; ?>
      </div>

      <details class="pdp-more">
        <summary>Information</summary>
        <div class="pdp-body">
          <?php if (!empty($product['composition'])): ?><p><strong>Materials</strong><br><?= esc($product['composition']) ?></p><?php endif; ?>
          <?php if (!empty($product['care'])): ?><p><strong>Care</strong><br><?= esc($product['care']) ?></p><?php endif; ?>
          <?php if (!empty($product['size_chart_image'])): ?>
            <p><button type="button" class="pdp-linkbtn" id="btn-size-chart">Size guide</button></p>
          <?php endif; ?>
          <p><strong>Shipping &amp; returns</strong><br>Shipping is calculated at checkout. Orders are prepared within 1–2 working days after payment confirmation. All sales are final, unless the item received is defective or incorrect. <a href="shipping.php">Read full policy</a></p>
        </div>
      </details>
    </div>
  </div>

  <?php if ($relatedProducts): ?>
  <section class="pdp-related" aria-labelledby="relatedTitle">
    <h2 class="pdp-related__title" id="relatedTitle">Related products</h2>
    <div class="product-grid">
      <?php foreach ($relatedProducts as $related): ?>
        <?= renderProductCard($related) ?>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
</main>

<?php if (!empty($product['size_chart_image'])): ?>
<div id="modal-size-chart" class="pdp-modal" role="dialog" aria-modal="true" aria-label="Size chart">
  <div class="pdp-chart">
    <button type="button" class="pdp-close" id="close-size-chart" aria-label="Close">&times;</button>
    <img src="<?= esc($product['size_chart_image']) ?>" alt="Size chart <?= esc($product['name']) ?>">
  </div>
</div>
<?php endif; ?>

<div id="fullscreenGallery" class="pdp-modal pdp-fs" role="dialog" aria-modal="true" aria-label="Photo gallery">
  <button type="button" class="pdp-close" id="closeGalleryBtn" aria-label="Close gallery">&times;</button>
  <img src="" alt="<?= esc($product['name']) ?>" id="fullscreenGalleryImg">
  <div class="pdp-fs-ctrl" id="fsCtrl">
    <button type="button" id="galleryPrevBtn" aria-label="Previous image">&#8592;</button>
    <span id="galleryIndicator">1 / 1</span>
    <button type="button" id="galleryNextBtn" aria-label="Next image">&#8594;</button>
  </div>
</div>
<?php
siteBodyClose();
?>
<script>
(function () {
  'use strict';

  const productId       = <?= (int)$product['id'] ?>;
  const hasSizeSelector = <?= $hasSizeSelector ? 'true' : 'false' ?>;

  // Data produk untuk keranjang
  const prodName  = <?= json_encode($product['name']) ?>;
  const prodSlug  = <?= json_encode($product['slug']) ?>;
  const prodThumb = <?= json_encode($product['thumbnail']) ?>;
  const prodPrice = <?= json_encode((float)$basePrice) ?>;

  const variants            = <?= json_encode($variantData) ?>;
  let selectedColor         = <?= json_encode($selectedColor) ?>;
  let selectedSize          = <?= json_encode($selectedSize) ?>;
  let selectedProductSizeId = <?= json_encode($selectedProductSizeId) ?>;
  let selectedStock         = <?= json_encode($selectedVariant ? (int)$selectedVariant['stock'] : 0) ?>;
  const quantity            = 1;

  const cart      = Site.cart;
  const cartItems = cart.items;
  const showToast = Site.showToast;
  const addToCartBtn = document.getElementById('addToCartBtn');

  /* ── pilihan warna & ukuran: tiap kombinasi punya id varian (product_sizes.id) & stok sendiri ────── */
  const sizeBtns  = Array.from(document.querySelectorAll('.size-btn'));
  const colorBtns = Array.from(document.querySelectorAll('.color-btn'));
  const colorLabel = document.getElementById('colorLabel');

  function applyVariant(v) {
    selectedProductSizeId = String(v.id);
    selectedSize  = v.size;
    selectedStock = v.stock;
    sizeBtns.forEach(b => {
      const on = b.dataset.id === String(v.id);
      b.classList.toggle('active', on);
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
  }

  colorBtns.forEach(btn => {
    btn.addEventListener('click', function () {
      if (this.disabled) return;
      selectedColor = this.dataset.color;
      colorBtns.forEach(b => { const on = b === this; b.classList.toggle('active', on); b.setAttribute('aria-pressed', on ? 'true' : 'false'); });
      if (colorLabel) colorLabel.textContent = selectedColor;

      // varian tanpa warna berlaku untuk semua warna
      const fits = c => c === '' || c.toLowerCase() === selectedColor.toLowerCase();
      const ofColor = variants.filter(v => fits(v.color));
      sizeBtns.forEach(b => { b.hidden = !fits(b.dataset.color); });
      // pertahankan ukuran yang sama kalau warna baru punya, kalau tidak pakai ukuran pertama
      const next = ofColor.find(v => v.size === selectedSize) || ofColor[0];
      if (next) applyVariant(next);
      filterGallery(selectedColor);
    });
  });

  sizeBtns.forEach(btn => {
    btn.addEventListener('click', function () {
      const v = variants.find(x => String(x.id) === this.dataset.id);
      if (v) applyVariant(v);
    });
  });

  function hasSelection() { return !hasSizeSelector || Boolean(selectedProductSizeId); }

  /* ── tambah ke keranjang ────── */
  const addLabelEl      = addToCartBtn ? addToCartBtn.querySelector('.cart-btn__label') : null;
  const addLabelDefault = addLabelEl ? addLabelEl.textContent : '';
  let addLabelTimer = null;

  function doAddToCart() {
    if (!hasSelection()) { showToast('Please select an available option first.'); return; }
    if (selectedStock <= 0) { showToast('This size is sold out.'); return; }

    const itemName    = prodName + (selectedSize ? ` - ${selectedSize}` : '') + (selectedColor ? ` - ${selectedColor}` : '');
    const existingIdx = cartItems.findIndex(i => i.id === productId && i.name === itemName);

    // validasi stok SEBELUM apa pun dikirim, supaya cart lokal & server tidak beda
    if (existingIdx > -1 && cartItems[existingIdx].qty + quantity > selectedStock) {
      showToast('Only ' + selectedStock + ' left in stock.');
      return;
    }

    // respons instan: cart lokal langsung diperbarui & dibuka, tanpa menunggu jaringan
    if (existingIdx > -1) {
      cartItems[existingIdx].qty += quantity;
    } else {
      cartItems.push({
        id: productId,
        slug: prodSlug,
        size_id: selectedProductSizeId,
        name: itemName,
        thumb: prodThumb,
        price: prodPrice,
        qty: quantity,
        stock: selectedStock,
        currency: 'USD'
      });
    }
    cart.lastAddedName = itemName;
    cart.save();
    cart.render();

    // sinkron ke server di belakang layar (checkout tetap re-sync lewat sync-cart.php)
    const fd = new FormData();
    fd.append('product_id', productId);
    fd.append('quantity', quantity);
    if (selectedProductSizeId) fd.append('product_size_id', selectedProductSizeId);
    fetch('add-to-cart.php', { method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'} }).catch(() => {});

    if (addLabelEl) {
      addLabelEl.textContent = 'Added ✓';
      clearTimeout(addLabelTimer);
      addLabelTimer = setTimeout(() => { addLabelEl.textContent = addLabelDefault; }, 1400);
    }
    Site.openCart();
  }
  if (addToCartBtn) addToCartBtn.addEventListener('click', doAddToCart);

  /* ── size chart ────── */
  const btnSizeChart   = document.getElementById('btn-size-chart');
  const modalSizeChart = document.getElementById('modal-size-chart');
  if (btnSizeChart && modalSizeChart) {
    const closeChart = () => modalSizeChart.classList.remove('active');
    btnSizeChart.addEventListener('click', e => { e.preventDefault(); modalSizeChart.classList.add('active'); });
    document.getElementById('close-size-chart').addEventListener('click', closeChart);
    modalSizeChart.addEventListener('click', e => { if (e.target === modalSizeChart) closeChart(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeChart(); });
  }

  /* ── galeri: foto mengikuti warna terpilih ────── */
  const imageContainer = document.getElementById('productImageContainer');
  const imageIndicator = document.getElementById('imageIndicator');
  const imageProgress  = document.getElementById('imageProgress');
  const fsCtrl         = document.getElementById('fsCtrl');
  const slides         = Array.from(document.querySelectorAll('.pdp-slide'));

  function visibleSlides() { return slides.filter(s => !s.hidden); }

  function refreshGalleryUi() {
    const n = visibleSlides().length;
    if (imageIndicator) { imageIndicator.style.width = (100 / Math.max(n, 1)) + '%'; imageIndicator.style.left = '0%'; }
    if (imageProgress) imageProgress.style.visibility = n > 1 ? '' : 'hidden';
    if (fsCtrl) fsCtrl.style.display = n > 1 ? '' : 'none';
  }

  function filterGallery(color) {
    const c = (color || '').toLowerCase();
    const hasOwn = c !== '' && slides.some(s => s.dataset.color.toLowerCase() === c);
    slides.forEach(s => {
      const sc = s.dataset.color.toLowerCase();
      s.hidden = c === '' ? false : (hasOwn ? sc !== c : sc !== '');
    });
    if (imageContainer) imageContainer.scrollLeft = 0;
    refreshGalleryUi();
  }
  refreshGalleryUi();

  if (imageContainer && imageIndicator) {
    imageContainer.addEventListener('scroll', () => {
      const total   = Math.max(visibleSlides().length, 1);
      const current = Math.min(total, Math.max(1, Math.round(imageContainer.scrollLeft / imageContainer.clientWidth) + 1));
      imageIndicator.style.left = ((current - 1) / total * 100) + '%';
    }, { passive: true });
  }

  /* ── galeri layar penuh ────── */
  let galleryImagesSrc = [];
  const fsGallery = document.getElementById('fullscreenGallery');
  const fsImg     = document.getElementById('fullscreenGalleryImg');
  const fsText    = document.getElementById('galleryIndicator');
  let fsIndex = 0;

  function updateFs() {
    fsImg.src = galleryImagesSrc[fsIndex];
    if (fsText) fsText.textContent = (fsIndex + 1) + ' / ' + galleryImagesSrc.length;
  }
  function openFs(index) {
    galleryImagesSrc = visibleSlides().map(s => s.querySelector('img').getAttribute('src'));
    fsIndex = Math.max(0, index);
    updateFs();
    fsGallery.classList.add('active');
    document.body.classList.add('lock-scroll');
  }
  function closeFs() {
    fsGallery.classList.remove('active');
    document.body.classList.remove('lock-scroll');
  }
  function stepFs(delta) {
    fsIndex = (fsIndex + delta + galleryImagesSrc.length) % galleryImagesSrc.length;
    updateFs();
  }

  slides.forEach(s => s.querySelector('img').addEventListener('click', () => openFs(visibleSlides().indexOf(s))));
  document.getElementById('closeGalleryBtn').addEventListener('click', closeFs);
  const prevBtn = document.getElementById('galleryPrevBtn'), nextBtn = document.getElementById('galleryNextBtn');
  if (prevBtn) prevBtn.addEventListener('click', () => stepFs(-1));
  if (nextBtn) nextBtn.addEventListener('click', () => stepFs(1));
  fsGallery.addEventListener('click', e => { if (e.target === fsGallery) closeFs(); });
  document.addEventListener('keydown', e => {
    if (!fsGallery.classList.contains('active')) return;
    if (e.key === 'Escape') closeFs();
    if (e.key === 'ArrowRight') stepFs(1);
    if (e.key === 'ArrowLeft') stepFs(-1);
  });
})();
</script>
<?php siteDocClose();
