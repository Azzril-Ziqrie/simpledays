<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/currency_helper.php';

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function formatAdminMoney(string $currency, float $amount): string
{
    return ddFormatMoney($amount, $currency);
}

/** "26 July 2026 at 06.31" */
function orderDate(?string $value): string
{
    $time = $value ? strtotime($value) : false;
    return $time ? date('j F Y \a\t H.i', $time) : '';
}

/** Satu status yang terbaca manusia untuk banner & chip: [label, tone]. */
function orderState(array $order): array
{
    $payment     = strtolower((string) $order['payment_status']);
    $fulfillment = strtolower((string) $order['fulfillment_status']);

    if ($fulfillment === 'cancelled')   return ['Order cancelled', 'muted'];
    if ($payment === 'failed')          return ['Payment rejected', 'bad'];
    if ($payment === 'expired')         return ['Payment expired', 'muted'];
    if ($payment === 'waiting_verification') return ['Waiting for review', 'warn'];
    if ($payment !== 'paid')            return ['Awaiting payment', 'warn'];
    if ($fulfillment === 'completed')   return ['Order fulfilled', 'good'];
    if ($fulfillment === 'shipped')     return ['Order shipped', 'good'];
    return ['Paid · processing', 'info'];
}

const ORDER_FILTERS = [
    ''                     => 'All',
    'pending'              => 'Pending',
    'waiting_verification' => 'Waiting review',
    'paid'                 => 'Approved',
    'failed'               => 'Rejected',
];

/* ── isi lembar detail (dimuat lewat ?fragment=ID) ─────── */
function renderOrderSheet(PDO $pdo, int $id): ?string
{
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) {
        return null;
    }
    $itemStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = :id ORDER BY id ASC');
    $itemStmt->execute(['id' => $id]);
    $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

    [$stateLabel, $tone] = orderState($order);
    $itemCount = array_sum(array_map(static fn($i) => (int) $i['quantity'], $items));
    $currency  = (string) $order['currency'];
    $phone     = trim((string) $order['phone']);
    $phoneDigits = preg_replace('/\D+/', '', $phone);
    $address   = array_filter([
        $order['full_name'],
        $order['address_line_1'],
        $order['address_line_2'],
        $order['city'] . ($order['state'] ? ', ' . $order['state'] : ''),
        strtoupper((string) $order['postal_code']),
        $order['country_name'],
    ], static fn($line) => trim((string) $line) !== '');
    $delivery  = trim(implode(' ', array_filter([(string) $order['courier_code'], (string) $order['courier_service']])));
    $shipped   = in_array($order['fulfillment_status'], ['shipped', 'completed'], true);

    ob_start(); ?>
<div class="s-banner s-banner--<?= h($tone) ?>">
  <svg class="ic" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M8 12.5l3 3 5-6"/></svg>
  <span><?= h($stateLabel) ?></span>
  <time><?= h(orderDate($order['payment_verified_at'] ?: $order['updated_at'])) ?></time>
</div>

<header class="s-head">
  <h2 class="s-number">#<?= h(str_pad((string) $order['id'], 5, '0', STR_PAD_LEFT)) ?></h2>
  <div class="s-meta">
    <time><?= h(orderDate($order['created_at'])) ?></time>
    <span><?= (int) $itemCount ?> item<?= $itemCount === 1 ? '' : 's' ?> · <?= h(formatAdminMoney($currency, (float) $order['grand_total'])) ?></span>
  </div>
</header>
<p class="s-ref"><?= h($order['order_number']) ?></p>

<ol class="s-items">
  <?php foreach ($items as $i => $item): ?>
    <li>
      <span class="s-qty"><?= $i + 1 ?></span>
      <div class="s-item">
        <div class="s-item__text">
          <strong><?= h($item['product_name']) ?></strong>
          <?php if (trim((string) $item['size_name']) !== ''): ?><small><?= h($item['size_name']) ?></small><?php endif; ?>
          <div class="s-item__line">
            <span><?= h($item['product_slug']) ?><?= (int) $item['quantity'] > 1 ? ' × ' . (int) $item['quantity'] : '' ?></span>
            <span><?= h(formatAdminMoney($currency, (float) $item['line_total'])) ?></span>
          </div>
        </div>
        <?php if (!empty($item['thumbnail'])): ?><img src="../<?= h($item['thumbnail']) ?>" alt="" loading="lazy"><?php endif; ?>
      </div>
    </li>
  <?php endforeach; ?>
  <?php if (!$items): ?><li class="s-empty">No items recorded for this order.</li><?php endif; ?>
