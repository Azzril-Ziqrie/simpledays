<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/custom_payment.php';

cpEnsureSchema($pdo);

$error = '';
$rates = cpCurrencies();
$csrf  = ddAdminCsrf();

// Buat pembayaran baru → redirect ke ?pay=REFERENCE (PRG, supaya reload tidak membuat dobel).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        $error = 'Session expired. Please reload the page and try again.';
    } else {
        [$created, $problem] = cpCreate(
            $pdo,
            (string)($_POST['title'] ?? ''),
            (float)str_replace(',', '', (string)($_POST['amount'] ?? '0')),
            (string)($_POST['currency'] ?? 'USD')
        );
        if ($created) {
            header('Location: payments.php?pay=' . urlencode($created['reference']));
            exit;
        }
        $error = (string)$problem;
    }
}

$current  = ($ref = trim((string)($_GET['pay'] ?? ''))) !== '' ? cpFind($pdo, $ref) : null;
$history  = $pdo->query('SELECT * FROM custom_payments ORDER BY id DESC LIMIT 50')->fetchAll(PDO::FETCH_ASSOC);
$formCur  = strtoupper((string)($_POST['currency'] ?? 'USD'));
$payable  = $current && $current['status'] !== 'paid';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Payments — Simple Days Admin</title>
  <style>
    * { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; }
    body { font-family: Arial, Helvetica, sans-serif; background: #efefef; color: #111; }
    a { color: inherit; text-decoration: none; }
    .topbar { height: 74px; display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; padding: 0 22px; background: #efefef; border-bottom: 1px solid #bdbdbd; }
    .topbar__left, .topbar__right { display: flex; align-items: center; gap: 18px; }
    .topbar__right { justify-content: flex-end; }
    .nav-link { font-size: 12px; text-transform: uppercase; letter-spacing: .6px; }
    .wrap { max-width: 1000px; margin: 0 auto; border-left: 1px solid #bdbdbd; border-right: 1px solid #bdbdbd; min-height: calc(100vh - 74px); background: #efefef; }
    .head { padding: 20px; border-bottom: 1px solid #bdbdbd; }
    .title { margin: 0 0 8px; font-size: 30px; text-transform: uppercase; font-weight: 400; }
    .sub { margin: 0; color: #555; line-height: 1.7; font-size: 14px; }
    .notice { margin: 20px; padding: 14px 16px; border: 1px solid #bdbdbd; background: #f8f8f8; font-size: 13px; }
    .notice.success { border-color: #1d8a4a; color: #1d8a4a; }
    .notice.error { border-color: #d70015; color: #d70015; }
    .panel { padding: 20px; }
    .box { border: 1px solid #bdbdbd; background: #f8f8f8; padding: 20px; margin-bottom: 20px; overflow-x: auto; }
    .box h2 { margin: 0 0 16px; font-size: 16px; text-transform: uppercase; font-weight: 400; letter-spacing: .6px; }
    .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 16px; }
    .field { display: flex; flex-direction: column; gap: 8px; margin-bottom: 16px; }
    .field small { color: #666; font-size: 12px; line-height: 1.5; }
    label { font-size: 12px; text-transform: uppercase; letter-spacing: .6px; }
    input, select { width: 100%; min-height: 48px; border: 1px solid #bdbdbd; background: #fff; padding: 12px 14px; outline: none; font: inherit; color: #111; }
    .btn, .btn-secondary { min-height: 40px; padding: 0 16px; border: 1px solid #111; background: #111; color: #fff; text-transform: uppercase; font-size: 12px; letter-spacing: .6px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-family: inherit; }
    .btn-secondary { background: transparent; color: #111; border-color: #999; }
    .preview { padding: 14px 16px; border: 1px dashed #999; background: #fff; margin-bottom: 16px; font-size: 14px; line-height: 1.7; }
    .preview strong { font-size: 22px; }
    .total { font-size: 34px; margin: 0 0 4px; }
    .muted { color: #555; font-size: 13px; line-height: 1.6; margin: 0 0 16px; }
    #paypal-button-container { max-width: 440px; margin-top: 8px; }
    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    th, td { text-align: left; padding: 10px 8px; border-bottom: 1px solid #ddd; vertical-align: middle; }
    th { font-size: 11px; text-transform: uppercase; letter-spacing: .6px; color: #555; }
    td.ref { font-family: ui-monospace, Menlo, Consolas, monospace; font-weight: 700; }
    .badge { display: inline-block; padding: 3px 8px; border: 1px solid #999; font-size: 11px; text-transform: uppercase; letter-spacing: .5px; }
    .badge.paid { border-color: #1d8a4a; color: #1d8a4a; }
    .badge.failed { border-color: #d70015; color: #d70015; }
    .badge.pending { border-color: #b25e00; color: #b25e00; }
    @media (max-width: 640px) { .grid { grid-template-columns: 1fr; } }
  </style>
  <link rel="stylesheet" href="admin-theme.css?v=1">
</head>
<body>
  <header class="topbar">
    <div class="topbar__left"><a href="dashboard.php" class="nav-link">Dashboard</a></div>
    <a href="../index.php" class="nav-link">Storefront</a>
    <div class="topbar__right"><a href="dashboard-orders.php" class="nav-link">Orders</a></div>
  </header>

  <main class="wrap">
    <section class="head">
      <h1 class="title">Payments</h1>
      <p class="sub">Enter an amount in USD or another currency, we convert it to USD, then you pay it with PayPal. PayPal always charges in USD.</p>
    </section>

    <?php if ($error !== ''): ?><div class="notice error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if (!ddExchangeRates()): ?>
      <div class="notice">Live exchange rates are unavailable (no <code>EXCHANGERATE_API_KEY</code> set), so only USD and IDR (fallback rate) can be used.</div>
    <?php endif; ?>

    <section class="panel">
      <?php if ($current): ?>
        <div class="box" id="payBox">
          <h2><?= $current['status'] === 'paid' ? 'Payment received' : 'Pay with PayPal' ?></h2>
          <p class="total">$<?= number_format((float)$current['amount_usd'], 2) ?> <small style="font-size:14px">USD</small></p>
          <p class="muted">
            <?= htmlspecialchars($current['title']) ?> · <?= htmlspecialchars($current['reference']) ?>
            <?php if ($current['source_currency'] !== 'USD'): ?>
              <br><?= htmlspecialchars($current['source_currency']) ?> <?= number_format((float)$current['source_amount'], 2) ?>
              @ 1 USD = <?= rtrim(rtrim(number_format((float)$current['exchange_rate'], 6, '.', ''), '0'), '.') ?> <?= htmlspecialchars($current['source_currency']) ?>
            <?php endif; ?>
          </p>
          <?php if ($payable): ?>
            <div id="paypal-button-container"></div>
            <div id="paypal-status" class="muted" style="color:#d70015"></div>
          <?php else: ?>
            <div class="notice success" style="margin:0">Paid<?= $current['paid_at'] ? ' on ' . htmlspecialchars(date('d M Y H:i', strtotime($current['paid_at']))) : '' ?>.</div>
          <?php endif; ?>
          <p style="margin:16px 0 0"><a class="btn-secondary" href="payments.php">New payment</a></p>
        </div>
      <?php else: ?>
        <form method="POST" class="box" id="payForm">
          <h2>New payment</h2>
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
          <div class="field">
            <label for="title">For</label>
            <input type="text" id="title" name="title" maxlength="190" required
                   value="<?= htmlspecialchars((string)($_POST['title'] ?? '')) ?>" placeholder="e.g. Event booth fee, Photographer deposit">
          </div>
          <div class="grid">
            <div class="field">
              <label for="amount">Amount</label>
              <input type="number" id="amount" name="amount" step="0.01" min="0.01" required inputmode="decimal"
                     value="<?= htmlspecialchars((string)($_POST['amount'] ?? '')) ?>" placeholder="0.00">
            </div>
            <div class="field">
              <label for="currency">Currency</label>
              <select id="currency" name="currency">
                <?php foreach (array_keys($rates) as $code): ?>
                  <option value="<?= $code ?>" <?= $formCur === $code ? 'selected' : '' ?>><?= $code ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="preview" id="preview">You will pay <strong>$0.00</strong> USD</div>
          <button type="submit" class="btn">Continue to PayPal</button>
        </form>
      <?php endif; ?>

      <div class="box">
        <h2>Recent payments</h2>
        <?php if (!$history): ?>
          <p class="sub">No payments yet.</p>
        <?php else: ?>
          <table>
            <thead><tr><th>Ref</th><th>For</th><th>Original</th><th>USD</th><th>Status</th><th>Date</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($history as $h): ?>
                <tr>
                  <td class="ref"><?= htmlspecialchars($h['reference']) ?></td>
                  <td><?= htmlspecialchars($h['title']) ?></td>
                  <td><?= $h['source_currency'] === 'USD' ? '—' : htmlspecialchars($h['source_currency']) . ' ' . number_format((float)$h['source_amount'], 2) ?></td>
                  <td>$<?= number_format((float)$h['amount_usd'], 2) ?></td>
                  <td><span class="badge <?= htmlspecialchars($h['status']) ?>"><?= htmlspecialchars($h['status']) ?></span></td>
                  <td><?= htmlspecialchars(date('d M Y H:i', strtotime($h['paid_at'] ?: $h['created_at']))) ?></td>
                  <td><?php if ($h['status'] !== 'paid'): ?><a class="btn-secondary" href="payments.php?pay=<?= urlencode($h['reference']) ?>">Pay</a><?php endif; ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </section>
  </main>

<?php if (!$current): ?>
  <script>
    // Pratinjau konversi. Nominal resmi tetap dihitung ulang di server saat dikirim.
    (function () {
      var RATES = <?= json_encode($rates, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
      var amount = document.getElementById('amount'), cur = document.getElementById('currency'), out = document.getElementById('preview');
      function sync() {
        var a = parseFloat(amount.value), r = RATES[cur.value], c = cur.value;
        if (!isFinite(a) || a <= 0 || !r) { out.innerHTML = 'You will pay <strong>$0.00</strong> USD'; return; }
        var usd = Math.round(a / r * 100) / 100;
        var text = 'You will pay <strong>$' + usd.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</strong> USD';
        if (c !== 'USD') text += '<br><small>' + a.toLocaleString('en-US') + ' ' + c + ' · 1 USD = ' + r + ' ' + c + ' (estimate)</small>';
        out.innerHTML = text;
      }
      amount.addEventListener('input', sync);
      cur.addEventListener('change', sync);
      sync();
    })();
  </script>
<?php elseif ($payable): ?>
  <script src="https://www.paypal.com/sdk/js?client-id=<?= htmlspecialchars(PAYPAL_CLIENT_ID) ?>&components=buttons&currency=USD&intent=capture"></script>
  <script>
    (function () {
      var REF = <?= json_encode($current['reference']) ?>, CSRF = <?= json_encode($csrf) ?>;
      var box = document.getElementById('paypal-button-container'), status = document.getElementById('paypal-status');
      var fail = function (m) { status.textContent = m; };
      if (!window.paypal) { box.textContent = 'PayPal could not be loaded. Please refresh the page.'; return; }
      var headers = { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF };
      paypal.Buttons({
        createOrder: function () {
          return fetch('payment-create-order.php?ref=' + encodeURIComponent(REF), { method: 'POST', headers: headers })
            .then(function (r) { return r.json(); })
            .then(function (d) { if (!d.id) throw new Error(d.error || 'Could not start PayPal payment.'); return d.id; });
        },
        onApprove: function (data) {
          return fetch('payment-capture.php', { method: 'POST', headers: headers, body: JSON.stringify({ reference: REF, paypal_order_id: data.orderID }) })
            .then(function (r) { return r.json(); })
            .then(function (d) {
              if (d.status === 'COMPLETED') window.location.reload();
              else fail(d.error || 'Payment could not be completed.');
            });
        },
        onError: function (e) { fail((e && e.message) || 'PayPal error. Please try again.'); }
      }).render('#paypal-button-container');
    })();
  </script>
<?php endif; ?>
</body>
</html>
