<?php
session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/db_schema.php';
require_once __DIR__ . '/includes/currency_helper.php';
require_once __DIR__ . '/includes/pricing.php';
require_once __DIR__ . '/includes/layout.php';

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
$imageStmt = $pdo->prepare(
    'SELECT image_path FROM product_images
     WHERE product_id = :product_id
     ORDER BY sort_order ASC, id ASC'
);
$imageStmt->execute(['product_id' => $product['id']]);
$detailImages = $imageStmt->fetchAll(PDO::FETCH_COLUMN);

$galleryImages = [];
if (!empty($product['thumbnail'])) $galleryImages[] = $product['thumbnail'];
foreach ($detailImages as $p) {
    if ($p !== '' && !in_array($p, $galleryImages, true)) $galleryImages[] = $p;
}
if (empty($galleryImages)) $galleryImages[] = 'assets/images/placeholder.png';

/* ── sizes ───────────────────────────────────────────── */
$sizeStmt = $pdo->prepare(
    'SELECT ps.id AS product_size_id, s.name, s.sort_order, ps.stock, ps.is_active
     FROM product_sizes ps INNER JOIN sizes s ON s.id=ps.size_id
     WHERE ps.product_id=:pid ORDER BY s.sort_order ASC, s.name ASC'
);
$sizeStmt->execute(['pid' => $product['id']]);
$productSizes = $sizeStmt->fetchAll(PDO::FETCH_ASSOC);

// Urut menurut sort_order dulu (S/M sebelum L/XL), lalu natural (1, 2, 10) untuk yang sama.
usort($productSizes, function ($a, $b) {
    $orderCmp = ((int)($a['sort_order'] ?? 0)) <=> ((int)($b['sort_order'] ?? 0));
    if ($orderCmp !== 0) return $orderCmp;
    return strnatcasecmp((string)$a['name'], (string)$b['name']);
});

$availableSizes = array_values(array_filter($productSizes, fn($s) => (int)$s['is_active']===1 && (int)$s['stock']>0));
$isSoldOut = count($availableSizes) === 0;
$selectedSize = $isSoldOut ? '' : $availableSizes[0]['name'];
$selectedProductSizeId = $isSoldOut ? null : (int)$availableSizes[0]['product_size_id'];

$relatedProducts = fetchRelatedProducts($pdo, catalogSchema($pdo), (int)$product['id'], (string)($product['category_slug'] ?? ''), 4);

$originalPrice = ddProductBasePrice($product);
$basePrice     = productFinalPrice($product);
$comparePrice  = productComparePrice($product);

$isFlashSaleActive = isDiscountActive($product);
$hasDiscount        = $isFlashSaleActive || ($comparePrice > 0 && $comparePrice > $basePrice);
$comparePriceToShow = $isFlashSaleActive ? $originalPrice : $comparePrice;

$displayPrice        = ddFormatDisplay($basePrice);
$displayComparePrice = $hasDiscount ? ddFormatDisplay($comparePriceToShow) : null;

$hasColor = $hasColorColumn && trim((string)($product['color'] ?? '')) !== '';
$galleryCount = count($galleryImages);
$metaDescription = trim(preg_replace('/\s+/', ' ', (string)$product['description']));
if ($metaDescription !== '') {
    $metaDescription = mb_strlen($metaDescription) > 160 ? mb_substr($metaDescription, 0, 157) . '…' : $metaDescription;
}
$metaImage = !empty($product['thumbnail']) ? $product['thumbnail'] : $galleryImages[0];