</ol>

<section class="s-sec">
  <h3>Shipping info</h3>
  <a class="s-row s-row--link" href="order-detail.php?id=<?= (int) $order['id'] ?>">
    <span>Customer</span><span class="s-val"><?= h(strtoupper((string) $order['full_name'])) ?></span>
    <svg class="ic" viewBox="0 0 24 24"><path d="M9 6l6 6-6 6"/></svg>
  </a>
  <div class="s-row s-row--top">
    <span>Ships to</span>
    <address class="s-val s-val--blue"><?php foreach ($address as $line): ?><?= h($line) ?><br><?php endforeach; ?></address>
  </div>
  <div class="s-row"><span>Phone number</span><span class="s-val"><?= h($phone !== '' ? $phone : '—') ?></span></div>
  <div class="s-row"><span>Delivery option</span><span class="s-val"><?= h($delivery !== '' ? $delivery : '—') ?></span></div>
  <div class="s-row"><span>Shipped via</span><span class="s-val"><?= $shipped && $delivery !== '' ? h($delivery) : '' ?></span></div>
</section>

<section class="s-sec">
  <h3>Payment</h3>
  <div class="s-row"><span>Status</span><span class="s-val"><?= h(str_replace('_', ' ', (string) $order['payment_status'])) ?></span></div>
  <?php if ((float) $order['discount_amount'] > 0): ?>
    <div class="s-row"><span>Discount<?= $order['promo_code'] ? ' (' . h($order['promo_code']) . ')' : '' ?></span><span class="s-val">−<?= h(formatAdminMoney($currency, (float) $order['discount_amount'])) ?></span></div>
  <?php endif; ?>
  <div class="s-row"><span>Shipping</span><span class="s-val"><?= h(formatAdminMoney($currency, (float) $order['shipping_cost'])) ?></span></div>
  <div class="s-row"><span>Total</span><span class="s-val"><strong><?= h(formatAdminMoney($currency, (float) $order['grand_total'])) ?></strong></span></div>
  <?php if (!empty($order['payment_proof_path'])): ?>
    <a class="s-row s-row--link" href="../<?= h($order['payment_proof_path']) ?>" target="_blank" rel="noopener"><span>Payment proof</span><span class="s-val s-val--blue">View</span><svg class="ic" viewBox="0 0 24 24"><path d="M9 6l6 6-6 6"/></svg></a>
  <?php endif; ?>
  <?php if (!empty($order['invoice_path'])): ?>
    <a class="s-row s-row--link" href="../<?= h($order['invoice_path']) ?>" target="_blank" rel="noopener"><span>Invoice</span><span class="s-val s-val--blue">Open</span><svg class="ic" viewBox="0 0 24 24"><path d="M9 6l6 6-6 6"/></svg></a>
  <?php endif; ?>
</section>

<div class="s-contact">
  <strong>Contact <?= h(strtoupper(strtok((string) $order['full_name'], ' ') ?: 'customer')) ?></strong>
  <div class="s-contact__btns">
    <a href="mailto:<?= h($order['email']) ?>" aria-label="Email"><svg class="ic" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg></a>
    <?php if ($phoneDigits !== ''): ?>
      <a href="https://wa.me/<?= h($phoneDigits) ?>" target="_blank" rel="noopener" aria-label="Message"><svg class="ic" viewBox="0 0 24 24"><path d="M4 5h16v11H9l-5 4z"/><path d="M8 9h8M8 12h5"/></svg></a>
      <a href="tel:<?= h($phone) ?>" aria-label="Call"><svg class="ic" viewBox="0 0 24 24"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/></svg></a>
    <?php endif; ?>
  </div>
</div>

