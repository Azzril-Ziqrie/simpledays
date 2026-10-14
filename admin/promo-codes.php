<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/currency_helper.php';
require_once __DIR__ . '/../includes/promo.php';

promoEnsureSchema($pdo);

$message = '';
$error   = '';

/** Ambil & validasi isian form promo. @return array{0: array, 1: ?string} [data, error] */
function readPromoForm(array $post): array
{
    $type = (string)($post['type'] ?? '');
    $data = [
        'code'         => promoNormalizeCode((string)($post['code'] ?? '')),
        'description'  => trim((string)($post['description'] ?? '')),
        'type'         => $type,
        'value'        => (float)($post['value'] ?? 0),
        'max_discount' => trim((string)($post['max_discount'] ?? '')) !== '' ? (float)$post['max_discount'] : null,
        'min_subtotal' => max(0.0, (float)($post['min_subtotal'] ?? 0)),
        'starts_at'    => trim((string)($post['starts_at'] ?? '')) !== '' ? date('Y-m-d H:i:s', strtotime((string)$post['starts_at'])) : null,
        'ends_at'      => trim((string)($post['ends_at'] ?? '')) !== '' ? date('Y-m-d H:i:s', strtotime((string)$post['ends_at'])) : null,
        'usage_limit'  => trim((string)($post['usage_limit'] ?? '')) !== '' ? max(0, (int)$post['usage_limit']) : null,
        'is_active'    => isset($post['is_active']) ? 1 : 0,
    ];

    if (!promoIsValidCodeFormat($data['code'])) return [$data, 'Code must be 3–32 characters: letters, numbers, "-" or "_".'];
    if (!isset(PROMO_TYPES[$type])) return [$data, 'Please choose a promo type.'];
    if ($type === 'percent' && ($data['value'] <= 0 || $data['value'] > 100)) return [$data, 'Percent must be between 0 and 100.'];
    if ($type === 'fixed' && $data['value'] <= 0) return [$data, 'Fixed amount must be greater than 0.'];
    if ($type === 'free_shipping') $data['value'] = 0.0;
    if ($type !== 'percent') $data['max_discount'] = null;
    if ($data['max_discount'] !== null && $data['max_discount'] <= 0) $data['max_discount'] = null;
    if ($data['starts_at'] !== null && $data['ends_at'] !== null && strtotime($data['ends_at']) < strtotime($data['starts_at'])) {
        return [$data, 'End date must be after the start date.'];
    }
    return [$data, null];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals(ddAdminCsrf(), (string)($_POST['csrf'] ?? ''))) {
        $error = 'Session expired. Please reload the page and try again.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        $id     = (int)($_POST['id'] ?? 0);

        try {
            if ($action === 'save') {
                [$data, $problem] = readPromoForm($_POST);
                if ($problem !== null) {
                    $error = $problem;
                } elseif ($id > 0) {
                    $data['id'] = $id;
                    $pdo->prepare(
                        'UPDATE promo_codes SET code=:code, description=:description, type=:type, value=:value,
                            max_discount=:max_discount, min_subtotal=:min_subtotal, starts_at=:starts_at,
                            ends_at=:ends_at, usage_limit=:usage_limit, is_active=:is_active WHERE id=:id'
                    )->execute($data);
                    $message = 'Promo code updated.';
                } else {
                    $pdo->prepare(
                        'INSERT INTO promo_codes (code, description, type, value, max_discount, min_subtotal,
                            starts_at, ends_at, usage_limit, is_active)
                         VALUES (:code, :description, :type, :value, :max_discount, :min_subtotal,
                            :starts_at, :ends_at, :usage_limit, :is_active)'
                    )->execute($data);
                    $message = 'Promo code created.';
                }
            } elseif ($action === 'toggle' && $id > 0) {
                $pdo->prepare('UPDATE promo_codes SET is_active = 1 - is_active WHERE id = :id')->execute(['id' => $id]);
                $message = 'Promo code status changed.';
            } elseif ($action === 'delete' && $id > 0) {
                $pdo->prepare('DELETE FROM promo_codes WHERE id = :id')->execute(['id' => $id]);
                $message = 'Promo code deleted.';
            }
        } catch (PDOException $e) {
            $error = $e->getCode() === '23000' ? 'That code already exists.' : 'Could not save: ' . $e->getMessage();
        }
    }
}

