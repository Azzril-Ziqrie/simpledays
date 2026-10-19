<?php
session_start();
require __DIR__ . '/includes/site_gate.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/currency_helper.php';
require_once __DIR__ . '/includes/layout.php';

if (!isset($_SESSION['user']['id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user']['id'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'remove_item') {
        $cartItemId = (int) ($_POST['cart_item_id'] ?? 0);

        if ($cartItemId > 0) {
            try {
                $deleteStmt = $pdo->prepare(
                    'DELETE ci
                     FROM cart_items ci
                     INNER JOIN carts c ON c.id = ci.cart_id
                     WHERE ci.id = :cart_item_id
                       AND c.user_id = :user_id
                       AND c.status = :status'
                );
                $deleteStmt->execute([
                    'cart_item_id' => $cartItemId,
                    'user_id' => $userId,
                    'status' => 'active',
                ]);

                if ($deleteStmt->rowCount() > 0) {
                    $message = 'Item removed from cart.';
                } else {
                    $error = 'Cart item was not found.';
                }
            } catch (Throwable $e) {
                $error = 'Failed to remove item: ' . $e->getMessage();
            }
        } else {
            $error = 'Invalid cart item.';
        }
    }
}

$cartStmt = $pdo->prepare(
    'SELECT id
     FROM carts
     WHERE user_id = :user_id AND status = :status
     LIMIT 1'
);
$cartStmt->execute([
    'user_id' => $userId,
    'status' => 'active',
]);
$cart = $cartStmt->fetch();

$cartItems = [];
$cartTotal = 0;
$cartCount = 0;

if ($cart) {
    $itemsStmt = $pdo->prepare(
        "SELECT
            ci.id,
            ci.quantity,
            ci.price,
            p.price_idr,
            p.price_usd,
            p.name,
            p.slug,
            p.thumbnail,
            s.name AS size_name,
            COALESCE(cat.name, 'Fashion') AS category_name,
            COALESCE(cat.slug, 'fashion') AS category_slug
         FROM cart_items ci
         INNER JOIN products p ON p.id = ci.product_id
         INNER JOIN product_sizes ps ON ps.id = ci.product_size_id
         INNER JOIN sizes s ON s.id = ps.size_id
         LEFT JOIN categories cat ON cat.id = p.category_id
         WHERE ci.cart_id = :cart_id
         ORDER BY ci.id DESC"
    );
    $itemsStmt->execute(['cart_id' => $cart['id']]);
    $cartItems = $itemsStmt->fetchAll();

    foreach ($cartItems as &$item) {
        // Selalu harga USD terkini dari produk (baris keranjang lama masih menyimpan harga rupiah).
        $item['price'] = ddProductBasePrice($item);
        $cartCount += (int) $item['quantity'];
        $cartTotal += ((float) $item['price'] * (int) $item['quantity']);
    }
    unset($item);
}

function formatMoney($amount): string
{
    return ddFormatMoney((float) $amount);
}

siteHeadOpen(['title' => 'Cart', 'noindex' => true]);
?>
  <style>
    .cart-wrap { max-width: 960px; }
    .cart-wrap .alert { margin-bottom: 20px; }
    .cart-empty-page { padding: 48px 0; color: var(--mute); }
    .cart-empty-page .btn { margin-top: 20px; }

    .cart-row {
      display: grid;
      grid-template-columns: 110px minmax(0, 1fr) auto;
      gap: 20px;
      align-items: start;
      padding: 20px 0;
      border-top: 1px solid var(--line);
    }
    .cart-row:last-child { border-bottom: 1px solid var(--line); }
    .cart-media {
      display: block;
      aspect-ratio: 3 / 4;
      background: var(--tile);
      overflow: hidden;
    }
    .cart-media img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .cart-name { font-size: 13px; font-weight: 500; line-height: 1.4; margin-bottom: 6px; }
    .cart-price { font-size: 13px; }
    .cart-meta { margin-top: 6px; font-size: 12px; color: var(--mute); }
    .cart-side { display: flex; flex-direction: column; align-items: flex-end; gap: 14px; }
    .cart-qty { font-size: 12px; color: var(--mute); }
    .cart-remove {
      border: 0; background: none; padding: 0; min-height: 44px; cursor: pointer;
      font-size: 12px; color: var(--ink); text-decoration: underline; text-underline-offset: 3px;
    }
    .cart-remove:hover { color: var(--mute); }

    .cart-summary {
      display: flex; justify-content: flex-end; align-items: center; gap: 28px;
      margin-top: 28px;
    }
    .cart-total { font-size: 14px; font-weight: 500; }

    @media (max-width: 560px) {
      .cart-row { grid-template-columns: 84px minmax(0, 1fr); gap: 14px; }
      .cart-side { grid-column: 1 / -1; flex-direction: row; justify-content: space-between; align-items: center; }
      .cart-summary { flex-direction: column; align-items: stretch; gap: 14px; }
      .cart-summary .btn { width: 100%; }
    }
  </style>
<?php
siteBodyOpen(['bodyClass' => 'page-cart', 'searchAction' => 'shop.php']);
?>
  <main class="page-main">
    <div class="page-wrap cart-wrap">
      <h1 class="page-title">Cart<?= $cartCount > 0 ? ' (' . (int) $cartCount . ')' : '' ?></h1>

      <?php if ($message !== ''): ?>
        <div class="alert alert--ok" role="status"><?= esc($message) ?></div>
      <?php endif; ?>
      <?php if ($error !== ''): ?>
        <div class="alert alert--error" role="alert"><?= esc($error) ?></div>
      <?php endif; ?>

      <?php if (!$cartItems): ?>
        <div class="cart-empty-page">
          <p>Your cart is empty.</p>
          <a href="shop.php" class="btn">Continue shopping</a>
        </div>
      <?php else: ?>
        <section aria-label="Cart items">
          <?php foreach ($cartItems as $item): ?>
            <article class="cart-row">
              <a class="cart-media" href="product.php?slug=<?= urlencode($item['slug']) ?>" aria-label="View <?= esc($item['name']) ?>">
                <img src="<?= esc($item['thumbnail']) ?>" alt="<?= esc($item['name']) ?>" loading="lazy" decoding="async">
              </a>

              <div>
                <h2 class="cart-name"><?= esc($item['name']) ?></h2>
                <p class="cart-price"><?= esc(formatMoney($item['price'])) ?></p>
                <?php if (($item['category_slug'] ?? 'fashion') === 'fashion'): ?>
                  <div class="cart-meta">Size <?= esc($item['size_name']) ?></div>
                <?php endif; ?>
              </div>

              <div class="cart-side">
                <span class="cart-qty">Qty <?= (int) $item['quantity'] ?></span>
                <form method="POST" action="" onsubmit="return confirm('Remove this item from cart?');">
                  <input type="hidden" name="action" value="remove_item">
                  <input type="hidden" name="cart_item_id" value="<?= (int) $item['id'] ?>">
                  <button type="submit" class="cart-remove">Remove</button>
                </form>
              </div>
            </article>
          <?php endforeach; ?>
        </section>

        <div class="cart-summary">
          <div class="cart-total">Total <?= esc(formatMoney($cartTotal)) ?></div>
          <a href="checkout.php" class="btn btn--solid">Continue to checkout</a>
        </div>
      <?php endif; ?>
    </div>
  </main>
<?php
siteBodyClose();
siteDocClose();
