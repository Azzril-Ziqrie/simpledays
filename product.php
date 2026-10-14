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

// Sort natural (1, 2, 10) di level PHP, supaya konsisten sebelum dikirim ke frontend.
usort($productSizes, function ($a, $b) {
    $nameCmp = strnatcasecmp((string)$a['name'], (string)$b['name']);
    if ($nameCmp !== 0) return $nameCmp;
    return ((int)($a['sort_order'] ?? 0)) <=> ((int)($b['sort_order'] ?? 0));
});

$availableSizes = array_values(array_filter($productSizes, fn($s) => (int)$s['is_active']===1 && (int)$s['stock']>0));
$isSoldOut = count($availableSizes) === 0;
$selectedSize = $isSoldOut ? '' : $availableSizes[0]['name'];
$selectedProductSizeId = $isSoldOut ? null : (int)$availableSizes[0]['product_size_id'];

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
.pdp{display:grid;grid-template-columns:minmax(0,7fr) minmax(0,4fr);gap:clamp(32px,5vw,80px);max-width:1440px;margin:0 auto;padding:24px var(--gutter) clamp(56px,8vw,112px);align-items:start}

/* galeri: foto bertumpuk di desktop */
.pdp-gallery{display:grid;gap:4px;position:relative}
.pdp-slide{background:var(--tile);margin:0}
.pdp-slide img{width:100%;height:auto;mix-blend-mode:multiply;cursor:zoom-in}
.pdp-counter{display:none}

/* info: menempel di bawah navbar */
.pdp-info{position:sticky;top:calc(var(--nav-h) + 24px);padding-top:8px;max-width:440px}
.pdp-crumb{font-size:11px;color:var(--mute);margin-bottom:14px}
.pdp-crumb a:hover{color:var(--ink)}
.pdp-title{font-size:clamp(18px,1.8vw,22px);font-weight:500;letter-spacing:-.02em;line-height:1.25;margin-bottom:10px}
.pdp-price{display:flex;align-items:baseline;gap:10px;font-size:14px;font-weight:500}
.pdp-price .was{color:var(--mute);font-weight:400;text-decoration:line-through}
.pdp-note{margin-top:6px;font-size:11px;color:var(--mute)}
.pdp-note a{text-decoration:underline;text-underline-offset:3px}

