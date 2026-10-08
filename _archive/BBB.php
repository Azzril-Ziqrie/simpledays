<?php
session_start();

if (isset($_GET['currency']) && in_array(strtoupper($_GET['currency']), ['IDR', 'USD'])) {
    $_SESSION['currency'] = strtoupper($_GET['currency']);
}

// Default ke IDR jika belum ada session
$currentCurrency = $_SESSION['currency'] ?? 'IDR';

require_once __DIR__ . '/config/database.php';

// Inisialisasi variabel untuk navbar/menu (sama seperti index)
$search   = isset($_GET['search'])   ? trim((string) $_GET['search'])   : '';
$category = isset($_GET['category']) ? trim((string) $_GET['category']) : '';
if (strlen($search) > 100) { $search = substr($search, 0, 100); }

$brandName       = 'deepdimes';
$brandLogo       = '11newlogo.PNG';

// Ambil instance PDO
$pdo = function_exists('db') ? db() : $pdo;

/* ── helpers untuk menu categories ───────────────────── */
function tableExists(PDO $pdo, string $table): bool {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
        return (bool) $stmt->fetchColumn();
    } catch (Throwable $e) { return false; }
}

$categories = [];
if (tableExists($pdo, 'categories')) {
    try { 
        $catStmt = $pdo->query("SELECT name, slug FROM categories ORDER BY id ASC"); 
        $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC); 
    } catch (Throwable $e) {}
}

/* ── Mengambil Data Blog ─────────────────────────────── */
$query = "SELECT * FROM blogs ORDER BY created_at DESC";
$stmt = $pdo->query($query);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="assets/images/link.png">
  <title>FURTHER RESEARCH - <?= htmlspecialchars($brandName) ?></title>
  
  <style>
