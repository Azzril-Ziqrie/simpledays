<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/currency_helper.php';

/* ── ganti password admin ─────────────────────────────── */
$pwMessage = '';
$pwError   = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['change_password'])) {
    $current = (string) ($_POST['current_password'] ?? '');
    $new     = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    if (!hash_equals(ddAdminCsrf(), (string) ($_POST['csrf'] ?? ''))) {
        $pwError = 'Session expired. Please try again.';
    } elseif (ddAdminLockedSeconds() > 0) {
        $pwError = 'Too many failed attempts. Try again in ' . ceil(ddAdminLockedSeconds() / 60) . ' minute(s).';
    } elseif (!password_verify($current, ddAdminCurrentHash())) {
        ddAdminRegisterFailure();
        usleep(500000);
        $pwError = 'Current password is incorrect.';
    } elseif (strlen($new) < 10) {
        $pwError = 'New password must be at least 10 characters.';
    } elseif (strlen($new) > 72) {
        $pwError = 'New password must be 72 characters or fewer.';
    } elseif ($new === $current) {
        $pwError = 'New password must be different from the current one.';
    } elseif ($new !== $confirm) {
        $pwError = 'New password and confirmation do not match.';
    } else {
        $saveError = ddAdminSetPassword($new);
        if ($saveError !== null) {
            $pwError = $saveError;
        } else {
            ddAdminClearFailures();
            // sesi ini tetap login (ID baru); sesi lain otomatis keluar karena versi password berubah
            session_regenerate_id(true);
            $_SESSION['admin_pw_ver'] = ddAdminPwVersion();
            $pwMessage = 'Password changed. Other signed-in sessions have been signed out.';
        }
    }
}
$pwOpen = $pwMessage !== '' || $pwError !== '';

$totalProducts = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
$totalActiveProducts = (int) $pdo->query('SELECT COUNT(*) FROM products WHERE is_active = 1')->fetchColumn();
$totalDraftProducts = (int) $pdo->query('SELECT COUNT(*) FROM products WHERE is_active = 0')->fetchColumn();
$totalImages = (int) $pdo->query('SELECT COUNT(*) FROM product_images')->fetchColumn();