siteHeadOpen([
    'title'       => $product['name'],
    'description' => $metaDescription !== '' ? $metaDescription : (BRAND_NAME . ' — ' . $product['name']),
    'image'       => $metaImage,
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

/* ukuran: teks polos, yang dipilih bergaris bawah */
.pdp-sizes{display:flex;flex-wrap:wrap;gap:6px 22px}
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
.pdp-soldout{font-size:12px;letter-spacing:.05em;text-transform:uppercase;color:var(--mute)}

.pdp-desc{margin-top:36px;font-size:12.5px;line-height:1.75;letter-spacing:.01em;color:var(--ink)}

/* tombol beli: hitam penuh, sudut tegas */
.pdp-buy{margin-top:32px}
.pdp-buy .btn{
  width:100%;height:46px;border-radius:0;border:1px solid var(--ink);background:var(--ink);color:#fff;
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
.pdp-related .product-card{background:#fff}
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
@media(prefers-reduced-motion:reduce){.pdp-modal,.size-btn,.pdp-buy .btn,.pdp-more summary::before,.pdp-progress span{transition:none}}
@media(prefers-reduced-transparency:reduce){.pdp-modal{-webkit-backdrop-filter:none;backdrop-filter:none;background:rgba(0,0,0,.45)}.pdp-chart{background:#fff;-webkit-backdrop-filter:none;backdrop-filter:none}.pdp-fs{background:#fff}}
</style>
<?php
siteBodyOpen(['bodyClass' => 'page-product', 'searchAction' => 'shop.php']);
?>
<main class="page-main">
  <div class="pdp">
    <div class="pdp-wrap">
      <div class="pdp-gallery" id="productImageContainer" aria-label="Product photos">
        <?php foreach ($galleryImages as $i => $imagePath): ?>
          <figure class="pdp-slide">
            <img src="<?= esc($imagePath) ?>" alt="<?= esc($product['name']) ?>" loading="<?= $i === 0 ? 'eager' : 'lazy' ?>" decoding="async" data-index="<?= $i ?>">
          </figure>
        <?php endforeach; ?>
      </div>
      <?php if ($galleryCount > 1): ?>
        <div class="pdp-progress" aria-hidden="true"><span id="imageIndicator" style="width:<?= round(100 / $galleryCount, 3) ?>%"></span></div>
      <?php endif; ?>
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

      <?php if ($hasSizeSelector && !$isSoldOut): ?>
        <div class="pdp-field">
          <span class="sr-only">Size</span>
          <div class="pdp-sizes" id="sizeBtnGroup" role="group" aria-label="Select size">
            <?php foreach ($availableSizes as $size): ?>
              <?php $isSelectedOpt = $selectedProductSizeId === (int)$size['product_size_id']; ?>
              <button type="button" class="size-btn<?= $isSelectedOpt ? ' active' : '' ?>"
                      data-id="<?= (int)$size['product_size_id'] ?>"
                      data-size="<?= esc($size['name']) ?>"
                      data-stock="<?= (int)$size['stock'] ?>"
                      aria-pressed="<?= $isSelectedOpt ? 'true' : 'false' ?>"><?= esc($size['name']) ?></button>
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
        <button type="button" class="btn btn--solid btn--block" id="addToCartBtn" <?= $isSoldOut ? 'disabled' : '' ?>>
          <span class="cart-btn__label"><?= $isSoldOut ? 'Sold out' : 'Add to cart' ?></span>
        </button>
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
  <?php if ($galleryCount > 1): ?>
  <div class="pdp-fs-ctrl">
    <button type="button" id="galleryPrevBtn" aria-label="Previous image">&#8592;</button>
    <span id="galleryIndicator">1 / <?= $galleryCount ?></span>
    <button type="button" id="galleryNextBtn" aria-label="Next image">&#8594;</button>
  </div>
  <?php endif; ?>
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

  let selectedSize          = <?= json_encode($selectedSize) ?>;
  let selectedProductSizeId = <?= json_encode($selectedProductSizeId) ?>;
  let selectedStock         = <?= json_encode($selectedProductSizeId ? (int)($availableSizes[0]['stock'] ?? 0) : 0) ?>;
  const quantity            = 1;

  const cart      = Site.cart;
  const cartItems = cart.items;
  const showToast = Site.showToast;
  const addToCartBtn = document.getElementById('addToCartBtn');

  /* ── pilihan ukuran ────── */
  document.querySelectorAll('.size-btn').forEach(btn => {
    btn.addEventListener('click', function () {
      document.querySelectorAll('.size-btn').forEach(b => { b.classList.remove('active'); b.setAttribute('aria-pressed', 'false'); });
      this.classList.add('active');
      this.setAttribute('aria-pressed', 'true');
      selectedSize = this.dataset.size;
      selectedProductSizeId = this.dataset.id;
      selectedStock = parseInt(this.dataset.stock || '0', 10) || 0;
    });
  });

  function hasSelection() { return !hasSizeSelector || Boolean(selectedProductSizeId); }

  /* ── tambah ke keranjang ────── */
  const addLabelEl      = addToCartBtn ? addToCartBtn.querySelector('.cart-btn__label') : null;
  const addLabelDefault = addLabelEl ? addLabelEl.textContent : '';
  let addLabelTimer = null;

  function doAddToCart() {
    if (!hasSelection()) { showToast('Please select an available option first.'); return; }
    if (selectedStock <= 0) { showToast('Stok untuk ukuran ini habis.'); return; }

    const itemName    = prodName + (selectedSize ? ` - ${selectedSize}` : '');
    const existingIdx = cartItems.findIndex(i => i.id === productId && i.name === itemName);

    // validasi stok SEBELUM apa pun dikirim, supaya cart lokal & server tidak beda
    if (existingIdx > -1 && cartItems[existingIdx].qty + quantity > selectedStock) {
      showToast('Stok tersisa hanya ' + selectedStock);
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

  /* ── penunjuk foto (galeri geser di mobile) ────── */
  const imageContainer = document.getElementById('productImageContainer');
  const imageIndicator = document.getElementById('imageIndicator');
  const totalImages    = <?= $galleryCount ?>;
  if (imageContainer && imageIndicator) {
    imageContainer.addEventListener('scroll', () => {
      const current = Math.min(totalImages, Math.max(1, Math.round(imageContainer.scrollLeft / imageContainer.clientWidth) + 1));
      imageIndicator.style.left = ((current - 1) / totalImages * 100) + '%';
    }, { passive: true });
  }

  /* ── galeri layar penuh ────── */
  const galleryImagesSrc = <?= json_encode(array_values($galleryImages)) ?>;
  const fsGallery = document.getElementById('fullscreenGallery');
  const fsImg     = document.getElementById('fullscreenGalleryImg');
  const fsText    = document.getElementById('galleryIndicator');
  let fsIndex = 0;

  function updateFs() {
    fsImg.src = galleryImagesSrc[fsIndex];
    if (fsText) fsText.textContent = (fsIndex + 1) + ' / ' + galleryImagesSrc.length;
  }
  function openFs(index) {
    fsIndex = index;
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

  document.querySelectorAll('#productImageContainer img').forEach((img, idx) => img.addEventListener('click', () => openFs(idx)));
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
