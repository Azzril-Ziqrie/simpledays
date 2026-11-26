<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/brand.php';
require_once __DIR__ . '/../includes/currency_helper.php';
require_once __DIR__ . '/../includes/campaign.php';

// Biasanya sudah dibuat oleh migrasi; dipanggil lagi supaya halaman ini tetap jalan kalau migrasi tertunda.
campaignCreateTables($pdo);

$message = '';
$error   = '';

/** Ambil & validasi isian form campaign. @return array{0: array, 1: ?string} [data, error] */
function readCampaignForm(array $post): array
{
    $productIds = array_values(array_unique(array_filter(
        array_map('intval', [(string)($post['product_1'] ?? ''), (string)($post['product_2'] ?? '')]),
        static fn(int $id): bool => $id > 0
    )));
    $data = [
        'name'         => trim((string)($post['name'] ?? '')),
        'min_subtotal' => max(0.0, round((float)($post['min_subtotal'] ?? 0), 2)),
        'product_ids'  => implode(',', $productIds),
        'starts_at'    => trim((string)($post['starts_at'] ?? '')) !== '' ? date('Y-m-d H:i:s', strtotime((string)$post['starts_at'])) : null,
        'ends_at'      => trim((string)($post['ends_at'] ?? '')) !== '' ? date('Y-m-d H:i:s', strtotime((string)$post['ends_at'])) : null,
        'usage_limit'  => trim((string)($post['usage_limit'] ?? '')) !== '' ? max(0, (int)$post['usage_limit']) : null,
        'is_active'    => isset($post['is_active']) ? 1 : 0,
    ];
    if ($data['name'] === '')       return [$data, 'Please enter a campaign name.'];
    if ($data['min_subtotal'] <= 0) return [$data, 'Minimum spend must be more than 0.'];
    if (!$productIds)               return [$data, 'Choose at least one gift product.'];
    if ($data['starts_at'] && $data['ends_at'] && $data['ends_at'] < $data['starts_at']) {
        return [$data, 'The end date must be after the start date.'];
    }
    return [$data, null];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals(ddAdminCsrf(), (string)($_POST['csrf'] ?? ''))) {
            throw new RuntimeException('Session expired. Please reload and try again.');
        }
        $action = (string)($_POST['action'] ?? '');
        $id     = (int)($_POST['id'] ?? 0);

        if ($action === 'save') {
            [$data, $formError] = readCampaignForm($_POST);
            if ($formError !== null) throw new RuntimeException($formError);
            if ($id > 0) {
                $pdo->prepare(
                    'UPDATE campaigns SET name=:name, min_subtotal=:min_subtotal, product_ids=:product_ids,
                     starts_at=:starts_at, ends_at=:ends_at, usage_limit=:usage_limit, is_active=:is_active WHERE id=:id'
                )->execute($data + ['id' => $id]);
                $message = 'Campaign updated.';
            } else {
                $pdo->prepare(
                    'INSERT INTO campaigns (name, min_subtotal, product_ids, starts_at, ends_at, usage_limit, is_active)
                     VALUES (:name, :min_subtotal, :product_ids, :starts_at, :ends_at, :usage_limit, :is_active)'
                )->execute($data);
                $message = 'Campaign created.';
            }
        } elseif ($action === 'toggle' && $id > 0) {
            $pdo->prepare('UPDATE campaigns SET is_active = 1 - is_active WHERE id = :id')->execute(['id' => $id]);
            $message = 'Campaign status changed.';
        } elseif ($action === 'delete' && $id > 0) {
            $claims = $pdo->prepare('SELECT COUNT(*) FROM campaign_claims WHERE campaign_id = :id');
            $claims->execute(['id' => $id]);
            if ((int)$claims->fetchColumn() > 0) {
                throw new RuntimeException('This campaign already has claimed gifts, so it is kept for your records. Turn it off instead.');
            }
            $pdo->prepare('DELETE FROM campaigns WHERE id = :id')->execute(['id' => $id]);
            $message = 'Campaign deleted.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$products = $pdo->query(
    'SELECT p.id, p.name, p.is_active,
            (SELECT COALESCE(SUM(ps.stock), 0) FROM product_sizes ps WHERE ps.product_id = p.id AND ps.is_active = 1) AS stock
     FROM products p ORDER BY p.name'
)->fetchAll(PDO::FETCH_ASSOC);
$productNames = array_column($products, 'name', 'id');

