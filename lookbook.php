<?php
session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/db_schema.php';
require_once __DIR__ . '/includes/currency_helper.php';

$brandName = 'DEEPDIMES';
$brandLogo = 'assets/images/1212.png';

// Required by navbar.php (search form state)
$category = '';
$search   = '';

/* ── helpers ─────────────────────────────────────────── */
/* ── schema detection ────────────────────────────────── */
$tableReady = tableExists($pdo, 'lookbook_photos');

/* ── query photos grouped by year ────────────────────── */
$yearGroups = [];
if ($tableReady) {
    try {
        // Get distinct years + counts (descending)
        $yearStmt = $pdo->query(
            "SELECT year, COUNT(*) AS photo_count
             FROM lookbook_photos
             WHERE is_active = 1
             GROUP BY year
             ORDER BY year DESC"
        );
        $yearList = $yearStmt->fetchAll(PDO::FETCH_ASSOC);

        // For each year, fetch photos
        $photoStmt = $pdo->prepare(
            "SELECT id, year, photo_path, caption, sort_order
             FROM lookbook_photos
             WHERE year = :year AND is_active = 1
             ORDER BY sort_order ASC, id ASC"
        );

        // Year titles (one title per year, shown below the year heading)
        $yearTitles = [];
        if (tableExists($pdo, 'lookbook_year_titles')) {
            $titleRows = $pdo->query('SELECT year, title FROM lookbook_year_titles')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($titleRows as $tr) {
                $yearTitles[(int) $tr['year']] = $tr['title'];
            }
        }

        foreach ($yearList as $y) {
            $yearVal = (int) $y['year'];
            $photoStmt->execute(['year' => $yearVal]);
            $photos = $photoStmt->fetchAll(PDO::FETCH_ASSOC);
            $yearGroups[] = [
                'year'  => $yearVal,
                'count' => (int) $y['photo_count'],
                'title' => $yearTitles[$yearVal] ?? null,
                'photos' => $photos,
            ];
        }
    } catch (Throwable $e) {
        $yearGroups = [];
    }
}

