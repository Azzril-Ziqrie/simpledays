<?php
session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/db_schema.php';
require_once __DIR__ . '/includes/currency_helper.php';

$search   = isset($_GET['search'])   ? trim((string) $_GET['search'])   : '';
$category = isset($_GET['category']) ? trim((string) $_GET['category']) : '';

// Batasi panjang search supaya tidak bikin URL/Query panjang & tidak rentan error
if (strlen($search) > 100) { $search = substr($search, 0, 100); }

$brandName       = 'deepdimes';
$brandLogo       = '11newlogo.PNG';

/* ── helpers ─────────────────────────────────────────── */
/* ── schema detection ────────────────────────────────── */
$productColumns     = tableColumns($pdo, 'products');
$hasCategoriesTable = tableExists($pdo, 'categories');
$hasCategoryId      = in_array('category_id',  $productColumns, true);
$hasCategoryText    = in_array('category',      $productColumns, true);

$joinCategory   = $hasCategoriesTable && $hasCategoryId;

/* ── categories (untuk menu navbar) ──────────────────── */
$categories = [];
if ($joinCategory) {
    try { $catStmt = $pdo->query("SELECT name, slug FROM categories ORDER BY id ASC"); $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) {}
} elseif ($hasCategoryText) {
    try { $catStmt = $pdo->query("SELECT DISTINCT category AS name, LOWER(REPLACE(category, ' ', '-')) AS slug FROM products WHERE category IS NOT NULL AND category <> '' ORDER BY category ASC"); $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) {}
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Shipping & Returns - <?= htmlspecialchars($brandName) ?></title>
  <style>
@import url('https://fonts.googleapis.com/css2?family=JetBrains+Mono:ital,wght@0,100..800;1,100..800&display=swap');
/* ── reset ───────────────────────────────────────────── */
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
.nav-count{font-size:10px;letter-spacing:.08em}

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
.dropdown-toggle {
  background: none; border: none; font-size: 10px; letter-spacing: .1em; color: #111; 
  text-transform: uppercase; cursor: pointer; display: flex; align-items: center; 
  justify-content: space-between; width: 100%; text-align: left; font-family: inherit;
  padding: 0; transition: opacity .15s;
}
.dropdown-toggle:hover { opacity: .4; }
.dropdown-toggle::after { content: '+'; font-size: 10px; font-weight: 300; transition: transform 0.25s ease; }
.dropdown.open .dropdown-toggle::after { transform: rotate(45deg); }
.dropdown-menu { display: none; flex-direction: column; gap: 16px; padding-left: 12px; margin-top: 16px; border-left: 1px solid #eee; margin-left: 4px; }
.dropdown.open .dropdown-menu { display: flex; animation: pageFadeIn .3s ease forwards; }

.menu-nav-secondary{display:flex;flex-direction:column;gap:16px;margin-top:auto;padding-top:20px}
.menu-nav-secondary a{font-size:10px;letter-spacing:.1em;color:#888;transition:color .15s}
.menu-nav-secondary a:hover{color:#111}

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
.footer{padding:28px;border-top:1px solid #f0f0f0;margin-top:auto;}
.footer-bottom{display:flex;justify-content:center;align-items:center;gap:12px;flex-wrap:wrap}
.footer-copy{font-size:9px;color:#bbb;letter-spacing:.08em;text-transform:none;text-align:center;}

/* ── KHUSUS HALAMAN LEGAL/SHIPPING ───────────────────── */
body { display: flex; flex-direction: column; min-height: 100vh; }
.legal-wrapper { max-width: 820px; margin: 60px auto 80px; padding: 0 28px; }
.legal-header { text-align: center; margin-bottom: 50px; }
.legal-header h1 { font-size: 14px; font-weight: 400; letter-spacing: 0.14em; }
.legal-section { margin-bottom: 40px; }
.legal-section h2 { font-size: 10px; letter-spacing: 0.14em; text-transform: uppercase; margin-bottom: 14px; font-weight: 700; color: #111; }
.legal-section p, .legal-section li { font-size: 11px; line-height: 1.8; letter-spacing: 0.04em; color: #555; text-transform: none; margin-bottom: 12px; }
.legal-section ul { padding-left: 20px; margin-bottom: 12px; }
.legal-section a { text-decoration: underline; text-underline-offset: 3px; color: #111; }
.legal-section a:hover { opacity: 0.6; }
.legal-version { font-size: 10px; color: #888; margin-top: 60px; text-transform: none; text-align: left; font-style: italic; }
/* ── FOOTER SECONDARY NAV ───────────────────────────────────── */
.footer-secondary {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 16px;
  padding: 24px 28px;
  border-top: 1px solid #f0f0f0;
  font-size: 10px;
  letter-spacing: .1em;
}

.footer-secondary-left,
.footer-secondary-right {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 16px;
}

.footer-secondary a {
  color: #111;
  transition: opacity .15s;
}

.footer-secondary a:hover {
  opacity: 0.6;
}

/* ── RESPONSIVE ──────────────────────────────────────── */
@media(max-width:560px){
  .navbar{padding:0 18px}
  .nav-right .search-form, 
  .nav-right #openAccountBtn { display: none; }
  .footer{padding-left:18px;padding-right:18px}
  .cart-body,.cart-foot,.cart-head{padding-left:18px;padding-right:18px}
  .account-popup__dialog{padding:32px 20px}
  .toast-container{right:12px;bottom:12px;left:12px}
  .toast{max-width:100%}
  .footer-bottom{flex-direction:column;align-items:center;text-align:center;gap:10px}
  
  .legal-wrapper { margin: 40px auto 60px; padding: 0 18px; }
  .legal-header { margin-bottom: 40px; }
}
@keyframes pageFadeIn{from{opacity:0}to{opacity:1}}
  </style>
  <script><?= ddDisplayJs() ?></script>
  <script src="assets/js/mini-cart.js"></script>
</head>
<body>
  <?php if (file_exists(__DIR__ . '/loader.php')) include_once 'loader.php'; ?>
  <div class="overlay" id="overlay"></div>

  <div class="toast-container" id="toastContainer" aria-live="polite"></div>

  <!-- MENU SIDEBAR -->
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
        <a href="research.php">Further Research</a>
        
      </nav>

    
    </div>
  </aside>

  <!-- CART SIDEBAR -->
  <aside class="cart-panel" id="cartPanel" aria-label="Shopping cart">
    <div class="cart-head">
      <div class="cart-title">Cart (<span id="cartCount">0</span>)</div>
      <button class="nav-btn" id="closeCart" aria-label="Close cart">×</button>
    </div>
    <div class="cart-body" id="cartBody">
      <div class="cart-empty">
        <span>Your cart is empty</span>
      </div>
    </div>
    <div class="cart-foot" id="cartFoot" style="display:none">
      <div class="cart-total">
        <span>Total</span>
        <strong id="cartTotal">$0.00</strong>
      </div>
      <button class="cart-checkout-btn" id="checkoutBtn">Proceed to Checkout</button>
    </div>
  </aside>

  <!-- ACCOUNT MODAL -->
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

  <!-- HEADER NAVBAR -->
  <header class="navbar">
    <div class="nav-left">
      <button class="menu-icon-wrap" id="menuToggle" aria-label="Open menu">
        <span></span>
        <span></span>
        <span></span>
      </button>
    </div>

    <div class="nav-center">
      <a href="index.php" class="brand-mark">
        <img src="<?= htmlspecialchars($brandLogo) ?>" alt="<?= htmlspecialchars($brandName) ?>">
      </a>
    </div>

    <div class="nav-right">
      <form class="search-form" action="index.php" method="GET" role="search">
        <input type="text" name="search" placeholder="Search" value="<?= htmlspecialchars($search) ?>" aria-label="Search products">
        <button type="submit" aria-label="Search">&#8981;</button>
      </form>
      <button class="nav-btn" id="openAccountBtn" aria-label="Account">Account</button>
      <button class="nav-btn" id="openCartBtn" aria-label="Cart">Cart <span id="navCartCount">0</span></button>
    </div>
  </header>

  <!-- SHIPPING CONTENT -->
  <main class="legal-wrapper">
    <div class="legal-header">
    </div>

    <section class="legal-section">
      <p>All sales are final. We do not offer refunds under any circumstances.</p>
    </section>

    <section class="legal-section">
      <h2>Domestic Shipping (Indonesia)</h2>
      <p>All domestic orders within Indonesia are shipped via JNT/JNE Express.</p>
      <ul>
        <li>Processing Time: 1–3 business days</li>
        <li>Delivery Time: 1–3 business days after dispatch</li>
        <li>Shipping Fee: Calculated at checkout</li>
      </ul>
    </section>

    <section class="legal-section">
      <h2>International Shipping (ASIA, USA, EU)</h2>
      <p>For orders placed outside of Indonesia, international checkout is powered by Paypal.</p>
      <ul>
        <li>Courier: DHL Express</li>
        <li>Processing Time: 1–3 business days</li>
        <li>Delivery Time: 3–10 business days, depending on the destination</li>
        <li>Shipping terms: Delivered Duty Paid (DDP)</li>
        <li>Shipping Fee: Calculated at checkout</li>
        <li>Free Shipping: Thresholds vary by region and are shown at checkout</li>
        <li>Customs & Duties: Included at checkout (Delivered Duty Paid)</li>
      </ul>
      <p>Customers from eligible countries will not be charged any additional fees upon delivery unless otherwise stated.</p>
      <p>You will receive a shipping confirmation and tracking number by email once your order has been dispatched.</p>
      <p><strong>Standard International:</strong> USA, Canada, Mexico, Austria, Belgium, France, Germany, Ireland, Luxembourg, Netherlands, Portugal, Spain, Sweden, Andorra, Liechtenstein, Croatia, Cyprus, Czechia, Greece, Hungary, Romania, Denmark, Finland, Italy, Sweden, Åland Islands, Albania, Armenia, Belarus, Bosnia & Herzegovina, Bouvet Island, Bulgaria, Estonia, Faroe Islands, Georgia, Gibraltar, Greenland, Guadeloupe, Guernsey, Iceland, Isle of Man, Jersey, Kosovo, Latvia, Lithuania, Malta, Mayotte, Moldova, Montenegro, North Macedonia, Poland, Réunion, San Marino, Serbia, Slovakia, Slovenia, Svalbard & Jan Mayen, Turkey, Ukraine, Vatican City†, China, Hong Kong SAR, Malaysia, Singapore, South Korea, Japan, Taiwan, Thailand, Vietnam</p>
      <p><strong>Rest of the world:</strong> 10–15 working days | Standard International</p>
      <p>Delivery times provided are estimates to be used as a guideline only. PASTPRESENTFURTHER cannot be held responsible for unforeseen delays due to weather conditions, busy holiday periods or customs delays.</p>
    </section>

    <section class="legal-section">
      <h2>Duties and Import Taxes</h2>
      <p>If your delivery address is outside the Indonesia, you may need to cover customs/duty and import taxes to receive your order. We recommend reaching out to your local customs office for detailed information and cost estimates prior to placing your order. Please be aware that any delays in paying these customs fees could result in a postponed delivery of your order. For orders delivered within the Indonesia order, PASTPRESENTFURTHER will manage all VAT charges, ensuring that there are no additional fees upon delivery.</p>
    </section>

    <section class="legal-section">
      <h2>Return & Exchanges Policy</h2>
      <p>We are happy to offer an exchange or refund on any unsuitable items within 14 days of receipt, provided they are unworn and in resale-able condition, with all original labels and swing tickets attached. Refunds exclude all shipping costs.</p>
      <p>Exchanges are only offered for the same style in a different size or colour. If you wish to exchange for a different style we ask that you request a refund and place a new order for the new item.</p>
      <p>You may request a return or exchange within 7 days of receiving your order, provided the item is:</p>
      <ul>
        <li>Original condition</li>
        <li>Unworn and unused</li>
        <li>With original tags attached</li>
        <li>In original packaging</li>
      </ul>
      <p>Requests made beyond this period, or for items that are used, damaged, or missing tags, will not be accepted.</p>
      <p>To initiate a return or exchange, please contact us at <a href="mailto:studio@pastpresentfurther.com">studio@pastpresentfurther.com</a> with your order number and reason for return. We will provide further instructions upon approval.</p>
      <p>Please fill in the exchange form below:</p>
      <ul>
        <li>Full Name:</li>
        <li>Address:</li>
        <li>Tracking Number:</li>
        <li>Items to Exchange:</li>
        <li>Reason for Exchange:</li>
      </ul>
      <p>Our team will confirm the return address upon approval. Please send the item within 3 days of confirmation.</p>
      <p>Return shipping fees are deducted from the refund in the following cases:</p>
      <ul>
        <li>Change-of-mind or customer error: Full round-trip shipping cost</li>
        <li>Partial return from multiple items: Return shipping cost</li>
      </ul>
      <p>Please note that we cannot accept returns of sale items in-store. PASTPRESENTFURTHER is not liable for any shipping expenses related to product returns. Refunds will be processed as quickly as possible, but no later than 30 business days after the store receives the returned products. Refunds will be credited back to the original credit or debit card used for the purchase. Additionally, PASTPRESENTFURTHER will not refund any duties, taxes, or other charges that may arise.</p>
      <p>For exchanges, there will be no extra shipping fees imposed by PASTPRESENTFURTHER after returning the items. However, any duties, taxes, or additional costs incurred will not be refunded. During sales or promotional events, the same policy applies, and exchanges will not be accepted.</p>
      <p>Package your return items securely and post the parcel back to us using a recorded courier service. Include a short note with your order number, the RAN number we emailed to you, and your preference for an exchange.</p>
      <p>Please ensure that the goods are clearly marked as “Return” on any customs paperwork and declare the correct value of the items. Failure to mark them correctly or declaring a zero value may result in delays or additional import duties.</p>
    </section>

    <section class="legal-section">
      <h2>Exchanges</h2>
      <p>You may request to return your order within 14 days of receiving your package. Unfortunately, we do not offer exchanges. If you wish to choose a different size or item, you will need to place a new order. Returned PASTPRESENTFURTHER products must be sent back to the PASTPRESENTFURTHER online store’s return address in perfect condition. The items should not have been worn, used, or washed. Returns will only be accepted if the products are returned in their original packaging, fully intact, with all original labels attached. Additionally, the return slip included with your shipment must be completed and enclosed with the returned products.</p>
    </section>

    <section class="legal-section">
      <h2>Cost for Return</h2>
      <p>Please note that you are responsible for covering the return shipping costs. Additionally, customs duties and the original shipping charges (if applicable) are non-refundable. If a return is refused, you will be responsible for the cost of shipping the item back to you. Once we receive your return, we will process the refund within 5–7 working days.</p>
    </section>

    <section class="legal-section">
      <h2>Currency Rates</h2>
      <p>PASTPRESENTFURTHER is not responsible for any fluctuations in currency or the exchange rates applied by banks when converting USD into other currencies.</p>
    </section>
    

    <p class="legal-version">Version - This Shipping & Return Policy was last updated in January, 2026.</p>
  </main>

    <div class="footer-secondary">
    <div class="footer-secondary-left">
      <a href="contact.php">CONTACT</a>
      <a href="stockist.php">STOCKIST</a>
    </div>
    <div class="footer-secondary-right">
      <a href="shipping.php">SHIPPING &amp; RETURNS</a>
      <a href="legal.php">LEGAL</a>
    </div>
  </div>

  <!-- FOOTER -->
  <footer class="footer" aria-label="Site footer">
    <div class="footer-bottom">
      <span class="footer-copy">© 2025 PASTPRESENTFURTHER - VAT: 0607250040631 - S.L Village Klapanunggal Bogor West Java, 16871 • Development by Cantum Studio</span>
    </div>
  </footer>

  <!-- SCRIPTS -->
  <script>
  (function () {
    'use strict';

    const body         = document.body;
    const overlay      = document.getElementById('overlay');
    const menuPanel    = document.getElementById('menuPanel');
    const cartPanel    = document.getElementById('cartPanel');
    const accountPopup = document.getElementById('accountPopup');

    // Keranjang mini bersama (assets/js/mini-cart.js)
    const cart      = DDMiniCart.create();
    function saveCart()   { cart.save(); }
    function renderCart() { cart.render(); }

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

    document.getElementById('menuToggle').addEventListener('click', openMenu);
    document.getElementById('closeMenu').addEventListener('click', () => { menuPanel.classList.remove('active'); if (!anyPanelOpen()) setOverlay(false); });
    document.getElementById('openCartBtn').addEventListener('click', openCart);
    document.getElementById('closeCart').addEventListener('click', () => { cartPanel.classList.remove('active'); if (!anyPanelOpen()) setOverlay(false); });
    document.getElementById('openAccountBtn').addEventListener('click', openAccount);
    
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

    document.querySelectorAll('.account-tab').forEach(tab => {
      tab.addEventListener('click', function () {
        document.querySelectorAll('.account-tab').forEach(t => { t.classList.remove('active'); t.setAttribute('aria-selected','false'); });
        this.classList.add('active'); this.setAttribute('aria-selected','true');
        const target = this.dataset.tab;
        document.getElementById('tabLogin').style.display    = target === 'login'    ? '' : 'none';
        document.getElementById('tabRegister').style.display = target === 'register' ? '' : 'none';
      });
    });

    /* ── CART FUNCTIONALITY ────────────────────────────── */
    document.getElementById('checkoutBtn').addEventListener('click', () => {
      window.location.href = 'checkout.php';
    });

    renderCart();

  })();
  </script>

</body>
</html>