@import url('https://fonts.googleapis.com/css2?family=JetBrains+Mono:ital,wght@0,100..800;1,100..800&display=swap');
/* ── reset & core dari index ─────────────────────────── */
*{margin:0;padding:0;box-sizing:border-box}
html{scroll-behavior:smooth}
body{background:#fff;color:#111;font-family:'JetBrains Mono',monospace;text-transform:uppercase;animation:pageFadeIn .4s ease forwards;letter-spacing:.04em}
body.lock-scroll{overflow:hidden}
a{color:inherit;text-decoration:none}
img{display:block;width:100%}
button,input{font:inherit}
button{cursor:pointer}

/* ── NAVBAR ──────────────────────────────────────────── */
.navbar{width:100%;height:44px;background:#fff;display:flex;align-items:center;justify-content:space-between;padding:0 28px;position:sticky;top:0;z-index:60}
.nav-left{display:flex;align-items:center;gap:0}
.brand-mark img { height: 32px; width: auto; display: block; border-radius: 50%; }
.nav-center{position:absolute;left:50%;transform:translateX(-50%);display:flex;align-items:center}
.nav-right{display:flex;align-items:center;gap:28px;margin-left:auto}
.brand-mark{font-size:22px;font-weight:300;letter-spacing:.08em;color:#111;line-height:1}
.menu-icon-wrap{width:28px;height:12px;display:flex;flex-direction:column;justify-content:center;gap:5px;cursor:pointer;background:none;border:none}
.menu-icon-wrap span{display:block;height:1px;background:#111;width:100%}
.nav-btn{background:none;border:none;font-size:10px;letter-spacing:.12em;color:#111;cursor:pointer;text-transform:uppercase}

/* ── SEARCH ──────────────────────────────────────────── */
.search-form{display:flex;align-items:center;gap:4px}
.search-form input{background:transparent;border:none;outline:none;color:#111;width:80px;font-size:10px;text-transform:uppercase;letter-spacing:.08em}
.search-form input::placeholder{color:#aaa}
.search-form button{background:transparent;border:none;color:#111;font-size:12px;line-height:1;padding:0}

/* ── OVERLAY + MENU ──────────────────────────────────── */
.overlay{position:fixed;inset:0;background:rgba(0,0,0,.15);opacity:0;visibility:hidden;transition:opacity .2s,visibility .2s;z-index:70}
.overlay.active{opacity:1;visibility:visible}
.menu-panel{position:fixed;top:0;left:0;width:300px;height:100vh;background:#fff;transform:translateX(-100%);transition:transform .25s ease;z-index:80;display:flex;flex-direction:column}
.menu-panel.active{transform:translateX(0)}
.menu-head{height:44px;display:flex;align-items:center;justify-content:flex-end;padding:0 28px;flex-shrink:0}
.menu-close{background:none;border:none;font-size:24px;color:#111;line-height:1;font-weight:300;cursor:pointer}
.menu-body{display:flex;flex-direction:column;flex:1;padding:0 28px 40px;overflow-y:auto}

.menu-search-form{display:flex;align-items:center;border-bottom:1px solid #111;padding-bottom:8px;margin-bottom:32px;margin-top:10px}
.menu-search-form input{width:100%;border:none;outline:none;font-size:11px;text-transform:uppercase;letter-spacing:.08em;background:transparent;color:#111}
.menu-search-form input::placeholder{color:#aaa}
.menu-search-form button{background:transparent;border:none;font-size:16px;color:#111;cursor:pointer;padding:0 0 0 10px;line-height:1}

.menu-nav-primary{display:flex;flex-direction:column;gap:20px;margin-bottom:40px}
.menu-nav-primary a{font-size:10px;letter-spacing:.1em;color:#111;transition:opacity .15s}
.menu-nav-primary a:hover{opacity:.4}

/* DROPDOWN MENU */
.dropdown-toggle { background: none; border: none; font-size: 10px; letter-spacing: .1em; color: #111; text-transform: uppercase; cursor: pointer; display: flex; align-items: center; justify-content: space-between; width: 100%; text-align: left; font-family: inherit; padding: 0; transition: opacity .15s; }
.dropdown-toggle:hover { opacity: .4; }
.dropdown-toggle::after { content: '+'; font-size: 10px; font-weight: 300; transition: transform 0.25s ease; }
.dropdown.open .dropdown-toggle::after { transform: rotate(45deg); }
.dropdown-menu { display: none; flex-direction: column; gap: 16px; padding-left: 12px; margin-top: 16px; border-left: 1px solid #eee; margin-left: 4px; }
.dropdown.open .dropdown-menu { display: flex; animation: pageFadeIn .3s ease forwards; }

/* ── CART SIDEBAR ────────────────────────────────────── */
.cart-panel{position:fixed;top:0;right:0;width:360px;max-width:100%;height:100vh;background:#fff;transform:translateX(100%);transition:transform .25s ease;z-index:80;display:flex;flex-direction:column}
.cart-panel.active{transform:translateX(0)}
.cart-head{height:44px;display:flex;align-items:center;justify-content:space-between;padding:0 28px;flex-shrink:0;border-bottom:1px solid #f0f0f0}
.cart-title{font-size:10px;letter-spacing:.12em}
.cart-body{flex:1;overflow-y:auto;padding:24px 28px}
.cart-empty{display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;gap:10px;color:#bbb;font-size:10px;letter-spacing:.08em}
.cart-item{display:grid;grid-template-columns:60px 1fr auto;gap:16px;align-items:center;padding:16px 0;border-bottom:1px solid #f0f0f0}
.cart-item:last-child{border-bottom:none}
.cart-item-img{width:60px;height:76px;object-fit:cover;background:#f4f4f2}
.cart-item-name{font-size:10px;letter-spacing:.06em;margin-bottom:6px;line-height:1.4}
.cart-item-price{font-size:10px;color:#888;letter-spacing:.04em}
.cart-item-qty{display:flex;align-items:center;gap:10px;margin-top:8px}
.cart-item-qty button{width:18px;height:18px;border:1px solid #ddd;background:transparent;font-size:11px;display:flex;align-items:center;justify-content:center}
.cart-item-qty span{font-size:10px;min-width:14px;text-align:center}
.cart-item-remove{background:transparent;border:none;color:#ccc;font-size:14px;cursor:pointer;padding:2px;align-self:flex-start}
.cart-item-remove:hover{color:#111}
.cart-foot{padding:20px 28px;border-top:1px solid #f0f0f0;flex-shrink:0}
.cart-total{display:flex;justify-content:space-between;align-items:baseline;font-size:10px;margin-bottom:16px;letter-spacing:.08em}
.cart-total strong{font-size:12px;letter-spacing:.06em}
.cart-checkout-btn{display:block;width:100%;min-height:44px;background:#111;color:#fff;border:none;font-size:10px;letter-spacing:.14em;cursor:pointer}
.cart-checkout-btn:hover{background:#000}
.cart-checkout-btn:disabled{background:#ddd;cursor:not-allowed}

/* ── ACCOUNT MODAL ───────────────────────────────────── */
.account-popup{position:fixed;inset:0;z-index:100;display:flex;align-items:center;justify-content:center;padding:20px;pointer-events:none}
.account-popup.active{pointer-events:auto}
.account-popup__backdrop{position:absolute;inset:0;background:rgba(0,0,0,.2);opacity:0;transition:opacity .2s}
.account-popup.active .account-popup__backdrop{opacity:1}
.account-popup__dialog{position:relative;width:100%;max-width:380px;background:#fff;padding:40px 36px;opacity:0;transform:translateY(12px);transition:opacity .2s,transform .2s}
.account-popup.active .account-popup__dialog{opacity:1;transform:translateY(0)}
.account-popup__eyebrow{margin-bottom:6px;font-size:9px;letter-spacing:.18em;color:#aaa;text-align:center}
.account-popup__title{margin:0 0 28px;font-size:14px;font-weight:400;letter-spacing:.14em;text-align:center}
.account-tabs{display:flex;border-bottom:1px solid #eee;margin-bottom:24px}
.account-tab{flex:1;padding:10px;font-size:10px;letter-spacing:.1em;background:transparent;border:none;cursor:pointer;border-bottom:1px solid transparent;transition:border-color .15s,color .15s;color:#bbb}
.account-tab.active{border-bottom-color:#111;color:#111}
.account-form{display:flex;flex-direction:column;gap:10px}
.account-form input{width:100%;min-height:40px;padding:0 12px;border:1px solid #e8e8e8;background:#fff;font-size:11px;letter-spacing:.04em;outline:none;transition:border-color .15s;text-transform:none}
.account-form input:focus{border-color:#111}
.account-form input::placeholder{text-transform:uppercase;letter-spacing:.08em;font-size:10px;color:#ccc}
.account-submit{min-height:44px;background:#111;color:#fff;border:none;font-size:10px;letter-spacing:.14em;cursor:pointer;margin-top:4px}
.account-submit:hover{background:#000}
.account-msg{font-size:10px;text-align:center;padding:8px;text-transform:none;letter-spacing:.04em}
.account-msg.success{background:#f0f7f0;color:#2e7d32}
.account-msg.error{background:#fdf0f0;color:#c62828}
.popup-close{position:absolute;top:14px;right:14px;width:32px;height:32px;border:0;background:transparent;color:#111;font-size:20px;font-weight:300;line-height:1}

/* ── TOAST ───────────────────────────────────────────── */
.toast-container{position:fixed;bottom:24px;right:24px;z-index:200;display:flex;flex-direction:column;gap:6px;pointer-events:none}
.toast{background:#111;color:#fff;padding:10px 16px;font-size:10px;letter-spacing:.1em;opacity:0;transform:translateY(8px);transition:opacity .2s,transform .2s;pointer-events:none;max-width:260px}
.toast.show{opacity:1;transform:translateY(0)}

/* ── FOOTER ──────────────────────────────────────────── */
.footer{padding:28px;border-top:1px solid #f0f0f0}
.footer-bottom{display:flex;justify-content:center;align-items:center;gap:12px;flex-wrap:wrap}
.footer-copy{font-size:9px;color:#bbb;letter-spacing:.08em;text-transform:none;text-align:center;}

.footer-secondary { display: flex; flex-direction: column; align-items: flex-start; gap: 16px; padding: 24px 28px; border-top: 1px solid #f0f0f0; font-size: 10px; letter-spacing: .1em; }
.footer-secondary-left, .footer-secondary-right { display: flex; flex-direction: column; align-items: flex-start; gap: 16px; }
.footer-secondary a { color: #111; transition: opacity .15s; }
.footer-secondary a:hover { opacity: 0.6; }

/* ── KHUSUS BLOG / JOURNAL STYLES ────────────────────── */
.blog-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 60px 28px 100px;
}
.blog-header {
    text-align: center;
    font-size: 14px;
    font-weight: 300;
    letter-spacing: .2em;
    margin-bottom: 50px;
    color: #111;
}
.blog-grid {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 40px;
    max-width: 1000px;
    margin: 0 auto;
}
.blog-card {
    width: calc(50% - 20px);
    display: flex;
    flex-direction: column;
    text-align: center;
    text-decoration: none;
    color: inherit;
    opacity: 0;
    animation: pageFadeIn .5s ease forwards;
}
.blog-card:nth-child(even) { animation-delay: .1s; }
.blog-image {
    width: 100%;
    aspect-ratio: 4/3;
    background: #f7f7f5;
    overflow: hidden;
    margin-bottom: 20px;
    order: 1;
}
.blog-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform .4s ease;
}
.blog-card:hover .blog-image img {
    transform: scale(1.02);
}
.blog-title {
    font-size: 12px;
    font-weight: 400;
    letter-spacing: .1em;
    line-height: 1.5;
    margin: 0 0 8px;
    text-transform: uppercase;
    order: 0;
}
.blog-author {
    font-size: 9px;
    letter-spacing: .08em;
    color: #888;
    text-transform: uppercase;
    order: 2;
}

/* ── RESPONSIVE ──────────────────────────────────────── */
@media(max-width:900px){
  .blog-card { width: 100%; }
}

@media(max-width:560px){
  .navbar{padding:0 18px}
  .nav-right .search-form, .nav-right #openAccountBtn { display: none; }
  .footer, .blog-container, .footer-secondary { padding-left:18px; padding-right:18px; }
  .cart-body,.cart-foot,.cart-head{padding-left:18px;padding-right:18px}
  .account-popup__dialog{padding:32px 20px}
  .toast-container{right:12px;bottom:12px;left:12px}
  .toast{max-width:100%}
  .footer-bottom{flex-direction:column;align-items:center;text-align:center;gap:10px}
}

/* ── ANIMATIONS ──────────────────────────────────────── */
@keyframes pageFadeIn{from{opacity:0}to{opacity:1}}
@media(min-width:561px){ #openAccountBtnMobile{display:none} }
@media(max-width:560px){ #openAccountBtnMobile{display:block; text-align:left; width:100%; box-sizing:border-box;} }
  </style>
</head>
<body>
  <?php if (file_exists(__DIR__ . '/loader.php')) include_once 'loader.php'; ?>
  <div class="overlay" id="overlay"></div>
  <div class="toast-container" id="toastContainer" aria-live="polite"></div>

  <!-- MENU OFF-CANVAS -->
  <aside class="menu-panel" id="menuPanel" aria-label="Main menu">
    <div class="menu-head">
      <button class="menu-close" id="closeMenu" aria-label="Close menu">×</button>
    </div>
    
    <div class="menu-body">
      <form class="menu-search-form" action="index.php" method="GET" role="search">
        <input type="text" name="search" placeholder="SEARCH" value="" aria-label="Search products">
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
        <a href="research.php">FURTHER RESEARCH</a>
        <button class="nav-btn" id="openAccountBtnMobile" aria-label="Account">Account</button>

        <div class="dropdown" id="currencyDropdown">
          <button class="dropdown-toggle" id="currencyDropdownBtn" aria-expanded="false">Currency (<?= $currentCurrency ?>)</button>
          <div class="dropdown-menu">
            <a href="?currency=IDR">IDR</a>
            <a href="?currency=USD">USD</a>
          </div>
        </div>
      </nav>
    </div>
  </aside>

  <!-- CART OFF-CANVAS -->
  <aside class="cart-panel" id="cartPanel" aria-label="Shopping cart">
    <div class="cart-head">
      <div class="cart-title">Cart (<span id="cartCount">0</span>)</div>
      <button class="nav-btn" id="closeCart" aria-label="Close cart">×</button>
    </div>
    <div class="cart-body" id="cartBody">
      <div class="cart-empty"><span>Your cart is empty</span></div>
    </div>
    <div class="cart-foot" id="cartFoot" style="display:none">
      <div class="cart-total">
        <span>Total</span>
        <strong id="cartTotal">RP 0</strong>
      </div>
      <button class="cart-checkout-btn" id="checkoutBtn">Proceed to Checkout</button>
    </div>
  </aside>

  <!-- ACCOUNT POPUP -->
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

  <!-- NAVBAR -->
  <header class="navbar">
    <div class="nav-left">
      <button class="menu-icon-wrap" id="menuToggle" aria-label="Open menu">
        <span></span><span></span><span></span>
      </button>
    </div>

    <div class="nav-center">
      <a href="index.php" class="brand-mark">
        <img src="<?= htmlspecialchars($brandLogo) ?>" alt="<?= htmlspecialchars($brandName) ?>">
      </a>
    </div>

    <div class="nav-right">
      <form class="search-form" action="index.php" method="GET" role="search">
        <input type="text" name="search" placeholder="Search" value="" aria-label="Search products">
        <button type="submit" aria-label="Search">&#8981;</button>
      </form>
      <button class="nav-btn" id="openAccountBtn" aria-label="Account">Account</button>
      <button class="nav-btn" id="openCartBtn" aria-label="Cart">Cart <span id="navCartCount">0</span></button>
    </div>
  </header>

  <!-- CONTENT: BLOG GRID -->
  <main class="blog-container">
    <h1 class="blog-header"></h1>
    
    <div class="blog-grid">
      <!-- Looping PDO untuk artikel blog -->
      <?php while($row = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
          <a href="detail.php?id=<?= $row['id'] ?>" class="blog-card">
              <div class="blog-image">
                  <img src="<?= htmlspecialchars($row['main_image']) ?>" alt="<?= htmlspecialchars($row['title']) ?>" loading="lazy">
              </div>
              <h2 class="blog-title"><?= htmlspecialchars($row['title']) ?></h2>
              <?php if(!empty($row['author'])): ?>
                  <div class="blog-author"><?= htmlspecialchars($row['author']) ?></div>
              <?php endif; ?>
          </a>
      <?php endwhile; ?>
    </div>
  </main>

  <!-- FOOTER -->
  <div class="footer-secondary">
    <div class="footer-secondary-left">
      <a href="contact.php">CONTACT</a>
      <a href="stockist.php">STOCKIST</a>
    </div>
    <div class="footer-secondary-right">
      <a href="shipping.php">SHIPPING & RETURNS</a>
      <a href="legal.php">LEGAL</a>
    </div>
  </div>
  
  <footer class="footer" aria-label="Site footer">
    <div class="footer-bottom">
      <span class="footer-copy">© 2025 PASTPRESENTFURTHER - VAT: 0607250040631 - S.L Village Klapanunggal Bogor West Java, 16871 • Development by Cantum Studio</span>
    </div>
  </footer>

  <!-- SCRIPTS -->
  <script>
  (function () {
    'use strict';

    const CURRENT_CURRENCY = '<?= $currentCurrency ?>';

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

    let cartItems = JSON.parse(localStorage.getItem('ppf_cart') || '[]');

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
      return (menuPanel && menuPanel.classList.contains('active')) ||
             (cartPanel && cartPanel.classList.contains('active')) ||
             (accountPopup && accountPopup.classList.contains('active'));
    }

    function closeAll() {
      if (menuPanel) menuPanel.classList.remove('active');
      if (cartPanel) cartPanel.classList.remove('active');
      if (accountPopup) accountPopup.classList.remove('active');
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

    /* ── CART FUNCTIONALITY ──────────────────────────── */
    function saveCart() { localStorage.setItem('ppf_cart', JSON.stringify(cartItems)); }

    function formatCurrencyJS(amount) {
      if (CURRENT_CURRENCY === 'USD') { return '$' + parseFloat(amount).toFixed(2); }
      return 'RP ' + Math.round(amount).toLocaleString('id-ID');
    }

    function renderCart() {
      const cbody  = document.getElementById('cartBody');
      const foot   = document.getElementById('cartFoot');
      const count  = cartItems.reduce((s, i) => s + i.qty, 0);
      const total  = cartItems.reduce((s, i) => s + i.price * i.qty, 0);

      const cartCountEl    = document.getElementById('cartCount');
      const navCartCountEl = document.getElementById('navCartCount');
      if (cartCountEl)    cartCountEl.textContent    = count;
      if (navCartCountEl) navCartCountEl.textContent = count;

      if (!cbody || !foot) return;

      if (cartItems.length === 0) {
        cbody.innerHTML = '<div class="cart-empty"><span>Your cart is empty</span></div>';
        foot.style.display = 'none';
        return;
      }

      foot.style.display = '';
      const cartTotalEl = document.getElementById('cartTotal');
      if (cartTotalEl) cartTotalEl.textContent = formatCurrencyJS(total);

      cbody.innerHTML = cartItems.map((item, idx) => `
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

      cbody.querySelectorAll('[data-action]').forEach(btn => {
        btn.addEventListener('click', () => {
          const idx    = parseInt(btn.dataset.idx, 10);
          const action = btn.dataset.action;
          if (action === 'inc') {
            const stockLimit = parseInt(cartItems[idx].stock || '0', 10) || 0;
            if (stockLimit > 0 && cartItems[idx].qty >= stockLimit) { showToast('Out Of Stock'); return; }
            cartItems[idx].qty++;
          }
          else if (action === 'dec') { cartItems[idx].qty--; if (cartItems[idx].qty <= 0) cartItems.splice(idx, 1); }
          else if (action === 'remove') { cartItems.splice(idx, 1); }
          saveCart(); renderCart();
        });
      });
    }

    on('checkoutBtn', 'click', () => { window.location.href = 'checkout.php'; });

    renderCart();

  })();
  </script>
</body>
</html>