$editId   = (int)($_GET['edit'] ?? 0);
$editing  = null;
$promos   = $pdo->query('SELECT * FROM promo_codes ORDER BY is_active DESC, id DESC')->fetchAll(PDO::FETCH_ASSOC);
foreach ($promos as $p) if ((int)$p['id'] === $editId) $editing = $p;

// Setelah gagal simpan, tampilkan kembali isian pengguna
$form = $editing ?? [
    'id' => 0, 'code' => '', 'description' => '', 'type' => 'percent', 'value' => '10', 'max_discount' => '',
    'min_subtotal' => '0', 'starts_at' => '', 'ends_at' => '', 'usage_limit' => '', 'is_active' => 1,
];
if ($error !== '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = array_merge($form, array_intersect_key($_POST, $form));
    $form['is_active'] = isset($_POST['is_active']) ? 1 : 0;
    $form['id'] = (int)($_POST['id'] ?? 0);
}

function dtLocal($value): string
{
    return !empty($value) ? date('Y-m-d\TH:i', strtotime((string)$value)) : '';
}

function promoStatus(array $p): string
{
    if ((int)$p['is_active'] !== 1) return 'Inactive';
    $now = time();
    if (!empty($p['ends_at']) && $now > strtotime($p['ends_at'])) return 'Expired';
    if (!empty($p['starts_at']) && $now < strtotime($p['starts_at'])) return 'Scheduled';
    if ($p['usage_limit'] !== null && (int)$p['used_count'] >= (int)$p['usage_limit']) return 'Used up';
    return 'Active';
}

