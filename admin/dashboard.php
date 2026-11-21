<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/currency_helper.php';
require_once __DIR__ . '/../includes/analytics.php';
require_once __DIR__ . '/../includes/sales_report.php';

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

/* ── analytics (periode lewat ?days=) ─────────────────── */
$anRanges = [7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days', 0 => 'All time'];
$anDays   = (int) ($_GET['days'] ?? 30);
if (!isset($anRanges[$anDays])) {
    $anDays = 30;
}
$report = analyticsReport($pdo, $anDays);
$sales  = salesReport($pdo, $anDays);

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

/* ── sidebar navigation ───────────────────────────────── */
$navGroups = [
    'Overview' => [
        ['dashboard.php', 'i-dash', 'Dashboard', null],
    ],
    'Orders & shipping' => [
        ['dashboard-orders.php', 'i-bag', 'Orders', $totalPendingOrders],
        ['send-resi.php', 'i-truck', 'Send tracking', null],
    ],
    'Catalog' => [
        ['dashboard-product.php', 'i-box', 'Products', null],
        ['dashboard-size.php', 'i-tag', 'Sizes & stock', null],
        ['dashboard-categories.php', 'i-folder', 'Categories', null],
        ['admin_products.php', 'i-grid', 'Product order', null],
    ],
    'Sales & marketing' => [
        ['set-diskon.php', 'i-percent', 'Discounts', null],
        ['promo-codes.php', 'i-percent', 'Promo codes', null],
        ['campaign-email.php', 'i-mail', 'Email campaign', null],
        ['payments.php', 'i-card', 'Payments', null],
    ],
    'Storefront content' => [
        ['dashboard-hero.php', 'i-image', 'Hero banner', null],
        ['dashboard-announcement.php', 'i-tag', 'Announcement bar', null],
        ['dashboard-home-media.php', 'i-grid', 'Homepage media', null],
        ['lookbook.php', 'i-book', 'Lookbook', null],
        ['dashboard-blogs.php', 'i-doc', 'Research / Blog', null],
    ],
    'Account' => [
        ['#change-password', 'i-key', 'Change password', null],
    ],
];

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

    /* ── ANALYTICS ─────────────────────────────────────── */
    .an-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin: 0 4px 12px; }
    .an-head .block-title { margin: 0; }
    .an-range { display: flex; gap: 6px; flex-wrap: wrap; }
    .an-range a { padding: 7px 12px; border: 1px solid var(--line); border-radius: 999px; font-size: 12px; color: var(--muted); }
    .an-range a.active { background: #111; border-color: #111; color: #fff; }

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

    /* ── SIDEBAR ───────────────────────────────────────── */
    .shell { display: grid; grid-template-columns: 264px minmax(0, 1fr); align-items: start; }
    .sidebar {
      position: sticky; top: 64px; height: calc(100vh - 64px); overflow-y: auto; overscroll-behavior: contain;
      padding: 18px 12px 28px;
      background: rgba(255, 255, 255, .72);
      -webkit-backdrop-filter: saturate(180%) blur(20px); backdrop-filter: saturate(180%) blur(20px);
      border-right: 1px solid var(--line);
    }
    .side-group + .side-group { margin-top: 18px; }
    .side-group h2 { margin: 0 12px 6px; font-size: 11px; font-weight: 600; letter-spacing: .02em; color: var(--muted); }
    .side-link {
      position: relative; display: flex; align-items: center; gap: 12px;
      padding: 9px 12px; border-radius: 10px; font-size: 14px; font-weight: 500; letter-spacing: -.005em;
      color: var(--text); transition: background .2s var(--spring), transform .1s ease-out;
    }
    .side-link:hover { background: rgba(0, 0, 0, .05); }
    .side-link:active { transform: scale(.97); }
    .side-link.active { background: rgba(0, 0, 0, .08); font-weight: 600; }
    .side-link svg { flex: none; width: 19px; height: 19px; color: var(--muted); transition: color .2s; }
    .side-link:hover svg, .side-link.active svg { color: var(--text); }
    .side-link span { flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .side-badge { flex: none; min-width: 20px; height: 20px; padding: 0 6px; border-radius: 999px; background: var(--amber); color: #fff; font-size: 11px; font-weight: 700; display: inline-grid; place-items: center; }
    .side-toggle, .side-scrim { display: none; }
    .side-toggle { width: 38px; height: 38px; border: 0; border-radius: 10px; background: transparent; color: var(--text); cursor: pointer; place-items: center; }
    .side-toggle:hover { background: rgba(0, 0, 0, .05); }
    .side-toggle:active { transform: scale(.94); }
    .side-toggle svg { width: 22px; height: 22px; }

    /* ── RESPONSIVE ────────────────────────────────────── */
    @media (max-width: 980px) {
      .shell { display: block; }
      .side-toggle { display: inline-grid; }
      .topbar .logo { margin-right: auto; }
      .sidebar {
        position: fixed; z-index: 30; top: 0; left: 0; bottom: 0; width: min(300px, 84vw); height: auto;
        padding-top: 20px; background: rgba(255, 255, 255, .86); box-shadow: 0 0 60px rgba(0, 0, 0, .18);
        transform: translateX(-102%); visibility: hidden;
        transition: transform .4s var(--spring), visibility 0s .4s;
      }
      body.side-open .sidebar { transform: none; visibility: visible; transition: transform .4s var(--spring), visibility 0s; }
      .side-scrim { display: block; position: fixed; inset: 0; z-index: 25; background: rgba(0, 0, 0, .32); opacity: 0; pointer-events: none; transition: opacity .3s ease; }
      body.side-open .side-scrim { opacity: 1; pointer-events: auto; }
      body.side-open { overflow: hidden; }
    }
    @media (prefers-reduced-motion: reduce) {
      .sidebar { transition: opacity .2s ease, visibility 0s !important; transform: none !important; opacity: 0; }
      body.side-open .sidebar { opacity: 1; }
    }
    @media (prefers-reduced-transparency: reduce) {
      .sidebar { background: #fff !important; -webkit-backdrop-filter: none; backdrop-filter: none; }
    }
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
  <link rel="stylesheet" href="analytics-panels.css?v=<?= filemtime(__DIR__ . '/analytics-panels.css') ?>">
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
    <symbol id="i-dash" viewBox="0 0 24 24"><path d="M4 11l8-7 8 7v9a1 1 0 0 1-1 1h-4v-6H9v6H5a1 1 0 0 1-1-1z"/></symbol>
    <symbol id="i-mail" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></symbol>
    <symbol id="i-card" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h4"/></symbol>
    <symbol id="i-menu" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></symbol>
  </svg>

  <!-- TOPBAR -->
  <header class="topbar">
    <div class="topbar__left">
      <button type="button" class="side-toggle" id="sideToggle" aria-label="Open menu" aria-controls="sidebar" aria-expanded="false"><svg class="ic"><use href="#i-menu"/></svg></button>
      <a href="../index.php" class="nav-link">Storefront</a>
    </div>
    <a href="dashboard.php" class="logo" aria-label="<?= htmlspecialchars(BRAND_NAME) ?> Admin">
      <span class="brand"><span class="brand__name"><?= htmlspecialchars(BRAND_NAME) ?></span></span>
    </a>
    <div class="topbar__right">
      <a href="logout.php" class="nav-link">Logout</a>
    </div>
  </header>

  <div class="side-scrim" id="sideScrim"></div>
  <div class="shell">
  <aside class="sidebar" id="sidebar" aria-label="Admin navigation">
    <nav>
      <?php foreach ($navGroups as $groupLabel => $links): ?>
        <div class="side-group">
          <h2><?= htmlspecialchars($groupLabel) ?></h2>
          <?php foreach ($links as [$href, $icon, $label, $badge]): ?>
            <a href="<?= htmlspecialchars($href) ?>" class="side-link<?= $href === 'dashboard.php' ? ' active' : '' ?>"<?= $href === 'dashboard.php' ? ' aria-current="page"' : '' ?><?= $href === '#change-password' ? ' id="openChangePassword"' : '' ?>>
              <svg class="ic"><use href="#<?= $icon ?>"/></svg>
              <span><?= htmlspecialchars($label) ?></span>
              <?php if ($badge): ?><em class="side-badge" style="font-style:normal"><?= (int) $badge ?></em><?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </nav>
  </aside>

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

    <!-- SALES OVERVIEW -->
    <section class="block" aria-label="Sales overview">
      <div class="an-head">
        <h2 class="block-title">Sales overview <small><?= htmlspecialchars($anRanges[$anDays]) ?></small></h2>
        <nav class="an-range" aria-label="Date range">
          <?php foreach ($anRanges as $value => $label): ?>
            <a href="dashboard.php?days=<?= $value ?>" class="<?= $anDays === $value ? 'active' : '' ?>"><?= htmlspecialchars($label) ?></a>
          <?php endforeach; ?>
        </nav>
      </div>
      <?php $days = $anDays; include __DIR__ . '/_sales_panels.php'; ?>
    </section>

    <!-- ANALYTICS -->
    <section class="block" aria-label="Analytics">
      <div class="an-head">
        <h2 class="block-title">Analytics <small><?= (int) $report['visitors'] ?> visitors · <?= (int) $report['pageviews'] ?> page views</small></h2>
        <nav class="an-range" aria-label="Date range">
          <?php foreach ($anRanges as $value => $label): ?>
            <a href="dashboard.php?days=<?= $value ?>" class="<?= $anDays === $value ? 'active' : '' ?>"><?= htmlspecialchars($label) ?></a>
          <?php endforeach; ?>
        </nav>
      </div>
      <?php include __DIR__ . '/_analytics_panels.php'; ?>
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
  </div>

  <script>
    (function () {
      var toggle = document.getElementById('sideToggle');
      var scrim = document.getElementById('sideScrim');
      function setSide(open) {
        document.body.classList.toggle('side-open', open);
        if (toggle) toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      }
      if (toggle) toggle.addEventListener('click', function () { setSide(!document.body.classList.contains('side-open')); });
      if (scrim) scrim.addEventListener('click', function () { setSide(false); });
      document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setSide(false); });
      document.querySelectorAll('.side-link').forEach(function (a) { a.addEventListener('click', function () { setSide(false); }); });

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
