<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/db_schema.php';
require_once __DIR__ . '/../includes/media_upload.php';
require_once __DIR__ . '/../includes/home_media.php';

const HOME_MEDIA_DIR_WEB = 'assets/images/home/';
const HOME_MEDIA_MAX_WIDTH = 1600;

$message = '';
$error   = '';

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/** Link boleh relatif atau http(s)/mailto; skema lain (javascript:, data:) ditolak. */
function isSafeLink(string $link): bool
{
    if ($link === '') {
        return true;
    }
    $scheme = parse_url($link, PHP_URL_SCHEME);
    return $scheme === null || $scheme === false || in_array(strtolower($scheme), ['http', 'https', 'mailto'], true);
}

/** Field teks/pilihan dari form, sudah dirapikan. */
function homeMediaFields(array $post): array
{
    $placement = (string)($post['placement'] ?? 'top');
    $text      = static function (string $key, int $max) use ($post): ?string {
        $value = trim((string)($post[$key] ?? ''));
        return $value === '' ? null : mb_substr($value, 0, $max);
    };

    return [
        'placement'  => isset(HOME_MEDIA_PLACEMENTS[$placement]) ? $placement : 'top',
        'title'      => $text('title', 150),
        'subtitle'   => $text('subtitle', 255),
        'link'       => $text('link', 500),
        'sort_order' => (int)($post['sort_order'] ?? 0),
        'is_active'  => isset($post['is_active']) ? 1 : 0,
    ];
}

$tableReady = tableExists($pdo, 'home_media') || ensureHomeMediaTable($pdo);
$uploadDir  = __DIR__ . '/../' . HOME_MEDIA_DIR_WEB;