/* ── categories (for Shop All dropdown) ──────────────── */
$categories = [];
$hasCategoriesTable = tableExists($pdo, 'categories');
$productColumns     = [];
try { $productColumns = $pdo->query("SHOW COLUMNS FROM products")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) {}
$hasCategoryId   = false;
$hasCategoryText = false;
foreach ($productColumns as $col) {
    if ($col['Field'] === 'category_id') $hasCategoryId = true;
    if ($col['Field'] === 'category')    $hasCategoryText = true;
}
$joinCategory = $hasCategoriesTable && $hasCategoryId;
if ($joinCategory) {
    try {
        $catStmt = $pdo->query("SELECT name, slug FROM categories ORDER BY id ASC");
        $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}
} elseif ($hasCategoryText) {
    try {
        $catStmt = $pdo->query("SELECT DISTINCT category AS name, LOWER(REPLACE(category, ' ', '-')) AS slug FROM products WHERE category IS NOT NULL AND category <> '' ORDER BY category ASC");
        $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="assets/images/link.png">
  <title><?= htmlspecialchars($brandName) ?> — Lookbook</title>
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

/* ── NAVBAR & FOOTER GLOBALS ─────────────────────────── */
.navbar{width:100%;height:70px;background:rgba(255,255,255,.72) !important;-webkit-backdrop-filter:blur(20px) saturate(180%);backdrop-filter:blur(20px) saturate(180%);display:flex;align-items:center;justify-content:space-between;padding:0 30px;position:sticky;top:0;left:0;z-index:60;box-shadow:0 1px 0 rgba(0,0,0,.06); color: #111;}
.nav-left{display:flex;align-items:center;gap:10px}
.nav-left-label{font-size:11px;letter-spacing:.1em; font-weight: 600;}
.brand-mark img { height: 70px; width: auto; display: block; border-radius: 50%; }
.nav-center{position:absolute;left:50%;transform:translateX(-50%);display:flex;align-items:center}
.nav-right{display:flex;align-items:center;gap:25px;margin-left:auto}
.brand-mark{font-size:24px;font-weight:700;letter-spacing:.05em;line-height:1; color:#111;}
.menu-icon-wrap{display:flex;align-items:center;gap:8px;cursor:pointer;background:none;border:none}
.menu-icon-wrap span{display:none}
.menu-icon-wrap::before{content:'MENU';font-size:11px;letter-spacing:.1em;color:#111; font-weight:600;}
.nav-btn{background:none;border:none;font-size:11px;letter-spacing:.1em;color:#111;cursor:pointer;text-transform:uppercase; font-weight: 600;}
.nav-count{font-size:11px;letter-spacing:.08em}

.search-form{display:flex;align-items:center;gap:4px}
.search-form input{background:transparent;border:none;outline:none;color:#111;width:80px;font-size:11px;text-transform:uppercase;letter-spacing:.08em; font-weight: 500;}
.search-form input::placeholder{color:#999}
.search-form button{background:transparent;border:none;color:#111;font-size:14px;line-height:1;padding:0}

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
.dropdown-toggle { background: none; border: none; font-size: 11px; letter-spacing: .1em; color: #111; font-weight: 600; text-transform: uppercase; cursor: pointer; display: flex; align-items: center; justify-content: space-between; width: 100%; text-align: left; font-family: inherit; padding: 0; transition: opacity .15s; }
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

/* ── TOAST ───────────────────────────────────────────── */
.toast-container{position:fixed;bottom:30px;right:30px;z-index:200;display:flex;flex-direction:column;gap:8px;pointer-events:none}
.toast{background:#111;color:#fff;padding:12px 20px;font-size:11px; font-weight: 600; letter-spacing:.08em;opacity:0;transform:translateY(10px);transition:opacity .2s,transform .2s;pointer-events:none;max-width:300px}
.toast.show{opacity:1;transform:translateY(0)}

/* ── YEAR JUMP ANCHORS ───────────────────────────────── */
.year-jump{display:flex;gap:20px;flex-wrap:wrap;padding:25px 40px;border-bottom:1px solid #eee; background: #fff;}
.year-jump a{font-size:12px; font-weight: 700; letter-spacing:.1em;color:#888;text-decoration:none;transition:color .2s}
.year-jump a:hover{color:#111;}

/* ── LOOKBOOK PER-YEAR ───────────────────────────────── */
.lookbook-year{position:relative;padding:60px 40px 80px;border-bottom:1px solid #eee; background: #fff;}
.year-head{display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:30px;}
.year-head h2{margin:0;font-size:48px;font-weight:900;letter-spacing:-0.02em;color:#111;line-height:1}
.year-head .year-title{margin:10px 0 0;font-size:12px;letter-spacing:.05em;color:#777;text-transform:uppercase;font-weight:600}

.photo-strip{display:flex;align-items:flex-start;gap:20px;overflow-x:auto;scroll-snap-type:x mandatory;scroll-behavior:smooth;-webkit-overflow-scrolling:touch;padding:10px 0 20px;scrollbar-width:none}
.photo-strip::-webkit-scrollbar{display:none}

.photo-tile{flex:0 0 auto;height:550px;max-width:80vw;overflow:hidden;background:#f9f9f9;scroll-snap-align:start;position:relative;display:flex;align-items:center;justify-content:center}
.photo-tile img{width:auto;height:100%;max-width:100%;object-fit:contain;object-position:center;transition:transform .4s ease,opacity .3s ease}
.photo-tile:hover img{transform:scale(1.02)}

.strip-wrap{position:relative}
.scroll-arrow{position:absolute;top:50%;margin-top:-22px;width:44px;height:44px;border:1px solid rgba(17,17,17,.2);background:rgba(255,255,255,.7);-webkit-backdrop-filter:blur(12px);backdrop-filter:blur(12px);color:#111;font-size:18px;font-weight:bold;cursor:pointer;z-index:10;display:flex;align-items:center;justify-content:center;transition:background-color .2s,color .2s,opacity .2s}
.scroll-arrow:hover{background:#111;color:#fff}
.scroll-arrow--left{left:20px}
.scroll-arrow--right{right:20px}
.scroll-arrow[disabled]{opacity:0; pointer-events:none;}

.empty-state{padding:150px 40px;text-align:center;color:#aaa;font-size:12px; font-weight: 600; letter-spacing:.08em}
.empty-state .icon{font-size:42px;margin-bottom:16px;opacity:.5;display:block}

/* ── FOOTER STYLING ──────────────────────────────────────────── */
.footer{padding:60px 40px 40px;border-top:1px solid #eee; text-align: center;}
.footer-shopall{margin-bottom:30px;text-align:center}
.footer-shopall a{font-size:11px;font-weight:600;letter-spacing:.1em;color:#111;transition:opacity .15s}
.footer-shopall a:hover{opacity:.5}
.footer-social{display:flex;justify-content:center;gap:20px;margin-bottom:30px}
.footer-social a{font-size:11px;font-weight:600;letter-spacing:.1em;color:#111;transition:opacity .15s}
.footer-social a:hover{opacity:.5}
.footer-links-row{display:flex;justify-content:center;gap:20px;flex-wrap:wrap;margin-bottom:40px}
.footer-links-row a{font-size:11px;font-weight:500;letter-spacing:.05em;color:#888;text-transform:uppercase;transition:color .15s}
.footer-links-row a:hover{color:#111}
.footer-secondary{display:flex;justify-content:space-between;align-items:center;padding:20px 0;border-top:1px solid #eee;margin-bottom:30px}
.footer-secondary{display:none}
.footer-secondary-left, .footer-secondary-right{display:flex;gap:20px}
.footer-secondary a{font-size:11px;font-weight:600;letter-spacing:.1em;color:#111;transition:opacity .15s}
.footer-secondary a:hover{opacity:.5}
.footer-bottom{display:flex;justify-content:center;align-items:center;gap:15px;flex-wrap:wrap; margin-top: 20px;}
.footer-copy{font-size:11px; font-weight: 500; color:#888;letter-spacing:.02em;text-transform:none;text-align:center;}

/* ── RESPONSIVE ──────────────────────────────────────── */
@media(max-width:900px){
  .photo-tile{height:450px;max-width:85vw}
}

@media(max-width:600px){
  .navbar{padding:0 20px}
  .nav-right .search-form,
  .nav-right #openAccountBtn { display: none; }
  .lookbook-year, .year-jump, .footer{padding-left:20px;padding-right:20px}
  .photo-tile{height:65vh;max-width:92vw}
  .scroll-arrow{display:none}
  .cart-body,.cart-foot,.cart-head{padding-left:20px;padding-right:20px}
  .account-popup__dialog{padding:35px 25px}
  .toast-container{right:15px;bottom:15px;left:15px}
  .toast{max-width:100%}
  .year-head h2{font-size:36px}
  .footer-bottom{flex-direction:column;align-items:center;text-align:center;gap:10px}
}

/* ── ANIMATIONS ──────────────────────────────────────── */
@keyframes pageFadeIn{from{opacity:0}to{opacity:1}}
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
</head>
<body>
  <?php if (file_exists(__DIR__ . '/loader.php')) include_once 'loader.php'; ?>
  <div class="overlay" id="overlay"></div>

  <div class="toast-container" id="toastContainer" aria-live="polite"></div>

  <aside class="menu-panel" id="menuPanel" aria-label="Main menu">
    <div class="menu-head">
      <button class="menu-close" id="closeMenu" aria-label="Close menu">×</button>
    </div>

    <div class="menu-body">
      <form class="menu-search-form" action="index.php" method="GET" role="search">
        <input type="text" name="search" placeholder="SEARCH" aria-label="Search products">
        <button type="submit" aria-label="Search">&#8594;</button>
      </form>

      <nav class="menu-nav-primary">
        <div class="dropdown" id="shopDropdown">
          <button class="dropdown-toggle" id="shopDropdownBtn" aria-expanded="false">Shop All</button>
          <div class="dropdown-menu" id="shopDropdownMenu">
            <a href="shop.php">All</a>
            <?php foreach ($categories as $cat): ?>
              <a href="index.php?category=<?= urlencode((string)$cat['slug']) ?>"><?= htmlspecialchars((string)$cat['name']) ?></a>
            <?php endforeach; ?>
          </div>
        </div>
        <button class="nav-btn" id="openAccountBtnMobile" aria-label="Account" style="color: #111;">Account</button>
        <a href="lookbook.php">LookBook</a>
        <a href="index.php?choose_currency=1">Currency (<?= htmlspecialchars(ddDisplayCurrency()) ?>)</a>
        </nav>

      <nav class="menu-nav-secondary">
        <a href="contact.php">CONTACT</a>
        <a href="about.php">ABOUT</a>
        <a href="shipping.php">SHIPPING</a>
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

  <?php include 'navbar.php'; ?>

  <?php if (count($yearGroups) >= 2): ?>
  <nav class="year-jump" aria-label="Jump to year">
    <?php foreach ($yearGroups as $group): ?>
      <a href="#year-<?= (int) $group['year'] ?>"><?= (int) $group['year'] ?></a>
    <?php endforeach; ?>
  </nav>
  <?php endif; ?>

  <?php if (!$tableReady): ?>
    <div class="empty-state">
      <span class="icon">○</span>
      Lookbook coming soon.
    </div>
  <?php elseif (!$yearGroups): ?>
    <div class="empty-state">
      <span class="icon">○</span>
      No lookbook photos yet.
    </div>
  <?php else: ?>
    <?php foreach ($yearGroups as $group): ?>
      <section class="lookbook-year" id="year-<?= (int) $group['year'] ?>" aria-label="Lookbook <?= (int) $group['year'] ?>">
        <div class="year-head">
          <div>
            <h2><?= (int) $group['year'] ?></h2>
            <?php if (!empty($group['title'])): ?>
              <p class="year-title"><?= htmlspecialchars($group['title']) ?></p>
            <?php endif; ?>
          </div>
        </div>

        <div class="strip-wrap">
        <button type="button" class="scroll-arrow scroll-arrow--left" aria-label="Previous photos">←</button>
        <div class="photo-strip">
          <?php foreach ($group['photos'] as $photo): ?>
            <a class="photo-tile" href="<?= htmlspecialchars((string)$photo['photo_path']) ?>" target="_blank" rel="noopener">
              <img src="<?= htmlspecialchars((string)$photo['photo_path']) ?>" alt="Lookbook <?= (int) $group['year'] ?>" loading="lazy">
            </a>
          <?php endforeach; ?>
        </div>
        <button type="button" class="scroll-arrow scroll-arrow--right" aria-label="Next photos">→</button>
        </div>
      </section>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php include 'footer.php'; ?>

  <script>
  (function () {
    'use strict';


    const body         = document.body;
    const overlay      = document.getElementById('overlay');
    const menuPanel    = document.getElementById('menuPanel');
    const cartPanel    = document.getElementById('cartPanel');
    const accountPopup = document.getElementById('accountPopup');

    // Keranjang mini bersama (assets/js/mini-cart.js)
    const cart      = DDMiniCart.create({ showToast });
    const cartItems = cart.items;
    function saveCart()   { cart.save(); }
    function renderCart() { cart.render(); }

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
             accountPopup.classList.contains('active');
    }

    function closeAll() {
      menuPanel.classList.remove('active');
      cartPanel.classList.remove('active');
      accountPopup.classList.remove('active');
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

    const openAccountBtnMobile = document.getElementById('openAccountBtnMobile');
    if (openAccountBtnMobile) openAccountBtnMobile.addEventListener('click', e => { e.preventDefault(); openAccount(); });

    const menuOpenAccountBtn = document.getElementById('menuOpenAccountBtn');
    if (menuOpenAccountBtn) menuOpenAccountBtn.addEventListener('click', e => { e.preventDefault(); openAccount(); });

    const menuOpenCartBtn = document.getElementById('menuOpenCartBtn');
    if (menuOpenCartBtn) menuOpenCartBtn.addEventListener('click', e => { e.preventDefault(); openCart(); });


    /* Dropdowns */
    document.querySelectorAll('.dropdown-toggle').forEach(btn => {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        this.parentElement.classList.toggle('open');
        const expanded = this.getAttribute('aria-expanded') === 'true';
        this.setAttribute('aria-expanded', !expanded);
      });
    });

    document.getElementById('closeAccountBtn').addEventListener('click', () => { accountPopup.classList.remove('active'); if (!anyPanelOpen()) setOverlay(false); });
    document.getElementById('accountBackdrop').addEventListener('click', () => { accountPopup.classList.remove('active'); if (!anyPanelOpen()) setOverlay(false); });

    overlay.addEventListener('click', () => { closeAll(); setOverlay(false); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeAll(); setOverlay(false); } });

    /* Account tabs */
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

    /* Cart */
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

    renderCart();

    /* Photo strip scroll arrows */
    document.querySelectorAll('.lookbook-year').forEach(section => {
      const strip = section.querySelector('.photo-strip');
      const left  = section.querySelector('.scroll-arrow--left');
      const right = section.querySelector('.scroll-arrow--right');
      if (!strip || !left || !right) return;

      function tileWidth() {
        const tile = strip.querySelector('.photo-tile');
        if (!tile) return 320;
        const styles = window.getComputedStyle(strip);
        const gap = parseFloat(styles.columnGap || styles.gap || '0') || 0;
        return tile.getBoundingClientRect().width + gap;
      }

      function updateArrows() {
        left.disabled  = strip.scrollLeft <= 1;
        right.disabled = strip.scrollLeft + strip.clientWidth >= strip.scrollWidth - 1;
      }

      left.addEventListener('click', () => { strip.scrollBy({ left: -tileWidth(), behavior: 'smooth' }); });
      right.addEventListener('click', () => { strip.scrollBy({ left:  tileWidth(), behavior: 'smooth' }); });
      strip.addEventListener('scroll', updateArrows);
      window.addEventListener('resize', updateArrows);
      // initial state after layout
      requestAnimationFrame(updateArrows);
    });

  })();
  </script>
</body>
</html>