$campaigns = $pdo->query('SELECT * FROM campaigns ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
$current   = campaignCurrent($pdo);

$editing = null;
if (isset($_GET['edit'])) {
    foreach ($campaigns as $c) {
        if ((int)$c['id'] === (int)$_GET['edit']) $editing = $c;
    }
}
// Kalau simpan gagal, tampilkan lagi isian terakhir
$form = $editing ?? ['id' => 0, 'name' => '', 'min_subtotal' => '200', 'product_ids' => '', 'starts_at' => null,
                     'ends_at' => null, 'usage_limit' => null, 'is_active' => 1];
if ($error !== '' && ($_POST['action'] ?? '') === 'save') {
    $form = readCampaignForm($_POST)[0] + ['id' => (int)($_POST['id'] ?? 0)];
}
$formProducts = campaignProductIds($form);

$claims = $pdo->query(
    'SELECT cc.*, c.name AS campaign_name FROM campaign_claims cc
     LEFT JOIN campaigns c ON c.id = cc.campaign_id ORDER BY cc.id DESC LIMIT 50'
)->fetchAll(PDO::FETCH_ASSOC);

/** Status untuk tabel: alasan tidak jalan, atau stok hadiah habis, atau Running. */
function campaignStatus(PDO $pdo, array $c, ?array $current): array
{
    $reason = campaignInactiveReason($c);
    if ($reason !== null) return [$reason, 'expired'];
    if (!campaignGiftOptions($pdo, $c)) return ['Out of stock', 'expired'];
    if ($current && (int)$current['id'] === (int)$c['id']) return ['Running', 'active'];
    return ['Waiting (newer campaign runs)', ''];
}

function dtLocal(?string $value): string
{
    return $value ? date('Y-m-d\TH:i', strtotime($value)) : '';
}

$csrf = ddAdminCsrf();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Campaigns — <?= htmlspecialchars(BRAND_NAME) ?> Admin</title>
  <style>
    * { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; }
    body { font-family: Arial, Helvetica, sans-serif; background: #efefef; color: #111; }
    a { color: inherit; text-decoration: none; }
    .topbar { height: 74px; display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; padding: 0 22px; background: #efefef; border-bottom: 1px solid #bdbdbd; }
    .topbar__left, .topbar__right { display: flex; align-items: center; gap: 18px; }
    .topbar__right { justify-content: flex-end; }
    .nav-link { font-size: 12px; text-transform: uppercase; letter-spacing: .6px; }
    .wrap { max-width: 1100px; margin: 0 auto; border-left: 1px solid #bdbdbd; border-right: 1px solid #bdbdbd; min-height: calc(100vh - 74px); background: #efefef; }
    .head { padding: 20px; border-bottom: 1px solid #bdbdbd; }
    .title { margin: 0 0 8px; font-size: 30px; text-transform: uppercase; font-weight: 400; }
    .sub { margin: 0; color: #555; line-height: 1.7; font-size: 14px; }
    .notice { margin: 20px; padding: 14px 16px; border: 1px solid #bdbdbd; background: #f8f8f8; font-size: 13px; }
    .notice.success { border-color: #111; }
    .notice.error { border-color: #d70015; color: #d70015; }
    .notice.live { border-color: #1d8a4a; }
    .panel { padding: 20px; }
    .form-box, .list-box { border: 1px solid #bdbdbd; background: #f8f8f8; padding: 20px; margin-bottom: 20px; }
    .list-box { overflow-x: auto; }
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
    .btn-link { background: transparent; color: #111; border-color: transparent; padding: 0 8px; text-decoration: underline; min-height: 32px; }
    .btn-danger { color: #d70015; }
    .actions { display: flex; gap: 10px; flex-wrap: wrap; }
    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    th, td { text-align: left; padding: 10px 8px; border-bottom: 1px solid #ddd; vertical-align: middle; }
    th { font-size: 11px; text-transform: uppercase; letter-spacing: .6px; color: #555; }
    td.code { font-family: ui-monospace, Menlo, Consolas, monospace; font-weight: 700; }
    td.empty { color: #777; text-align: center; padding: 24px 8px; }
    .badge { display: inline-block; padding: 3px 8px; border: 1px solid #999; font-size: 11px; text-transform: uppercase; letter-spacing: .5px; }
    .badge.active { border-color: #1d8a4a; color: #1d8a4a; }
    .badge.expired { border-color: #d70015; color: #d70015; }
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
      <h1 class="title">Campaign</h1>
      <p class="sub">Give a free gift when the cart reaches a minimum spend. At checkout the shopper automatically gets a random gift code (e.g. GIFT-7K3QX9) and picks the colour/size of the gift. The gift is added to the order at $0 and its stock is deducted when the order is paid. A campaign stops by itself when the gift is out of stock, the limit is reached, or the end date passes. It works together with normal promo codes. If several campaigns are on, the newest one runs.</p>
    </section>

    <?php if ($message !== ''): ?><div class="notice success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="notice error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($current): ?>
      <div class="notice live">Live now: <strong><?= htmlspecialchars($current['name']) ?></strong> — orders of <?= htmlspecialchars(ddFormatMoney((float)$current['min_subtotal'], 'USD')) ?>+ get a free gift (<?= count($current['gifts']) ?> option<?= count($current['gifts']) === 1 ? '' : 's' ?> in stock).</div>
    <?php else: ?>
      <div class="notice">No campaign is running right now.</div>
    <?php endif; ?>

    <section class="panel">
      <form method="POST" class="form-box" action="campaigns.php">
        <h2><?= (int)$form['id'] > 0 ? 'Edit campaign' : 'New campaign' ?></h2>
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">

        <div class="grid">
          <div class="field">
            <label for="name">Campaign name</label>
            <input type="text" id="name" name="name" maxlength="120" required value="<?= htmlspecialchars((string)$form['name']) ?>" placeholder="Free Beanie over $200">
          </div>
          <div class="field">
            <label for="min_subtotal">Minimum spend (USD)</label>
            <input type="number" id="min_subtotal" name="min_subtotal" min="0.01" step="0.01" required value="<?= htmlspecialchars((string)$form['min_subtotal']) ?>">
            <small>Cart subtotal before shipping and promo discounts. The gift unlocks at this amount or more.</small>
          </div>
          <?php foreach ([1, 2] as $n): $selected = $formProducts[$n - 1] ?? 0; ?>
          <div class="field">
            <label for="product_<?= $n ?>">Gift product <?= $n ?><?= $n === 2 ? ' (optional)' : '' ?></label>
            <select id="product_<?= $n ?>" name="product_<?= $n ?>"<?= $n === 1 ? ' required' : '' ?>>
              <option value=""><?= $n === 1 ? 'Choose a product' : 'None' ?></option>
              <?php foreach ($products as $p): ?>
                <option value="<?= (int)$p['id'] ?>"<?= (int)$p['id'] === $selected ? ' selected' : '' ?>>
                  <?= htmlspecialchars($p['name']) ?> — stock <?= (int)$p['stock'] ?><?= (int)$p['is_active'] === 1 ? '' : ' (hidden in shop)' ?>
                </option>
              <?php endforeach; ?>
            </select>
            <?php if ($n === 1): ?><small>Shoppers choose from every colour/size of the gift that is in stock. Create the gift product first in Products, with its colours in Sizes &amp; stock.</small><?php else: ?><small>Add a second product to let shoppers pick one of the two.</small><?php endif; ?>
          </div>
          <?php endforeach; ?>
          <div class="field">
            <label for="starts_at">Starts (optional)</label>
            <input type="datetime-local" id="starts_at" name="starts_at" value="<?= htmlspecialchars(dtLocal($form['starts_at'])) ?>">
          </div>
          <div class="field">
            <label for="ends_at">Ends (optional)</label>
            <input type="datetime-local" id="ends_at" name="ends_at" value="<?= htmlspecialchars(dtLocal($form['ends_at'])) ?>">
          </div>
          <div class="field">
            <label for="usage_limit">Gift limit (optional)</label>
            <input type="number" id="usage_limit" name="usage_limit" min="0" step="1" value="<?= htmlspecialchars((string)($form['usage_limit'] ?? '')) ?>" placeholder="Unlimited">
            <small>Maximum number of gifts to give away. Leave empty to run until the gift is out of stock.</small>
          </div>
        </div>

        <div class="check-row">
          <input type="checkbox" id="is_active" name="is_active" value="1"<?= !empty($form['is_active']) ? ' checked' : '' ?>>
          <label for="is_active" style="margin:0;">Campaign is on</label>
        </div>

        <div class="actions">
          <button type="submit" class="btn"><?= (int)$form['id'] > 0 ? 'Save changes' : 'Create campaign' ?></button>
          <?php if ((int)$form['id'] > 0): ?><a href="campaigns.php" class="btn-secondary btn">Cancel</a><?php endif; ?>
        </div>
      </form>

      <div class="list-box">
        <h2>Campaigns</h2>
        <table>
          <thead><tr><th>Name</th><th>Min. spend</th><th>Gift</th><th>Dates</th><th>Given</th><th>Status</th><th></th></tr></thead>
          <tbody>
          <?php if (!$campaigns): ?>
            <tr><td colspan="7" class="empty">No campaigns yet.</td></tr>
          <?php endif; ?>
          <?php foreach ($campaigns as $c): [$statusLabel, $statusClass] = campaignStatus($pdo, $c, $current); ?>
            <tr>
              <td><?= htmlspecialchars($c['name']) ?></td>
              <td><?= htmlspecialchars(ddFormatMoney((float)$c['min_subtotal'], 'USD')) ?></td>
              <td><?= htmlspecialchars(implode(' / ', array_map(static fn(int $id): string => $productNames[$id] ?? ('#' . $id . ' (deleted)'), campaignProductIds($c)))) ?></td>
              <td><?= $c['starts_at'] ? htmlspecialchars(date('d M Y H:i', strtotime($c['starts_at']))) : 'Now' ?> – <?= $c['ends_at'] ? htmlspecialchars(date('d M Y H:i', strtotime($c['ends_at']))) : 'no end' ?></td>
              <td><?= (int)$c['used_count'] ?><?= $c['usage_limit'] !== null ? ' / ' . (int)$c['usage_limit'] : '' ?></td>
              <td><span class="badge <?= $statusClass ?>"><?= htmlspecialchars($statusLabel) ?></span></td>
              <td>
                <div class="row-actions">
                  <a class="btn-link" href="campaigns.php?edit=<?= (int)$c['id'] ?>">Edit</a>
                  <form method="POST">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                    <button class="btn-link" name="action" value="toggle"><?= (int)$c['is_active'] === 1 ? 'Turn off' : 'Turn on' ?></button>
                  </form>
                  <?php if ((int)$c['used_count'] === 0): ?>
                  <form method="POST" onsubmit="return confirm('Delete this campaign?');">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                    <button class="btn-link btn-danger" name="action" value="delete">Delete</button>
                  </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="list-box">
        <h2>Claimed gift codes (latest 50)</h2>
        <table>
          <thead><tr><th>Code</th><th>Order</th><th>Gift</th><th>Campaign</th><th>Date</th></tr></thead>
          <tbody>
          <?php if (!$claims): ?>
            <tr><td colspan="5" class="empty">No gifts claimed yet.</td></tr>
          <?php endif; ?>
          <?php foreach ($claims as $cl): ?>
            <tr>
              <td class="code"><?= htmlspecialchars($cl['code']) ?></td>
              <td><a href="order-detail.php?id=<?= (int)$cl['order_id'] ?>" style="text-decoration:underline"><?= htmlspecialchars($cl['order_number']) ?></a></td>
              <td><?= htmlspecialchars($cl['gift_label']) ?></td>
              <td><?= htmlspecialchars((string)($cl['campaign_name'] ?? '—')) ?></td>
              <td><?= htmlspecialchars(date('d M Y H:i', strtotime($cl['created_at']))) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>
</body>
</html>