/* ── POST ────────────────────────────────────────────── */
if ($tableReady && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $id     = (int)($_POST['item_id'] ?? 0);

    try {
        if (!hash_equals(ddAdminCsrf(), (string)($_POST['csrf'] ?? ''))) {
            throw new RuntimeException('Session expired. Please reload and try again.');
        }

        if ($action === 'create' || $action === 'update') {
            $fields = homeMediaFields($_POST);
            if (!isSafeLink((string)$fields['link'])) {
                throw new RuntimeException('Link must be a relative path or start with http(s)://.');
            }
            $newMedia = storeUploadedImage($_FILES['media'] ?? [], $uploadDir, HOME_MEDIA_DIR_WEB, 'home_', HOME_MEDIA_MAX_WIDTH);

            if ($action === 'create') {
                if ($newMedia === null) {
                    throw new RuntimeException('Please choose a photo or GIF.');
                }
                [$width, $height] = storedImageSize($newMedia);
                $pdo->prepare(
                    'INSERT INTO home_media (media, placement, title, subtitle, link, width, height, sort_order, is_active)
                     VALUES (:media, :placement, :title, :subtitle, :link, :width, :height, :sort_order, :is_active)'
                )->execute($fields + ['media' => $newMedia, 'width' => $width, 'height' => $height]);
                $message = 'Item added.';
            } else {
                $find = $pdo->prepare('SELECT media FROM home_media WHERE id = :id');
                $find->execute(['id' => $id]);
                $row = $find->fetch(PDO::FETCH_ASSOC);
                if (!$row) {
                    throw new RuntimeException('Item not found.');
                }
                $media = $newMedia ?? $row['media'];
                [$width, $height] = $newMedia !== null ? storedImageSize($newMedia) : [null, null];

                $sql = 'UPDATE home_media SET media = :media, placement = :placement, title = :title,
                               subtitle = :subtitle, link = :link, sort_order = :sort_order, is_active = :is_active'
                     . ($newMedia !== null ? ', width = :width, height = :height' : '')
                     . ' WHERE id = :id';
                $params = $fields + ['media' => $media, 'id' => $id];
                if ($newMedia !== null) {
                    $params += ['width' => $width, 'height' => $height];
                }
                $pdo->prepare($sql)->execute($params);
                if ($newMedia !== null) {
                    deleteMediaFile($row['media']);
                }
                $message = 'Item saved.';
            }
        } elseif ($action === 'delete' && $id > 0) {
            $find = $pdo->prepare('SELECT media FROM home_media WHERE id = :id');
            $find->execute(['id' => $id]);
            $row = $find->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $pdo->prepare('DELETE FROM home_media WHERE id = :id')->execute(['id' => $id]);
                deleteMediaFile($row['media']);
                $message = 'Item deleted.';
            }
        } elseif ($action === 'toggle' && $id > 0) {
            $pdo->prepare('UPDATE home_media SET is_active = 1 - is_active WHERE id = :id')->execute(['id' => $id]);
            $message = 'Status updated.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

/* ── load ────────────────────────────────────────────── */
$items   = $tableReady
    ? $pdo->query('SELECT * FROM home_media ORDER BY placement ASC, sort_order ASC, id ASC')->fetchAll(PDO::FETCH_ASSOC)
    : [];
$editing = null;
$editId  = (int)($_GET['edit'] ?? 0);
if ($tableReady && $editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM home_media WHERE id = :id');
    $stmt->execute(['id' => $editId]);
    $editing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
$csrf = ddAdminCsrf();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Homepage Media — <?= htmlspecialchars(BRAND_NAME) ?> Admin</title>
  <style>
    * { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; }
    body { font-family: Arial, Helvetica, sans-serif; background: #ffffff; color: #111; }
    a { color: inherit; text-decoration: none; }

    .topbar {
      height: 74px;
      display: grid;
      grid-template-columns: 1fr auto 1fr;
      align-items: center;
      padding: 0 22px;
      background: #ffffff;
      border-bottom: 1px solid #d8d5ce;
      position: sticky;
      top: 0;
      z-index: 20;
    }
    .topbar__left, .topbar__right {
      display: flex;
      align-items: center;
      gap: 18px;
    }
    .topbar__right { justify-content: flex-end; }
    .logo { width: 140px; justify-self: center; display: flex; align-items: center; justify-content: center; }
    .logo img { width: 100%; height: 36px; object-fit: contain; }
    .nav-link { font-size: 12px; text-transform: uppercase; letter-spacing: .6px; }

    .wrap { max-width: 1440px; margin: 0 auto; border-left: 1px solid #d8d5ce; border-right: 1px solid #d8d5ce; min-height: calc(100vh - 74px); }

    .hero { padding: 22px 20px; border-bottom: 1px solid #d8d5ce; display: grid; grid-template-columns: 1.2fr .8fr; gap: 0; }
    .hero-left, .hero-right { min-width: 0; }
    .hero-right { border-left: 1px solid #d8d5ce; padding-left: 20px; }
    .eyebrow { font-size: 12px; text-transform: uppercase; letter-spacing: .7px; margin-bottom: 14px; }
    .headline { margin: 0; font-size: 36px; line-height: 1.08; text-transform: uppercase; font-weight: 400; max-width: 620px; }
    .subcopy { margin: 18px 0 0; max-width: 560px; font-size: 14px; line-height: 1.7; color: #555; }
    .hero-actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 24px; }
    .btn, .btn-secondary {
      height: 48px;
      border: 1px solid #111;
      background: #111;
      color: #fff;
      padding: 0 18px;
      text-transform: uppercase;
      font-size: 12px;
      letter-spacing: .6px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
    }
    .btn-secondary { background: #faf9f5; color: #111; border-color: #d8d5ce; }
    .btn-secondary:hover { background: #f4f2ec; }
    .btn-sm { height: 36px; padding: 0 14px; font-size: 10px; }
    .btn-danger { background: #fff; color: #c62828; border-color: #c62828; }
    .btn-danger:hover { background: #c62828; color: #fff; }

    .section { min-width: 0; border-bottom: 1px solid #d8d5ce; }
    .section-head {
      padding: 18px 20px;
      border-bottom: 1px solid #d8d5ce;
      text-transform: uppercase;
      letter-spacing: .7px;
      font-size: 13px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      background: #faf9f5;
    }
    .section-body { padding: 20px; background: #ffffff; }

    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .form-group { margin-bottom: 14px; }
    .form-group.full { grid-column: 1 / -1; }
    .form-group label { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: .6px; margin-bottom: 6px; color: #555; }
    .form-input, .form-textarea, .form-file {
      width: 100%;
      padding: 10px 12px;
      border: 1px solid #d8d5ce;
      background: #fff;
      font-size: 13px;
      font-family: inherit;
    }
    .form-textarea { resize: vertical; min-height: 60px; }
    .form-row-check { display: flex; align-items: center; gap: 8px; }
    .form-row-check input { width: 16px; height: 16px; }
    .form-help { font-size: 11px; color: #888; margin-top: 4px; text-transform: none; }

    .alert { padding: 12px 14px; font-size: 12px; text-transform: none; letter-spacing: .02em; margin: 16px 20px 0; border: 1px solid #d8d5ce; }
    .alert.success { background: #f0f7f0; color: #2e7d32; border-color: #2e7d32; }
    .alert.error   { background: #fdf0f0; color: #c62828; border-color: #c62828; }

    .table-wrap { overflow: auto; border: 1px solid #d8d5ce; background: #faf9f5; }
    table { width: 100%; border-collapse: collapse; min-width: 720px; }
    th, td {
      border-bottom: 1px solid #e8e6df;
      padding: 14px 12px;
      text-align: left;
      font-size: 13px;
      vertical-align: middle;
    }
    th { text-transform: uppercase; letter-spacing: .6px; font-size: 12px; background: #f4f2ec; }
    .thumb-mini { width: 100px; height: 56px; border: 1px solid #e0ddd5; background: #fff; object-fit: cover; }

    .status { display: inline-block; padding: 4px 8px; border: 1px solid #d8d5ce; font-size: 10px; text-transform: uppercase; letter-spacing: .5px; background: #faf9f5; }
    .status.live { border-color: #111; background: #f4f2ec; }
    .row-actions { display: flex; gap: 6px; flex-wrap: wrap; }
    .row-actions form { display: inline; }

    .preview-img { max-width: 240px; max-height: 160px; object-fit: contain; background:#f4f4f4; border: 1px solid #d8d5ce; margin-top: 8px; }
    .thumb-mini { width: 56px; height: 72px; object-fit: cover; }

    @media (max-width: 980px) {
      .hero { grid-template-columns: 1fr; }
      .hero-right { border-left: 0; padding-left: 0; margin-top: 20px; }
      .form-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 640px) {
      .topbar { padding: 0 16px; }
      .headline { font-size: 28px; }
    }
  </style>
  <link rel="stylesheet" href="admin-theme.css?v=1">
</head>
<body>
  <header class="topbar">
    <div class="topbar__left">
      <a href="dashboard.php" class="nav-link">Dashboard</a>
      <a href="../index.php" class="nav-link">Storefront</a>
    </div>
    <a href="dashboard.php" class="logo" aria-label="<?= htmlspecialchars(BRAND_NAME) ?> Admin">
      <span class="brand"><span class="brand__name"><?= htmlspecialchars(BRAND_NAME) ?></span></span>
    </a>
    <div class="topbar__right"><span class="nav-link">Homepage Media</span></div>
  </header>

  <main class="wrap">
    <section class="hero">
      <div class="hero-left">
        <div class="eyebrow"><?= htmlspecialchars(BRAND_NAME) ?> Admin</div>
        <h1 class="headline">Homepage Media</h1>
        <p class="subcopy">
          One swipeable row of photos and GIFs right below the hero, and one below the products.
          Items in a row are shown in <strong>sort order</strong>.
          Photos are compressed to WEBP automatically. For video, upload a short GIF.
        </p>
      </div>
      <div class="hero-right">
        <div class="eyebrow">Statistics</div>
        <p class="subcopy" style="margin-top:0;">
          <strong>Total:</strong> <?= count($items) ?><br>
          <strong>Active:</strong> <?= count(array_filter($items, static fn($i) => (int)$i['is_active'] === 1)) ?>
        </p>
      </div>
    </section>

    <?php if ($message): ?><div class="alert success"><?= h($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert error"><?= h($error) ?></div><?php endif; ?>

    <?php if (!$tableReady): ?>
      <div class="alert error">Table <code>home_media</code> could not be created automatically. Import <code>simple.sql</code> or run the CREATE TABLE manually.</div>
    <?php else: ?>

    <section class="section" id="form-section">
      <div class="section-head">
        <span><?= $editing ? 'Edit item #' . (int)$editing['id'] : 'Add item' ?></span>
        <?php if ($editing): ?><a href="dashboard-home-media.php" class="nav-link">Cancel edit</a><?php endif; ?>
      </div>
      <div class="section-body">
        <form method="POST" enctype="multipart/form-data" action="dashboard-home-media.php<?= $editing ? '?edit=' . (int)$editing['id'] : '' ?>">
          <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
          <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
          <?php if ($editing): ?><input type="hidden" name="item_id" value="<?= (int)$editing['id'] ?>"><?php endif; ?>

          <div class="form-grid">
            <div class="form-group full">
              <label for="media">Photo or GIF <?= $editing ? '(leave empty to keep current)' : '' ?></label>
              <input type="file" name="media" id="media" class="form-file" accept="image/jpeg,image/png,image/webp,image/gif" <?= $editing ? '' : 'required' ?>>
              <?php if ($editing): ?><img class="preview-img" src="../<?= h($editing['media']) ?>" alt="Current media"><?php endif; ?>
              <p class="form-help">JPG, PNG, WEBP (auto-compressed to WEBP, max width <?= HOME_MEDIA_MAX_WIDTH ?>px) or GIF (kept as-is, max 8 MB).</p>
            </div>

            <div class="form-group">
              <label for="placement">Placement</label>
              <select name="placement" id="placement" class="form-input">
                <?php foreach (HOME_MEDIA_PLACEMENTS as $value => $label): ?>
                  <option value="<?= h($value) ?>" <?= ($editing['placement'] ?? 'top') === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label for="title">Caption</label>
              <input type="text" name="title" id="title" class="form-input" value="<?= h($editing['title'] ?? '') ?>" placeholder="Optional">
            </div>
            <div class="form-group">
              <label for="subtitle">Small text</label>
              <input type="text" name="subtitle" id="subtitle" class="form-input" value="<?= h($editing['subtitle'] ?? '') ?>" placeholder="Optional">
            </div>
            <div class="form-group">
              <label for="link">Link</label>
              <input type="text" name="link" id="link" class="form-input" value="<?= h($editing['link'] ?? '') ?>" placeholder="e.g. shop.php or https://…">
            </div>
            <div class="form-group">
              <label for="sort_order">Sort order</label>
              <input type="number" name="sort_order" id="sort_order" class="form-input" value="<?= (int)($editing['sort_order'] ?? 0) ?>">
              <p class="form-help">Lower numbers show first.</p>
            </div>
            <div class="form-group">
              <label>Status</label>
              <label class="form-row-check">
                <input type="checkbox" name="is_active" value="1" <?= !$editing || (int)$editing['is_active'] === 1 ? 'checked' : '' ?>>
                <span>Active (show on storefront)</span>
              </label>
            </div>
          </div>

          <div class="hero-actions" style="margin-top:14px;">
            <button type="submit" class="btn"><?= $editing ? 'Save changes' : 'Add item' ?></button>
          </div>
        </form>
      </div>
    </section>

    <section class="section">
      <div class="section-head"><span>Items</span><span style="font-size:11px;color:#888;"><?= count($items) ?> item(s)</span></div>
      <div class="section-body">
        <?php if (!$items): ?>
          <p style="font-size:13px;color:#888;margin:0;">Nothing yet. Add a photo or GIF above.</p>
        <?php else: ?>
          <div class="table-wrap">
            <table>
              <thead><tr><th>Preview</th><th>Caption</th><th>Placement</th><th>Order</th><th>Status</th><th>Actions</th></tr></thead>
              <tbody>
              <?php foreach ($items as $item): ?>
                <tr>
                  <td><img class="thumb-mini" src="../<?= h($item['media']) ?>" alt="" loading="lazy"></td>
                  <td><?= $item['title'] ? '<strong>' . h($item['title']) . '</strong>' : '<span style="color:#bbb;">—</span>' ?>
                      <?php if ($item['subtitle']): ?><br><span style="color:#888;font-size:12px;"><?= h($item['subtitle']) ?></span><?php endif; ?></td>
                  <td><?= h(HOME_MEDIA_PLACEMENTS[$item['placement']] ?? $item['placement']) ?></td>
                  <td><?= (int)$item['sort_order'] ?></td>
                  <td><span class="status <?= (int)$item['is_active'] === 1 ? 'live' : '' ?>"><?= (int)$item['is_active'] === 1 ? 'Active' : 'Hidden' ?></span></td>
                  <td>
                    <div class="row-actions">
                      <a href="dashboard-home-media.php?edit=<?= (int)$item['id'] ?>#form-section" class="btn btn-sm btn-secondary">Edit</a>
                      <form method="POST">
                        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-secondary">Toggle</button>
                      </form>
                      <form method="POST" onsubmit="return confirm('Delete this item?');">
                        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </section>
    <?php endif; ?>
  </main>
</body>
</html>