.pdp-field{margin-top:28px}
.pdp-label{display:block;margin-bottom:10px;font-size:11px;font-weight:500;color:var(--ink-2)}
.pdp-label b{color:var(--ink);font-weight:500}
.pdp-sizes{display:flex;flex-wrap:wrap;gap:6px}
.size-btn{min-width:48px;height:44px;padding:0 14px;border:1px solid #ddd;background:#fff;color:var(--ink);font-size:12px;font-weight:500;display:flex;align-items:center;justify-content:center;transition:border-color .15s,background-color .15s,color .15s}
.size-btn:hover{border-color:var(--ink)}
.size-btn.active{background:var(--ink);border-color:var(--ink);color:#fff}
.pdp-soldout{display:inline-block;padding:4px 10px;border:1px solid var(--line);color:var(--mute);font-size:11px;font-weight:500}
.pdp-buy{margin-top:28px}
.pdp-buy .btn:disabled{opacity:1;background:var(--tile);border-color:var(--tile);color:var(--mute)}

/* detail: <details> polos */
.pdp-details{margin-top:36px;border-top:1px solid var(--line)}
.pdp-details details{border-bottom:1px solid var(--line)}
.pdp-details summary{list-style:none;display:flex;justify-content:space-between;align-items:center;padding:16px 0;font-size:12px;font-weight:500;cursor:pointer}
.pdp-details summary::-webkit-details-marker{display:none}
.pdp-details summary::after{content:'+';font-size:16px;font-weight:400;line-height:1}
.pdp-details details[open] summary::after{content:'−'}
.pdp-body{padding-bottom:18px;font-size:12px;line-height:1.75;color:var(--ink-2)}
.pdp-body p+p{margin-top:10px}
.pdp-body strong{color:var(--ink);font-weight:500}
.pdp-body a,.pdp-linkbtn{text-decoration:underline;text-underline-offset:3px;color:var(--ink)}
.pdp-linkbtn{font-size:12px}

/* modal size chart & galeri layar penuh */
.pdp-modal{position:fixed;inset:0;z-index:150;display:flex;align-items:center;justify-content:center;padding:20px;background:rgba(0,0,0,.5);opacity:0;visibility:hidden;transition:opacity .2s,visibility .2s}
.pdp-modal.active{opacity:1;visibility:visible}
.pdp-chart{position:relative;max-width:min(92vw,720px);max-height:90vh;background:#fff;padding:20px}
.pdp-chart img{max-width:100%;max-height:78vh;margin:0 auto;object-fit:contain}
.pdp-close{position:absolute;top:6px;right:6px;width:44px;height:44px;font-size:24px;font-weight:300;line-height:1;color:var(--ink);z-index:2}
.pdp-fs{background:#fff;padding:0}
.pdp-fs img{max-width:92%;max-height:86vh;object-fit:contain;mix-blend-mode:multiply}
.pdp-fs-ctrl{position:absolute;left:50%;bottom:24px;transform:translateX(-50%);display:flex;align-items:center;gap:6px;font-size:11px;font-weight:500}
.pdp-fs-ctrl button{width:44px;height:44px;font-size:16px}
.pdp-fs-ctrl span{min-width:44px;text-align:center;color:var(--ink-2)}

/* mobile: galeri geser (scroll-snap) */
@media(max-width:900px){
  .pdp{grid-template-columns:1fr;gap:0;padding:0 0 64px}
  .pdp-gallery{display:flex;overflow-x:auto;scroll-snap-type:x mandatory;scrollbar-width:none;gap:0;-webkit-overflow-scrolling:touch}
  .pdp-gallery::-webkit-scrollbar{display:none}
  .pdp-slide{flex:0 0 100%;scroll-snap-align:start}
  .pdp-wrap{position:relative}
  .pdp-counter{display:block;position:absolute;right:var(--gutter);bottom:12px;padding:3px 9px;border-radius:999px;font-size:10px;font-weight:500;background:rgba(255,255,255,.72);-webkit-backdrop-filter:blur(10px);backdrop-filter:blur(10px);pointer-events:none}
  .pdp-info{position:static;max-width:none;padding:24px var(--gutter) 0}
}
@media(min-width:901px){.pdp-wrap{display:contents}}
@media(prefers-reduced-motion:reduce){.pdp-modal{transition:none}}
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
        <div class="pdp-counter" id="imageIndicator">1 / <?= $galleryCount ?></div>
      <?php endif; ?>
    </div>

    <div class="pdp-info">
      <nav class="pdp-crumb" aria-label="Breadcrumb">
        <a href="shop.php">Shop</a><?php if ($categorySlug !== ''): ?> / <a href="shop.php?category=<?= urlencode((string)$product['category_slug']) ?>"><?= esc(sentenceCase((string)$categoryName)) ?></a><?php endif; ?>
      </nav>
      <h1 class="pdp-title"><?= esc($product['name']) ?></h1>

      <div class="pdp-price">
        <?php if ($hasDiscount): ?><span class="was"><?= esc($displayComparePrice) ?></span><?php endif; ?>
        <span><?= esc($displayPrice) ?></span>
      </div>
      <p class="pdp-note"><a href="shipping.php">Shipping</a> calculated at checkout.</p>

      <?php if ($hasColor): ?>
        <div class="pdp-field"><span class="pdp-label">Color: <b><?= esc($product['color']) ?></b></span></div>
      <?php endif; ?>

      <?php if ($hasSizeSelector && !$isSoldOut): ?>
        <div class="pdp-field">
          <span class="pdp-label">Size</span>
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

      <div class="pdp-buy">
        <button type="button" class="btn btn--solid btn--block" id="addToCartBtn" <?= $isSoldOut ? 'disabled' : '' ?>>
          <span class="cart-btn__label"><?= $isSoldOut ? 'Sold out' : 'Add to cart' ?></span>
        </button>
      </div>

      <div class="pdp-details">
        <details open>
          <summary>Details</summary>
          <div class="pdp-body">
            <p><?= nl2br(esc($product['description'])) ?></p>
            <?php if (!empty($product['composition']) || !empty($product['care'])): ?>
              <p><strong>Materials</strong><br><?= esc($product['composition']) ?></p>
              <p><strong>Care</strong><br><?= esc($product['care']) ?></p>
            <?php endif; ?>
          </div>
        </details>

        <?php if (!empty($product['size_chart_image'])): ?>
        <details>
          <summary>Size guide</summary>
          <div class="pdp-body">
            <button type="button" class="pdp-linkbtn" id="btn-size-chart">View size chart</button>
          </div>
        </details>
        <?php endif; ?>

        <details>
          <summary>Shipping &amp; returns</summary>
          <div class="pdp-body">
            <p>Orders will be prepared for shipment within 1–2 working days after payment confirmation, unless otherwise stated. All sales are final, unless the item received is defective or incorrect.</p>
            <p><a href="shipping.php">Read full policy</a></p>
          </div>
        </details>
      </div>
    </div>
  </div>
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
      imageIndicator.textContent = current + ' / ' + totalImages;
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
