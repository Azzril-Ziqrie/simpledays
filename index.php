<?php
session_start();

require_once __DIR__ . "/config/database.php";
require_once __DIR__ . '/includes/db_schema.php';
require_once __DIR__ . '/includes/currency_helper.php';
require_once __DIR__ . '/includes/pricing.php';

// Buka lagi pop-up pilih currency (link "Currency" di menu).
if (isset($_GET['choose_currency'])) unset($_SESSION['currency_prompt_done']);
require_once __DIR__ . "/config/kasera_client.php";

// TAHAP PERBAIKAN: Memanggil Composer Autoload agar PHPMailer terdeteksi
$composerAutoload = __DIR__ . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
}

require_once __DIR__ . "/includes/email_helpers.php";

/* ── KASERA RETURN VERIFICATION ────────────────────────── */
if (isset($_GET["kasera_verify"]) && $_GET["kasera_verify"] === "1") {
    $orderNumber = trim((string)($_GET["order"] ?? ""));
    $paymentRequestId = trim((string)($_GET["id"] ?? ""));

    if ($orderNumber !== "" && $paymentRequestId !== "") {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number = :on LIMIT 1");
        $stmt->execute(["on" => $orderNumber]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($order) {
            // FIX UTAMA: Sebelumnya kode hanya masuk ke blok verifikasi kalau
            // payment_status BELUM "paid". Masalahnya, kalau webhook Kasera
            // sudah lebih dulu menandai order ini "paid" (dan mengirim email
            // invoice) SEBELUM browser user diarahkan balik ke halaman ini,
            // maka payment_status di sini SUDAH "paid" saat halaman dimuat.
            // Akibatnya kondisi lama (!== "paid") jadi false, seluruh blok
            // di-skip, tidak pernah redirect ke payment_uploaded=1, dan
            // popup "Invoice Sent to Gmail" tidak pernah tampil — padahal
            // email memang sudah terkirim oleh webhook.
            //
            // Sekarang: kalau order SUDAH paid (misal oleh webhook), kita
            // tetap redirect untuk menampilkan popup sukses — tanpa perlu
            // verifikasi ulang ke Kasera atau kirim email ulang.
            if ($order["payment_status"] === "paid") {
                header("Location: index.php?payment_uploaded=1&invoice_email=sent&order=" . urlencode($orderNumber));
                exit;
            }

            try {
                $tx = kaseraGetTransaction($paymentRequestId);
                $txStatus = strtolower((string)($tx["status"] ?? ""));

                if (in_array($txStatus, ["succeeded", "paid"], true)) {
                    $pdo->beginTransaction();

                    // FIX: query sebelumnya punya double quotes bersarang di dalam
                    // double-quoted string ("...= "paid", ...") yang membuat PHP
                    // gagal parse file ini sama sekali (fatal parse error → HTTP 500
                    // sebelum kode apapun sempat jalan). Sekarang pakai bound param.
                    $upd = $pdo->prepare("UPDATE orders SET payment_status = :status, updated_at = NOW() WHERE id = :id");
                    $upd->execute(["status" => "paid", "id" => $order["id"]]);
                    $wasJustPaid = $upd->rowCount() > 0;

                    if ($wasJustPaid) {
                        // FIX: sama, INSERT sebelumnya juga punya double quotes
                        // bersarang yang tidak di-escape. Sekarang pakai placeholder.
                        $pdo->prepare(
                            "INSERT INTO order_status_logs (order_id, status_type, status_value, notes)
                             VALUES (:oid, :st, :sv, :n)"
                        )->execute([
                            "oid" => $order["id"],
                            "st"  => "payment",
                            "sv"  => "paid",
                            "n"   => "Verified via Kasera return redirect on index.",
                        ]);
                    }
                    $pdo->commit();

                    $emailSent = $wasJustPaid ? sendDDInvoiceEmail($pdo, $order) : true;

                    header("Location: index.php?payment_uploaded=1&invoice_email=" . ($emailSent ? "sent" : "failed") . "&order=" . urlencode($orderNumber));
                    exit;
                }
                // Kalau status transaksi belum succeeded/paid, biarkan halaman
                // lanjut render normal (tanpa popup) — user bisa dicoba lagi
                // via webhook atau refresh nanti.
            } catch (Throwable $e) {
                error_log("[kasera index verify] failed: " . $e->getMessage());
            }
        }
    }
}

$search   = isset($_GET['search'])   ? trim((string) $_GET['search'])   : '';
$category = isset($_GET['category']) ? trim((string) $_GET['category']) : '';

// Batasi panjang search supaya tidak bikin URL/Query panjang & tidak rentan error
if (strlen($search) > 100) { $search = substr($search, 0, 100); }

$brandName       = 'DEEPDIMES';
$brandLogo = 'assets/images/logo-putih.png';
$customHeroImage = '1212ppf.JPEG';

/* ── helpers ─────────────────────────────────────────── */
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

/* ── hero slides ─────────────────────────────────────── */
$heroSlides = [];
if (tableExists($pdo, 'hero_slides')) {
    try {
        $heroStmt = $pdo->query(
            "SELECT id, title, subtitle, button_text, button_link, image, image_mobile
               FROM hero_slides
              WHERE is_active = 1
              ORDER BY sort_order ASC, id ASC"
        );
        $heroSlides = $heroStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('[index.php] Hero slides query failed: ' . $e->getMessage());
        $heroSlides = [];
    }
}
if (!$heroSlides) {
    // Fallback ke gambar hero statis kalau tabel/slide belum ada
    $heroSlides = [[
        'id'          => 0,
        'title'       => null,
        'subtitle'    => null,
        'button_text' => null,
        'button_link' => null,
        'image'       => $customHeroImage,
    ]];
}

/* ── product query ───────────────────────────────────── */
// Menambahkan p.price_usd ke dalam query
$sql = "
    SELECT p.id, p.name, p.slug, p.price_idr, p.price_usd,
            p.discount_percent, p.discount_start_at, p.discount_end_at, p.is_discount_active,
            p.thumbnail, {$categorySelect}, {$stockSelect}
     FROM products p

";
if ($joinCategory) $sql .= " LEFT JOIN categories c ON p.category_id = c.id";
$sql .= " WHERE p.is_active = 1";

$params = [];
if ($search !== '') {
    $like = '%' . $search . '%';
    if ($joinCategory) {
        $sql .= " AND (p.name LIKE :search1 OR p.slug LIKE :search2 OR c.name LIKE :search3 OR c.slug LIKE :search4)";
        $params[':search1'] = $like;
        $params[':search2'] = $like;
        $params[':search3'] = $like;
        $params[':search4'] = $like;
    } elseif ($hasCategoryText) {
        $sql .= " AND (p.name LIKE :search1 OR p.slug LIKE :search2 OR p.category LIKE :search3)";
        $params[':search1'] = $like;
        $params[':search2'] = $like;
        $params[':search3'] = $like;
    } else {
        $sql .= " AND (p.name LIKE :search1 OR p.slug LIKE :search2)";
        $params[':search1'] = $like;
        $params[':search2'] = $like;
    }
}
if ($category !== '') {
    if ($joinCategory) {
        // Kategori "new" juga memuat produk yang ditandai baru (products.is_new) di kategori lain, mis. Top.
        if (strtolower($category) === 'new' && in_array('is_new', $productColumns, true)) {
            $sql .= " AND (c.slug = :category OR p.is_new = 1)";
        } else {
            $sql .= " AND c.slug = :category";
        }
        $params[':category'] = $category;
    } elseif ($hasCategoryText) {
        $sql .= " AND LOWER(REPLACE(p.category, ' ', '-')) = :category";
        $params[':category'] = strtolower($category);
    }
}

if ($hasSortOrder) {
    $sql .= " ORDER BY p.sort_order ASC, p.id DESC";
} else {
    $sql .= " ORDER BY p.id DESC";
}

$products = [];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Tambahkan baris ini untuk memfilter produk yang sold out
    $products = array_filter($products, function($product) {
        return (int)($product['is_in_stock'] ?? 1) === 1;
    });
} catch (Throwable $e) {
    // Jangan biarkan halaman crash — tampilkan log dan lanjut dengan $products kosong
    error_log('[index.php] Product query failed: ' . $e->getMessage());
}

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

