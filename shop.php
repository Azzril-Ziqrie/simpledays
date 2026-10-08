<?php
session_start();

// Tangkap pilihan currency dari URL
if (isset($_GET['currency']) && in_array(strtoupper($_GET['currency']), ['IDR', 'USD', 'SGD', 'MYR'])) {
    $_SESSION['currency'] = strtoupper($_GET['currency']);
    $_SESSION['currency_chosen'] = true;
}

// Default ke IDR jika belum ada session
$currentCurrency = $_SESSION['currency'] ?? 'IDR';

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/currency_helper.php';

$search   = isset($_GET['search'])   ? trim((string) $_GET['search'])   : '';
$category = isset($_GET['category']) ? trim((string) $_GET['category']) : '';

$brandName       = 'DEEPDIMES';
$brandLogo = 'assets/images/1212.png';
$searchAction = 'shop.php'; // search tetap di halaman shop (dipakai navbar.php)
$customHeroImage = '1212ppf.JPEG';

/* ── helpers ─────────────────────────────────────────── */
function tableExists(PDO $pdo, string $table): bool
{
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
        return (bool) $stmt->fetchColumn();
    } catch (Throwable $e) { return false; }
}

function tableColumns(PDO $pdo, string $table): array
{
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
        return array_map(static fn($row) => $row['Field'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Throwable $e) { return []; }
}

/* ── schema detection ────────────────────────────────── */
$productColumns     = tableColumns($pdo, 'products');
$hasCategoriesTable = tableExists($pdo, 'categories');
$hasCategoryId      = in_array('category_id',  $productColumns, true);
$hasCategoryText    = in_array('category',      $productColumns, true);
$hasSortOrder       = in_array('sort_order',    $productColumns, true); 

$joinCategory   = $hasCategoriesTable && $hasCategoryId;
$categorySelect = $joinCategory
    ? "c.name AS category_name, c.slug AS category_slug"
    : ($hasCategoryText
        ? "p.category AS category_name, LOWER(REPLACE(p.category, ' ', '-')) AS category_slug"
        : "NULL AS category_name, NULL AS category_slug");

// Deteksi stok untuk tulisan Sold Out
$hasProductSizes = tableExists($pdo, 'product_sizes');
$hasMainStock    = in_array('stock', $productColumns, true);

$stockSelect = "1 AS is_in_stock"; // fallback
if ($hasProductSizes && $hasMainStock) {
    $stockSelect = "(p.stock > 0 OR IFNULL((SELECT SUM(stock) FROM product_sizes WHERE product_id = p.id AND is_active = 1), 0) > 0) AS is_in_stock";
} elseif ($hasProductSizes) {
    $stockSelect = "(IFNULL((SELECT SUM(stock) FROM product_sizes WHERE product_id = p.id AND is_active = 1), 0) > 0) AS is_in_stock";
} elseif ($hasMainStock) {
    $stockSelect = "(p.stock > 0) AS is_in_stock";
}

/* ── product query ───────────────────────────────────── */
// Menambahkan p.price_usd ke dalam query supaya harga USD ikut terbaca
$sql = "
    SELECT p.id, p.name, p.slug, p.price_idr, p.price_usd, p.price_sgd, p.price_myr,
            p.discount_percent, p.discount_start_at, p.discount_end_at, p.is_discount_active,
            p.thumbnail, {$categorySelect}, {$stockSelect}
     FROM products p

";
if ($joinCategory) $sql .= " LEFT JOIN categories c ON p.category_id = c.id";
$sql .= " WHERE p.is_active = 1";

$params = [];
if ($search !== '') {
    if ($joinCategory)      $sql .= " AND (p.name LIKE :search OR p.slug LIKE :search OR c.name LIKE :search OR c.slug LIKE :search)";
    elseif ($hasCategoryText) $sql .= " AND (p.name LIKE :search OR p.slug LIKE :search OR p.category LIKE :search)";
    else                    $sql .= " AND (p.name LIKE :search OR p.slug LIKE :search)";
    $params['search'] = '%' . $search . '%';
}
if ($category !== '') {
    if ($joinCategory) {
        // Kategori "new" juga memuat produk yang ditandai baru (products.is_new) di kategori lain, mis. Top.
        if (strtolower($category) === 'new' && in_array('is_new', $productColumns, true)) {
            $sql .= " AND (c.slug = :category OR p.is_new = 1)";
        } else {
            $sql .= " AND c.slug = :category";
        }
        $params['category'] = $category;
    }
    elseif ($hasCategoryText) { $sql .= " AND LOWER(REPLACE(p.category, ' ', '-')) = :category"; $params['category'] = strtolower($category); }
}

// LOGIKA SORTING:
if ($hasSortOrder) {
    $sql .= " ORDER BY p.sort_order ASC, p.id DESC";
} else {
    $sql .= " ORDER BY p.id DESC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ── categories ──────────────────────────────────────── */
$categories = [];
if ($joinCategory) {
    try { $catStmt = $pdo->query("SELECT name, slug FROM categories ORDER BY id ASC"); $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) {}
} elseif ($hasCategoryText) {
    try { $catStmt = $pdo->query("SELECT DISTINCT category AS name, LOWER(REPLACE(category, ' ', '-')) AS slug FROM products WHERE category IS NOT NULL AND category <> '' ORDER BY category ASC"); $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) {}
}

/* ── payment popup ───────────────────────────────────── */
$paymentUploaded    = isset($_GET['payment_uploaded']) && $_GET['payment_uploaded'] === '1';
$orderUploaded      = isset($_GET['order'])         ? trim((string) $_GET['order'])        : '';
$invoiceEmailStatus  = isset($_GET['invoice_email']) ? trim((string) $_GET['invoice_email']) : '';

$uploadedOrder = null;
if ($paymentUploaded && $orderUploaded !== '') {
    $popupStmt = $pdo->prepare("SELECT order_number, payment_status FROM orders WHERE order_number = :order_number LIMIT 1");
    $popupStmt->execute(['order_number' => $orderUploaded]);
    $uploadedOrder = $popupStmt->fetch(PDO::FETCH_ASSOC);
}

/* ── pricing helpers ─────────────────────────────────── */
function isDiscountCurrentlyActive(array $product): bool
{
    if ((int)($product['is_discount_active'] ?? 0) !== 1) return false;
    if ((float)($product['discount_percent'] ?? 0) <= 0)  return false;
    $now = time();
    if (!empty($product['discount_start_at'])) { $s = strtotime((string)$product['discount_start_at']); if ($s !== false && $now < $s) return false; }
    if (!empty($product['discount_end_at']))   { $e = strtotime((string)$product['discount_end_at']);   if ($e !== false && $now > $e) return false; }
    return true;
}

// Menyesuaikan getBasePrice untuk mengambil IDR / USD sesuai session (sama seperti index.php)
function getBasePrice(array $product, string $currency = 'IDR'): float {
    return ddProductBasePrice($product, $currency);
}

function getFinalPrice(array $product, string $currency = 'IDR'): float
{
    $base = getBasePrice($product, $currency);
    if (!isDiscountCurrentlyActive($product)) return $base;
    return max($base - ($base * ((float)($product['discount_percent'] ?? 0) / 100)), 0);
}

// Ganti formatRupiah -> formatCurrency supaya bisa format USD juga
function formatCurrency(float $amount, string $currency = 'IDR'): string {
    return ddFormatMoney($amount, $currency);
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="assets/images/link.png">
  <title><?= htmlspecialchars($brandName) ?> — Shop</title>
  <style>
@import url('https://fonts.googleapis.com/css2?family=Helvetica+Neue:wght@400;500;700;900&display=swap');
/* ── reset ───────────────────────────────────────────── */
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

/* ── OVERLAY + MENU ──────────────────────────────────── */
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

/* DROPDOWN MENU */
.dropdown-toggle {
  background: none; border: none; font-size: 11px; letter-spacing: .1em; color: #111; font-weight: 600; 
  text-transform: uppercase; cursor: pointer; display: flex; align-items: center; 
  justify-content: space-between; width: 100%; text-align: left; font-family: inherit;
  padding: 0; transition: opacity .15s;
}
.dropdown-toggle:hover { opacity: .5; }
.dropdown-toggle::after { content: '+'; font-size: 14px; font-weight: 400; transition: transform 0.25s ease; }
.dropdown.open .dropdown-toggle::after { transform: rotate(45deg); }
.dropdown-menu { display: none; flex-direction: column; gap: 16px; padding-left: 14px; margin-top: 18px; border-left: 1px solid #eee; margin-left: 4px; }
.dropdown.open .dropdown-menu { display: flex; animation: pageFadeIn .3s ease forwards; }
#openAccountBtnMobile::after { content: none !important; }

/* ── CATEGORY NAV (CENTERED) ─────────────────────────── */
.category-nav-wrapper {
    padding: 100px 40px 10px; /* 70px = tinggi navbar fixed, supaya nav tidak tertutup logo */
    background: #fff;
    position: static; /* tidak ikut saat di-scroll */
    width: 100%;
}
.category-nav {
    display: flex;
    justify-content: center; /* Meratakan ke tengah di layar besar */
    gap: 20px;
    overflow-x: auto;
    white-space: nowrap;
    scrollbar-width: none;
    -ms-overflow-style: none;
}
.category-nav::-webkit-scrollbar {
    display: none;
}
.category-nav a {
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0.05em;
    color: #b0b0b0;
    text-transform: uppercase;
    padding-bottom: 2px;
    transition: color 0.15s;
    text-decoration: none;
}
.category-nav a:hover {
    color: #111;
}
.category-nav a.active {
    color: #111;
    border-bottom: 2px solid #111;
}

/* ── CART SIDEBAR ────────────────────────────────────── */
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

/* ── ACCOUNT MODAL ───────────────────────────────────── */
.account-popup{position:fixed;inset:0;z-index:100;display:flex;align-items:center;justify-content:center;padding:20px;pointer-events:none}
.account-popup.active{pointer-events:auto}
.account-popup__backdrop{position:absolute;inset:0;background:rgba(0,0,0,.4);opacity:0;transition:opacity .2s}
.account-popup.active .account-popup__backdrop{opacity:1}
.account-popup__dialog{position:relative;width:100%;max-width:400px;background:#fff;padding:45px 40px;opacity:0;transform:translateY(15px);transition:opacity .2s,transform .2s}
.account-popup.active .account-popup__dialog{opacity:1;transform:translateY(0)}
.account-popup__eyebrow{margin-bottom:8px;font-size:10px;letter-spacing:.15em; font-weight: 600; color:#888;text-align:center}
.account-popup__title{margin:0 0 30px;font-size:16px;font-weight:700;letter-spacing:.1em;text-align:center}
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

/* ── PRODUCTS ────────────────────────────────────────── */
.product-section{width:100%;background:#fff; padding: 30px 40px 60px;}
.section-head{display:flex;justify-content:space-between;align-items:center;padding:14px 28px;font-size:10px;letter-spacing:.1em;border-bottom:1px solid #f0f0f0;border-top:1px solid #f0f0f0}
.product-grid{width:100%;display:grid;grid-template-columns:repeat(4,minmax(0,1fr)); row-gap: 50px; column-gap: 20px;}
.product-card{position:relative;display:block;background:#fff;border:none;opacity:0;animation:productFadeIn .4s ease forwards}
.product-card:nth-child(1){animation-delay:.03s}
.product-card:nth-child(2){animation-delay:.06s}
.product-card:nth-child(3){animation-delay:.09s}
.product-card:nth-child(4){animation-delay:.12s}
.product-card:nth-child(5){animation-delay:.15s}
.product-card:nth-child(6){animation-delay:.18s}

.discount-label{position:absolute;top:10px;left:10px;z-index:3;font-size:10px; font-weight: 700; letter-spacing:.1em;color:#fff;background:#111;padding:4px 8px}

.product-image{position:relative;background:#fff;aspect-ratio:1/1;overflow:hidden; display: flex; align-items: center; justify-content: center;}
.product-image img{width:90%;height:auto;object-fit:contain;transition:transform .35s ease}
.product-card:hover .product-image img{transform:scale(1.03)}

.product-info{padding:18px 10px 10px;background:#fff; display:flex; flex-direction:column; text-align: center; align-items: center;}
.product-card h3{font-size:11px;letter-spacing:.05em;font-weight:700;margin-bottom:6px;color:#111}
.product-price{font-size:11px;letter-spacing:.05em;font-weight:600;color:#111;margin-top:2px}
.price-original{margin-right:8px;text-decoration:line-through;color:#aaa}
.price-final{color:#111}
.product-category{display:none}

.sold-out-text{font-size:11px;color:#777;margin-top:2px;letter-spacing:.05em;font-weight:600;}
.empty-text{padding:100px 40px;font-size:12px; font-weight: 600; letter-spacing:.08em;text-align:center;color:#999}

/* ── PAYMENT POPUP ───────────────────────────────────── */
.payment-popup,.info-popup{position:fixed;inset:0;z-index:100;display:flex;align-items:center;justify-content:center;padding:20px;pointer-events:none}
.payment-popup{pointer-events:auto}
.info-popup.active{pointer-events:auto}
.payment-popup__backdrop,.info-popup__backdrop{position:absolute;inset:0;background:rgba(0,0,0,.4);opacity:0;transition:opacity .2s}
.payment-popup--open .payment-popup__backdrop,.info-popup.active .info-popup__backdrop{opacity:1}
.payment-popup__dialog,.info-popup__dialog{position:relative;width:100%;max-width:480px;max-height:85vh;background:#fff;padding:45px 40px;opacity:0;transform:translateY(15px);transition:opacity .2s,transform .2s;overflow:hidden}
.payment-popup--open .payment-popup__dialog,.info-popup.active .info-popup__dialog{opacity:1;transform:translateY(0)}
.payment-popup--closing .payment-popup__backdrop,.payment-popup--closing .payment-popup__dialog{opacity:0}
.payment-popup__eyebrow{margin-bottom:10px;font-size:10px; font-weight: 600; letter-spacing:.15em;color:#888;text-align:center}
.payment-popup__title,.info-popup__dialog h2{margin:0 0 25px;font-size:16px;font-weight:700;letter-spacing:.1em;text-align:center}
.payment-popup__text{margin:0 auto 25px;max-width:360px;font-size:12px;line-height:1.8;color:#555;text-transform:none;letter-spacing:.02em;text-align:center}
.payment-popup__meta{margin:0 auto 25px;padding:16px;border:1px solid #eee;background:#fafafa;display:grid;gap:8px;font-size:11px; font-weight: 600; letter-spacing:.05em}
.payment-popup__actions{display:flex;justify-content:center;gap:12px;flex-wrap:wrap}
.button{min-height:46px;padding:0 28px;border:1px solid #111;background:transparent;color:#111;display:inline-flex;align-items:center;justify-content:center;text-transform:uppercase; font-weight: 600; letter-spacing:.1em;font-size:11px;transition:background .15s,color .15s}
.button:hover{background:#111;color:#fff}
.button--dark{background:#111;color:#fff}
.button--dark:hover{background:#000}
.info-popup__content{overflow-y:auto;max-height:calc(85vh - 130px);padding-right:12px;color:#444;text-transform:none}
.info-section{margin-bottom:25px}
.info-section h3{font-size:11px; font-weight: 700; letter-spacing:.1em;text-transform:uppercase;color:#111;margin-bottom:10px}
.info-section p{font-size:13px;line-height:1.8;text-transform:none;letter-spacing:.02em; color: #555;}

/* ── TOAST ───────────────────────────────────────────── */
.toast-container{position:fixed;bottom:30px;right:30px;z-index:200;display:flex;flex-direction:column;gap:8px;pointer-events:none}
.toast{background:#111;color:#fff;padding:12px 20px;font-size:11px; font-weight: 600; letter-spacing:.08em;opacity:0;transform:translateY(10px);transition:opacity .2s,transform .2s;pointer-events:none;max-width:300px}
.toast.show{opacity:1;transform:translateY(0)}

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

/* ── RESPONSIVE ──────────────────────────────────────── */
@media(max-width:1100px){
  .product-grid{grid-template-columns:repeat(3,minmax(0,1fr))}
}
@media(max-width:900px){
  /* Saat layar kecil (tablet), hilangkan centered supaya item awal ga kepotong di kiri saat overflow */
  .category-nav { justify-content: flex-start; }
  .product-grid{grid-template-columns:repeat(2,minmax(0,1fr)); gap: 30px 15px;}
  .product-section{padding: 25px 20px 30px;}
}
@media(max-width:600px){
  .search-form, .nav-btn[id*='openAccountBtn'] { display: none !important; }
  .category-nav-wrapper { padding: 90px 20px 10px; }
  .category-nav a { font-size: 11px; }
  .product-info{padding:14px 10px 10px}
  .cart-body,.cart-foot,.cart-head{padding-left:20px;padding-right:20px}
  .account-popup__dialog{padding:35px 25px}
  .payment-popup__dialog,.info-popup__dialog{padding:35px 25px}
  .toast-container{right:15px;bottom:15px;left:15px}
  .toast{max-width:100%}
}

/* ── ANIMATIONS ──────────────────────────────────────── */
@keyframes pageFadeIn{from{opacity:0}to{opacity:1}}
@keyframes productFadeIn{from{opacity:0; transform:translateY(10px)}to{opacity:1; transform:translateY(0)}}
  </style>
</head>
<body>
  <?php if (file_exists(__DIR__ . '/loader.php')) include_once 'loader.php'; ?>
  <div class="overlay" id="overlay"></div>

  <div class="toast-container" id="toastContainer" aria-live="polite"></div>

  <?php if ($paymentUploaded): ?>
  <div id="paymentPopup" class="payment-popup payment-popup--open" role="dialog" aria-modal="true" aria-labelledby="paymentPopupTitle">
    <div class="payment-popup__backdrop"></div>
    <div class="payment-popup__dialog">
      <button type="button" class="popup-close" id="closePaymentPopup" aria-label="Close">×</button>
      <div class="payment-popup__eyebrow"><?= htmlspecialchars($brandName) ?></div>
      <h2 class="payment-popup__title" id="paymentPopupTitle">Invoice Sent to Gmail</h2>
      <p class="payment-popup__text">Your payment proof has been submitted successfully. The invoice has been sent to your email, and your order is now waiting for verification.</p>
      <?php if ($orderUploaded !== ''): ?>
        <div class="payment-popup__meta">
          <span>Order Number</span>
          <strong><?= htmlspecialchars($orderUploaded) ?></strong>
        </div>
      <?php endif; ?>
      <div class="payment-popup__actions">
        <button type="button" id="closePaymentPopupSecondary" class="button">Continue Shopping</button>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <aside class="menu-panel" id="menuPanel" aria-label="Main menu">
    <div class="menu-head">
      <button class="menu-close" id="closeMenu" aria-label="Close menu">×</button>
    </div>

    <div class="menu-body">
      <form class="menu-search-form" action="shop.php" method="GET" role="search">
        <?php if ($category !== ''): ?>
          <input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>">
        <?php endif; ?>
        <input type="text" name="search" placeholder="SEARCH" value="<?= htmlspecialchars($search) ?>" aria-label="Search products">
        <button type="submit" aria-label="Search">&#8594;</button>
      </form>

      <nav class="menu-nav-primary">
        <div class="dropdown" id="shopDropdown">
          <button class="dropdown-toggle" id="shopDropdownBtn" aria-expanded="false">Shop</button>
          <div class="dropdown-menu" id="shopDropdownMenu">
            <a href="shop.php">All</a>
            <?php foreach ($categories as $cat): ?>
              <a href="shop.php?category=<?= urlencode((string)$cat['slug']) ?>"><?= htmlspecialchars((string)$cat['name']) ?></a>
            <?php endforeach; ?>
          </div>
        </div>
        <a href="lookbook.php">LookBook</a>
        <div class="dropdown">
          <button class="dropdown-toggle" id="openAccountBtnMobile" aria-expanded="false" aria-label="Account" style="justify-content:flex-start;">Account</button>
        </div>

        <div class="dropdown" id="currencyDropdown">
          <button class="dropdown-toggle" id="currencyDropdownBtn" aria-expanded="false">Currency (<?= $currentCurrency ?>)</button>
          <div class="dropdown-menu">
            <a href="?currency=IDR<?= $search !== '' ? '&search='.urlencode($search) : '' ?><?= $category !== '' ? '&category='.urlencode($category) : '' ?>">IDR</a>
            <a href="?currency=USD<?= $search !== '' ? '&search='.urlencode($search) : '' ?><?= $category !== '' ? '&category='.urlencode($category) : '' ?>">USD</a>
            <a href="?currency=SGD<?= $search !== '' ? '&search='.urlencode($search) : '' ?><?= $category !== '' ? '&category='.urlencode($category) : '' ?>">SGD</a>
            <a href="?currency=MYR<?= $search !== '' ? '&search='.urlencode($search) : '' ?><?= $category !== '' ? '&category='.urlencode($category) : '' ?>">MYR</a>
          </div>
        </div>
      </nav>
    </div>
  </aside>

  <aside class="cart-panel" id="cartPanel" aria-label="Shopping cart">
    <div class="cart-head">
      <div class="cart-title">Cart (<span id="cartCount">0</span>)</div>
      <button class="nav-btn" id="closeCart" aria-label="Close cart" style="color: #111; font-size: 24px; font-weight: 300;">×</button>
    </div>
    <div class="cart-body" id="cartBody">
      <div class="cart-empty"><span>Your cart is empty</span><a class="cart-empty-link" href="shop.php">Continue shopping</a></div>
    </div>
    <div class="cart-foot" id="cartFoot" style="display:none">
      <div class="cart-total">
        <span>Total</span>
        <strong id="cartTotal">RP 0</strong>
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

  <?php include 'navbar.php'; ?>

  <!-- CATEGORY NAV HORIZONTAL BAR -->
  <div class="category-nav-wrapper">
    <nav class="category-nav">
      <a href="shop.php" class="<?= ($category === '' && $search === '') ? 'active' : '' ?>">ALL</a>
     
      <?php foreach ($categories as $cat): ?>
        <a href="shop.php?category=<?= urlencode((string)$cat['slug']) ?>" class="<?= $category === (string)$cat['slug'] ? 'active' : '' ?>"><?= htmlspecialchars(strtoupper((string)$cat['name'])) ?></a>
      <?php endforeach; ?>
    </nav>
    
    <?php if ($search !== ''): ?>
    <div style="font-size:11px; color:#888; text-transform:uppercase; font-weight:600; letter-spacing:0.05em; margin-top: 15px; text-align:center;">
      Search: <strong><?= htmlspecialchars(strtoupper($search)) ?></strong>
      <a href="shop.php<?= $category !== '' ? '?category='.urlencode($category) : '' ?>" style="text-decoration:underline; text-underline-offset:3px; margin-left:10px;">Clear</a>
    </div>
    <?php endif; ?>
  </div>

  <section class="product-section">
    <?php if (!$products): ?>
      <div class="empty-text">No active products yet.</div>
    <?php else: ?>
      <main class="product-grid">
        <?php foreach ($products as $product): ?>
          <?php
            $originalPrice = getBasePrice($product, $currentCurrency);
            $finalPrice    = getFinalPrice($product, $currentCurrency);
            $hasDiscount   = $finalPrice < $originalPrice;
            $isInStock     = (int)($product['is_in_stock'] ?? 1) === 1; 
            $slug          = htmlspecialchars((string)$product['slug']);
            $name          = htmlspecialchars(strtoupper((string)$product['name']));
            $thumb         = htmlspecialchars((string)$product['thumbnail']);
            $productId     = (int)$product['id'];
          ?>
          <div class="product-card <?= !$isInStock ? 'sold-out' : '' ?>" data-product-id="<?= $productId ?>" data-product-name="<?= $name ?>" data-product-price="<?= $finalPrice ?>" data-product-thumb="<?= $thumb ?>">
            
            <div style="position:relative;">
              <?php if ($hasDiscount && $isInStock): ?>
                <span class="discount-label">SALE</span>
              <?php endif; ?>
              
               <a href="product.php?slug=<?= urlencode((string)$product['slug']) ?>" tabindex="-1" style="display:block;">
                 <div class="product-image">
                   <img src="<?= $thumb ?>" alt="<?= $name ?>" loading="lazy">
                 </div>
               </a>

            </div>

            <a href="product.php?slug=<?= urlencode((string)$product['slug']) ?>" aria-label="<?= $name ?>" style="display:block;">
              <div class="product-info">
                <h3><?= $name ?></h3>
                
                <?php if (!$isInStock): ?>
                  <span class="sold-out-text">SOLD OUT</span>
                <?php else: ?>
                  <p class="product-price">
                    <?php if ($hasDiscount): ?>
                      <span class="price-original"><?= htmlspecialchars(formatCurrency($originalPrice, $currentCurrency)) ?></span>
                      <span class="price-final"><?= htmlspecialchars(formatCurrency($finalPrice, $currentCurrency)) ?></span>
                    <?php else: ?>
                      <span class="price-final"><?= htmlspecialchars(formatCurrency($originalPrice, $currentCurrency)) ?></span>
                    <?php endif; ?>
                  </p>
                <?php endif; ?>
              </div>
            </a>
            
          </div>
        <?php endforeach; ?>
      </main>
    <?php endif; ?>
  </section>

  <?php include 'footer.php'; ?>

  <div id="termsPopup" class="info-popup" role="dialog" aria-modal="true" aria-label="Terms and Conditions">
    <div class="info-popup__backdrop" id="termsBackdrop"></div>
    <div class="info-popup__dialog">
      <button class="popup-close" id="closeTermsBtn" aria-label="Close">×</button>
      <h2>Terms &amp; Conditions</h2>
      <div class="info-popup__content">
        <div class="info-section"><h3>General</h3><p>This website is operated by Deepdimes. By visiting our site and/or purchasing from us, you agree to be bound by the following Terms and Conditions.</p></div>
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

  <script>
  (function () {
    'use strict';

    // Ambil session currency dari PHP ke Javascript
    const CURRENT_CURRENCY = '<?= $currentCurrency ?>';

    const body         = document.body;
    const overlay      = document.getElementById('overlay');
    const menuPanel    = document.getElementById('menuPanel');
    const cartPanel    = document.getElementById('cartPanel');
    const accountPopup = document.getElementById('accountPopup');

    let cartItems = JSON.parse(localStorage.getItem('ppf_cart') || '[]');

    function showToast(msg) {
      const container = document.getElementById('toastContainer');
      const t = document.createElement('div');
      t.className = 'toast';
      t.textContent = msg;
      container.appendChild(t);
      requestAnimationFrame(() => { requestAnimationFrame(() => { t.classList.add('show'); }); });
      setTimeout(() => { t.classList.remove('show'); setTimeout(() => t.remove(), 250); }, 2600);
    }

    function setOverlay(active) {
      overlay.classList.toggle('active', active);
      body.classList.toggle('lock-scroll', active);
    }

    function anyPanelOpen() {
      return menuPanel.classList.contains('active') ||
             cartPanel.classList.contains('active') ||
             accountPopup.classList.contains('active') ||
             document.getElementById('termsPopup').classList.contains('active') ||
             document.getElementById('faqPopup').classList.contains('active');
    }

    function closeAll() {
      menuPanel.classList.remove('active');
      cartPanel.classList.remove('active');
      accountPopup.classList.remove('active');
      document.getElementById('termsPopup').classList.remove('active');
      document.getElementById('faqPopup').classList.remove('active');
    }

    function openMenu()  { closeAll(); menuPanel.classList.add('active');  setOverlay(true); }
    function openCart()  { closeAll(); cartPanel.classList.add('active');  setOverlay(true); }
    function openAccount() { closeAll(); accountPopup.classList.add('active'); setOverlay(true); }

    const menuToggle = document.getElementById('menuToggle');
    if (menuToggle) menuToggle.addEventListener('click', openMenu);
    
    document.getElementById('closeMenu').addEventListener('click', () => { menuPanel.classList.remove('active'); if (!anyPanelOpen()) setOverlay(false); });
    
    const openCartBtn = document.getElementById('openCartBtn');
    if (openCartBtn) openCartBtn.addEventListener('click', openCart);
    
    document.getElementById('closeCart').addEventListener('click', () => { cartPanel.classList.remove('active'); if (!anyPanelOpen()) setOverlay(false); });
    
    const openAccountBtn = document.getElementById('openAccountBtn');
    if (openAccountBtn) openAccountBtn.addEventListener('click', openAccount);

    // Tombol Account di menu mobile: buka popup login (bukan toggle dropdown)
    const openAccountBtnMobile = document.getElementById('openAccountBtnMobile');
    if (openAccountBtnMobile) {
      openAccountBtnMobile.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        openAccount();
      });
    }

    /* ── DROPDOWN TOGGLE LISTENER ─────────────────────── */
    document.querySelectorAll('.dropdown-toggle').forEach(btn => {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        this.parentElement.classList.toggle('open');
        const expanded = this.getAttribute('aria-expanded') === 'true';
        this.setAttribute('aria-expanded', !expanded);
      });
    });

    document.getElementById('menuOpenAccountBtn')?.addEventListener('click', (e) => {
      e.preventDefault();
      openAccount();
    });
    document.getElementById('menuOpenCartBtn')?.addEventListener('click', (e) => {
      e.preventDefault();
      openCart();
    });

    document.getElementById('closeAccountBtn').addEventListener('click', () => { accountPopup.classList.remove('active'); if (!anyPanelOpen()) setOverlay(false); });
    document.getElementById('accountBackdrop').addEventListener('click', () => { accountPopup.classList.remove('active'); if (!anyPanelOpen()) setOverlay(false); });

    overlay.addEventListener('click', () => { closeAll(); setOverlay(false); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeAll(); setOverlay(false); } });

    document.querySelectorAll('.account-tab').forEach(tab => {
      tab.addEventListener('click', function () {
        document.querySelectorAll('.account-tab').forEach(t => { t.classList.remove('active'); t.setAttribute('aria-selected','false'); });
        this.classList.add('active'); this.setAttribute('aria-selected','true');
        const target = this.dataset.tab;
        document.getElementById('tabLogin').style.display    = target === 'login'    ? '' : 'none';
        document.getElementById('tabRegister').style.display = target === 'register' ? '' : 'none';
      });
    });

    document.getElementById('loginBtn').addEventListener('click', function () {
      const email = document.getElementById('loginEmail').value.trim();
      const pass  = document.getElementById('loginPassword').value;
      const msg   = document.getElementById('loginMsg');
      if (!email || !pass) { msg.className='account-msg error'; msg.textContent='Please fill in all fields.'; msg.style.display='block'; return; }
      msg.className='account-msg success'; msg.textContent='Redirecting...'; msg.style.display='block';
      setTimeout(() => { window.location.href = 'login.php?email=' + encodeURIComponent(email); }, 800);
    });
    document.getElementById('registerBtn').addEventListener('click', function () {
      const name  = document.getElementById('regName').value.trim();
      const email = document.getElementById('regEmail').value.trim();
      const pass  = document.getElementById('regPassword').value;
      const msg   = document.getElementById('registerMsg');
      if (!name || !email || !pass) { msg.className='account-msg error'; msg.textContent='Please fill in all fields.'; msg.style.display='block'; return; }
      msg.className='account-msg success'; msg.textContent='Redirecting...'; msg.style.display='block';
      setTimeout(() => { window.location.href = 'register.php?email=' + encodeURIComponent(email) + '&name=' + encodeURIComponent(name); }, 800);
    });

    const termsPopup = document.getElementById('termsPopup');
    const faqPopup   = document.getElementById('faqPopup');

    function openModal(modal)  { closeAll(); modal.classList.add('active');  setOverlay(true); }
    function closeModal(modal) { modal.classList.remove('active'); if (!anyPanelOpen()) setOverlay(false); }

    ['openTermsLink','footerTermsLink','menuOpenTermsBtn'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.addEventListener('click', e => { e.preventDefault(); openModal(termsPopup); });
    });
    ['openFaqLink','footerFaqLink','menuOpenFaqBtn'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.addEventListener('click', e => { e.preventDefault(); openModal(faqPopup); });
    });
    document.getElementById('closeTermsBtn').addEventListener('click', () => closeModal(termsPopup));
    document.getElementById('termsBackdrop').addEventListener('click', () => closeModal(termsPopup));
    document.getElementById('closeFaqBtn').addEventListener('click',   () => closeModal(faqPopup));
    document.getElementById('faqBackdrop').addEventListener('click',   () => closeModal(faqPopup));

    /* ── CART ──────────────────────────────────────────── */
    function saveCart() { localStorage.setItem('ppf_cart', JSON.stringify(cartItems)); }

    function formatCurrencyJS(amount) {
      const DD_CUR = {IDR:{s:'RP ',d:0},USD:{s:'$',d:2},SGD:{s:'S$',d:2},MYR:{s:'RM ',d:2}};
      const m = DD_CUR[CURRENT_CURRENCY] || DD_CUR.IDR;
      let a = parseFloat(amount); if (!isFinite(a)) a = 0;
      if (m.d === 0) return m.s + Math.round(a).toLocaleString('id-ID').replace(/,/g, '.');
      return m.s + a.toFixed(m.d).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    function renderCart() {
      const body  = document.getElementById('cartBody');
      const foot  = document.getElementById('cartFoot');
      const count = cartItems.reduce((s, i) => s + i.qty, 0);
      const total = cartItems.reduce((s, i) => s + i.price * i.qty, 0);

      const cartCountEl       = document.getElementById('cartCount');
      const navCartCountEl    = document.getElementById('navCartCount');
      const menuCartCountEl   = document.getElementById('menuCartCount');
      if (cartCountEl)       cartCountEl.textContent       = count;
      if (navCartCountEl)    navCartCountEl.textContent    = count;
      if (menuCartCountEl)   menuCartCountEl.textContent   = count;

      if (cartItems.length === 0) {
        body.innerHTML = '<div class="cart-empty"><span>Your cart is empty</span><a class="cart-empty-link" href="shop.php">Continue shopping</a></div>';
        foot.style.display = 'none';
        return;
      }

      foot.style.display = '';
      document.getElementById('cartTotal').textContent = formatCurrencyJS(total);

      body.innerHTML = cartItems.map((item, idx) => `
        <div class="cart-item">
          <img class="cart-item-img" src="${item.thumb}" alt="${item.name}">
          <div>
            <div class="cart-item-name">${item.name}</div>
            <div class="cart-item-price">${formatCurrencyJS(item.price)}</div>
            <div class="cart-item-qty">
              <button aria-label="Decrease" data-action="dec" data-idx="${idx}">−</button>
              <span>${item.qty}</span>
              <button aria-label="Increase" data-action="inc" data-idx="${idx}">+</button>
            </div>
          </div>
          <button class="cart-item-remove" aria-label="Remove" data-action="remove" data-idx="${idx}">×</button>
        </div>
      `).join('');

      body.querySelectorAll('[data-action]').forEach(btn => {
        btn.addEventListener('click', () => {
          const idx    = parseInt(btn.dataset.idx, 10);
          const action = btn.dataset.action;
          if (action === 'inc') { cartItems[idx].qty++; }
          else if (action === 'dec') { cartItems[idx].qty--; if (cartItems[idx].qty <= 0) cartItems.splice(idx, 1); }
          else if (action === 'remove') { cartItems.splice(idx, 1); }
          saveCart(); renderCart();
        });
      });
    }

    document.getElementById('checkoutBtn').addEventListener('click', () => {
      window.location.href = 'checkout.php';
    });

    renderCart();

    /* ── PAYMENT POPUP ─────────────────────────────────── */
    (function () {
      const popup = document.getElementById('paymentPopup');
      if (!popup) return;
      const closeBtn  = document.getElementById('closePaymentPopup');
      const closeBtn2 = document.getElementById('closePaymentPopupSecondary');
      const backdrop  = popup.querySelector('.payment-popup__backdrop');

      function clearUrlParams() {
        if (!window.history.replaceState) return;
        const url = new URL(window.location.href);
        ['payment_uploaded','order','invoice_email'].forEach(p => url.searchParams.delete(p));
        window.history.replaceState({}, document.title, url.pathname + (url.searchParams.toString() ? '?' + url.searchParams.toString() : ''));
      }
      function closePopup() {
        popup.classList.remove('payment-popup--open');
        popup.classList.add('payment-popup--closing');
        setTimeout(() => { popup.remove(); clearUrlParams(); }, 250);
      }
      if (closeBtn)  closeBtn.addEventListener('click',  closePopup);
      if (closeBtn2) closeBtn2.addEventListener('click', closePopup);
      if (backdrop)  backdrop.addEventListener('click',  closePopup);
    })();

  })();
  </script>
</body>
</html>