<a class="s-manage" href="order-detail.php?id=<?= (int) $order['id'] ?>">Approve, reject or ship this order</a>
<?php
    return (string) ob_get_clean();
}

if (isset($_GET['fragment'])) {
    header('Cache-Control: no-store');
    $html = renderOrderSheet($pdo, (int) $_GET['fragment']);
    if ($html === null) {
        http_response_code(404);
        exit('Order not found.');
    }
    echo $html;
    exit;
}

/* ── daftar ───────────────────────────────────────────── */
$status = trim((string) ($_GET['status'] ?? ''));
if (!array_key_exists($status, ORDER_FILTERS)) {
    $status = '';
}

$sql = "SELECT o.id, o.order_number, o.full_name, o.currency, o.grand_total, o.payment_status, o.fulfillment_status,
               o.created_at, o.country_name,
               (SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi WHERE oi.order_id = o.id) AS item_count,
               (SELECT oi.thumbnail FROM order_items oi WHERE oi.order_id = o.id ORDER BY oi.id ASC LIMIT 1) AS thumb
        FROM orders o";
$params = [];
if ($status !== '') {
    $sql .= ' WHERE o.payment_status = :s';
    $params['s'] = $status;
}
$sql .= ' ORDER BY o.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$counts = $pdo->query('SELECT payment_status, COUNT(*) AS n FROM orders GROUP BY payment_status')->fetchAll(PDO::FETCH_KEY_PAIR);
$counts[''] = array_sum($counts);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>Orders — <?= h(BRAND_NAME) ?> Admin</title>
  <link rel="stylesheet" href="admin-theme.css?v=1">
  <style>
    * { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; }
    body { background: var(--bg); color: var(--text); -webkit-tap-highlight-color: transparent; }
    a { color: inherit; text-decoration: none; }
    .ic { width: 20px; height: 20px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; flex: none; }

    /* ── topbar ───────────────────────────────────────── */
    .topbar { height: 64px; display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; padding: 0 22px; position: sticky; top: 0; z-index: 20;
      background: rgba(245, 245, 247, .78); -webkit-backdrop-filter: blur(20px) saturate(180%); backdrop-filter: blur(20px) saturate(180%); box-shadow: 0 1px 0 var(--line); }
    .topbar__right { display: flex; justify-content: flex-end; }
    .logo { justify-self: center; }
    .nav-link { font-size: 12px; text-transform: uppercase; letter-spacing: .6px; }

    /* ── page ─────────────────────────────────────────── */
    .wrap { max-width: 760px; margin: 0 auto; padding: 28px 16px 80px; }
    .eyebrow { font-size: 12px; color: var(--muted); margin-bottom: 10px; }
    .headline { margin: 0 0 20px; font-size: clamp(30px, 6vw, 40px); line-height: 1.05; letter-spacing: -.03em; font-weight: 600; }

    .filters { display: flex; gap: 6px; overflow-x: auto; scrollbar-width: none; margin: 0 -16px 18px; padding: 0 16px; }
    .filters::-webkit-scrollbar { display: none; }
    .filter { flex: none; display: inline-flex; align-items: center; gap: 8px; min-height: 40px; padding: 0 16px; border-radius: 999px; background: var(--surface); box-shadow: inset 0 0 0 1px var(--line); font-size: 13px; transition: transform .12s ease-out, background .2s; }
    .filter:active { transform: scale(.96); }
    .filter small { color: var(--muted); font-size: 12px; font-variant-numeric: tabular-nums; }
    .filter.active { background: var(--text); color: #fff; box-shadow: none; }
    .filter.active small { color: rgba(255, 255, 255, .6); }

    /* ── daftar order ─────────────────────────────────── */
    .o-list { display: grid; gap: 10px; }
    .o-row { display: grid; grid-template-columns: 56px 1fr auto; gap: 14px; align-items: center; padding: 12px; background: var(--surface); border-radius: var(--radius); box-shadow: var(--shadow);
      transition: transform .12s ease-out, box-shadow .2s; }
    .o-row:active { transform: scale(.985); }   /* respons di pointer-down, bukan saat lepas */
    .o-thumb { width: 56px; height: 56px; border-radius: 12px; background: #f0f0f3 center / cover no-repeat; }
    .o-main { min-width: 0; }
    .o-num { font-size: 17px; font-weight: 600; letter-spacing: -.02em; font-variant-numeric: tabular-nums; }
    .o-name { margin-top: 2px; font-size: 13px; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .o-side { text-align: right; display: grid; gap: 6px; justify-items: end; }
    .o-total { font-size: 14px; font-variant-numeric: tabular-nums; }
    .chip { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 11px; white-space: nowrap; }
    .chip--good { background: #e6f4ec; color: var(--green); }
    .chip--warn { background: #fff4e0; color: var(--amber); }
    .chip--bad  { background: #fdecee; color: var(--red); }
    .chip--info { background: #e8f1fc; color: var(--blue); }
    .chip--muted { background: #efeff2; color: var(--muted); }
    .o-empty { padding: 48px 16px; text-align: center; color: var(--muted); font-size: 14px; }

    /* ── lembar detail ────────────────────────────────── */
    .scrim { position: fixed; inset: 0; z-index: 60; background: rgba(0, 0, 0, .32); -webkit-backdrop-filter: blur(14px) saturate(140%); backdrop-filter: blur(14px) saturate(140%); opacity: 0; visibility: hidden; }
    .sheet { position: fixed; z-index: 61; background: var(--surface); box-shadow: 0 18px 48px rgba(0, 0, 0, .22); visibility: hidden; display: flex; flex-direction: column; overflow: hidden; outline: none; will-change: transform; }
    .sheet.is-open, .scrim.is-open { visibility: visible; }
    @media (max-width: 899px) {
      .sheet { left: 0; right: 0; bottom: 0; height: 92vh; height: 92dvh; border-radius: 22px 22px 0 0; }
    }
    @media (min-width: 900px) {
      .sheet { top: 0; right: 0; bottom: 0; width: 480px; border-radius: 22px 0 0 22px; }
    }
    .sheet__bar { position: absolute; top: 0; left: 0; right: 0; z-index: 2; display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; touch-action: none; cursor: grab;
      background: linear-gradient(var(--surface) 55%, rgba(255, 255, 255, 0)); }
    .sheet__bar::before { content: ''; position: absolute; top: 6px; left: 50%; width: 36px; height: 4px; margin-left: -18px; border-radius: 2px; background: var(--line-strong); }
    @media (min-width: 900px) { .sheet__bar::before { display: none; } }
    .round { display: inline-grid; place-items: center; height: 44px; min-width: 44px; padding: 0 14px; border: 0; border-radius: 999px; cursor: pointer; color: var(--text);
      background: rgba(255, 255, 255, .86); -webkit-backdrop-filter: blur(12px); backdrop-filter: blur(12px); box-shadow: 0 2px 12px rgba(0, 0, 0, .12); transition: transform .12s ease-out; }
    .round:active { transform: scale(.92); }
    .sheet__tools { display: flex; gap: 8px; }
    .sheet__body { flex: 1; overflow-y: auto; overscroll-behavior: contain; padding: 72px 0 calc(32px + env(safe-area-inset-bottom)); }
    .sheet__loading { padding: 40px 24px; color: var(--muted); font-size: 14px; }

    .s-banner { display: flex; align-items: center; gap: 10px; margin: 0 16px; padding: 12px 14px; border-radius: 12px; font-size: 14px; font-weight: 600; }
    .s-banner time { margin-left: auto; font-weight: 400; font-size: 13px; }
    .s-banner--good { background: #e6f4ec; color: var(--green); }
    .s-banner--warn { background: #fff4e0; color: var(--amber); }
    .s-banner--bad  { background: #fdecee; color: var(--red); }
    .s-banner--info { background: #e8f1fc; color: var(--blue); }
    .s-banner--muted { background: #efeff2; color: var(--muted); }
    .s-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 12px; padding: 26px 20px 0; }
    .s-number { margin: 0; font-size: 40px; line-height: 1; font-weight: 500; letter-spacing: -.04em; font-variant-numeric: tabular-nums; }
    .s-meta { text-align: right; font-size: 13px; color: var(--muted); display: grid; gap: 2px; }
    .s-ref { margin: 8px 20px 0; font-size: 11px; color: var(--muted); word-break: break-all; }

    .s-items { list-style: none; margin: 20px 0 0; padding: 14px 16px; background: var(--bg); display: grid; gap: 10px; }
    .s-items li { display: grid; grid-template-columns: 28px 1fr; align-items: center; }
    .s-qty { color: var(--muted); font-size: 14px; text-align: center; }
    .s-item { display: flex; gap: 12px; align-items: center; justify-content: space-between; padding: 14px; background: var(--surface); border-radius: 10px; }
    .s-item__text { flex: 1; min-width: 0; display: grid; gap: 2px; }
    .s-item__text strong { font-size: 15px; font-weight: 500; }
    .s-item__text small { color: var(--muted); font-size: 13px; }
    .s-item__line { display: flex; justify-content: space-between; gap: 10px; margin-top: 12px; font-size: 13px; font-variant-numeric: tabular-nums; }
    .s-item img { width: 72px; height: 72px; object-fit: cover; border-radius: 8px; background: #f0f0f3; }
    .s-empty { grid-column: 1 / -1; color: var(--muted); font-size: 13px; padding: 8px; }

    .s-sec { padding: 22px 20px 0; }
    .s-sec h3 { margin: 0 0 6px; font-size: 17px; font-weight: 600; letter-spacing: -.02em; }
    .s-row { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 16px 0; font-size: 15px; box-shadow: 0 1px 0 var(--line); }
    .s-row:last-child { box-shadow: none; }
    .s-row--top { align-items: flex-start; }
    .s-row--link { transition: opacity .12s; }
    .s-row--link:active { opacity: .55; }
    .s-val { margin-left: auto; text-align: right; color: var(--muted); font-style: normal; line-height: 1.55; font-variant-numeric: tabular-nums; }
    .s-val strong { color: var(--text); font-weight: 600; }
    .s-val--blue { color: var(--blue); }
    .s-row--link .ic { width: 16px; height: 16px; margin-right: -2px; }

    .s-contact { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin: 14px 0 0; padding: 18px 20px; background: var(--bg); }
    .s-contact strong { font-size: 15px; letter-spacing: -.01em; }
    .s-contact__btns { display: flex; gap: 10px; }
    .s-contact__btns a { display: inline-grid; place-items: center; width: 48px; height: 48px; border-radius: 50%; background: #e8e8ed; transition: transform .12s ease-out; }
    .s-contact__btns a:active { transform: scale(.92); }
    .s-manage { display: block; margin: 18px 16px 0; padding: 15px; text-align: center; border-radius: 999px; background: var(--text); color: #fff; font-size: 14px; transition: transform .12s ease-out; }
    .s-manage:active { transform: scale(.97); }

    /* ── aksesibilitas ────────────────────────────────── */
    @media (prefers-reduced-motion: reduce) {
      .o-row, .filter, .round, .s-manage, .s-contact__btns a { transition: none; }
    }
    @media (prefers-reduced-transparency: reduce) {
      .topbar, .scrim, .round { -webkit-backdrop-filter: none; backdrop-filter: none; }
      .topbar { background: var(--bg); }
      .scrim { background: rgba(0, 0, 0, .5); }
    }
    @media print {
      .topbar, .wrap, .scrim, .sheet__bar { display: none !important; }
      .sheet { position: static; visibility: visible !important; transform: none !important; box-shadow: none; height: auto; }
      .sheet__body { padding: 0; overflow: visible; }
    }
  </style>
</head>
<body>

  <header class="topbar">
    <div class="topbar__left"><a href="dashboard.php" class="nav-link">Dashboard</a></div>
    <a href="../index.php" class="logo" aria-label="<?= h(BRAND_NAME) ?> Admin">
      <span class="brand"><span class="brand__name"><?= h(BRAND_NAME) ?></span></span>
    </a>
    <div class="topbar__right"><a href="../index.php" class="nav-link">Storefront</a></div>
  </header>

  <main class="wrap">
    <div class="eyebrow"><?= h(BRAND_NAME) ?> Admin</div>
    <h1 class="headline">Orders</h1>

    <nav class="filters" aria-label="Filter orders">
      <?php foreach (ORDER_FILTERS as $value => $label): ?>
        <a class="filter<?= $status === $value ? ' active' : '' ?>" href="dashboard-orders.php<?= $value !== '' ? '?status=' . h($value) : '' ?>">
          <?= h($label) ?> <small><?= (int) ($counts[$value] ?? 0) ?></small>
        </a>
      <?php endforeach; ?>
    </nav>

    <?php if (!$orders): ?>
      <p class="o-empty">No orders found.</p>
    <?php else: ?>
      <div class="o-list">
        <?php foreach ($orders as $order): [$stateLabel, $tone] = orderState($order); ?>
          <a class="o-row" href="order-detail.php?id=<?= (int) $order['id'] ?>" data-order="<?= (int) $order['id'] ?>">
            <span class="o-thumb"<?= $order['thumb'] ? ' style="background-image:url(\'../' . h($order['thumb']) . '\')"' : '' ?>></span>
            <span class="o-main">
              <div class="o-num">#<?= h(str_pad((string) $order['id'], 5, '0', STR_PAD_LEFT)) ?></div>
              <div class="o-name"><?= h($order['full_name']) ?> · <?= h(orderDate($order['created_at'])) ?></div>
            </span>
            <span class="o-side">
              <span class="o-total"><?= h(formatAdminMoney((string) $order['currency'], (float) $order['grand_total'])) ?></span>
              <span class="chip chip--<?= h($tone) ?>"><?= h($stateLabel) ?></span>
            </span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>

  <!-- Lembar detail: bottom sheet di ponsel, panel kanan di desktop -->
  <div class="scrim" id="scrim"></div>
  <aside class="sheet" id="sheet" role="dialog" aria-modal="true" aria-label="Order detail" tabindex="-1">
    <div class="sheet__bar" id="sheetBar">
      <button type="button" class="round" id="sheetClose" aria-label="Close"><svg class="ic" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
      <div class="sheet__tools">
        <button type="button" class="round" id="sheetPrint" aria-label="Print"><svg class="ic" viewBox="0 0 24 24"><path d="M7 9V4h10v5M7 17H5a1 1 0 0 1-1-1v-5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v5a1 1 0 0 1-1 1h-2"/><rect x="7" y="14" width="10" height="6"/></svg></button>
      </div>
    </div>
    <div class="sheet__body" id="sheetBody"></div>
  </aside>

  <script>
    (function () {
      var sheet = document.getElementById('sheet'), scrim = document.getElementById('scrim');
      var body = document.getElementById('sheetBody'), bar = document.getElementById('sheetBar');
      var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      var state = { pos: 0, size: 0, axis: 'y', open: false, raf: 0, token: 0, opener: null };

      function apply() {
        var t = state.axis === 'y' ? 'translate3d(0,' + state.pos + 'px,0)' : 'translate3d(' + state.pos + 'px,0,0)';
        sheet.style.transform = t;
        // Scrim mengikuti posisi lembar 1:1 — umpan balik terus-menerus selama drag.
        scrim.style.opacity = state.size ? Math.max(0, Math.min(1, 1 - state.pos / state.size)) : 0;
      }

      // Spring (damping 1.0 = tanpa overshoot, response 0.38s). Mulai dari posisi & kecepatan saat ini → bisa dipotong kapan saja.
      function springTo(target, velocity) {
        cancelAnimationFrame(state.raf);
        if (reduced) { state.pos = target; apply(); return Promise.resolve(); }
        var response = 0.38, k = Math.pow(2 * Math.PI / response, 2), c = 4 * Math.PI / response;
        var v = velocity || 0, last = performance.now();
        return new Promise(function (resolve) {
          (function step(now) {
            var dt = Math.min((now - last) / 1000, 1 / 30); last = now;
            for (var i = 0; i < 4; i++) { var h = dt / 4; v += (-k * (state.pos - target) - c * v) * h; state.pos += v * h; }
            apply();
            if (Math.abs(state.pos - target) < 0.4 && Math.abs(v) < 4) { state.pos = target; apply(); resolve(); return; }
            state.raf = requestAnimationFrame(step);
          })(last);
        });
      }

      function open(id, opener) {
        var token = ++state.token;
        state.opener = opener;
        state.axis = window.matchMedia('(min-width: 900px)').matches ? 'x' : 'y';
        sheet.classList.add('is-open'); scrim.classList.add('is-open');
        state.size = state.axis === 'y' ? sheet.offsetHeight : sheet.offsetWidth;
        if (!state.open) { state.pos = state.size; apply(); }   // masuk dari tepi yang sama dengan jalur keluar
        state.open = true;
        document.documentElement.style.overflow = 'hidden';
        body.innerHTML = '<div class="sheet__loading">Loading…</div>';
        springTo(0, 0);
        sheet.focus({ preventScroll: true });

        fetch('dashboard-orders.php?fragment=' + id, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
          .then(function (r) { if (!r.ok) throw new Error(r.status); return r.text(); })
          .then(function (html) { if (token === state.token) { body.innerHTML = html; body.scrollTop = 0; } })
          .catch(function () { if (token === state.token) body.innerHTML = '<div class="sheet__loading">Could not load this order. <a href="order-detail.php?id=' + id + '" style="text-decoration:underline">Open the full page</a>.</div>'; });
      }

      function close(velocity) {
        if (!state.open) return;
        state.open = false; state.token++;
        document.documentElement.style.overflow = '';
        springTo(state.size, velocity || 0).then(function () {
          if (state.open) return;
          sheet.classList.remove('is-open'); scrim.classList.remove('is-open');
          if (state.opener) state.opener.focus({ preventScroll: true });
        });
      }

      // Tap baris → lembar (klik dengan modifier / tab baru tetap membuka halaman penuh).
      document.addEventListener('click', function (e) {
        var row = e.target.closest && e.target.closest('a.o-row');
        if (!row || e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
        e.preventDefault();
        open(row.getAttribute('data-order'), row);
      });
      document.getElementById('sheetClose').addEventListener('click', function () { close(0); });
      document.getElementById('sheetPrint').addEventListener('click', function () { window.print(); });
      scrim.addEventListener('click', function () { close(0); });
      document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(0); });

      // Drag untuk menutup (ponsel): lembar menempel ke jari 1:1, dilepas dengan kecepatan jari + proyeksi momentum.
      var drag = null;
      bar.addEventListener('pointerdown', function (e) {
        if (state.axis !== 'y' || e.target.closest('button')) return;
        cancelAnimationFrame(state.raf);
        bar.setPointerCapture(e.pointerId);
        drag = { startY: e.clientY, startPos: state.pos, samples: [{ y: e.clientY, t: e.timeStamp }], moved: false };
      });
      bar.addEventListener('pointermove', function (e) {
        if (!drag) return;
        var dy = e.clientY - drag.startY;
        if (!drag.moved && Math.abs(dy) < 6) return;   // hysteresis
        drag.moved = true;
        var pos = drag.startPos + dy;
        if (pos < 0) pos = -(-pos * 0.55 * state.size) / (state.size + 0.55 * -pos);   // rubber-band di tepi atas
        state.pos = pos; apply();
        drag.samples.push({ y: e.clientY, t: e.timeStamp });
        if (drag.samples.length > 6) drag.samples.shift();
      });
      function release(e) {
        if (!drag) return;
        var d = drag; drag = null;
        if (!d.moved) { springTo(Math.max(0, state.pos), 0); return; }
        var a = d.samples[0], b = d.samples[d.samples.length - 1];
        var v = b.t > a.t ? (b.y - a.y) / (b.t - a.t) * 1000 : 0;          // px/s
        var projected = state.pos + (v / 1000) * 0.998 / (1 - 0.998);       // ke mana gestur ini menuju
        if (projected > state.size * 0.5) close(v); else springTo(0, v);
      }
      bar.addEventListener('pointerup', release);
      bar.addEventListener('pointercancel', release);
    })();
  </script>
</body>
</html>