/* ── hero copy ───────────────────────────────────────── */
$heroTitle    = $category !== '' ? strtoupper(str_replace('-', ' ', $category)) : $brandName;

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="assets/images/link.png">
  <!-- SEO Meta Tags -->
  <title><?= htmlspecialchars($brandName) ?></title>
  <meta name="description" content="DEEPDIMES is a constant practice focused on continuous development inspired by coexisting conditions. Explore our premium contemporary fashion collection that blends form, function, and craftsmanship.">
  <meta name="keywords" content="DeepDimes, contemporary fashion, premium clothing, Indonesian fashion brand, sustainable fashion, avant-garde clothing">
  <!-- Geo Meta Tags -->
  <meta name="geo.position" content="-6.5950384;106.8166355">
  <meta name="geo.placename" content="Klapanunggal, Bogor, West Java">
  <meta name="geo.region" content="ID-JB">
  <!-- Open Graph / Facebook -->
  <meta property="og:type" content="website">
  <meta property="og:url" content="<?= 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ?>">
  <meta property="og:title" content="<?= htmlspecialchars($brandName) ?> - Premium Contemporary Fashion Brand">
  <meta property="og:description" content="DeepDimes is a constant practice focused on continuous development inspired by coexisting conditions. Explore our premium contemporary fashion collection that blends form, function, and craftsmanship.">
  <meta property="og:image" content="<?= 'https://' . $_SERVER['HTTP_HOST'] . '/1212ppf.JPEG' ?>">
  <meta property="og:site_name" content="<?= htmlspecialchars($brandName) ?>">
  <!-- Twitter -->
  <meta property="twitter:card" content="summary_large_image">
  <meta property="twitter:url" content="<?= 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ?>">
  <meta property="twitter:title" content="<?= htmlspecialchars($brandName) ?> - Premium Contemporary Fashion Brand">
  <meta property="twitter:description" content="DeepDimes is a constant practice focused on continuous development inspired by coexisting conditions. Explore our premium contemporary fashion collection that blends form, function, and craftsmanship.">
  <meta property="twitter:image" content="<?= 'https://' . $_SERVER['HTTP_HOST'] . '/1212ppf.JPEG' ?>">
  <!-- Schema.org JSON-LD -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "LocalBusiness",
    "name": "DeepDimes",
    "image": "https://<?= $_SERVER['HTTP_HOST'] ?>/1212ppf.JPEG",
    "@id": "https://<?= $_SERVER['HTTP_HOST'] ?>",
    "url": "https://<?= $_SERVER['HTTP_HOST'] ?>",
    "telephone": "+62-251-8323xxx",
    "priceRange": "$$",
    "address": {
      "@type": "PostalAddress",
      "streetAddress": "S.L. Village Klapanunggal",
      "addressLocality": "Bogor",
      "addressRegion": "West Java",
      "postalCode": "16871",
      "addressCountry": "ID"
    },
    "geo": {
      "@type": "GeoCoordinates",
      "latitude": "-6.5950384",
      "longitude": "106.8166355"
    },
    "openingHoursSpecification": [
      {
        "@type": "OpeningHoursSpecification",
        "dayOfWeek": [
          "Monday",
          "Tuesday",
          "Wednesday",
          "Thursday",
          "Friday",
          "Saturday",
          "Sunday"
        ],
        "opens": "10:00",
        "closes": "19:00"
      }
    ],
    "sameAs": [
      "https://www.instagram.com/deepdimes/",
      "https://www.facebook.com/deepdimes"
    ]
  }
  </script>
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
.navbar{width:100%;height:70px;background:transparent !important;display:flex;align-items:center;justify-content:space-between;padding:0 30px;position:fixed;top:0;left:0;z-index:60;border-bottom:none; color: #fff;}
.nav-left{display:flex;align-items:center;gap:10px}
.nav-left-label{font-size:11px;letter-spacing:.1em; font-weight: 600;}
.brand-mark img { height: 70px; width: auto; display: block; border-radius: 50%; }
.nav-center{position:absolute;left:50%;transform:translateX(-50%);display:flex;align-items:center}
.nav-right{display:flex;align-items:center;gap:25px;margin-left:auto}
.brand-mark{font-size:24px;font-weight:700;letter-spacing:.05em;line-height:1; color:#fff;}
.menu-icon-wrap{display:flex;align-items:center;gap:8px;cursor:pointer;background:none;border:none}
.menu-icon-wrap span{display:none}
.menu-icon-wrap::before{content:'MENU';font-size:11px;letter-spacing:.1em;color:#fff; font-weight:600;}
.nav-btn{background:none;border:none;font-size:11px;letter-spacing:.1em;color:#fff;cursor:pointer;text-transform:uppercase; font-weight: 600;}
.nav-count{font-size:11px;letter-spacing:.08em}

/* ── SEARCH ──────────────────────────────────────────── */
.search-form{display:flex;align-items:center;gap:4px}
.search-form input{background:transparent;border:none;outline:none;color:#fff;width:80px;font-size:11px;text-transform:uppercase;letter-spacing:.08em; font-weight: 500;}
.search-form input::placeholder{color:rgba(255,255,255,0.7)}
.search-form button{background:transparent;border:none;color:#fff;font-size:14px;line-height:1;padding:0}

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

.menu-nav-secondary{display:flex;flex-direction:column;gap:18px;margin-top:auto;padding-top:20px}
.menu-nav-secondary a{font-size:11px;letter-spacing:.1em;color:#888; font-weight: 500; transition:color .15s}
.menu-nav-secondary a:hover{color:#111}

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

/* ── HERO / SLIDER ───────────────────────────────────── */
.hero{position:relative;background:#111}
.hero-slider{position:relative;overflow:hidden}
/* scrim tipis supaya navbar putih & SHOP NOW terbaca di atas foto apa pun */
.hero-slider::before{content:'';position:absolute;inset:0;z-index:3;pointer-events:none;background:linear-gradient(rgba(0,0,0,.35),transparent 160px),linear-gradient(transparent 65%,rgba(0,0,0,.35))}
.hero-slide{position:absolute;inset:0;opacity:0;visibility:hidden;transition:opacity .7s ease;z-index:1}
.hero-slide.active{position:relative;opacity:1;visibility:visible;z-index:2}
.hero-media{position:relative;overflow:hidden}
.hero-media picture{display:block;width:100%;height:100%}
.hero-media img{width:100%;height:100vh;height:100svh;min-height:600px;object-fit:cover;object-position:center;opacity:0;animation:heroFade .5s ease forwards;}
@media(max-width:600px){
  .hero-media img{height:100vh; height:100svh; min-height:400px;}
}
.hero-copy{position:absolute;left:0;right:0;bottom:0;padding:50px 40px;color:#fff;z-index:3}
.hero-sub{font-size:11px; font-weight: 600; letter-spacing:.15em;margin-bottom:12px;opacity:.8}
.hero-title{font-size:42px;font-weight:900;letter-spacing:-0.02em;margin-bottom:8px;text-transform:uppercase;font-family:inherit}
.hero-cta{display:inline-block;font-size:11px; font-weight: 700; letter-spacing:.1em;color:#fff;border-bottom:2px solid #fff;padding-bottom:4px;margin-top:16px;transition:opacity .2s}
.hero-cta:hover{opacity:.6}

/* HUGE "SHOP NOW" OVERLAY (from restyle) */
.hero-shop-now{position:absolute;left:40px;bottom:40px;font-size:clamp(48px, 6vw, 92px);font-weight:900;letter-spacing:-0.02em;color:#fff;z-index:3; line-height: 1;}
.hero-shop-now:hover{opacity: 0.9;}

.hero-now-available{position:absolute;right:40px;bottom:40px;text-align:right;font-size:11px; font-weight: 700; letter-spacing:.08em;color:#fff;z-index:3;line-height:1.6}
.hero-now-available .label{opacity:.7;margin-bottom:6px;font-weight:600}
.hero-now-available strong{display:block;font-weight:800}
.hero-arrow, .hero-dots { display: none !important; }

/* ── STATEMENT ───────────────────────────────────────── */
.statement{display:none}
.statement-cta{display:none}

/* ── RESULT BAR ──────────────────────────────────────── */
.result-bar{display:flex;gap:18px;align-items:center;justify-content:space-between;padding:12px 40px;border-bottom:1px solid #eee;font-size:11px; font-weight: 600; letter-spacing:.08em}
.result-bar a{text-decoration:underline;text-underline-offset:3px}

/* ── SHOP LAYOUT & CATEGORY NAV ──────────────────────── */
.shop-layout {
    display: block;
    width: 100%;
}
.category-nav-wrapper {
    padding: 30px 40px 10px;
    background: #fff;
    position: static; /* tidak ikut saat di-scroll */
    display: flex;
    justify-content: center;
}
.category-nav {
    display: flex;
    gap: 15px;
    overflow-x: auto;
    white-space: nowrap;
    scrollbar-width: none;
    -ms-overflow-style: none;
    justify-content: center;
}
@media(max-width:600px){
    .category-nav-wrapper {
        display: block;
    }
    .category-nav {
        justify-content: flex-start;
    }
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

/* ── PRODUCTS ────────────────────────────────────────── */
.product-section {
    width: 100%;
    background: #fff; 
    padding: 20px 40px 60px;
}
.section-head{display:none}
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
  .product-section{padding: 30px 20px;}
  .product-grid{grid-template-columns:repeat(2,minmax(0,1fr)); gap: 30px 15px;}
}
@media(max-width:600px){
  .navbar{padding:0 20px}
  .nav-right .search-form, 
  .nav-right #openAccountBtn { display: none; }
  .statement,.section-head,.result-bar,.footer{padding-left:20px;padding-right:20px}
  
  .category-nav-wrapper { padding: 20px 20px 10px; }
  .category-nav a { font-size: 11px; }
  .product-section { padding: 15px 20px 40px; }
  
  .product-info{padding:14px 10px 10px}
  .hero-copy{padding:30px 20px}
  .hero-shop-now{font-size:42px;left:20px;bottom:25px}
  .hero-shop-now{font-size:25px !important;left:20px;bottom:25px}
  .hero-now-available{right:20px;bottom:25px;font-size:10px}
  .cart-body,.cart-foot,.cart-head{padding-left:20px;padding-right:20px}
  .account-popup__dialog{padding:35px 25px}
  .payment-popup__dialog,.info-popup__dialog{padding:35px 25px}
  .toast-container{right:15px;bottom:15px;left:15px}
  .toast{max-width:100%}
}

/* ── ANIMATIONS ──────────────────────────────────────── */
@keyframes pageFadeIn{from{opacity:0}to{opacity:1}}
@keyframes heroFade{from{opacity:0}to{opacity:1}}
@keyframes productFadeIn{from{opacity:0; transform:translateY(10px)}to{opacity:1; transform:translateY(0)}}
  /* Hide mobile account button on desktop */
  @media(min-width:601px){
    #openAccountBtnMobile{display:none}
  }
  /* Show mobile account button on mobile */
  @media(max-width:600px){
    #openAccountBtnMobile{display:block; text-align:left; width:100%; box-sizing:border-box;}
  }
  </style>
  <script><?= ddDisplayJs() ?></script>
  <script src="assets/js/mini-cart.js"></script>
  <script>
    // Tab yang sudah memilih currency: sembunyikan pop-up sejak awal (tanpa berkedip).
    (function () {
      try {
        if (location.search.indexOf('choose_currency') === -1 && sessionStorage.getItem('ddCurrencyPicked') === '1')
          document.documentElement.classList.add('cg-skip');
      } catch (e) {}
    })();
  </script>
</head>
<body>
  <?php if (file_exists(__DIR__ . '/loader.php')) include_once 'loader.php'; ?>

  <?php
  // Pop-up pilih mata uang setiap web dibuka di tab baru (sessionStorage hilang saat tab ditutup;
  // refresh / pindah halaman di tab yang sama tidak menanyakan lagi). Negara dideteksi dari IP,
  // mata uang lokalnya disarankan. Tagihan tetap USD; ini hanya mata uang tampilan harga.
  $showGate = false; $suggest = null; $gateOptions = [];
  {
      $rates = ddExchangeRates();
      $geo   = ddDetectVisitorCurrency();
      if ($geo && $geo['currency'] !== 'USD' && $rates && !empty($rates[$geo['currency']])) {
          $suggest = $geo + ['rate' => (float)$rates[$geo['currency']]];
      }
      if ($rates) {
          $codes = array_unique(array_values(ddCountryCurrencyMap()));
          sort($codes);
          foreach ($codes as $c) if ($c !== 'USD' && !empty($rates[$c])) $gateOptions[] = $c;
      }
      $showGate = true;
  }
  ?>
  <?php if ($showGate): ?>
  <style>
    #currencyGate{position:fixed;inset:0;z-index:100000;display:flex;align-items:center;justify-content:center;padding:20px;
      background:rgba(0,0,0,.25);-webkit-backdrop-filter:blur(10px) saturate(110%);backdrop-filter:blur(10px) saturate(110%)}
    html.cg-skip #currencyGate{display:none}
    body.cg-lock{overflow:hidden}
    #currencyGate .cg-dialog{outline:none;width:100%;max-width:400px;background:#fff;padding:40px;text-align:center}
    #currencyGate .cg-eyebrow{margin-bottom:8px;font-size:10px;letter-spacing:.15em;font-weight:600;color:#888}
    #currencyGate .cg-title{margin:0 0 12px;font-size:16px;font-weight:700;letter-spacing:.1em}
    #currencyGate .cg-text{margin:0 auto 22px;max-width:320px;font-size:12px;line-height:1.8;color:#555;text-transform:none;letter-spacing:.02em}
    #currencyGate .cg-flag{display:inline-block;margin-bottom:10px;border:1px solid #e5e5e5;object-fit:cover}
    #currencyGate .cg-list{display:grid;gap:10px}
    #currencyGate a.cg-opt{position:relative;padding:14px 12px;border:1px solid #111;color:#111;text-decoration:none;display:flex;flex-direction:column;gap:3px;text-transform:uppercase;transition:background .15s,color .15s}
    #currencyGate a.cg-opt:hover,#currencyGate a.cg-opt:focus-visible{background:#111;color:#fff;outline:none}
    #currencyGate a.cg-opt.cg-rec{background:#111;color:#fff}
    #currencyGate a.cg-opt.cg-rec:hover{background:#333}
    #currencyGate .cg-code{font-size:13px;font-weight:700;letter-spacing:.12em}
    #currencyGate .cg-name{font-size:9px;font-weight:600;letter-spacing:.08em;opacity:.7}
    #currencyGate .cg-badge{position:absolute;top:6px;right:8px;font-size:8px;font-weight:700;letter-spacing:.1em}
    #currencyGate select{margin-top:14px;width:100%;padding:10px;border:1px solid #ccc;background:#fff;font-size:12px}
    #currencyGate .cg-note{margin:16px 0 0;font-size:10px;color:#888;letter-spacing:.02em;text-transform:none}
  </style>
  <div id="currencyGate" role="dialog" aria-modal="true" aria-labelledby="cgTitle">
    <div class="cg-dialog" tabindex="-1" id="cgDialog">
      <div class="cg-eyebrow"><?= htmlspecialchars($brandName ?? '') ?></div>
      <h2 class="cg-title" id="cgTitle">Choose your currency</h2>
      <p class="cg-text">
        <?php if ($suggest): ?>
          <img class="cg-flag" src="https://flagcdn.com/w80/<?= htmlspecialchars(strtolower($suggest['country_code'])) ?>.png"
               alt="" width="40" height="30" onerror="this.style.display='none'"><br>
          Are you from <strong><?= htmlspecialchars($suggest['country']) ?></strong>?
          The currency there is <strong><?= htmlspecialchars($suggest['currency']) ?></strong>.
          Would you like to switch to <?= htmlspecialchars($suggest['currency']) ?>, or stay on USD?
        <?php else: ?>
          Pick the currency you'd like to see prices in.
        <?php endif; ?>
      </p>
      <div class="cg-list">
        <?php if ($suggest): ?>
          <a class="cg-opt cg-rec" href="set-currency.php?to=<?= urlencode($suggest['currency']) ?>">
            <span class="cg-badge">SUGGESTED</span>
            <span class="cg-code">Switch to <?= htmlspecialchars($suggest['currency']) ?></span>
            <span class="cg-name">1 USD ≈ <?= htmlspecialchars($suggest['currency']) ?> <?= number_format($suggest['rate'], $suggest['rate'] >= 100 ? 0 : 2, '.', ',') ?></span>
          </a>
        <?php endif; ?>
        <a class="cg-opt" href="set-currency.php?to=USD">
          <span class="cg-code"><?= $suggest ? 'Stay on USD' : 'USD' ?></span>
          <span class="cg-name">US Dollar</span>
        </a>
      </div>
      <?php if ($gateOptions): ?>
        <select id="cgOther" aria-label="Other currency">
          <option value="">Other currency…</option>
          <?php foreach ($gateOptions as $c): if ($suggest && $c === $suggest['currency']) continue; ?>
            <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
          <?php endforeach; ?>
        </select>
      <?php endif; ?>
      <p class="cg-note">Prices in other currencies are estimates. You will be charged in USD at checkout.</p>
    </div>
  </div>
  <script>
    (function () {
      var KEY = 'ddCurrencyPicked', root = document.documentElement;
      try {
        // Link "Currency" di menu membuka pop-up lagi di tab yang sama.
        if (location.search.indexOf('choose_currency') !== -1) {
          sessionStorage.removeItem(KEY);
          // Buang parameter dari URL supaya refresh / kembali dari pilihan tidak membuka pop-up lagi.
          history.replaceState(null, '', location.pathname + location.search.replace(/([?&])choose_currency(=[^&]*)?(&|$)/, '$1').replace(/[?&]$/, '') + location.hash);
        }
        if (sessionStorage.getItem(KEY) === '1') root.classList.add('cg-skip');
      } catch (e) {}
      var gate = document.getElementById('currencyGate');
      if (!gate || root.classList.contains('cg-skip')) return;
      function picked() { try { sessionStorage.setItem(KEY, '1'); } catch (e) {} }
      document.body.classList.add('cg-lock');
      gate.querySelectorAll('a.cg-opt').forEach(function (a) { a.addEventListener('click', picked); });
      var sel = document.getElementById('cgOther');
      if (sel) sel.addEventListener('change', function () { if (sel.value) { picked(); location.href = 'set-currency.php?to=' + encodeURIComponent(sel.value); } });
      // Fokus ke mata uang yang disarankan supaya cukup tekan Enter; kalau tak ada saran, fokus ke dialog.
      var rec = gate.querySelector('a.cg-rec'), dlg = document.getElementById('cgDialog');
      if (rec) rec.focus({ preventScroll: true }); else if (dlg) dlg.focus({ preventScroll: true });
    })();
  </script>
  <?php endif; ?>

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
      <form class="menu-search-form" action="index.php" method="GET" role="search">
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
              <a href="index.php?category=<?= urlencode((string)$cat['slug']) ?>"><?= htmlspecialchars((string)$cat['name']) ?></a>
            <?php endforeach; ?>
          </div>
        </div>
        <a href="lookbook.php">LookBook</a>
        <a href="index.php?choose_currency=1">Currency (<?= htmlspecialchars(ddDisplayCurrency()) ?>)</a>
        <button class="nav-btn" id="openAccountBtnMobile" aria-label="Account" style="color: #111;">Account</button>

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
          <input type="password" id="regPassword" placeholder="Password (min. 6 characters)" autocomplete="new-password">
          <input type="password" id="regPasswordConfirm" placeholder="Confirm Password" autocomplete="new-password">
          <button class="account-submit" id="registerBtn">Create Account</button>
        </div>
      </div>
    </div>
  </div>

  <?php include 'navbar.php'; ?>

  <section class="hero" aria-label="Featured">
    <div class="hero-slider" id="heroSlider">
      <?php foreach ($heroSlides as $i => $slide): ?>
        <div class="hero-slide<?= $i === 0 ? ' active' : '' ?>" data-slide-index="<?= $i ?>">
        <div class="hero-media">
          <picture>
            <?php if (!empty($slide['image_mobile'])): ?>
              <source media="(max-width: 600px)" srcset="<?= htmlspecialchars((string)$slide['image_mobile']) ?>">
            <?php endif; ?>
            <img src="<?= htmlspecialchars((string)$slide['image']) ?>" alt="<?= htmlspecialchars((string)($slide['title'] ?? $brandName)) ?>" loading="<?= $i === 0 ? 'eager' : 'lazy' ?>">
          </picture>
        </div>
          <?php if (!empty($slide['title']) || !empty($slide['subtitle']) || !empty($slide['button_text'])): ?>
            <div class="hero-copy">
              <?php if (!empty($slide['subtitle'])): ?>
                <div class="hero-sub"><?= htmlspecialchars((string)$slide['subtitle']) ?></div>
              <?php endif; ?>
              <?php if (!empty($slide['title'])): ?>
                <h1 class="hero-title"><?= htmlspecialchars((string)$slide['title']) ?></h1>
              <?php endif; ?>
              <?php if (!empty($slide['button_text'])): ?>
                <a href="<?= htmlspecialchars((string)($slide['button_link'] ?: '#')) ?>" class="hero-cta"><?= htmlspecialchars((string)$slide['button_text']) ?></a>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>

      <?php if ($search === '' && $category === ''): ?>
        <a href="shop.php" class="hero-shop-now">SHOP NOW</a>
      <?php endif; ?>

      <?php if (count($heroSlides) > 1): ?>
        <button class="hero-arrow hero-arrow--prev" id="heroPrev" aria-label="Previous slide">&#8592;</button>
        <button class="hero-arrow hero-arrow--next" id="heroNext" aria-label="Next slide">&#8594;</button>
        <div class="hero-dots" id="heroDots">
          <?php foreach ($heroSlides as $i => $slide): ?>
            <button class="hero-dot<?= $i === 0 ? ' active' : '' ?>" data-dot-index="<?= $i ?>" aria-label="Go to slide <?= $i + 1 ?>"></button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <?php if ($search !== '' || $category !== ''): ?>
  <div class="result-bar" role="status">
    <?php if ($category !== ''): ?>
      <span>Category: <strong><?= htmlspecialchars(strtoupper(str_replace('-', ' ', $category))) ?></strong></span>
    <?php endif; ?>
    <?php if ($search !== ''): ?>
      <span>Search: <strong><?= htmlspecialchars(strtoupper($search)) ?></strong></span>
    <?php endif; ?>
    <a href="index.php">Clear</a>
  </div>
  <?php endif; ?>

  <div class="shop-layout">
    
    <!-- CATEGORY NAV HORIZONTAL BAR -->
    <div class="category-nav-wrapper">
      <nav class="category-nav">
        <a href="shop.php" class="<?= ($category === '' && $search === '') ? 'active' : '' ?>">ALL</a>
        <?php foreach ($categories as $cat): ?>
          <a href="index.php?category=<?= urlencode((string)$cat['slug']) ?>" class="<?= $category === (string)$cat['slug'] ? 'active' : '' ?>"><?= htmlspecialchars(strtoupper((string)$cat['name'])) ?></a>
        <?php endforeach; ?>
      </nav>
    </div>

    <section class="product-section" id="products" aria-label="Products">

      <?php if (!$products): ?>
        <?php
          $emptyParts = [];
          if ($search   !== '') $emptyParts[] = 'matching "<strong>' . htmlspecialchars(strtoupper($search)) . '</strong>"';
          if ($category !== '') $emptyParts[] = 'in <strong>' . htmlspecialchars(strtoupper(str_replace('-', ' ', $category))) . '</strong>';
          $emptyMessage = !empty($emptyParts)
            ? 'No products found ' . implode(' ', $emptyParts) . '.'
            : 'No active products yet.';
        ?>
        <div class="empty-text"><?= $emptyMessage ?></div>
      <?php else: ?>
        <main class="product-grid">
          <?php foreach ($products as $product): ?>
            <?php
              $originalPrice = ddProductBasePrice($product);
              $finalPrice    = productFinalPrice($product);
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
                        <span class="price-original"><?= htmlspecialchars(ddFormatDisplay($originalPrice)) ?></span>
                        <span class="price-final"><?= htmlspecialchars(ddFormatDisplay($finalPrice)) ?></span>
                      <?php else: ?>
                        <span class="price-final"><?= htmlspecialchars(ddFormatDisplay($originalPrice)) ?></span>
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
  </div>

  <?php include 'footer.php'; ?>

  <div id="termsPopup" class="info-popup" role="dialog" aria-modal="true" aria-label="Terms and Conditions">
    <div class="info-popup__backdrop" id="termsBackdrop"></div>
    <div class="info-popup__dialog">
      <button class="popup-close" id="closeTermsBtn" aria-label="Close">×</button>
      <h2>Terms &amp; Conditions</h2>
      <div class="info-popup__content">
        <div class="info-section"><h3>General</h3><p>This website is operated by DeepDimes. By visiting our site and/or purchasing from us, you agree to be bound by the following Terms and Conditions.</p></div>
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


    // ── Helper aman: pasang addEventListener hanya jika elemen ada ──
    // Ini mencegah error "Cannot read properties of null (reading 'addEventListener')"
    // jika suatu saat ada id HTML yang berubah/hilang.
    function on(id, event, handler) {
      const el = document.getElementById(id);
      if (el) el.addEventListener(event, handler);
      return el;
    }
    function onAll(selector, event, handler) {
      document.querySelectorAll(selector).forEach(el => el.addEventListener(event, handler));
    }

    const body         = document.body;
    const overlay      = document.getElementById('overlay');
    const menuPanel    = document.getElementById('menuPanel');
    const cartPanel    = document.getElementById('cartPanel');
    const accountPopup = document.getElementById('accountPopup');

    // Keranjang mini bersama (assets/js/mini-cart.js)
    const cart      = DDMiniCart.create({ showToast });
    function saveCart()   { cart.save(); }
    function renderCart() { cart.render(); }

    function showToast(msg) {
      const container = document.getElementById('toastContainer');
      if (!container) return;
      const t = document.createElement('div');
      t.className = 'toast';
      t.textContent = msg;
      container.appendChild(t);
      requestAnimationFrame(() => { requestAnimationFrame(() => { t.classList.add('show'); }); });
      setTimeout(() => { t.classList.remove('show'); setTimeout(() => t.remove(), 250); }, 2600);
    }

    function setOverlay(active) {
      if (overlay) overlay.classList.toggle('active', active);
      body.classList.toggle('lock-scroll', active);
    }

    function anyPanelOpen() {
      const termsPopupEl = document.getElementById('termsPopup');
      const faqPopupEl   = document.getElementById('faqPopup');
      return (menuPanel && menuPanel.classList.contains('active')) ||
             (cartPanel && cartPanel.classList.contains('active')) ||
             (accountPopup && accountPopup.classList.contains('active')) ||
             (termsPopupEl && termsPopupEl.classList.contains('active')) ||
             (faqPopupEl && faqPopupEl.classList.contains('active'));
    }

    function closeAll() {
      if (menuPanel) menuPanel.classList.remove('active');
      if (cartPanel) cartPanel.classList.remove('active');
      if (accountPopup) accountPopup.classList.remove('active');
      const termsPopupEl = document.getElementById('termsPopup');
      const faqPopupEl   = document.getElementById('faqPopup');
      if (termsPopupEl) termsPopupEl.classList.remove('active');
      if (faqPopupEl) faqPopupEl.classList.remove('active');
    }

    function openMenu()  { closeAll(); if (menuPanel) menuPanel.classList.add('active');  setOverlay(true); }
    function openCart()  { closeAll(); if (cartPanel) cartPanel.classList.add('active');  setOverlay(true); }
    function openAccount() { closeAll(); if (accountPopup) accountPopup.classList.add('active'); setOverlay(true); }

    on('menuToggle', 'click', openMenu);
    on('closeMenu', 'click', () => { if (menuPanel) menuPanel.classList.remove('active'); if (!anyPanelOpen()) setOverlay(false); });
    on('openCartBtn', 'click', openCart);
    on('closeCart', 'click', () => { if (cartPanel) cartPanel.classList.remove('active'); if (!anyPanelOpen()) setOverlay(false); });
    on('openAccountBtn', 'click', openAccount);
    on('openAccountBtnMobile', 'click', openAccount);

    /* ── DROPDOWN TOGGLE LISTENER ─────────────────────── */
    onAll('.dropdown-toggle', 'click', function (e) {
      e.preventDefault();
      this.parentElement.classList.toggle('open');
      const expanded = this.getAttribute('aria-expanded') === 'true';
      this.setAttribute('aria-expanded', !expanded);
    });

    on('menuOpenAccountBtn', 'click', (e) => {
      e.preventDefault();
      openAccount();
    });

    on('closeAccountBtn', 'click', () => { if (accountPopup) accountPopup.classList.remove('active'); if (!anyPanelOpen()) setOverlay(false); });
    on('accountBackdrop', 'click', () => { if (accountPopup) accountPopup.classList.remove('active'); if (!anyPanelOpen()) setOverlay(false); });

    if (overlay) overlay.addEventListener('click', () => { closeAll(); setOverlay(false); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeAll(); setOverlay(false); } });

    onAll('.account-tab', 'click', function () {
      document.querySelectorAll('.account-tab').forEach(t => { t.classList.remove('active'); t.setAttribute('aria-selected','false'); });
      this.classList.add('active'); this.setAttribute('aria-selected','true');
      const target = this.dataset.tab;
      const tabLogin    = document.getElementById('tabLogin');
      const tabRegister = document.getElementById('tabRegister');
      if (tabLogin)    tabLogin.style.display    = target === 'login'    ? '' : 'none';
      if (tabRegister) tabRegister.style.display = target === 'register' ? '' : 'none';
    });

    on('loginBtn', 'click', function () { submitAccount('login'); });
    on('registerBtn', 'click', function () { submitAccount('register'); });

    function setAccountMsg(target, kind, text) {
      const el = document.getElementById(target);
      if (!el) return;
      el.className = 'account-msg ' + kind;
      el.textContent = text;
      el.style.display = 'block';
    }

    function setAccountSubmitDisabled(disabled) {
      const loginBtn    = document.getElementById('loginBtn');
      const registerBtn = document.getElementById('registerBtn');
      if (loginBtn) {
        loginBtn.disabled = disabled;
        loginBtn.textContent = disabled ? 'Signing In...' : 'Sign In';
      }
      if (registerBtn) {
        registerBtn.disabled = disabled;
        registerBtn.textContent = disabled ? 'Creating Account...' : 'Create Account';
      }
    }

    function validateEmail(value) {
      return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
    }

    function submitAccount(action) {
      const isLogin = action === 'login';
      const msgTarget = isLogin ? 'loginMsg' : 'registerMsg';

      const regNameEl = document.getElementById('regName');
      const name  = regNameEl ? regNameEl.value.trim() : '';

      const emailEl = document.getElementById(isLogin ? 'loginEmail' : 'regEmail');
      const email = emailEl ? emailEl.value.trim() : '';

      const passEl = document.getElementById(isLogin ? 'loginPassword' : 'regPassword');
      const pass  = passEl ? passEl.value : '';

      const confirmEl = document.getElementById('regPasswordConfirm');
      const confirm = confirmEl ? confirmEl.value : '';

      if (isLogin) {
        if (!email || !pass) return setAccountMsg(msgTarget, 'error', 'Please fill in all fields.');
        if (!validateEmail(email)) return setAccountMsg(msgTarget, 'error', 'Please enter a valid email address.');
      } else {
        if (!name || !email || !pass || !confirm) return setAccountMsg(msgTarget, 'error', 'Please complete all required fields.');
        if (!validateEmail(email)) return setAccountMsg(msgTarget, 'error', 'Please enter a valid email address.');
        if (pass.length < 6) return setAccountMsg(msgTarget, 'error', 'Password must be at least 6 characters.');
        if (pass !== confirm) return setAccountMsg(msgTarget, 'error', 'Password confirmation does not match.');
      }

      setAccountMsg(msgTarget, 'success', isLogin ? 'Signing in...' : 'Creating account...');
      setAccountSubmitDisabled(true);

      const bodyParams = new URLSearchParams();
      bodyParams.set('action', action);
      bodyParams.set('email', email);
      bodyParams.set('password', pass);
      if (!isLogin) {
        bodyParams.set('full_name', name);
        bodyParams.set('confirm_password', confirm);
      }

      fetch('auth_api.php', {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: bodyParams.toString(),
      })
      .then(r => r.json().catch(() => ({ ok: false, message: 'Unexpected server response.' })).then(data => ({ status: r.status, data })))
      .then(({ status, data }) => {
        if (!data || data.ok !== true) {
          setAccountMsg(msgTarget, 'error', (data && data.message) ? data.message : 'Something went wrong.');
          setAccountSubmitDisabled(false);
          return;
        }
        const redirect = (data.redirect && typeof data.redirect === 'string') ? data.redirect : 'index.php';
        setAccountMsg(msgTarget, 'success', isLogin ? 'Signed in. Refreshing...' : 'Account created. Refreshing...');
        setTimeout(() => { window.location.href = redirect; }, 450);
      })
      .catch(err => {
        console.error('[auth]', err);
        setAccountMsg(msgTarget, 'error', 'Network error. Please try again.');
        setAccountSubmitDisabled(false);
      });
    }

    const termsPopup = document.getElementById('termsPopup');
    const faqPopup   = document.getElementById('faqPopup');

    function openModal(modal)  { if (!modal) return; closeAll(); modal.classList.add('active');  setOverlay(true); }
    function closeModal(modal) { if (!modal) return; modal.classList.remove('active'); if (!anyPanelOpen()) setOverlay(false); }

    ['openTermsLink','footerTermsLink'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.addEventListener('click', e => { e.preventDefault(); openModal(termsPopup); });
    });
    ['openFaqLink','footerFaqLink'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.addEventListener('click', e => { e.preventDefault(); openModal(faqPopup); });
    });
    on('closeTermsBtn', 'click', () => closeModal(termsPopup));
    on('termsBackdrop', 'click', () => closeModal(termsPopup));
    on('closeFaqBtn', 'click',   () => closeModal(faqPopup));
    on('faqBackdrop', 'click',   () => closeModal(faqPopup));

    /* ── CART ──────────────────────────────────────────── */
    on('checkoutBtn', 'click', () => {
      window.location.href = 'checkout.php';
    });

    renderCart();

    /* ── HERO SLIDER ───────────────────────────────────── */
    (function () {
      const slides = document.querySelectorAll('#heroSlider .hero-slide');
      const dots   = document.querySelectorAll('#heroSlider .hero-dot');
      if (slides.length <= 1) return;

      let current = 0;
      let autoTimer = null;
      const AUTOPLAY_MS = 5000;

      function goTo(index) {
        slides[current].classList.remove('active');
        if (dots[current]) dots[current].classList.remove('active');
        current = (index + slides.length) % slides.length;
        slides[current].classList.add('active');
        if (dots[current]) dots[current].classList.add('active');
      }

      function nextSlide() { goTo(current + 1); }
      function prevSlide() { goTo(current - 1); }

      function startAutoplay() {
        stopAutoplay();
        autoTimer = setInterval(nextSlide, AUTOPLAY_MS);
      }
      function stopAutoplay() {
        if (autoTimer) { clearInterval(autoTimer); autoTimer = null; }
      }

      on('heroNext', 'click', () => { nextSlide(); startAutoplay(); });
      on('heroPrev', 'click', () => { prevSlide(); startAutoplay(); });

      dots.forEach(dot => {
        dot.addEventListener('click', () => {
          goTo(parseInt(dot.dataset.dotIndex, 10));
          startAutoplay();
        });
      });

      startAutoplay();
    })();

    /* ── PAYMENT POPUP ─────────────────────────────────── */
    (function () {
      const popup = document.getElementById('paymentPopup');
      if (!popup) return;

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
      on('closePaymentPopup', 'click', closePopup);
      on('closePaymentPopupSecondary', 'click', closePopup);
      if (backdrop) backdrop.addEventListener('click', closePopup);
    })();

  })();
  </script>
</body>
</html>