$totalOrders = (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
$totalPendingOrders = (int) $pdo->query("
    SELECT COUNT(*)
    FROM orders
    WHERE payment_status IN ('pending', 'waiting_verification')
")->fetchColumn();
$totalApprovedOrders = (int) $pdo->query("
    SELECT COUNT(*)
    FROM orders
    WHERE payment_status = 'paid'
")->fetchColumn();
$totalRejectedOrders = (int) $pdo->query("
    SELECT COUNT(*)
    FROM orders
    WHERE payment_status = 'failed'
")->fetchColumn();

$recentProducts = $pdo->query(
    'SELECT id, name, slug, thumbnail, is_active, created_at
     FROM products
     ORDER BY id DESC
     LIMIT 8'
)->fetchAll(PDO::FETCH_ASSOC);

$recentApprovedOrders = $pdo->query(
    "SELECT
        id,
        order_number,
        full_name,
        email,
        currency,
        grand_total,
        payment_status,
        fulfillment_status,
        payment_verified_at,
        created_at
     FROM orders
     WHERE payment_status = 'paid'
     ORDER BY payment_verified_at DESC, id DESC
     LIMIT 8"
)->fetchAll(PDO::FETCH_ASSOC);

$recentPendingOrders = $pdo->query(
    "SELECT
        id,
        order_number,
        full_name,
        email,
        currency,
        grand_total,
        payment_status,
        fulfillment_status,
        created_at
     FROM orders
     WHERE payment_status IN ('pending', 'waiting_verification')
     ORDER BY id DESC
     LIMIT 8"
)->fetchAll(PDO::FETCH_ASSOC);

function formatDashboardMoney(string $currency, float $amount): string
{
    return ddFormatMoney($amount, $currency);
}

function paymentBadgeClass(string $status): string
{
    $status = strtolower(trim($status));
    if ($status === 'paid') return 'paid';
    if ($status === 'waiting_verification') return 'waiting';
    if ($status === 'failed') return 'failed';
    return 'pending';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — <?= htmlspecialchars(BRAND_NAME) ?> Admin</title>
  <style>
    * { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; }
    body { font-family: Arial, Helvetica, sans-serif; background: #fff; color: #111; }
    a { color: inherit; text-decoration: none; }

    /* ── TOPBAR ────────────────────────────────────────── */
    .topbar { height: 74px; display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; padding: 0 22px; background: #fff; border-bottom: 1px solid #d8d5ce; position: sticky; top: 0; z-index: 20; }
    .topbar__left, .topbar__right { display: flex; align-items: center; gap: 18px; }
    .topbar__right { justify-content: flex-end; }
    .logo { justify-self: center; display: flex; align-items: center; }
    .nav-link { font-size: 12px; text-transform: uppercase; letter-spacing: .6px; }

    /* ── PAGE ──────────────────────────────────────────── */
    .wrap { max-width: 1440px; margin: 0 auto; min-height: calc(100vh - 74px); }
    .block { margin-top: 28px; }
    .block-title { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; margin: 0 4px 12px; font-size: 20px; font-weight: 700; letter-spacing: -.02em; }
    .block-title small { font-size: 13px; font-weight: 400; color: var(--muted); letter-spacing: 0; }

    .page-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 24px; flex-wrap: wrap; padding: 4px 4px 0; }
    .eyebrow { font-size: 12px; margin-bottom: 14px; }
    .headline { margin: 0; font-size: 36px; line-height: 1.08; }
    .subcopy { margin: 10px 0 0; max-width: 520px; font-size: 15px; }
    .head-actions { display: flex; gap: 10px; flex-wrap: wrap; }

    .btn, .btn-secondary { display: inline-flex; align-items: center; justify-content: center; gap: 8px; cursor: pointer; border: 0; font-family: inherit; }
    .count-pill { min-width: 22px; height: 22px; padding: 0 7px; border-radius: 999px; background: #fff; color: #1d1d1f; font-size: 12px; font-weight: 700; display: inline-grid; place-items: center; }

    /* ── KPIs ──────────────────────────────────────────── */
    .kpi-groups { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; }
    .kpi-group { background: var(--surface); border: 1px solid var(--line); border-radius: var(--radius); box-shadow: var(--shadow); padding: 18px; }
    .kpi-group h2 { margin: 2px 4px 14px; font-size: 13px; font-weight: 600; color: var(--muted); }
    .kpi-row { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
    .kpi { display: block; padding: 14px 14px 12px; border-radius: 12px; background: var(--surface-2); border: 1px solid var(--line); transition: transform .25s var(--spring), box-shadow .25s var(--spring), background .2s; }
    .kpi:hover { transform: translateY(-2px); background: #fff; box-shadow: var(--shadow); }
    .kpi:active { transform: scale(.97); transition-duration: .1s; }
    .kpi__label { font-size: 12px; color: var(--muted); margin-bottom: 10px; line-height: 1.25; min-height: 30px; }
    .kpi__value { font-size: 30px; font-weight: 700; letter-spacing: -.03em; line-height: 1; font-variant-numeric: tabular-nums; }
    .kpi--alert { background: #fff4e0; border-color: rgba(178, 94, 0, .25); }
    .kpi--alert .kpi__label { color: var(--amber); font-weight: 600; }
    .kpi--alert .kpi__value { color: var(--amber); }

    /* ── MANAGE GROUPS ─────────────────────────────────── */
    .groups { display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 20px; align-items: start; }
    .group { background: var(--surface); border: 1px solid var(--line); border-radius: var(--radius); box-shadow: var(--shadow); padding: 10px; }
    .group h2 { margin: 10px 12px 6px; font-size: 13px; font-weight: 600; color: var(--muted); }
    .tile { display: flex; align-items: center; gap: 14px; padding: 12px; border-radius: 12px; transition: background .2s var(--spring), transform .12s ease-out; }
    .tile:hover { background: rgba(0, 0, 0, .045); }
    .tile:active { transform: scale(.985); }
    .tile__icon { flex: none; width: 40px; height: 40px; border-radius: 11px; display: grid; place-items: center; background: #f0f0f3; color: #1d1d1f; }
    .tile__icon svg { width: 21px; height: 21px; }
    .tile__text { flex: 1; min-width: 0; }
    .tile__title { font-size: 15px; font-weight: 600; letter-spacing: -.012em; }
    .tile__desc { font-size: 13px; color: var(--muted); line-height: 1.4; margin-top: 1px; }
    .tile__go { flex: none; width: 16px; height: 16px; color: #aeaeb2; transition: transform .25s var(--spring); }
    .tile:hover .tile__go { transform: translateX(3px); color: var(--text); }
    .ic { fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }

    /* ── ACTIVITY TABLES ───────────────────────────────── */
    .section-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; }
    .section--wide { grid-column: 1 / -1; }
    .section-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
    .table-wrap { overflow: auto; }
    table { width: 100%; border-collapse: collapse; min-width: 520px; }
    th, td { text-align: left; vertical-align: middle; }
    .thumb-mini { width: 48px; height: 48px; object-fit: cover; background: #fff; }
    .status { display: inline-block; }
    .sub { color: var(--muted); font-size: 12px; }
    .empty { padding: 28px 12px !important; text-align: center !important; color: var(--muted); }
    td a:hover { text-decoration: underline; }

    /* ── CHANGE PASSWORD ───────────────────────────────── */
    .pw-form { max-width: 520px; display: grid; gap: 14px; }
    .pw-form label { display: block; margin-bottom: 8px; }
    .pw-form input[type=password], .pw-form input[type=text] { width: 100%; height: 44px; padding: 0 14px; font-family: inherit; }
    .pw-actions { display: flex; gap: 10px; flex-wrap: wrap; }
    .pw-hint { line-height: 1.6; }
    .pw-alert { margin-bottom: 16px; max-width: 520px; }
    details.pw-box > summary { cursor: pointer; list-style: none; padding: 18px 22px; display: flex; justify-content: space-between; }
    details.pw-box > summary::-webkit-details-marker { display: none; }
    details.pw-box > summary::after { content: '+'; font-size: 18px; color: var(--muted); }
    details.pw-box[open] > summary::after { content: '−'; }

    /* ── RESPONSIVE ────────────────────────────────────── */
    @media (max-width: 1180px) {
      .kpi-groups, .section-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 640px) {
      .headline { font-size: 28px; }
      .kpi-row { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .groups { grid-template-columns: 1fr; }
      .head-actions { width: 100%; }
      .head-actions .btn, .head-actions .btn-secondary { flex: 1; }
    }
  </style>
  <link rel="stylesheet" href="admin-theme.css?v=1">
</head>
<body>

  <!-- icon sprite -->
  <svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false">
    <symbol id="i-bag" viewBox="0 0 24 24"><path d="M5 8h14l-1 12H6z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></symbol>
    <symbol id="i-truck" viewBox="0 0 24 24"><path d="M2 6h11v10H2zM13 10h4l4 3v3h-8"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/></symbol>
    <symbol id="i-box" viewBox="0 0 24 24"><path d="M21 8l-9-5-9 5v8l9 5 9-5z"/><path d="M3 8l9 5 9-5M12 13v8"/></symbol>
    <symbol id="i-tag" viewBox="0 0 24 24"><path d="M20.6 13.4l-7.2 7.2a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.2"/></symbol>
    <symbol id="i-folder" viewBox="0 0 24 24"><path d="M3 6a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></symbol>
    <symbol id="i-percent" viewBox="0 0 24 24"><path d="M19 5L5 19"/><circle cx="7" cy="7" r="2.2"/><circle cx="17" cy="17" r="2.2"/></symbol>
    <symbol id="i-grid" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></symbol>
    <symbol id="i-image" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="1.8"/><path d="M21 16l-5-5-9 9"/></symbol>
    <symbol id="i-book" viewBox="0 0 24 24"><path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2z"/><path d="M4 21V5"/></symbol>
    <symbol id="i-doc" viewBox="0 0 24 24"><path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4M9 12h6M9 16h6"/></symbol>
    <symbol id="i-key" viewBox="0 0 24 24"><circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M16 7l3 3"/></symbol>
    <symbol id="i-go" viewBox="0 0 24 24"><path d="M9 6l6 6-6 6"/></symbol>
  </svg>

  <!-- TOPBAR -->
  <header class="topbar">
    <div class="topbar__left">
      <a href="../index.php" class="nav-link">Storefront</a>
    </div>
    <a href="dashboard.php" class="logo" aria-label="<?= htmlspecialchars(BRAND_NAME) ?> Admin">
      <span class="brand"><span class="brand__name"><?= htmlspecialchars(BRAND_NAME) ?></span></span>
    </a>
    <div class="topbar__right">
      <a href="logout.php" class="nav-link">Logout</a>
    </div>
  </header>

  <main class="wrap">

    <!-- PAGE HEAD -->
    <section class="page-head">
      <div>
        <div class="eyebrow"><?= htmlspecialchars(BRAND_NAME) ?> Admin</div>
        <h1 class="headline">Dashboard</h1>
        <p class="subcopy">
          <?php if ($totalPendingOrders > 0): ?>
            <?= (int) $totalPendingOrders ?> payment<?= $totalPendingOrders === 1 ? '' : 's' ?> waiting for your review.
          <?php else: ?>
            You're all caught up — no payments waiting for review.
          <?php endif; ?>
        </p>
      </div>
      <div class="head-actions">
        <a href="dashboard-orders.php" class="btn">
          Orders
          <?php if ($totalPendingOrders > 0): ?><span class="count-pill"><?= (int) $totalPendingOrders ?></span><?php endif; ?>
        </a>
        <a href="dashboard-product.php" class="btn-secondary">Add product</a>
        <a href="../index.php" class="btn-secondary">View storefront</a>
      </div>
    </section>

    <!-- OVERVIEW -->
    <section class="block" aria-label="Overview">
      <div class="kpi-groups">
        <div class="kpi-group">
          <h2>Orders</h2>
          <div class="kpi-row">
            <a href="dashboard-orders.php" class="kpi">
              <div class="kpi__label">Total orders</div>
              <div class="kpi__value"><?= $totalOrders ?></div>
            </a>
            <a href="dashboard-orders.php" class="kpi<?= $totalPendingOrders > 0 ? ' kpi--alert' : '' ?>">
              <div class="kpi__label">Waiting review</div>
              <div class="kpi__value"><?= $totalPendingOrders ?></div>
            </a>
            <a href="dashboard-orders.php?status=paid" class="kpi">
              <div class="kpi__label">Approved</div>
              <div class="kpi__value"><?= $totalApprovedOrders ?></div>
            </a>
            <a href="dashboard-orders.php" class="kpi">
              <div class="kpi__label">Rejected payments</div>
              <div class="kpi__value"><?= $totalRejectedOrders ?></div>
            </a>
          </div>
        </div>
        <div class="kpi-group">
          <h2>Catalog</h2>
          <div class="kpi-row">
            <a href="dashboard-product.php" class="kpi">
              <div class="kpi__label">Total products</div>
              <div class="kpi__value"><?= $totalProducts ?></div>
            </a>
            <a href="dashboard-product.php" class="kpi">
              <div class="kpi__label">Active</div>
              <div class="kpi__value"><?= $totalActiveProducts ?></div>
            </a>
            <a href="dashboard-product.php" class="kpi">
              <div class="kpi__label">Drafts</div>
              <div class="kpi__value"><?= $totalDraftProducts ?></div>
            </a>
            <a href="dashboard-product.php" class="kpi">
              <div class="kpi__label">Gallery images</div>
              <div class="kpi__value"><?= $totalImages ?></div>
            </a>
          </div>
        </div>
      </div>
    </section>

    <!-- MANAGE -->
    <section class="block" aria-label="Manage">
      <h2 class="block-title">Manage</h2>
      <div class="groups">

        <div class="group">
          <h2>Orders &amp; shipping</h2>
          <a href="dashboard-orders.php" class="tile">
            <span class="tile__icon"><svg class="ic"><use href="#i-bag"/></svg></span>
            <span class="tile__text"><div class="tile__title">Orders</div><div class="tile__desc">Check payment proofs, approve or reject.</div></span>
            <svg class="tile__go ic"><use href="#i-go"/></svg>
          </a>
          <a href="send-resi.php" class="tile">
            <span class="tile__icon"><svg class="ic"><use href="#i-truck"/></svg></span>
            <span class="tile__text"><div class="tile__title">Send tracking (resi)</div><div class="tile__desc">Email the tracking number to a customer.</div></span>
            <svg class="tile__go ic"><use href="#i-go"/></svg>
          </a>
        </div>

        <div class="group">
          <h2>Catalog</h2>
          <a href="dashboard-product.php" class="tile">
            <span class="tile__icon"><svg class="ic"><use href="#i-box"/></svg></span>
            <span class="tile__text"><div class="tile__title">Products</div><div class="tile__desc">Photos, prices and details.</div></span>
            <svg class="tile__go ic"><use href="#i-go"/></svg>
          </a>
          <a href="dashboard-size.php" class="tile">
            <span class="tile__icon"><svg class="ic"><use href="#i-tag"/></svg></span>
            <span class="tile__text"><div class="tile__title">Sizes &amp; stock</div><div class="tile__desc">Sizes and stock for each product.</div></span>
            <svg class="tile__go ic"><use href="#i-go"/></svg>
          </a>
          <a href="dashboard-categories.php" class="tile">
            <span class="tile__icon"><svg class="ic"><use href="#i-folder"/></svg></span>
            <span class="tile__text"><div class="tile__title">Categories</div><div class="tile__desc">Organise how the shop is grouped.</div></span>
            <svg class="tile__go ic"><use href="#i-go"/></svg>
          </a>
          <a href="set-diskon.php" class="tile">
            <span class="tile__icon"><svg class="ic"><use href="#i-percent"/></svg></span>
            <span class="tile__text"><div class="tile__title">Discounts</div><div class="tile__desc">Sale prices and strikethrough pricing.</div></span>
            <svg class="tile__go ic"><use href="#i-go"/></svg>
          </a>
          <a href="promo-codes.php" class="tile">
            <span class="tile__icon"><svg class="ic"><use href="#i-percent"/></svg></span>
            <span class="tile__text"><div class="tile__title">Promo codes</div><div class="tile__desc">Checkout codes: percent off, fixed off, free shipping.</div></span>
            <svg class="tile__go ic"><use href="#i-go"/></svg>
          </a>
          <a href="payments.php" class="tile">
            <span class="tile__icon"><svg class="ic"><use href="#i-tag"/></svg></span>
            <span class="tile__text"><div class="tile__title">Payments</div><div class="tile__desc">Enter an amount in any currency, convert to USD, pay with PayPal.</div></span>
            <svg class="tile__go ic"><use href="#i-go"/></svg>
          </a>
          <a href="admin_products.php" class="tile">
            <span class="tile__icon"><svg class="ic"><use href="#i-grid"/></svg></span>
            <span class="tile__text"><div class="tile__title">Product order</div><div class="tile__desc">Arrange products on the home and shop pages.</div></span>
            <svg class="tile__go ic"><use href="#i-go"/></svg>
          </a>
        </div>

        <div class="group">
          <h2>Storefront content</h2>
          <a href="dashboard-hero.php" class="tile">
            <span class="tile__icon"><svg class="ic"><use href="#i-image"/></svg></span>
            <span class="tile__text"><div class="tile__title">Hero banner</div><div class="tile__desc">Landscape photos at the top of the homepage.</div></span>
            <svg class="tile__go ic"><use href="#i-go"/></svg>
          </a>
          <a href="dashboard-home-media.php" class="tile">
            <span class="tile__icon"><svg class="ic"><use href="#i-grid"/></svg></span>
            <span class="tile__text"><div class="tile__title">Homepage media</div><div class="tile__desc">Photos and GIFs between the products while scrolling.</div></span>
            <svg class="tile__go ic"><use href="#i-go"/></svg>
          </a>
          <a href="lookbook.php" class="tile">
            <span class="tile__icon"><svg class="ic"><use href="#i-book"/></svg></span>
            <span class="tile__text"><div class="tile__title">Lookbook</div><div class="tile__desc">Collections and photos.</div></span>
            <svg class="tile__go ic"><use href="#i-go"/></svg>
          </a>
          <a href="dashboard-blogs.php" class="tile">
            <span class="tile__icon"><svg class="ic"><use href="#i-doc"/></svg></span>
            <span class="tile__text"><div class="tile__title">Research / Blog</div><div class="tile__desc">Write and publish articles.</div></span>
            <svg class="tile__go ic"><use href="#i-go"/></svg>
          </a>
        </div>

        <div class="group">
          <h2>Account</h2>
          <a href="#change-password" class="tile" id="openChangePassword">
            <span class="tile__icon"><svg class="ic"><use href="#i-key"/></svg></span>
            <span class="tile__text"><div class="tile__title">Change password</div><div class="tile__desc">Other signed-in sessions are signed out.</div></span>
            <svg class="tile__go ic"><use href="#i-go"/></svg>
          </a>
        </div>

      </div>
    </section>

    <!-- ACTIVITY -->
    <section class="block" aria-label="Recent activity">
      <h2 class="block-title">Recent activity</h2>
      <div class="section-grid">

        <!-- WAITING PAYMENT REVIEW -->
        <div class="section">
          <div class="section-head">
            <span>Waiting payment review</span>
            <a href="dashboard-orders.php" class="mini-link">View all</a>
          </div>
          <div class="section-body">
            <div class="table-wrap">
              <table>
                <thead>
                  <tr>
                    <th>Order</th>
                    <th>Customer</th>
                    <th>Total</th>
                    <th>Payment</th>
                    <th>Created</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!$recentPendingOrders): ?>
                    <tr><td colspan="5" class="empty">No pending payment review.</td></tr>
                  <?php else: ?>
                    <?php foreach ($recentPendingOrders as $order): ?>
                      <tr>
                        <td>
                          <a href="order-detail.php?id=<?= (int) $order['id'] ?>">
                            <?= htmlspecialchars($order['order_number']) ?>
                          </a>
                        </td>
                        <td>
                          <?= htmlspecialchars($order['full_name']) ?><br>
                          <span class="sub"><?= htmlspecialchars($order['email']) ?></span>
                        </td>
                        <td><?= htmlspecialchars(formatDashboardMoney($order['currency'], (float) $order['grand_total'])) ?></td>
                        <td>
                          <span class="status <?= paymentBadgeClass($order['payment_status']) ?>">
                            <?= htmlspecialchars(str_replace('_', ' ', $order['payment_status'])) ?>
                          </span>
                        </td>
                        <td><?= htmlspecialchars((string) $order['created_at']) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- APPROVED ORDERS -->
        <div class="section">
          <div class="section-head">
            <span>Approved orders</span>
            <a href="dashboard-orders.php?status=paid" class="mini-link">View all</a>
          </div>
          <div class="section-body">
            <div class="table-wrap">
              <table>
                <thead>
                  <tr>
                    <th>Order</th>
                    <th>Customer</th>
                    <th>Total</th>
                    <th>Fulfillment</th>
                    <th>Approved</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!$recentApprovedOrders): ?>
                    <tr><td colspan="5" class="empty">No approved orders yet.</td></tr>
                  <?php else: ?>
                    <?php foreach ($recentApprovedOrders as $order): ?>
                      <tr>
                        <td>
                          <a href="order-detail.php?id=<?= (int) $order['id'] ?>">
                            <?= htmlspecialchars($order['order_number']) ?>
                          </a>
                        </td>
                        <td>
                          <?= htmlspecialchars($order['full_name']) ?><br>
                          <span class="sub"><?= htmlspecialchars($order['email']) ?></span>
                        </td>
                        <td><?= htmlspecialchars(formatDashboardMoney($order['currency'], (float) $order['grand_total'])) ?></td>
                        <td>
                          <span class="status">
                            <?= htmlspecialchars($order['fulfillment_status']) ?>
                          </span>
                        </td>
                        <td><?= htmlspecialchars((string) ($order['payment_verified_at'] ?: $order['created_at'])) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- LATEST PRODUCTS -->
        <div class="section section--wide">
          <div class="section-head">
            <span>Latest products</span>
            <a href="dashboard-product.php" class="mini-link">Manage products</a>
          </div>
          <div class="section-body">
            <div class="table-wrap">
              <table>
                <thead>
                  <tr>
                    <th>Thumb</th>
                    <th>Name</th>
                    <th>Slug</th>
                    <th>Status</th>
                    <th>Created</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!$recentProducts): ?>
                    <tr><td colspan="5" class="empty">No products yet.</td></tr>
                  <?php else: ?>
                    <?php foreach ($recentProducts as $item): ?>
                      <tr>
                        <td><img class="thumb-mini" src="../<?= htmlspecialchars($item['thumbnail']) ?>" alt="<?= htmlspecialchars($item['name']) ?>"></td>
                        <td><?= htmlspecialchars($item['name']) ?></td>
                        <td><?= htmlspecialchars($item['slug']) ?></td>
                        <td>
                          <span class="status <?= (int) $item['is_active'] === 1 ? 'live' : '' ?>">
                            <?= (int) $item['is_active'] === 1 ? 'Active' : 'Draft' ?>
                          </span>
                        </td>
                        <td><?= htmlspecialchars((string) $item['created_at']) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

      </div>
    </section>

    <!-- CHANGE PASSWORD -->
    <section class="pw-section" id="change-password">
      <details class="pw-box" id="pwBox"<?= $pwOpen ? ' open' : '' ?>>
        <summary>Change password</summary>
        <div class="section-body">
          <?php if ($pwMessage !== ''): ?><div class="pw-alert" role="status"><?= htmlspecialchars($pwMessage) ?></div><?php endif; ?>
          <?php if ($pwError !== ''): ?><div class="pw-alert error" role="alert"><?= htmlspecialchars($pwError) ?></div><?php endif; ?>
          <form method="POST" action="dashboard.php#change-password" class="pw-form" autocomplete="off">
            <input type="hidden" name="change_password" value="1">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(ddAdminCsrf()) ?>">
            <div>
              <label for="current_password">Current password</label>
              <input type="password" id="current_password" name="current_password" required>
            </div>
            <div>
              <label for="new_password">New password</label>
              <input type="password" id="new_password" name="new_password" minlength="10" maxlength="72" required>
            </div>
            <div>
              <label for="confirm_password">Confirm new password</label>
              <input type="password" id="confirm_password" name="confirm_password" minlength="10" maxlength="72" required>
            </div>
            <div class="pw-hint" id="pwHint">At least 10 characters. Use a long, unique password (or generate one below).</div>
            <div class="pw-actions">
              <button type="button" class="btn-secondary" id="pwGenerate">Generate strong password</button>
              <button type="submit" class="btn">Save new password</button>
            </div>
          </form>
        </div>
      </details>
    </section>
  </main>

  <script>
    (function () {
      var box = document.getElementById('pwBox');
      var link = document.getElementById('openChangePassword');
      function openBox() { if (box) box.open = true; }
      if (location.hash === '#change-password') openBox();
      if (link) link.addEventListener('click', function () { openBox(); });

      var gen = document.getElementById('pwGenerate');
      if (gen) gen.addEventListener('click', function () {
        var chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        var buf = new Uint32Array(16); crypto.getRandomValues(buf);
        var pw = ''; for (var i = 0; i < buf.length; i++) pw += chars[buf[i] % chars.length];
        var n = document.getElementById('new_password'), c = document.getElementById('confirm_password');
        n.type = 'text'; c.type = 'text'; n.value = pw; c.value = pw;
        document.getElementById('pwHint').textContent = 'Generated: ' + pw + '  — copy and store it now; it will not be shown again after you save.';
      });
    })();
  </script>

</body>
</html>