$csrf = ddAdminCsrf();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Promo Codes — <?= htmlspecialchars(BRAND_NAME) ?> Admin</title>
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
      <h1 class="title">Promo Codes</h1>
      <p class="sub">Create optional codes shoppers can enter at checkout: a percentage off, a fixed amount off, or free shipping, with a minimum spend. All amounts are in USD.</p>
    </section>

    <?php if ($message !== ''): ?><div class="notice success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="notice error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <section class="panel">
      <form method="POST" class="form-box" id="promoForm">
        <h2><?= (int)$form['id'] > 0 ? 'Edit promo code' : 'New promo code' ?></h2>
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">

        <div class="grid">
          <div class="field">
            <label for="code">Code</label>
            <input type="text" id="code" name="code" maxlength="32" required style="text-transform:uppercase"
                   value="<?= htmlspecialchars((string)$form['code']) ?>" placeholder="WELCOME10">
            <small>Letters, numbers, "-" or "_". Shoppers type it at checkout (not case sensitive).</small>
          </div>
          <div class="field">
            <label for="type">Type</label>
            <select id="type" name="type">
              <?php foreach (PROMO_TYPES as $key => $label): ?>
                <option value="<?= $key ?>" <?= $form['type'] === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="field" id="valueField">
            <label for="value" id="valueLabel">Value</label>
            <input type="number" id="value" name="value" step="0.01" min="0" value="<?= htmlspecialchars((string)$form['value']) ?>">
            <small id="valueHint"></small>
          </div>
          <div class="field" id="maxField">
            <label for="max_discount">Maximum discount (USD)</label>
            <input type="number" id="max_discount" name="max_discount" step="0.01" min="0"
                   value="<?= htmlspecialchars((string)($form['max_discount'] ?? '')) ?>" placeholder="No limit">
            <small>Caps a percentage discount, e.g. 20% off but never more than $15.</small>
          </div>

          <div class="field">
            <label for="min_subtotal">Minimum spend (USD)</label>
            <input type="number" id="min_subtotal" name="min_subtotal" step="0.01" min="0" value="<?= htmlspecialchars((string)$form['min_subtotal']) ?>">
            <small>Subtotal of the items (after product discounts), before shipping. 0 = no minimum.</small>
          </div>
          <div class="field">
            <label for="usage_limit">Usage limit</label>
            <input type="number" id="usage_limit" name="usage_limit" step="1" min="0"
                   value="<?= htmlspecialchars((string)($form['usage_limit'] ?? '')) ?>" placeholder="Unlimited">
            <small>Total number of orders that can use this code.</small>
          </div>

          <div class="field">
            <label for="starts_at">Starts</label>
            <input type="datetime-local" id="starts_at" name="starts_at" value="<?= htmlspecialchars(dtLocal($form['starts_at'])) ?>">
          </div>
          <div class="field">
            <label for="ends_at">Ends</label>
            <input type="datetime-local" id="ends_at" name="ends_at" value="<?= htmlspecialchars(dtLocal($form['ends_at'])) ?>">
          </div>
        </div>

        <div class="field">
          <label for="description">Internal note</label>
          <input type="text" id="description" name="description" maxlength="190" value="<?= htmlspecialchars((string)$form['description']) ?>" placeholder="Optional — e.g. Instagram giveaway June">
        </div>

        <div class="check-row">
          <input type="checkbox" id="is_active" name="is_active" value="1" <?= (int)$form['is_active'] === 1 ? 'checked' : '' ?>>
          <label for="is_active" style="margin:0;">Active</label>
        </div>

        <div class="actions">
          <button type="submit" class="btn"><?= (int)$form['id'] > 0 ? 'Save changes' : 'Create promo code' ?></button>
          <?php if ((int)$form['id'] > 0): ?><a href="promo-codes.php" class="btn-secondary">Cancel</a><?php endif; ?>
        </div>
      </form>

      <div class="list-box">
        <h2>All promo codes (<?= count($promos) ?>)</h2>
        <?php if (!$promos): ?>
          <p class="sub">No promo codes yet. Create the first one above.</p>
        <?php else: ?>
          <table>
            <thead>
              <tr><th>Code</th><th>Discount</th><th>Min spend</th><th>Valid</th><th>Used</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
              <?php foreach ($promos as $p): $status = promoStatus($p); ?>
                <tr>
                  <td class="code"><?= htmlspecialchars($p['code']) ?><?php if ($p['description'] !== null && $p['description'] !== ''): ?><br><small style="font-weight:400;font-family:Arial"><?= htmlspecialchars($p['description']) ?></small><?php endif; ?></td>
                  <td><?= htmlspecialchars(promoLabel($p)) ?></td>
                  <td><?= (float)$p['min_subtotal'] > 0 ? htmlspecialchars(ddFormatMoney((float)$p['min_subtotal'])) : '—' ?></td>
                  <td>
                    <?= $p['starts_at'] ? htmlspecialchars(date('d M Y', strtotime($p['starts_at']))) : 'Now' ?>
                    → <?= $p['ends_at'] ? htmlspecialchars(date('d M Y', strtotime($p['ends_at']))) : 'No end' ?>
                  </td>
                  <td><?= (int)$p['used_count'] ?><?= $p['usage_limit'] !== null ? ' / ' . (int)$p['usage_limit'] : '' ?></td>
                  <td><span class="badge <?= strtolower(str_replace(' ', '', $status)) ?>"><?= htmlspecialchars($status) ?></span></td>
                  <td>
                    <div class="row-actions">
                      <a class="btn-link" href="promo-codes.php?edit=<?= (int)$p['id'] ?>">Edit</a>
                      <form method="POST">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                        <button type="submit" class="btn-link"><?= (int)$p['is_active'] === 1 ? 'Disable' : 'Enable' ?></button>
                      </form>
                      <form method="POST" onsubmit="return confirm('Delete this promo code? Past orders keep their record.');">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                        <button type="submit" class="btn-link btn-danger">Delete</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </section>
  </main>

  <script>
    // Isian "Value" menyesuaikan tipe promo.
    (function () {
      var type = document.getElementById('type');
      var label = document.getElementById('valueLabel');
      var hint = document.getElementById('valueHint');
      var valueField = document.getElementById('valueField');
      var maxField = document.getElementById('maxField');
      var value = document.getElementById('value');
      function sync() {
        var t = type.value;
        valueField.style.display = t === 'free_shipping' ? 'none' : '';
        maxField.style.display = t === 'percent' ? '' : 'none';
        if (t === 'percent') { label.textContent = 'Percent off (%)'; hint.textContent = 'e.g. 10 = 10% off the items.'; value.max = 100; }
        else if (t === 'fixed') { label.textContent = 'Amount off (USD)'; hint.textContent = 'e.g. 5 = $5.00 off the items.'; value.removeAttribute('max'); }
        else { hint.textContent = ''; }
      }
      type.addEventListener('change', sync);
      sync();
    })();
  </script>
</body>
</html>
