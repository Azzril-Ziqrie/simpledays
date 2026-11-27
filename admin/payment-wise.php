<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/brand.php';
require_once __DIR__ . '/../includes/wise_payment.php';

$message = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals(ddAdminCsrf(), (string)($_POST['csrf'] ?? ''))) {
            throw new RuntimeException('Session expired. Please reload and try again.');
        }
        $rawLink = trim((string)($_POST['pay_link'] ?? ''));
        $settings = wiseNormalize($_POST);
        if ($rawLink !== '' && $settings['pay_link'] === '') {
            throw new RuntimeException('The Wise payment link must start with https://');
        }
        if ($settings['enabled'] && !wiseIsAvailable($settings)) {
            throw new RuntimeException('Add at least one account or a payment link before turning Wise on.');
        }
        wiseSave($pdo, $settings);
        $message = 'Wise settings saved.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$w = $error !== '' && $_SERVER['REQUEST_METHOD'] === 'POST' ? wiseNormalize($_POST) : wiseSettings($pdo);
// Selalu sisakan 2 baris kosong untuk rekening baru
$rows = $w['accounts'];
for ($i = 0; $i < 2 && count($rows) < WISE_MAX_ACCOUNTS; $i++) $rows[] = ['currency' => '', 'details' => ''];
$csrf = ddAdminCsrf();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Wise Payments — <?= htmlspecialchars(BRAND_NAME) ?> Admin</title>
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
    .notice.success { border-color: #111; }
    .notice.error { border-color: #d70015; color: #d70015; }
    .panel { padding: 20px; }
    .form-box { border: 1px solid #bdbdbd; background: #f8f8f8; padding: 20px; margin-bottom: 20px; }
    .form-box h2, .list-box h2 { margin: 0 0 16px; font-size: 16px; text-transform: uppercase; font-weight: 400; letter-spacing: .6px; }
    .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 16px; }
    .field { display: flex; flex-direction: column; gap: 8px; margin-bottom: 16px; }
    .field small { color: #666; font-size: 12px; line-height: 1.5; }
    label { font-size: 12px; text-transform: uppercase; letter-spacing: .6px; }
    input, select { width: 100%; min-height: 48px; border: 1px solid #bdbdbd; background: #fff; padding: 12px 14px; outline: none; font: inherit; color: #111; }
    .check-row { display: flex; align-items: center; gap: 10px; margin-bottom: 18px; }
    .check-row input { width: auto; min-height: auto; }
    .btn, .btn-secondary, .btn-link { min-height: 40px; padding: 0 16px; border: 1px solid #111; background: #111; color: #fff; text-transform: uppercase; font-size: 12px; letter-spacing: .6px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-family: inherit; }
    .btn-secondary { background: transparent; color: #111; border-color: #999; }
    .btn-link { background: transparent; color: #111; border-color: transparent; padding: 0 8px; text-decoration: underline; }
    .btn-danger { color: #d70015; }
    .actions { display: flex; gap: 10px; flex-wrap: wrap; }
    .list-box { border: 1px solid #bdbdbd; background: #f8f8f8; padding: 20px; overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    th, td { text-align: left; padding: 10px 8px; border-bottom: 1px solid #ddd; vertical-align: middle; }
    th { font-size: 11px; text-transform: uppercase; letter-spacing: .6px; color: #555; }
    td.code { font-family: ui-monospace, Menlo, Consolas, monospace; font-weight: 700; }
    .badge { display: inline-block; padding: 3px 8px; border: 1px solid #999; font-size: 11px; text-transform: uppercase; letter-spacing: .5px; }
    .badge.active { border-color: #1d8a4a; color: #1d8a4a; }
    .badge.expired, .badge.usedup { border-color: #d70015; color: #d70015; }
    .row-actions { display: flex; gap: 4px; align-items: center; flex-wrap: wrap; }
    .row-actions form { margin: 0; }
    @media (max-width: 640px) { .grid { grid-template-columns: 1fr; } }
    textarea { width: 100%; min-height: 120px; border: 1px solid #bdbdbd; background: #fff; padding: 12px 14px; font: 13px/1.6 ui-monospace, Menlo, Consolas, monospace; color: #111; resize: vertical; }
    .acct { display: grid; grid-template-columns: 120px 1fr; gap: 16px; padding: 16px 0; border-top: 1px solid #ddd; }
    .acct:first-of-type { border-top: 0; padding-top: 0; }
    @media (max-width: 640px) { .acct { grid-template-columns: 1fr; } }
  </style>
  <link rel="stylesheet" href="admin-theme.css?v=1">
  <script src="../assets/js/validation-en.js" defer></script>
</head>
<body>
  <header class="topbar">
    <div class="topbar__left"><a href="dashboard.php" class="nav-link">Dashboard</a></div>
    <a href="../index.php" class="nav-link">Storefront</a>
    <div class="topbar__right"><a href="dashboard-orders.php" class="nav-link">Orders</a></div>
  </header>

  <main class="wrap">
    <section class="head">
      <h1 class="title">Wise Payments</h1>
      <p class="sub">Let shoppers pay by bank transfer to your Wise account. They see your account details at checkout, transfer the order total with a short payment code (e.g. SD79DE5639, shown on the order page) as reference, then upload their proof. Check the payment in your Wise app, then approve the order in Orders (status "Waiting verification").</p>
    </section>

    <?php if ($message !== ''): ?><div class="notice success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="notice error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <section class="panel">
      <form method="POST" class="form-box">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">

        <div class="check-row">
          <input type="checkbox" id="enabled" name="enabled" value="1" <?= $w['enabled'] ? 'checked' : '' ?>>
          <label for="enabled" style="margin:0;">Show Wise as a payment option at checkout</label>
        </div>

        <h2>Account details</h2>
        <p class="sub" style="margin:-6px 0 16px">In the Wise app open a balance, then "Account details" (or "Receive"). Copy them here, one <strong>Label: value</strong> per line. Orders are in USD; for other currencies the shopper sees the total converted at today's rate.</p>

        <?php foreach ($rows as $i => $acct): ?>
          <div class="acct">
            <div class="field" style="margin:0">
              <label for="cur<?= $i ?>">Currency</label>
              <input type="text" id="cur<?= $i ?>" name="accounts[currency][]" maxlength="3" style="text-transform:uppercase"
                     value="<?= htmlspecialchars($acct['currency']) ?>" placeholder="USD">
            </div>
            <div class="field" style="margin:0">
              <label for="det<?= $i ?>">Details</label>
              <textarea id="det<?= $i ?>" name="accounts[details][]" placeholder="Account holder: Simple Days&#10;Routing number (ACH): 026073150&#10;Account number: 1234567890&#10;Account type: Checking&#10;Bank: Community Federal Savings Bank"><?= htmlspecialchars($acct['details']) ?></textarea>
            </div>
          </div>
        <?php endforeach; ?>
        <p class="sub" style="margin:0 0 20px;font-size:12px">Leave a row empty to remove it. Save to get more empty rows (up to <?= WISE_MAX_ACCOUNTS ?>).</p>

        <div class="field">
          <label for="pay_link">Wise payment link (optional)</label>
          <input type="url" id="pay_link" name="pay_link" maxlength="300" value="<?= htmlspecialchars($w['pay_link']) ?>" placeholder="https://wise.com/pay/me/yourname">
          <small>If you have a Wise "Pay me" / payment link, shoppers get a button to open it.</small>
        </div>

        <div class="field">
          <label for="note">Extra instructions (optional)</label>
          <input type="text" id="note" name="note" maxlength="500" value="<?= htmlspecialchars($w['note']) ?>" placeholder="e.g. Please cover any transfer fees.">
        </div>

        <div class="actions"><button type="submit" class="btn">Save Wise settings</button></div>
      </form>
    </section>
  </main>
</body>
</html>
