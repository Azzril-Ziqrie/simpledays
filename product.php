<?php
session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/db_schema.php';
require_once __DIR__ . '/includes/currency_helper.php';
require_once __DIR__ . '/includes/pricing.php';

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
if ($slug === '') { http_response_code(404); exit('Product not found.'); }

// FIX: $category dan $search sebelumnya tidak pernah didefinisikan di file ini,
// padahal dipakai di form search & currency dropdown menu mobile di bawah.
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$search   = isset($_GET['search']) ? trim($_GET['search']) : '';

/* ── brand constants ────── */
$brandName = 'DEEPDIMES';
$brandLogo = 'assets/images/1212.png';

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

/* ── categories ──────────────────────────────────────── */
$categories = [];
try {
    if (tableExists($pdo, 'categories')) {
        $catStmt = $pdo->query("SELECT name, slug FROM categories ORDER BY id ASC");
        $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {}

$originalPrice = ddProductBasePrice($product);
$basePrice     = productFinalPrice($product);
$comparePrice  = productComparePrice($product);

$isFlashSaleActive = isDiscountActive($product);
$hasDiscount        = $isFlashSaleActive || ($comparePrice > 0 && $comparePrice > $basePrice);
$comparePriceToShow = $isFlashSaleActive ? $originalPrice : $comparePrice;

$displayPrice        = ddFormatDisplay($basePrice);
$displayComparePrice = $hasDiscount ? ddFormatDisplay($comparePriceToShow) : null;

$hasColor = $hasColorColumn && trim((string)($product['color'] ?? '')) !== '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="assets/images/link.png">
  <title><?= htmlspecialchars($product['name']) ?> — <?= htmlspecialchars($brandName) ?></title>
  <style>
@import url('https://fonts.googleapis.com/css2?family=Helvetica+Neue:wght@400;500;700;900&display=swap');
/* ── reset ──────────────────────────── */
*{margin:0;padding:0;box-sizing:border-box}
html{scroll-behavior:smooth}
body{background:#fff;color:#111;font-family:'Helvetica Neue', Helvetica, Arial, sans-serif;text-transform:uppercase;animation:pageFadeIn .4s ease forwards;letter-spacing:.02em}
body.lock-scroll{overflow:hidden}
a{color:inherit;text-decoration:none}
img{display:block;width:100%}
button,input{font:inherit}
button{cursor:pointer}

/* ── NAVBAR ──────────────────────────────────────────── */
.navbar{width:100%;height:70px;background:transparent !important;display:flex;align-items:center;justify-content:space-between;padding:0 30px;position:fixed;top:0;left:0;z-index:60;border-bottom:none; color: #000; pointer-events: none;}
.nav-left, .nav-center, .nav-right{pointer-events: auto;}
.nav-left-label{font-size:11px;letter-spacing:.1em; font-weight: 600; color: #000;}
.brand-mark img { height: 70px; width: auto; display: block; border-radius: 50%; }
.nav-center{position:absolute;left:50%;transform:translateX(-50%);display:flex;align-items:center}
.nav-right{display:flex;align-items:center;gap:25px;margin-left:auto}
.brand-mark{font-size:24px;font-weight:700;letter-spacing:.05em;line-height:1; color:#000;}
.menu-icon-wrap{display:flex;align-items:center;gap:8px;cursor:pointer;background:none;border:none}
.menu-icon-wrap span{display:none}
.menu-icon-wrap::before{content:'MENU';font-size:11px;letter-spacing:.1em;color:#000; font-weight:600;}
.nav-btn{background:none;border:none;font-size:11px;letter-spacing:.1em;color:#000;cursor:pointer;text-transform:uppercase; font-weight: 600;}
.nav-count{font-size:11px;letter-spacing:.08em}

/* ── SEARCH ──────────────────────────────────────────── */
.search-form{display:flex;align-items:center;gap:4px}
.search-form input{background:transparent;border:none;outline:none;color:#000;width:80px;font-size:11px;text-transform:uppercase;letter-spacing:.08em; font-weight: 500;}
.search-form input::placeholder{color:rgba(0,0,0,0.7)}
.search-form button{background:transparent;border:none;color:#000;font-size:14px;line-height:1;padding:0}


.overlay{position:fixed;inset:0;background:rgba(0,0,0,.3);opacity:0;visibility:hidden;transition:opacity .2s,visibility .2s;z-index:70}
.overlay.active{opacity:1;visibility:visible}
.menu-panel{position:fixed;top:0;left:0;width:320px;height:100vh;background:#fff;color:#111;transform:translateX(-100%);transition:transform .25s ease;z-index:80;display:flex;flex-direction:column}
.menu-panel.active{transform:translateX(0)}
.menu-head{height:70px;display:flex;align-items:center;justify-content:flex-end;padding:0 30px;flex-shrink:0}
.menu-close{background:none;border:none;font-size:28px;color:#111;line-height:1;font-weight:300;cursor:pointer}
.menu-body{display:flex;flex-direction:column;flex:1;padding:0 30px 40px;overflow-y:auto}

.menu-search-form{display:flex;align-items:center;border-bottom:1px solid #111;padding-bottom:10px;margin-bottom:35px;margin-top:10px}
.menu-search-form input{width:100%;border:none;outline:none;font-size:12px;text-transform:uppercase;letter-spacing:.08em;background:transparent;color:#111; font-weight: 500;}
.menu-search-form input::placeholder{color:#999}
.menu-search-form button{background:transparent;border:none;font-size:18px;color:#111;cursor:pointer;padding:0 0 0 10px;line-height:1}

.menu-nav-primary{display:flex;flex-direction:column;gap:22px;margin-bottom:40px}
.menu-nav-primary a{font-size:11px;letter-spacing:.1em;color:#111; font-weight: 600; transition:opacity .15s}
.menu-nav-primary a:hover{opacity:.5}

/* Dropdown */
.dropdown-toggle { background: none; border: none; font-size: 11px; letter-spacing: .1em; color: #111; font-weight: 600; text-transform: uppercase; cursor: pointer; display: flex; align-items: center; justify-content: space-between; width: 100%; text-align: left; font-family: inherit; padding: 0; transition: opacity .15s; }
.dropdown-toggle:hover { opacity: .5; }
.dropdown-toggle::after { content: '+'; font-size: 14px; font-weight: 400; transition: transform 0.25s ease; }
.dropdown.open .dropdown-toggle::after { transform: rotate(45deg); }
.dropdown-menu { display: none; flex-direction: column; gap: 16px; padding-left: 14px; margin-top: 18px; border-left: 1px solid #eee; margin-left: 4px; }
.dropdown.open .dropdown-menu { display: flex; animation: pageFadeIn .3s ease forwards; }
#openAccountBtnMobile::after { content: none !important; }

/* ── CART SIDEBAR ───────────────────── */
.cart-panel{position:fixed;top:0;right:0;width:380px;max-width:100%;height:100vh;background:#fff;transform:translateX(100%);transition:transform .25s ease;z-index:80;display:flex;flex-direction:column}
.cart-panel.active{transform:translateX(0)}
.cart-head{height:70px;display:flex;align-items:center;justify-content:space-between;padding:0 30px;flex-shrink:0;border-bottom:1px solid #eee}
.cart-title{font-size:11px;letter-spacing:.1em; font-weight: 600;}
.cart-body{flex:1;overflow-y:auto;padding:25px 30px}
.cart-empty{display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;gap:12px;color:#aaa;font-size:11px;letter-spacing:.08em; font-weight: 500;}
.cart-item{display:grid;grid-template-columns:70px 1fr auto;gap:18px;align-items:center;padding:18px 0;border-bottom:1px solid #eee}
.cart-item:last-child{border-bottom:none}
.cart-item-img{width:70px;height:90px;object-fit:cover;background:#fff}
.cart-item-name{font-size:11px;letter-spacing:.05em; font-weight: 600; margin-bottom:6px;line-height:1.4}
.cart-item-price{font-size:11px;color:#777;letter-spacing:.04em; font-weight: 500;}
.cart-item-qty{display:flex;align-items:center;gap:12px;margin-top:10px}
.cart-item-qty button{width:22px;height:22px;border:1px solid #ddd;background:transparent;font-size:13px;display:flex;align-items:center;justify-content:center}
.cart-item-qty span{font-size:11px;min-width:16px;text-align:center; font-weight: 600;}
.cart-item-remove{background:transparent;border:none;color:#aaa;font-size:18px;cursor:pointer;padding:2px;align-self:flex-start; line-height: 1;}
.cart-item-remove:hover{color:#111}
.cart-foot{padding:25px 30px;border-top:1px solid #eee;flex-shrink:0}
.cart-total{display:flex;justify-content:space-between;align-items:baseline;font-size:11px; font-weight: 600; margin-bottom:20px;letter-spacing:.08em}
.cart-total strong{font-size:14px;letter-spacing:.05em; font-weight: 700;}
.cart-checkout-btn{display:block;width:100%;min-height:48px;background:#111;color:#fff;border:none;font-size:11px; font-weight: 600; letter-spacing:.1em;cursor:pointer}
.cart-checkout-btn:hover{background:#000}
.cart-checkout-btn:disabled{background:#ccc;cursor:not-allowed}

/* ── ACCOUNT MODAL ──────────────────── */
.account-popup,.info-popup{position:fixed;inset:0;z-index:100;display:flex;align-items:center;justify-content:center;padding:20px;pointer-events:none}
.account-popup.active,.info-popup.active{pointer-events:auto}
.account-popup__backdrop,.info-popup__backdrop{position:absolute;inset:0;background:rgba(0,0,0,.4);opacity:0;transition:opacity .2s}
.account-popup.active .account-popup__backdrop,.info-popup.active .info-popup__backdrop{opacity:1}
.account-popup__dialog,.info-popup__dialog{position:relative;width:100%;max-width:400px;background:#fff;padding:45px 40px;opacity:0;transform:translateY(15px);transition:opacity .2s,transform .2s}
.info-popup__dialog { max-width: 480px; }
.account-popup.active .account-popup__dialog,.info-popup.active .info-popup__dialog{opacity:1;transform:translateY(0)}
.account-popup__eyebrow{margin-bottom:8px;font-size:10px;letter-spacing:.15em; font-weight: 600; color:#888;text-align:center}
.account-popup__title,.info-popup__dialog h2{margin:0 0 30px;font-size:16px;font-weight:700;letter-spacing:.1em;text-align:center}
.account-tabs{display:flex;border-bottom:1px solid #eee;margin-bottom:25px}
.account-tab{flex:1;padding:12px;font-size:11px; font-weight: 600; letter-spacing:.1em;background:transparent;border:none;cursor:pointer;border-bottom:2px solid transparent;transition:border-color .15s,color .15s;color:#999}
.account-tab.active{border-bottom-color:#111;color:#111}
.account-form{display:flex;flex-direction:column;gap:12px}
.account-form input{width:100%;min-height:44px;padding:0 14px;border:1px solid #ddd;background:#fff;font-size:12px;letter-spacing:.04em;outline:none;transition:border-color .15s;text-transform:none}
.account-form input:focus{border-color:#111}
.account-form input::placeholder{text-transform:uppercase;letter-spacing:.08em;font-size:11px;color:#aaa; font-weight: 500;}
.account-submit{min-height:48px;background:#111;color:#fff;border:none;font-size:11px; font-weight: 600; letter-spacing:.1em;cursor:pointer;margin-top:8px}
.account-submit:hover{background:#000}
.account-msg{font-size:11px;text-align:center;padding:10px;text-transform:none;letter-spacing:.02em; font-weight: 500;}
.account-msg.success{background:#e8f5e9;color:#2e7d32}
.account-msg.error{background:#ffebee;color:#c62828}
.popup-close{position:absolute;top:15px;right:15px;width:35px;height:35px;border:0;background:transparent;color:#111;font-size:24px;font-weight:300;line-height:1}

.info-popup__content{overflow-y:auto;max-height:calc(85vh - 130px);padding-right:12px;color:#444;text-transform:none}
.info-section{margin-bottom:25px}
.info-section h3{font-size:11px; font-weight: 700; letter-spacing:.1em;text-transform:uppercase;color:#111;margin-bottom:10px}
.info-section p{font-size:13px;line-height:1.8;text-transform:none;letter-spacing:.02em; color: #555;}

/* ── FOOTER ──────────────────────────────────────────── */
.footer{padding:60px 40px 40px;border-top:1px solid #eee; text-align: center;}
.footer-shopall{padding:0 0 30px;text-align:center}
.footer-shopall a{font-size:12px;font-weight:700;letter-spacing:.1em;color:#111;}
.footer-shopall a:hover{opacity:.5}
.footer-social{display:flex;justify-content:center;gap:20px;padding:30px 0 20px;border-top:1px solid #eee}
.footer-social a{width:36px;height:36px;border:none;display:flex;align-items:center;justify-content:center;font-size:18px;color:#111;transition:opacity .15s}
.footer-social a:hover{opacity:.5;}
.footer-links-row{display:flex;justify-content:center;gap:30px;padding:10px 0 30px;flex-wrap:wrap;font-size:11px; font-weight: 500; letter-spacing:.05em}
.footer-links-row a{color:#555; text-transform: capitalize;}
.footer-links-row a:hover{color:#111}
.footer-newsletter{padding:10px 0 40px;max-width:400px;margin:0 auto;text-align:center}
.footer-newsletter input{width:100%;border:none;border-bottom:1px solid #111;outline:none;padding:10px 0;font-size:12px; font-weight: 500; letter-spacing:.05em;text-align:left;background:transparent;color:#111}
.footer-newsletter input::placeholder{color:#999;text-transform:none}
.footer-newsletter-hint{font-size:11px;color:#888;letter-spacing:.02em;margin-top:12px; text-transform:none; text-align:left;}
.footer-bottom{display:flex;justify-content:center;align-items:center;gap:15px;flex-wrap:wrap; margin-top: 20px;}
.footer-copy{font-size:11px; font-weight: 500; color:#888;letter-spacing:.02em;text-transform:none;text-align:center;}
.footer-secondary{display:none}


.toast-container{position:fixed;bottom:30px;right:30px;z-index:200;display:flex;flex-direction:column;gap:8px;pointer-events:none}
.toast{background:#111;color:#fff;padding:12px 20px;font-size:11px; font-weight: 600; letter-spacing:.08em;opacity:0;transform:translateY(10px);transition:opacity .2s,transform .2s;pointer-events:none;max-width:300px}
.toast.show{opacity:1;transform:translateY(0)}

/* ── SIZE CHART MODAL ────────────────────────────────── */
.size-chart-modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); z-index: 999; display: flex; align-items: center; justify-content: center; opacity: 0; visibility: hidden; transition: opacity 0.3s ease; }
.size-chart-modal.active { opacity: 1; visibility: visible; }
.size-chart-content { position: relative; max-width: 90%; max-height: 90vh; background: #fff; padding: 20px; border-radius: 8px; text-align: center; }
.size-chart-content img { max-width: 100%; max-height: 80vh; object-fit: contain; }
.close-size-chart { position: absolute; top: -15px; right: -15px; background: #111; color: #fff; border: none; border-radius: 50%; width: 30px; height: 30px; cursor: pointer; font-size: 16px; font-weight: bold; display: flex; align-items: center; justify-content: center;}

/* ════════════════════════════════════════════════════
   PRODUCT SHELL - NEW TWO COLUMN LAYOUT
═════════════════════════════════════════════════════ */
.product-layout {
    display: grid;
    grid-template-columns: 1fr 1fr;
    width: 100%;
    min-height: 100vh;
    align-items: stretch;
}

/* LEFT: IMAGE COLUMN */
.product-media-col {
    background: #f7f7f7;
    position: relative;
    border-right: 1px solid #eee;
}
.product-image-container {
    position: sticky;
    top: 0;
    height: 100vh;
    height: 100svh;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    scrollbar-width: none;
    -ms-overflow-style: none;
}
.product-image-container::-webkit-scrollbar { display: none; }
.product-image-container img {
    width: 100%;
    height: auto;
    object-fit: contain;
    display: block;
    cursor: zoom-in; /* Indicate image is clickable */
}

/* RIGHT: INFO COLUMN */
.product-info-col {
    padding: 110px 60px 60px; /* ruang untuk navbar fixed (Account/Cart ada di kolom ini) */
    display: flex;
    flex-direction: column;
    background: #fff;
}

.product-title {
    font-size: 14px;
    font-weight: 700;
    letter-spacing: 0.05em;
    margin-bottom: 15px;
    color: #111;
}

.product-price-wrapper {
    font-size: 11px;
    font-weight: 500;
    margin-bottom: 5px;
    color: #111;
}
.price-compare {
    text-decoration: line-through;
    opacity: 0.5;
    margin-right: 8px;
}

.shipping-calc-text {
    font-size: 11px;
    color: #888;
    text-transform: none;
    letter-spacing: 0.02em;
    margin-bottom: 30px;
}
.shipping-calc-text a {
    text-decoration: underline;
    text-underline-offset: 2px;
}

/* VARIANTS & SIZES */
.variant-section {
    margin-bottom: 25px;
}
.variant-label {
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.05em;
    margin-bottom: 12px;
    display: block;
}
.size-btn-group {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.size-btn {
    background: #fff;
    border: 1px solid #ddd;
    font-family: inherit;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    cursor: pointer;
    min-width: 44px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 10px;
    color: #111;
}
.size-btn:hover:not(:disabled) {
    border-color: #111;
}
.size-btn.active {
    background: #111; /* Changed to Black */
    border-color: #111;
    color: #fff; /* Changed to White */
}
.size-btn:disabled {
    color: #ccc;
    background: #f9f9f9;
    cursor: not-allowed;
    text-decoration: line-through;
}

/* ADD TO CART BUTTON */
.btn-add-cart {
    background: #111; /* Changed to Black */
    color: #fff; /* Changed to White */
    border: none;
    width: 100%;
    height: 50px;
    font-family: inherit;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    cursor: pointer;
    transition: opacity 0.2s;
    margin-bottom: 40px;
}
.btn-add-cart:hover:not(:disabled) {
    opacity: 0.8;
}
.btn-add-cart:disabled {
    background: #eee;
    color: #aaa;
    cursor: not-allowed;
}

/* ACCORDIONS (Details, Size Guide, etc) */
.accordion-group {
    border-top: 1px solid #eee;
}
.accordion-item {
    border-bottom: 1px solid #eee;
}
.accordion-header {
    width: 100%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 0;
    background: none;
    border: none;
    font-family: inherit;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.05em;
    color: #111;
    cursor: pointer;
    text-transform: uppercase;
}
.accordion-header .icon {
    font-size: 12px;
    transition: transform 0.3s;
}
.accordion-item.open .accordion-header .icon {
    transform: rotate(180deg);
}
.accordion-content {
    display: none;
    padding-bottom: 20px;
    font-size: 12px;
    line-height: 1.8;
    color: #444;
    text-transform: none;
    letter-spacing: 0.02em;
}
.accordion-content p {
    margin-bottom: 12px;
}
.accordion-item.open .accordion-content {
    display: block;
    animation: pageFadeIn 0.3s ease forwards;
}
.btn-text-link {
    background: none;
    border: none;
    color: #111;
    text-decoration: underline;
    text-underline-offset: 3px;
    cursor: pointer;
    font-family: inherit;
    font-size: inherit;
    padding: 0;
}

.image-indicator {
    display: none;
}

/* ── RESPONSIVE ──────────────────────────────────────── */
@media(max-width:900px){
    .product-layout {
        grid-template-columns: 1fr;
        height: auto;
    }
    .product-media-col {
        border-right: none;
        border-bottom: 1px solid #eee;
        position: relative;
    }
    .product-image-container {
        position: relative;
        top: 0;
        height: auto;
        flex-direction: row;
        overflow-x: auto;
        scroll-snap-type: x mandatory;
    }
    .product-image-container img {
        width: 100%;
        flex: 0 0 100%;
        scroll-snap-align: start;
    }
    .product-info-col {
        padding: 40px 20px 60px;
    }
    
    /* MOBILE IMAGE INDICATOR STYLES */
    .image-indicator {
        display: flex;
        position: absolute;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%);
        background: rgba(0, 0, 0, 0.7);
        color: #fff;
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.1em;
        z-index: 10;
        pointer-events: none;
    }
}

/* ── FULLSCREEN GALLERY (NEW) ────────────────────────── */
.fullscreen-gallery {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: #fff;
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s ease;
}
.fullscreen-gallery.active {
    opacity: 1;
    visibility: visible;
}
.fullscreen-gallery-close {
    position: absolute;
    top: 20px;
    right: 30px;
    background: none;
    border: none;
    font-size: 30px;
    color: #111;
    cursor: pointer;
    z-index: 1001;
    line-height: 1;
    font-weight: 300;
}
.fullscreen-gallery-img {
    max-width: 90%;
    max-height: 90vh;
    object-fit: contain;
}
.fullscreen-gallery-controls {
    display: flex;
    align-items: center;
    gap: 15px; /* Spacing between arrows and indicator */
    position: absolute;
    bottom: 30px;
    left: 50%;
    transform: translateX(-50%);
    
    /* Reusing your mobile indicator styles as requested */
    background: rgba(0, 0, 0, 0.7);
    color: #fff;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.1em;
    z-index: 1001;
}
.fullscreen-gallery-controls button {
    background: none;
    border: none;
    color: #fff;
    font-size: 16px;
    cursor: pointer;
    line-height: 1;
    display: flex;
    align-items: center;
    padding: 0 4px;
}
.fullscreen-gallery-controls button:hover {
    opacity: 0.7;
}
.fullscreen-gallery-controls span {
    min-width: 45px;
    text-align: center;
}

/* ── MEDIA QUERIES FOR ACCOUNT BUTTON ── */
@media(min-width:601px){
  #openAccountBtnMobile{display:none}
}
@media(max-width:600px){
  .search-form, .nav-btn[id*='openAccountBtn'] { display: none !important; }
  #openAccountBtnMobile{display:block; text-align:left; width:100%; box-sizing:border-box;}
}

/* ── ANIMATIONS ──────────────────────────────────────── */
@keyframes pageFadeIn{from{opacity:0}to{opacity:1}}
  </style>
  <script><?= ddDisplayJs() ?></script>
  <script src="assets/js/mini-cart.js"></script>
</head>
<body>
  <?php if (file_exists(__DIR__ . '/loader.php')) include_once 'loader.php'; ?>
  <div class="overlay" id="overlay"></div>
  <div class="toast-container" id="toastContainer" aria-live="polite"></div>

  <!-- FULLSCREEN GALLERY MODAL (NEW) -->
  <div id="fullscreenGallery" class="fullscreen-gallery" role="dialog" aria-modal="true">
      <button class="fullscreen-gallery-close" id="closeGalleryBtn" aria-label="Close Gallery">&times;</button>
      <img src="" alt="Gallery Image" id="fullscreenGalleryImg" class="fullscreen-gallery-img">
      <div class="fullscreen-gallery-controls">
          <button id="galleryPrevBtn" aria-label="Previous Image">&#8592;</button>
          <span id="galleryIndicator">1 / 1</span>
          <button id="galleryNextBtn" aria-label="Next Image">&#8594;</button>
      </div>
  </div>

  <aside class="menu-panel" id="menuPanel" aria-label="Main menu">
    <div class="menu-head">
      <button class="menu-close" id="closeMenu" aria-label="Close menu">×</button>
    </div>
    <div class="menu-body">
      <form class="menu-search-form" action="index.php" method="GET" role="search">
        <?php if ($category !== ''): ?>
          <input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>">
        <?php endif; ?>
        <input type="text" name="search" placeholder="SEARCH" aria-label="Search products">
        <button type="submit" aria-label="Search">&#8594;</button>
      </form>
      <nav class="menu-nav-primary">
        <div class="dropdown" id="shopDropdown">
          <button class="dropdown-toggle" id="shopDropdownBtn" aria-expanded="false">Shop</button>
          <div class="dropdown-menu" id="shopDropdownMenu">
            <a href="shop.php">All</a>
            <?php foreach ($categories as $cat): ?>
              <a href="index.php?category=<?= urlencode((string)$cat['slug']) ?>"><?= htmlspecialchars((string)$cat['name']) ?></a>
            <?php endforeach; ?>
          </div>
        </div>
        <a href="lookbook.php">LookBook</a>
        <a href="index.php?choose_currency=1">Currency (<?= htmlspecialchars(ddDisplayCurrency()) ?>)</a>
        <button class="nav-btn" id="openAccountBtnMobile" aria-label="Account">Account</button>

</nav>
    </div>
  </aside>

  <aside class="cart-panel" id="cartPanel" aria-label="Shopping cart">
    <div class="cart-head">
      <div class="cart-title">Cart (<span id="cartCount">0</span>)</div>
      <button class="nav-btn" id="closeCart" aria-label="Close cart" style="font-size: 24px; font-weight: 300;">×</button>
    </div>
    <div class="cart-body" id="cartBody">
      <div class="cart-empty"><span>Your cart is empty</span><a class="cart-empty-link" href="shop.php">Continue shopping</a></div>
    </div>
    <div class="cart-foot" id="cartFoot" style="display:none">
      <div class="cart-total">
        <span>Total</span>
        <strong id="cartTotal">$0.00</strong>
      </div>
      <p class="cart-note">Shipping calculated at checkout.</p>
      <button class="cart-checkout-btn" id="checkoutBtn">Proceed to Checkout</button>
    </div>
  </aside>

  <div id="accountPopup" class="account-popup" role="dialog" aria-modal="true" aria-labelledby="accountPopupTitle">
    <div class="account-popup__backdrop" id="accountBackdrop"></div>
    <div class="account-popup__dialog">
      <button type="button" class="popup-close" id="closeAccountBtn" aria-label="Close">×</button>
      <div class="account-popup__eyebrow"><?= htmlspecialchars($brandName) ?></div>
      <h2 class="account-popup__title" id="accountPopupTitle">My Account</h2>
      <div class="account-tabs">
        <button class="account-tab active" data-tab="login" aria-selected="true">Sign In</button>
        <button class="account-tab" data-tab="register" aria-selected="false">Register</button>
      </div>
      <div id="tabLogin">
        <div id="loginMsg" class="account-msg" style="display:none"></div>
        <div class="account-form">
          <input type="email" id="loginEmail" placeholder="Email" autocomplete="email">
          <input type="password" id="loginPassword" placeholder="Password" autocomplete="current-password">
          <button class="account-submit" id="loginBtn">Sign In</button>
        </div>
      </div>
      <div id="tabRegister" style="display:none">
        <div id="registerMsg" class="account-msg" style="display:none"></div>
        <div class="account-form">
          <input type="text" id="regName" placeholder="Full Name" autocomplete="name">
          <input type="email" id="regEmail" placeholder="Email" autocomplete="email">
          <input type="password" id="regPassword" placeholder="Password" autocomplete="new-password">
          <button class="account-submit" id="registerBtn">Create Account</button>
        </div>
      </div>
    </div>
  </div>

  <div id="termsPopup" class="info-popup" role="dialog" aria-modal="true" aria-label="Terms and Conditions">
    <div class="info-popup__backdrop" id="termsBackdrop"></div>
    <div class="info-popup__dialog">
      <button class="popup-close" id="closeTermsBtn" aria-label="Close">×</button>
      <h2>Terms &amp; Conditions</h2>
      <div class="info-popup__content">
        <div class="info-section"><h3>General</h3><p>This website is operated by <?= htmlspecialchars($brandName) ?>. By visiting our site and/or purchasing from us, you agree to be bound by the following Terms and Conditions.</p></div>
        <div class="info-section"><h3>Payments and Charges</h3><p>We currently accept payments via Bank Transfer and other payment methods displayed at checkout. Shipping costs are calculated based on your order total, delivery location, and selected shipping method.</p></div>
        <div class="info-section"><h3>Shipping Information</h3><p>Orders will be prepared for shipment within 1–2 working days after payment confirmation, unless otherwise stated.</p></div>
        <div class="info-section"><h3>Exchange &amp; Return Policy</h3><p>All sales are final, unless the item received is defective or incorrect. Requests must be submitted within 3 days of receiving the order.</p></div>
      </div>
    </div>
  </div>

  <div id="faqPopup" class="info-popup" role="dialog" aria-modal="true" aria-label="FAQ">
    <div class="info-popup__backdrop" id="faqBackdrop"></div>
    <div class="info-popup__dialog">
      <button class="popup-close" id="closeFaqBtn" aria-label="Close">×</button>
      <h2>FAQ</h2>
      <div class="info-popup__content">
        <div class="info-section"><h3>Do I need an account to place an order?</h3><p>Creating an account is not mandatory. Registering allows you to track orders, view order history, and receive release updates.</p></div>
        <div class="info-section"><h3>How do I place an order?</h3><p>Select your desired product, choose the available option, and proceed to checkout.</p></div>
        <div class="info-section"><h3>How can I check my order status?</h3><p>After payment verification, you will receive order and shipping updates through email.</p></div>
        <div class="info-section"><h3>Do you accept returns or exchanges?</h3><p>All sales are final unless the received item is defective or incorrect.</p></div>
      </div>
    </div>
  </div>

  <?php include 'navbar.php'; ?>

  <main class="product-layout">
    <!-- LEFT: Media -->
    <div class="product-media-col">
        <div class="product-image-container" id="productImageContainer">
            <?php foreach ($galleryImages as $i => $imagePath): ?>
                <img src="<?= htmlspecialchars($imagePath) ?>" alt="<?= htmlspecialchars($product['name']) ?>" loading="<?= $i === 0 ? 'eager' : 'lazy' ?>" data-index="<?= $i ?>">
            <?php endforeach; ?>
        </div>
        
        <?php if (count($galleryImages) > 1): ?>
        <div class="image-indicator" id="imageIndicator">1 / <?= count($galleryImages) ?></div>
        <?php endif; ?>
    </div>

    <!-- RIGHT: Info -->
    <div class="product-info-col">
        <h1 class="product-title"><?= htmlspecialchars($product['name']) ?></h1>
        
        <div class="product-price-wrapper">
            <?php if ($hasDiscount): ?><span class="price-compare"><?= htmlspecialchars($displayComparePrice) ?></span><?php endif; ?>
            <span class="price-current"><?= htmlspecialchars($displayPrice) ?></span>
        </div>
        
        <p class="shipping-calc-text"><a href="shipping.php">Shipping</a> calculated at checkout.</p>

        <?php if ($hasColor): ?>
            <div class="variant-section">
                <span class="variant-label">Color: <?= htmlspecialchars((string)$product['color']) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($hasSizeSelector && !$isSoldOut): ?>
            <div class="variant-section">
                <span class="variant-label">Size</span>
                <div class="size-btn-group" id="sizeBtnGroup" role="group" aria-label="Select size">
                    <?php foreach ($availableSizes as $size): ?>
                        <?php $isSelectedOpt = $selectedProductSizeId === (int)$size['product_size_id']; ?>
                        <button
                            type="button"
                            class="size-btn <?= $isSelectedOpt ? 'active' : '' ?>"
                            data-id="<?= (int)$size['product_size_id'] ?>"
                            data-size="<?= htmlspecialchars($size['name']) ?>"
                            data-stock="<?= (int)$size['stock'] ?>"
                        ><?= htmlspecialchars($size['name']) ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php elseif ($isSoldOut): ?>
            <div class="variant-section"><span class="variant-label">Sold Out</span></div>
        <?php endif; ?>

        <button type="button" class="btn-add-cart" id="addToCartBtn" <?= $isSoldOut ? 'disabled' : '' ?>>
            <span class="cart-btn__label"><?= $isSoldOut ? 'Sold Out' : 'Add to cart' ?></span>
        </button>

        <!-- ACCORDIONS -->
        <div class="accordion-group">
            <div class="accordion-item open"> <!-- Details open by default -->
                <button class="accordion-header">
                    Details
                    <span class="icon">▼</span>
                </button>
                <div class="accordion-content">
                    <p><?= nl2br(htmlspecialchars((string)$product['description'])) ?></p>
                    <?php if (!empty($product['composition']) || !empty($product['care'])): ?>
                        <p><strong>Materials:</strong> <?= htmlspecialchars((string)$product['composition']) ?></p>
                        <p><strong>Care:</strong> <?= htmlspecialchars((string)$product['care']) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($product['size_chart_image'])): ?>
            <div class="accordion-item">
                <button class="accordion-header">
                    Size Guide
                    <span class="icon">▼</span>
                </button>
                <div class="accordion-content">
                    <button type="button" class="btn-text-link" id="btn-size-chart">View Size Chart Image</button>
                </div>
            </div>
            <?php endif; ?>

            <div class="accordion-item">
                <button class="accordion-header">
                    Shipping & Returns
                    <span class="icon">▼</span>
                </button>
                <div class="accordion-content">
                    <p>Orders will be prepared for shipment within 1–2 working days after payment confirmation, unless otherwise stated. All sales are final, unless the item received is defective or incorrect.</p>
                    <p><a href="shipping.php" style="text-decoration: underline;">Read Full Policy</a></p>
                </div>
            </div>
        </div>

    </div>
  </main>

  <!-- MODAL SIZE CHART -->
  <?php if (!empty($product['size_chart_image'])): ?>
  <div id="modal-size-chart" class="size-chart-modal" role="dialog" aria-modal="true">
      <div class="size-chart-content">
          <button class="close-size-chart" id="close-size-chart" aria-label="Close">&times;</button>
          <img src="<?= htmlspecialchars($product['size_chart_image']) ?>" alt="Size Chart <?= htmlspecialchars($product['name']) ?>">
      </div>
  </div>
  <?php endif; ?>

  <?php include 'footer.php'; ?>

  <script>
  (function () {
    'use strict';

    const productId       = <?= (int)$product['id'] ?>;
    const hasSizeSelector = <?= $hasSizeSelector ? 'true' : 'false' ?>;
    const isSoldOut       = <?= $isSoldOut ? 'true' : 'false' ?>;


    // PHP variables for cart
    const prodName  = <?= json_encode($product['name']) ?>;
    const prodSlug  = <?= json_encode($product['slug']) ?>;
    const prodThumb = <?= json_encode($product['thumbnail']) ?>;
    const prodPrice = <?= json_encode((float)$basePrice) ?>;

    let selectedSize          = <?= json_encode($selectedSize) ?>;
    let selectedProductSizeId = <?= json_encode($selectedProductSizeId) ?>;
    let selectedStock         = <?= json_encode($selectedProductSizeId ? (int)($availableSizes[0]['stock'] ?? 0) : 0) ?>;
    const quantity            = 1;

    /* ── DOM refs ───────────────────────────────────── */
    const body         = document.body;
    const overlay      = document.getElementById('overlay');
    const menuPanel    = document.getElementById('menuPanel');
    const cartPanel    = document.getElementById('cartPanel');
    const accountPopup = document.getElementById('accountPopup');
    const termsPopup   = document.getElementById('termsPopup');
    const faqPopup     = document.getElementById('faqPopup');
    const addToCartBtn = document.getElementById('addToCartBtn');

    // Keranjang mini bersama (assets/js/mini-cart.js)
    const cart      = DDMiniCart.create({ showToast });
    const cartItems = cart.items;
    function saveCart()   { cart.save(); }
    function renderCart() { cart.render(); }

    function showToast(msg) {
      const c = document.getElementById('toastContainer');
      const t = document.createElement('div');
      t.className = 'toast'; t.textContent = msg;
      c.appendChild(t);
      requestAnimationFrame(() => requestAnimationFrame(() => t.classList.add('show')));
      setTimeout(() => { t.classList.remove('show'); setTimeout(() => t.remove(), 300); }, 2600);
    }

    function anyOpen() {
      return menuPanel.classList.contains('active') ||
             cartPanel.classList.contains('active') ||
             accountPopup.classList.contains('active') ||
             termsPopup.classList.contains('active') ||
             faqPopup.classList.contains('active');
    }
    function setOverlay(on) {
      overlay.classList.toggle('active', on);
      body.classList.toggle('lock-scroll', on);
    }
    function closeAll()  {
      [menuPanel, cartPanel, accountPopup, termsPopup, faqPopup].forEach(el => el.classList.remove('active'));
    }
    function openMenu()    { closeAll(); menuPanel.classList.add('active');    setOverlay(true); }
    function openCart()    { closeAll(); cartPanel.classList.add('active');    setOverlay(true); }
    function openAccount() { closeAll(); accountPopup.classList.add('active'); setOverlay(true); }

    overlay.addEventListener('click', () => { closeAll(); setOverlay(false); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeAll(); setOverlay(false); } });

    const menuToggle = document.getElementById('menuToggle');
    if (menuToggle) menuToggle.addEventListener('click', openMenu);
    
    document.getElementById('closeMenu').addEventListener('click', () => { menuPanel.classList.remove('active'); if(!anyOpen()) setOverlay(false); });
    
    const openCartBtn = document.getElementById('openCartBtn');
    if(openCartBtn) openCartBtn.addEventListener('click', openCart);
    
    document.getElementById('closeCart').addEventListener('click', () => { cartPanel.classList.remove('active'); if(!anyOpen()) setOverlay(false); });

    const openAccountBtn = document.getElementById('openAccountBtn');
    if (openAccountBtn) openAccountBtn.addEventListener('click', openAccount);

    const openAccountBtnMobile = document.getElementById('openAccountBtnMobile');
    if (openAccountBtnMobile) openAccountBtnMobile.addEventListener('click', e => { e.preventDefault(); openAccount(); });

    const menuOpenAccountBtn = document.getElementById('menuOpenAccountBtn');
    if (menuOpenAccountBtn) menuOpenAccountBtn.addEventListener('click', e => { e.preventDefault(); openAccount(); });

    const menuOpenCartBtn = document.getElementById('menuOpenCartBtn');
    if (menuOpenCartBtn) menuOpenCartBtn.addEventListener('click', e => { e.preventDefault(); openCart(); });

    document.getElementById('closeAccountBtn').addEventListener('click', () => { accountPopup.classList.remove('active'); if(!anyOpen()) setOverlay(false); });
    document.getElementById('accountBackdrop').addEventListener('click', () => { accountPopup.classList.remove('active'); if(!anyOpen()) setOverlay(false); });

    /* DROPDOWN TOGGLE LISTENER (semua dropdown, termasuk Currency) */
    document.querySelectorAll('.dropdown-toggle').forEach(btn => {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        this.parentElement.classList.toggle('open');
        const expanded = this.getAttribute('aria-expanded') === 'true';
        this.setAttribute('aria-expanded', !expanded);
      });
    });

    /* ACCORDION TOGGLE */
    document.querySelectorAll('.accordion-header').forEach(btn => {
      btn.addEventListener('click', function () {
        const item = this.parentElement;
        item.classList.toggle('open');
      });
    });

    document.querySelectorAll('.account-tab').forEach(tab => {
      tab.addEventListener('click', function () {
        document.querySelectorAll('.account-tab').forEach(t => { t.classList.remove('active'); t.setAttribute('aria-selected','false'); });
        this.classList.add('active'); this.setAttribute('aria-selected','true');
        const target = this.dataset.tab;
        document.getElementById('tabLogin').style.display    = target === 'login'    ? '' : 'none';
        document.getElementById('tabRegister').style.display = target === 'register' ? '' : 'none';
      });
    });

    document.getElementById('loginBtn').addEventListener('click', () => {
      const email = document.getElementById('loginEmail').value.trim();
      const pass  = document.getElementById('loginPassword').value;
      const msg   = document.getElementById('loginMsg');
      if (!email || !pass) { msg.className='account-msg error'; msg.textContent='Please fill in all fields.'; msg.style.display='block'; return; }
      msg.className='account-msg success'; msg.textContent='Redirecting...'; msg.style.display='block';
      setTimeout(() => { window.location.href = 'login.php?email=' + encodeURIComponent(email); }, 700);
    });
    document.getElementById('registerBtn').addEventListener('click', () => {
      const name  = document.getElementById('regName').value.trim();
      const email = document.getElementById('regEmail').value.trim();
      const pass  = document.getElementById('regPassword').value;
      const msg   = document.getElementById('registerMsg');
      if (!name || !email || !pass) { msg.className='account-msg error'; msg.textContent='Please fill in all fields.'; msg.style.display='block'; return; }
      msg.className='account-msg success'; msg.textContent='Redirecting...'; msg.style.display='block';
      setTimeout(() => { window.location.href = 'register.php?email=' + encodeURIComponent(email) + '&name=' + encodeURIComponent(name); }, 700);
    });

    function openModal(m) { closeAll(); m.classList.add('active'); setOverlay(true); }
    function closeModal(m){ m.classList.remove('active'); if(!anyOpen()) setOverlay(false); }

    ['openTermsLink','menuOpenTermsBtn', 'footerTermsLink'].forEach(id => {
      const el = document.getElementById(id); if (el) el.addEventListener('click', e => { e.preventDefault(); openModal(termsPopup); });
    });
    ['openFaqLink','menuOpenFaqBtn', 'footerFaqLink'].forEach(id => {
      const el = document.getElementById(id); if (el) el.addEventListener('click', e => { e.preventDefault(); openModal(faqPopup); });
    });
    document.getElementById('closeTermsBtn').addEventListener('click', () => closeModal(termsPopup));
    document.getElementById('termsBackdrop').addEventListener('click', () => closeModal(termsPopup));
    document.getElementById('closeFaqBtn').addEventListener('click',   () => closeModal(faqPopup));
    document.getElementById('faqBackdrop').addEventListener('click',   () => closeModal(faqPopup));

    /* ── size buttons ────── */
    document.querySelectorAll('.size-btn').forEach(btn => {
      btn.addEventListener('click', function () {
        if (this.disabled) return;
        document.querySelectorAll('.size-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        selectedSize = this.dataset.size;
        selectedProductSizeId = this.dataset.id;
        selectedStock = parseInt(this.dataset.stock || '0', 10) || 0;
      });
    });

    function hasSelection() { return !hasSizeSelector || Boolean(selectedProductSizeId); }

    /* ── CART LOGIC (Synced with index.php LocalStorage) ──────────────────── */
    document.getElementById('checkoutBtn').addEventListener('click', async () => { 
      const btn = document.getElementById('checkoutBtn');
      const origText = btn.textContent;
      btn.disabled = true;
      btn.textContent = 'Preparing Checkout...';

      try {
        const fd = new FormData();
        fd.append('cart_data', JSON.stringify(cartItems));

        await fetch('sync-cart.php', { method: 'POST', body: fd });
        window.location.href = 'checkout.php';
      } catch (err) {
        showToast('Failed to prepare checkout.');
        btn.disabled = false;
        btn.textContent = origText;
      }
    });

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
      saveCart();
      renderCart();

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
      openCart();
    }

    if(addToCartBtn) {
        addToCartBtn.addEventListener('click', doAddToCart);
    }
    renderCart();

    /* ── SIZE CHART MODAL LOGIC ── */
    const btnSizeChart = document.getElementById('btn-size-chart');
    const modalSizeChart = document.getElementById('modal-size-chart');
    const closeSizeChart = document.getElementById('close-size-chart');

    if (btnSizeChart && modalSizeChart) {
        btnSizeChart.addEventListener('click', (e) => {
            e.preventDefault();
            modalSizeChart.classList.add('active');
        });

        closeSizeChart.addEventListener('click', () => {
            modalSizeChart.classList.remove('active');
        });

        modalSizeChart.addEventListener('click', (e) => {
            if (e.target === modalSizeChart) {
                modalSizeChart.classList.remove('active');
            }
        });
    }

    /* ── MOBILE IMAGE SLIDER INDICATOR LOGIC ── */
    const imageContainer = document.getElementById('productImageContainer');
    const imageIndicator = document.getElementById('imageIndicator');
    
    if (imageContainer && imageIndicator) {
        const totalImages = <?= count($galleryImages) ?>;
        
        imageContainer.addEventListener('scroll', () => {
            // Hitung index gambar saat ini berdasarkan posisi scroll
            const scrollLeft = imageContainer.scrollLeft;
            const containerWidth = imageContainer.clientWidth;
            
            // Menggunakan Math.round untuk pindah indikator saat image sudah berganti > 50% layar
            let currentIndex = Math.round(scrollLeft / containerWidth) + 1;
            
            if (currentIndex > totalImages) currentIndex = totalImages;
            if (currentIndex < 1) currentIndex = 1;
            
            imageIndicator.textContent = currentIndex + ' / ' + totalImages;
        });
    }

    /* ── FULLSCREEN GALLERY LOGIC (NEW) ── */
    const galleryImagesSrc = [
        <?php foreach ($galleryImages as $imagePath): ?>
            "<?= htmlspecialchars($imagePath) ?>",
        <?php endforeach; ?>
    ];
    
    const productImageNodes = document.querySelectorAll('.product-image-container img');
    const fsGallery = document.getElementById('fullscreenGallery');
    const fsGalleryImg = document.getElementById('fullscreenGalleryImg');
    const closeGalleryBtn = document.getElementById('closeGalleryBtn');
    const galleryPrevBtn = document.getElementById('galleryPrevBtn');
    const galleryNextBtn = document.getElementById('galleryNextBtn');
    const galleryIndicatorText = document.getElementById('galleryIndicator');
    
    let currentFsIndex = 0;
    
    function updateFsGallery() {
        if (galleryImagesSrc.length === 0) return;
        fsGalleryImg.src = galleryImagesSrc[currentFsIndex];
        galleryIndicatorText.textContent = (currentFsIndex + 1) + ' / ' + galleryImagesSrc.length;
    }
    
    function openFsGallery(index) {
        currentFsIndex = index;
        updateFsGallery();
        fsGallery.classList.add('active');
        document.body.classList.add('lock-scroll');
    }
    
    function closeFsGallery() {
        fsGallery.classList.remove('active');
        if (!anyOpen()) {
            document.body.classList.remove('lock-scroll');
        }
    }
    
    function nextFsImg() {
        currentFsIndex = (currentFsIndex + 1) % galleryImagesSrc.length;
        updateFsGallery();
    }
    
    function prevFsImg() {
        currentFsIndex = (currentFsIndex - 1 + galleryImagesSrc.length) % galleryImagesSrc.length;
        updateFsGallery();
    }
    
    productImageNodes.forEach((img, idx) => {
        img.addEventListener('click', () => {
            openFsGallery(idx);
        });
    });
    
    closeGalleryBtn.addEventListener('click', closeFsGallery);
    galleryNextBtn.addEventListener('click', nextFsImg);
    galleryPrevBtn.addEventListener('click', prevFsImg);
    
    // Close on background click
    fsGallery.addEventListener('click', (e) => {
        if (e.target === fsGallery) closeFsGallery();
    });
    
    // Keyboard navigation
    document.addEventListener('keydown', (e) => {
        if (fsGallery.classList.contains('active')) {
            if (e.key === 'Escape') closeFsGallery();
            if (e.key === 'ArrowRight') nextFsImg();
            if (e.key === 'ArrowLeft') prevFsImg();
        }
    });

  })();
  </script>
</body>
</html>