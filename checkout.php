<?php
session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/currency_helper.php';
require_once __DIR__ . '/includes/pricing.php';
require_once __DIR__ . '/includes/promo.php';
require_once __DIR__ . '/config/kasera_client.php';
require_once __DIR__ . '/config/intl_shipping.php';
require_once __DIR__ . '/includes/order_payment.php';
require_once __DIR__ . '/includes/email_helpers.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/db_schema.php';
$composerAutoload = __DIR__ . '/vendor/autoload.php';
if (is_file($composerAutoload)) require_once $composerAutoload;

promoEnsureSchema($pdo);

const PAYER_COLUMNS = [
    'payer_name'  => 'VARCHAR(150) DEFAULT NULL',
    'payer_email' => 'VARCHAR(150) DEFAULT NULL',
    'payer_phone' => 'VARCHAR(50) DEFAULT NULL',
];

function ensurePayerColumns(PDO $pdo): void
{
    foreach (PAYER_COLUMNS as $column => $definition) {
        if (!columnExists($pdo, 'orders', $column)) {
            $pdo->exec("ALTER TABLE orders ADD COLUMN {$column} {$definition}");
        }
    }
}

/** Detail pembayar bersifat opsional: string kosong disimpan sebagai NULL. */
function nullIfBlank(string $value, int $maxLength): ?string
{
    $value = mb_substr(trim($value), 0, $maxLength);
    return $value === '' ? null : $value;
}

function savePayerDetails(PDO $pdo, int $orderId, array $input): array
{
    $email = nullIfBlank((string)($input['payer_email'] ?? ''), 150);
    if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Please enter a valid email address.'];
    }

    $pdo->prepare(
        'UPDATE orders SET payer_name = :name, payer_email = :email, payer_phone = :phone WHERE id = :id'
    )->execute([
        'name'  => nullIfBlank((string)($input['payer_name'] ?? ''), 150),
        'email' => $email,
        'phone' => nullIfBlank((string)($input['payer_phone'] ?? ''), 50),
        'id'    => $orderId,
    ]);
    return ['ok' => true];
}

/* ── helpers ─────────────────────────────────────────── */
function makeOrderNumber(): string
{
    return 'SIMPLEDAYS-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
}

/**
 * Hitung ulang ongkir (IDR) di sisi server, supaya nilai
 * shipping_cost dari browser tidak dipercaya begitu saja.
 * Domestik: RajaOngkir. Internasional: tarif flat (INTL_FLAT_SHIPPING_USD).
 * Label layanan harus sama dengan yang ditampilkan di checkout (lihat calculateShipping() di JS).
 * Return null kalau gagal / layanan tidak ditemukan.
 */
function lookupShippingCostIdr(string $mode, string $destination, string $courier, string $service, int $weightGram): ?float
{
    if ($destination === '' || $courier === '' || $service === '' || $weightGram <= 0) return null;

    // Internasional: tarif flat (config/intl_shipping.php), tidak bergantung berat
    if ($mode !== 'domestic') {
        try {
            foreach (intlShippingServices($destination, $courier) ?? [] as $row) {
                if ($row['service'] === $service) return (float)$row['cost'];
            }
        } catch (Throwable $e) {
            error_log('[checkout] intl shipping lookup failed: ' . $e->getMessage());
        }
        return null;
    }

    $config   = require __DIR__ . '/config/rajaongkir.php';
    $endpoint = '/calculate/domestic-cost';

    $ch = curl_init(rtrim((string)$config['base_url'], '/') . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'origin'      => (int)$config['origin_city_id'],
            'destination' => $destination,
            'weight'      => $weightGram,
            'courier'     => $courier,
            'price'       => 'lowest',
        ]),
        CURLOPT_HTTPHEADER     => [
            'key: ' . $config['api_key'],
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT        => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode < 200 || $httpCode >= 300) return null;
    $data = json_decode($response, true);
    if (!is_array($data) || !isset($data['data']) || !is_array($data['data'])) return null;

    foreach ($data['data'] as $row) {
        $label = (string)($row['service'] ?? ($row['description'] ?? ($row['name'] ?? ($row['code'] ?? 'Service'))));
        if ($label !== $service) continue;

        if (isset($row['cost']) && !is_array($row['cost'])) return (float)$row['cost'];
        if (isset($row['cost'][0]['value'])) return (float)$row['cost'][0]['value'];
        if (isset($row['value'])) return (float)$row['value'];
    }
    return null;
}

function getProductSizeRow(PDO $pdo, string $slug, int $productSizeId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT p.id AS product_id, p.name, p.slug, p.thumbnail, p.price_idr, p.price_usd, p.weight_gram,
                p.discount_percent, p.discount_start_at, p.discount_end_at, p.is_discount_active,
                COALESCE(c.slug,\'fashion\') AS category_slug,
                ps.id AS product_size_id, ps.stock, ps.is_active, s.name AS size_name
         FROM products p
         INNER JOIN product_sizes ps ON ps.product_id = p.id
         INNER JOIN sizes s ON s.id = ps.size_id
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE p.slug = :slug AND ps.id = :product_size_id AND p.is_active = 1
         LIMIT 1'
    );
    $stmt->execute(['slug' => $slug, 'product_size_id' => $productSizeId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return ($row && (int)$row['is_active'] === 1) ? $row : null;
}

function getProductSizeById(PDO $pdo, int $productSizeId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT p.id AS product_id, p.name, p.slug, p.thumbnail, p.price_idr, p.price_usd, p.weight_gram,
                p.discount_percent, p.discount_start_at, p.discount_end_at, p.is_discount_active,
                COALESCE(c.slug,\'fashion\') AS category_slug,
                ps.id AS product_size_id, ps.stock, ps.is_active, s.name AS size_name
         FROM product_sizes ps
         INNER JOIN products p ON p.id = ps.product_id
         INNER JOIN sizes s ON s.id = ps.size_id
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE ps.id = :product_size_id AND p.is_active = 1
         LIMIT 1'
    );
    $stmt->execute(['product_size_id' => $productSizeId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return ($row && (int)$row['is_active'] === 1) ? $row : null;
}

function seedGuestCartFromQuery(PDO $pdo): void
{
    if (!isset($_GET['guest']) || $_GET['guest'] !== '1') return;
    $productSlug = trim((string)($_GET['product'] ?? ''));
    $sizeId      = (int)($_GET['size_id'] ?? 0);
    $qty         = (int)($_GET['qty'] ?? 1);
    
    $currency    = DD_BASE_CURRENCY;
    if ($productSlug === '' || $sizeId <= 0 || $qty <= 0) return;
    $row = getProductSizeRow($pdo, $productSlug, $sizeId);
    if (!$row || (int)$row['stock'] <= 0) return;
    $qty = min($qty, (int)$row['stock']);
    if (!isset($_SESSION['guest_cart']) || !is_array($_SESSION['guest_cart'])) $_SESSION['guest_cart'] = [];
    $found = false;
    foreach ($_SESSION['guest_cart'] as &$ci) {
        if ((int)($ci['product_id'] ?? 0) === (int)$row['product_id'] &&
            (int)($ci['product_size_id'] ?? 0) === (int)$row['product_size_id'] &&
            strtoupper((string)($ci['currency'] ?? 'USD')) === $currency) {
            $ci['quantity'] = min((int)$ci['quantity'] + $qty, (int)$row['stock']);
            $found = true; break;
        }
    }
    unset($ci);
    if (!$found) $_SESSION['guest_cart'][] = [
        'product_id' => (int)$row['product_id'], 'product_size_id' => (int)$row['product_size_id'],
        'slug' => $row['slug'], 'quantity' => $qty, 'currency' => $currency,
    ];
}

function getGuestCartItemsFromSession(PDO $pdo): array
{
    $sessionCart = $_SESSION['guest_cart'] ?? [];
    if (!is_array($sessionCart) || !$sessionCart) return [];
    $items = [];
    foreach ($sessionCart as $si) {
        $slug = trim((string)($si['slug'] ?? '')); $psid = (int)($si['product_size_id'] ?? 0);
        $qty  = (int)($si['quantity'] ?? 0); $cur = DD_BASE_CURRENCY;
        if ($slug === '' || $psid <= 0 || $qty <= 0) continue;
        $row = getProductSizeRow($pdo, $slug, $psid);
        if (!$row || (int)$row['stock'] <= 0) continue;
        $qty   = min($qty, (int)$row['stock']);
        $price = productFinalPrice($row);
        $items[] = ['cart_item_id' => null, 'product_id' => (int)$row['product_id'],
            'product_size_id' => (int)$row['product_size_id'], 'name' => $row['name'],
            'slug' => $row['slug'], 'thumbnail' => $row['thumbnail'], 'size_name' => $row['size_name'],
            'category_slug' => $row['category_slug'] ?? 'fashion', 'quantity' => $qty,
            'price' => $price, 'currency' => $cur, 'line_total' => $price * $qty, 'stock' => (int)$row['stock'],
            'weight_gram' => (int)$row['weight_gram']];
    }
    return $items;
}

function getBuyNowItemsFromSession(PDO $pdo): array
{

    $bn = $_SESSION['buy_now'] ?? [];
    if (!is_array($bn) || !$bn) return [];
    $pid = (int)($bn['product_id'] ?? 0); $psid = (int)($bn['product_size_id'] ?? 0);
    $qty = (int)($bn['quantity'] ?? 0); $cur = DD_BASE_CURRENCY;
    if ($pid <= 0 || $psid <= 0 || $qty <= 0) return [];
    $row = getProductSizeById(db(), $psid);
    if (!$row || (int)$row['product_id'] !== $pid || (int)$row['stock'] <= 0) return [];
    $qty = min($qty, (int)$row['stock']); $_SESSION['buy_now']['quantity'] = $qty;
    $price = productFinalPrice($row);
    return [['cart_item_id' => null, 'product_id' => (int)$row['product_id'],
        'product_size_id' => (int)$row['product_size_id'], 'name' => $row['name'],
        'slug' => $row['slug'], 'thumbnail' => $row['thumbnail'], 'size_name' => $row['size_name'],
        'category_slug' => $row['category_slug'] ?? 'fashion', 'quantity' => $qty,
        'price' => $price, 'currency' => $cur, 'line_total' => $price * $qty, 'stock' => (int)$row['stock'],
        'weight_gram' => (int)$row['weight_gram']]];
}

function getMemberCartItems(PDO $pdo, int $userId): array
{
                $cartStmt = db()->prepare('SELECT id FROM carts WHERE user_id=:uid AND status=:s LIMIT 1');
    $cartStmt->execute(['uid' => $userId, 's' => 'active']);
    $cart = $cartStmt->fetch(PDO::FETCH_ASSOC);
    if (!$cart) return [];

    $stmt = $pdo->prepare(
        'SELECT ci.id AS cart_item_id, ci.product_id, ci.product_size_id, ci.quantity,
                p.name, p.slug, p.thumbnail, p.price_idr, p.price_usd, p.weight_gram,
                p.discount_percent, p.discount_start_at, p.discount_end_at, p.is_discount_active,
                s.name AS size_name, COALESCE(c.slug,\'fashion\') AS category_slug,
                ps.stock
         FROM cart_items ci
         INNER JOIN products p ON p.id=ci.product_id
         LEFT JOIN product_sizes ps ON ps.id=ci.product_size_id
         LEFT JOIN sizes s ON s.id=ps.size_id
         LEFT JOIN categories c ON c.id=p.category_id
         WHERE ci.cart_id=:cid ORDER BY ci.id DESC'
    );
    $stmt->execute(['cid' => $cart['id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($items as &$item) {
        $price = productFinalPrice($item);
        $item['line_total'] = (float)$price * (int)$item['quantity'];
        $item['currency'] = DD_BASE_CURRENCY;
        $item['price'] = $price;
        $item['stock'] = (int)$item['stock'];
    }
    unset($item);
    return $items;
}

/** Total berat keranjang (gram) = Σ berat produk × qty. Berat kosong/0 dianggap 1000 g. */
function cartTotalWeightGram(array $items): int
{
    $total = 0;
    foreach ($items as $it) {
        $w = (int)($it['weight_gram'] ?? 0);
        $total += (int)($it['quantity'] ?? 0) * ($w > 0 ? $w : 1000);
    }
    return $total;
}

function validateCheckoutStock(PDO $pdo, array $cartItems): void
{
    foreach ($cartItems as $item) {
        $psid = (int)($item['product_size_id'] ?? 0); $qty = (int)($item['quantity'] ?? 0);
        if ($psid <= 0 || $qty <= 0) throw new Exception('Invalid checkout item detected.');
        $row = getProductSizeById(db(), $psid);
        if (!$row) throw new Exception('Selected size no longer available for ' . $item['name'] . '.');
        if ((int)$row['stock'] < $qty) throw new Exception('Stock not enough for ' . $item['name'] . '.');
    }
}

/* ── pay mode: order sudah dibuat, bayar langsung di halaman checkout ─ */
$payOrder = null;
$payOrderNumber = trim((string)($_GET['order'] ?? ''));
if ($payOrderNumber !== '') {
    $payStmt = $pdo->prepare('SELECT * FROM orders WHERE order_number = :n LIMIT 1');
    $payStmt->execute(['n' => $payOrderNumber]);
    $payOrder = $payStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$payOrder) { header('Location: checkout.php'); exit; }
    ensurePayerColumns($pdo);
    $payStmt->execute(['n' => $payOrderNumber]);
    $payOrder = $payStmt->fetch(PDO::FETCH_ASSOC);
    $payOrder = array_merge(array_fill_keys(array_keys(PAYER_COLUMNS), null), $payOrder);
}

/* ── pay mode: simpan detail pembayar (opsional, AJAX) ── */
if ($payOrder && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_payer'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(savePayerDetails($pdo, (int)$payOrder['id'], $_POST));
    exit;
}

/* ── pay mode: polling status (+ sinkron ke Kasera sebagai cadangan webhook) ── */
if ($payOrder && isset($_GET['status'])) {
    header('Content-Type: application/json; charset=utf-8');
    $statusNow = strtolower((string)$payOrder['payment_status']);
    if (isset($_GET['sync'])) $statusNow = kaseraSyncOrderStatus($pdo, $payOrder);
    echo json_encode(['status' => $statusNow]);
    exit;
}

/* ── pay mode: handler QRIS / Virtual Account (Kasera) & upload bukti transfer ── */
$payError = '';
if ($payOrder && strtolower((string)$payOrder['payment_status']) !== 'paid' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $kaseraMethods = kaseraPaymentMethods();

    if (isset($_POST['kasera_method'])) {
        $method = (string)$_POST['kasera_method'];
        if (!KASERA_ENABLED) {
            $payError = 'This payment method is not available right now. Please choose another method.';
        } elseif (!isset($kaseraMethods[$method]) && $method !== 'checkout') {
            $payError = 'Invalid payment method.';
        } else {
            try {
                // 'checkout' = metode lain (e-wallet dll.): pembeli memilih di halaman Kasera.
                $isDirect = $method !== 'checkout';
                $kaseraResponse = kaseraEnsureTransactionForOrder($pdo, $payOrder, $isDirect ? $method : null);
                if ($isDirect) {
                    // QRIS / VA tampil di halaman kita sendiri, bukan di checkout_url Kasera.
                    header('Location: checkout.php?order=' . urlencode((string)$payOrder['order_number']));
                    exit;
                }
                if (!empty($kaseraResponse['checkout_url'])) {
                    header('Location: ' . $kaseraResponse['checkout_url']);
                    exit;
                }
                $payError = 'Could not start this payment. Please try again.';
            } catch (Throwable $e) {
                error_log('[kasera] ' . $method . ' failed for order ' . $payOrder['order_number'] . ': ' . $e->getMessage());
                $payError = $e instanceof RuntimeException
                    ? $e->getMessage()
                    : 'This payment method is not available right now. Please choose another method.';
            }
        }
    } elseif (isset($_FILES['payment_proof'])) {
        if ($_FILES['payment_proof']['error'] !== UPLOAD_ERR_OK) {
            $payError = 'Please upload payment proof.';
        } else {
            $allowedMime = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'];
            $tmpPath = $_FILES['payment_proof']['tmp_name'];
            $mime    = mime_content_type($tmpPath);
            if (!isset($allowedMime[$mime])) {
                $payError = 'Only JPG, PNG, or PDF files are allowed.';
            } else {
                $dir = __DIR__ . '/uploads/payment-proofs';
                if (!is_dir($dir)) mkdir($dir, 0775, true);
                $filename     = 'proof-' . $payOrder['order_number'] . '-' . time() . '.' . $allowedMime[$mime];
                $relativePath = 'uploads/payment-proofs/' . $filename;
                if (!move_uploaded_file($tmpPath, $dir . '/' . $filename)) {
                    $payError = 'Failed to save uploaded file.';
                } else {
                    try {
                        $pdo->beginTransaction();
                        $pdo->prepare("UPDATE orders SET payment_proof_path = :p, payment_status = 'waiting_verification', updated_at = NOW() WHERE id = :id")
                            ->execute(['p' => $relativePath, 'id' => $payOrder['id']]);
                        $pdo->prepare('INSERT INTO order_status_logs (order_id, status_type, status_value, notes) VALUES (:oid, :st, :sv, :n)')
                            ->execute(['oid' => $payOrder['id'], 'st' => 'payment', 'sv' => 'waiting_verification', 'n' => 'Customer uploaded payment proof.']);
                        $pdo->commit();

                        $updStmt = $pdo->prepare('SELECT * FROM orders WHERE id = :id LIMIT 1');
                        $updStmt->execute(['id' => $payOrder['id']]);
                        $emailSent = sendDDInvoiceEmail($pdo, $updStmt->fetch(PDO::FETCH_ASSOC) ?: $payOrder);
                        header('Location: index.php?payment_uploaded=1&invoice_email=' . ($emailSent ? 'sent' : 'failed') . '&order=' . urlencode((string)$payOrder['order_number']));
                        exit;
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) $pdo->rollBack();
                        $payError = 'Failed to process payment proof: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}

/* ── state ───────────────────────────────────────────── */
$isLoggedIn = isset($_SESSION['user']['id']);
$userId     = $isLoggedIn ? (int)$_SESSION['user']['id'] : null;
$userData   = $_SESSION['user'] ?? [];

$checkoutMode = strtolower(trim((string)($_POST['checkout_mode'] ?? $_GET['mode'] ?? 'cart')));
if ($checkoutMode !== 'buy_now') $checkoutMode = 'cart';

/* ════════════════════════════════════════════════════════════
   AJAX HANDLER: UPDATE QUANTITY LANGSUNG DI HALAMAN CHECKOUT
═══════════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_update_qty'])) {
    header('Content-Type: application/json');
    $psid = (int)($_POST['product_size_id'] ?? 0);
    $newQty = (int)($_POST['qty'] ?? 0);

    if ($psid <= 0 || $newQty < 1) {
        echo json_encode(['success' => false, 'message' => 'Invalid quantity.']);
        exit;
    }

    $row = getProductSizeById(db(), $psid);
    if (!$row || $row['stock'] < $newQty) {
        echo json_encode(['success' => false, 'message' => 'Max stock is ' . ($row['stock'] ?? 0)]);
        exit;
    }

    if ($checkoutMode === 'buy_now') {
        if (isset($_SESSION['buy_now']) && (int)$_SESSION['buy_now']['product_size_id'] === $psid) {
            $_SESSION['buy_now']['quantity'] = $newQty;
        }
    } else {
        if ($isLoggedIn) {
                        $cartStmt = db()->prepare('SELECT id FROM carts WHERE user_id=:uid AND status=:s LIMIT 1');
            $cartStmt->execute(['uid' => $userId, 's' => 'active']);
            $cart = $cartStmt->fetch(PDO::FETCH_ASSOC);
            if ($cart) {
                $upd = db()->prepare('UPDATE cart_items SET quantity=:qty WHERE cart_id=:cid AND product_size_id=:psid');
                $upd->execute(['qty' => $newQty, 'cid' => $cart['id'], 'psid' => $psid]);
            }
        } else {
            if (isset($_SESSION['guest_cart'])) {
                foreach ($_SESSION['guest_cart'] as &$ci) {
                    if ((int)($ci['product_size_id'] ?? 0) === $psid) {
                        $ci['quantity'] = $newQty;
                        break;
                    }
                }
                unset($ci);
            }
        }
    }

    $cartItemsUpdated = $checkoutMode === 'buy_now'
        ? getBuyNowItemsFromSession($pdo)
        : ($isLoggedIn ? getMemberCartItems($pdo, $userId) : getGuestCartItemsFromSession($pdo));

    $newSubtotal = 0.0;
    $newLineTotal = 0.0;
    foreach ($cartItemsUpdated as $it) {
        $newSubtotal += (float)$it['line_total'];
        if ((int)$it['product_size_id'] === $psid) {
            $newLineTotal = (float)$it['line_total'];
        }
    }

    echo json_encode([
        'success' => true,
        'new_qty' => $newQty,
        'new_line_total' => $newLineTotal,
        'new_subtotal' => $newSubtotal,
        'new_total_weight' => cartTotalWeightGram($cartItemsUpdated)
    ]);
    exit;
}
/* ════════════════════════════════════════════════════════════ */

/* ════════════════════════════════════════════════════════════
   AJAX HANDLER: HAPUS ITEM DARI CHECKOUT
═══════════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_remove_item'])) {
    header('Content-Type: application/json');
    $psid = (int)($_POST['product_size_id'] ?? 0);

    if ($psid > 0) {
        if ($checkoutMode === 'buy_now') {
            unset($_SESSION['buy_now']);
        } else {
            if ($isLoggedIn) {
                $cartStmt = db()->prepare('SELECT id FROM carts WHERE user_id=:uid AND status=:s LIMIT 1');
                $cartStmt->execute(['uid' => $userId, 's' => 'active']);
                $cart = $cartStmt->fetch(PDO::FETCH_ASSOC);
                if ($cart) {
                    $del = db()->prepare('DELETE FROM cart_items WHERE cart_id=:cid AND product_size_id=:psid');
                    $del->execute(['cid' => $cart['id'], 'psid' => $psid]);
                }
            } else {
                if (isset($_SESSION['guest_cart'])) {
                    foreach ($_SESSION['guest_cart'] as $k => $ci) {
                        if ((int)($ci['product_size_id'] ?? 0) === $psid) {
                            unset($_SESSION['guest_cart'][$k]);
                        }
                    }
                    $_SESSION['guest_cart'] = array_values($_SESSION['guest_cart']);
                }
            }
        }
    }

    echo json_encode(['success' => true]);
    exit;
}
/* ════════════════════════════════════════════════════════════ */

if (!$isLoggedIn && $checkoutMode === 'cart') seedGuestCartFromQuery($pdo);

$cartItems = $checkoutMode === 'buy_now'
    ? getBuyNowItemsFromSession($pdo)
    : ($isLoggedIn ? getMemberCartItems($pdo, $userId) : getGuestCartItemsFromSession($pdo));

$subtotal = 0.0; $currency = DD_BASE_CURRENCY;
foreach ($cartItems as $item) { $subtotal += (float)$item['line_total']; }

// Berat paket untuk hitung ongkir = Σ (berat produk × qty), dari products.weight_gram
$totalWeightGram = cartTotalWeightGram($cartItems);

/* ════════════════════════════════════════════════════════════
   AJAX HANDLER: TERAPKAN KODE PROMO (pratinjau; dihitung ulang saat order dibuat)
═══════════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_apply_promo'])) {
    header('Content-Type: application/json');
    $shippingPreviewUsd = max(0.0, (float)($_POST['shipping_usd'] ?? 0));
    $applied = promoResolve($pdo, (string)($_POST['promo_code'] ?? ''), $subtotal, $shippingPreviewUsd);

    if ($applied['promo'] === null) {
        echo json_encode(['success' => false, 'message' => $applied['error'] ?? 'Please enter a promo code.']);
        exit;
    }
    echo json_encode([
        'success'       => true,
        'code'          => $applied['promo']['code'],
        'type'          => $applied['promo']['type'],
        'label'         => promoLabel($applied['promo']),
        'item_discount' => number_format($applied['discounts']['item'], 2, '.', ''),
        'message'       => $applied['promo']['code'] . ' applied: ' . promoLabel($applied['promo']) . '.',
    ]);
    exit;
}

// Rate konversi ongkir (1 USD = ... IDR) — sinkron dengan JS
$usdRateFromIdr = ddRateFromIdr();

$message = ''; $error = '';

// Set ke true untuk mengaktifkan kembali pengiriman internasional
$internationalShippingEnabled = true;

$selectedShippingCost = 0.0;
$grandTotal = $subtotal;

$form = [
    'full_name' => $userData['full_name'] ?? '', 'email' => $userData['email'] ?? '',
    'phone' => '', 'shipping_mode' => 'domestic', 'country_code' => 'ID',
    'country_name' => 'Indonesia', 'address_line_1' => '', 'address_line_2' => '',
    'city' => '', 'state' => '', 'postal_code' => '',
    'courier_code' => '', 'courier_service' => '', 'shipping_cost' => '0', 'notes' => '', 'promo_code' => '',
];

// Pastikan proses checkout hanya jalan jika POST biasa (bukan request AJAX qty / hapus)
if (!$payOrder && $_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax_update_qty']) && !isset($_POST['ajax_remove_item']) && !isset($_POST['ajax_apply_promo'])) {
    foreach ($form as $key => $value) $form[$key] = trim((string)($_POST[$key] ?? ''));
    $selectedShippingCost = (float)$form['shipping_cost'];
    $grandTotal = $subtotal + $selectedShippingCost;
    $shippingCostIdr = (float)($_POST['shipping_cost_idr'] ?? 0);

    if (!$cartItems) {
        $error = 'No items available for checkout.';
    } elseif (!$internationalShippingEnabled && ($form['shipping_mode'] !== 'domestic' || strtoupper($form['country_code']) !== 'ID')) {
        $error = 'International shipping is currently unavailable. We only ship within Indonesia for now.';
    } elseif ($form['full_name'] === '' || $form['email'] === '' || $form['phone'] === '' ||
              $form['country_code'] === '' || $form['address_line_1'] === '' ||
              $form['city'] === '' || $form['postal_code'] === '') {
        $error = 'Please complete all required fields.';
    } elseif ($form['courier_code'] === '' || $form['courier_service'] === '') {
        $error = 'Please calculate shipping and choose a courier service.';
    } elseif (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($selectedShippingCost < 0) {
        $error = 'Invalid shipping amount.';
    } elseif (($serverShippingIdr = lookupShippingCostIdr(
                  $form['shipping_mode'], trim((string)($_POST['destination_id'] ?? '')),
                  $form['courier_code'], $form['courier_service'], $totalWeightGram
              )) === null) {
        $error = 'Could not verify the shipping cost. Please re-select your destination and courier service.';
    } elseif (($promoResult = promoResolve(
                  $pdo, (string)($_POST['promo_code'] ?? ''), $subtotal, ddConvertFromIdr($serverShippingIdr)
              ))['error'] !== null) {
        $error = $promoResult['error'];
    } else {
        // Internasional: negara diambil dari daftar statis (bukan dari input browser); country_code = kode ISO.
        if ($form['shipping_mode'] !== 'domestic' && ($intlCountry = intlFindCountry((string)($_POST['destination_id'] ?? '')))) {
            $form['country_code'] = $intlCountry['code'];
            $form['country_name'] = $intlCountry['name'];
        }
        // Pakai ongkir hasil hitung server, bukan nilai dari browser
        $selectedShippingCost = ddConvertFromIdr($serverShippingIdr);
        $promo         = $promoResult['promo'];
        $discountTotal = $promoResult['discounts']['total'];
        $grandTotal    = round($subtotal + $selectedShippingCost - $discountTotal, 2);
        try {
            validateCheckoutStock($pdo, $cartItems);
            $pdo->beginTransaction();
            $orderNumber = makeOrderNumber();

            $pdo->prepare(
                'INSERT INTO orders (order_number,user_id,customer_type,full_name,email,phone,
                 country_code,country_name,address_line_1,address_line_2,city,state,postal_code,
                 courier_code,courier_service,currency,subtotal,shipping_cost,promo_code,discount_amount,grand_total,
                 payment_provider,payment_status,fulfillment_status,notes)
                 VALUES (:on,:uid,:ct,:fn,:em,:ph,:cc,:cn,:a1,:a2,:ci,:st,:pc,:coc,:cs,:cur,
                         :sub,:sc,:promo,:disc,:gt,:pp,:ps,:fs,:no)'
            )->execute([
                'on' => $orderNumber, 'uid' => $userId,
                'ct' => $isLoggedIn ? 'member' : 'guest',
                'fn' => $form['full_name'], 'em' => $form['email'], 'ph' => $form['phone'],
                'cc' => $form['country_code'], 'cn' => $form['country_name'],
                'a1' => $form['address_line_1'],
                'a2' => $form['address_line_2'] !== '' ? $form['address_line_2'] : null,
                'ci' => $form['city'],
                'st' => $form['state'] !== '' ? $form['state'] : null,
                'pc' => $form['postal_code'], 'coc' => $form['courier_code'],
                'cs' => $form['courier_service'], 'cur' => $currency,
                'sub' => $subtotal, 'sc' => $selectedShippingCost,
                'promo' => $promo ? $promo['code'] : null, 'disc' => $discountTotal, 'gt' => $grandTotal,
                'pp' => null, 'ps' => 'pending', 'fs' => 'pending',
                'no' => $form['notes'] !== '' ? $form['notes'] : null,
            ]);
            $orderId = (int)$pdo->lastInsertId();

            // Kuota promo dipakai di transaksi yang sama: gagal → order ikut dibatalkan.
            if ($promo && !promoConsume($pdo, (int)$promo['id'])) {
                throw new RuntimeException('This promo code has reached its usage limit.');
            }

            $insertItem = $pdo->prepare(
                'INSERT INTO order_items (order_id,product_id,product_size_id,product_name,
                 product_slug,size_name,quantity,price,line_total,thumbnail)
                 VALUES (:oid,:pid,:psid,:pn,:ps,:sn,:qty,:pr,:lt,:th)'
            );
            foreach ($cartItems as $item) {
                $insertItem->execute([
                    'oid' => $orderId, 'pid' => $item['product_id'],
                    'psid' => $item['product_size_id'], 'pn' => $item['name'],
                    'ps' => $item['slug'],
                    'sn' => $item['size_name'] !== '' ? $item['size_name'] : null,
                    'qty' => $item['quantity'], 'pr' => $item['price'],
                    'lt' => $item['line_total'], 'th' => $item['thumbnail'],
                ]);
            }
            $pdo->prepare(
                'INSERT INTO order_status_logs (order_id,status_type,status_value,notes)
                 VALUES (:oid,:st,:sv,:n)'
            )->execute(['oid' => $orderId, 'st' => 'payment', 'sv' => 'pending',
                        'n' => 'Order created. Waiting for payment method selection.']);
            $pdo->commit();

            if ($checkoutMode === 'buy_now') unset($_SESSION['buy_now']);
            elseif (!$isLoggedIn) $_SESSION['guest_cart'] = [];

            // Order sudah tersimpan (payment_status = pending). Pembayaran (PayPal)
            // ditampilkan langsung di halaman checkout ini.
            header('Location: checkout.php?order=' . urlencode($orderNumber));
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'Failed to create order: ' . $e->getMessage();
        }
    }
}

$payIsPaid = false; $payCharge = ['currency' => 'USD', 'value' => '0.00']; $payStatusLabel = '';
$showKasera = false; $kData = []; $kMethod = ''; $kExpiresAt = ''; $kAmountIdr = 0;
$payItems = [];
if ($payOrder) {
    $payIsPaid       = strtolower((string)$payOrder['payment_status']) === 'paid';
    $payCharge       = ddPaypalCharge($payOrder);
    $payStatusLabel  = strtoupper(str_replace('_', ' ', (string)$payOrder['payment_status']));
    $payCurrency     = ddOrderCurrency($payOrder); // 'USD'; 'IDR' hanya untuk order lama

    $itemStmt = $pdo->prepare('SELECT product_name, size_name, quantity, line_total, thumbnail FROM order_items WHERE order_id = :id ORDER BY id');
    $itemStmt->execute(['id' => $payOrder['id']]);
    $payItems = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

    // Data QRIS / VA langsung dari Kasera (disimpan saat transaksi dibuat).
    $kMethod = (string)($payOrder['kasera_payment_method'] ?? '');
    $kData   = json_decode((string)($payOrder['kasera_payment_data'] ?? ''), true);
    $kData   = is_array($kData) ? $kData : [];
    $kExpiresAt = (string)($kData['expires_at'] ?? '');
    $kAmountIdr = kaseraAmountIdr($payOrder); // nominal yang benar-benar ditagih Kasera (selalu IDR)
    $showKasera = !$payIsPaid
        && strtolower((string)$payOrder['payment_status']) === 'pending'
        && isset(kaseraPaymentMethods()[$kMethod]) && !empty($kData['payment'])
        && !isset($_GET['change']);
}

/** Field form dengan label di atas input (komponen .field). $o: type, autocomplete, required. */
function ckField(string $id, string $label, string $value, array $o = []): string
{
    return '<div class="field"><label for="' . $id . '">' . htmlspecialchars($label) . '</label>'
        . '<input type="' . htmlspecialchars($o['type'] ?? 'text') . '" id="' . $id . '" name="' . $id . '"'
        . ' value="' . htmlspecialchars($value) . '"'
        . (!empty($o['autocomplete']) ? ' autocomplete="' . htmlspecialchars($o['autocomplete']) . '"' : '')
        . (!empty($o['required']) ? ' required' : '') . '></div>';
}

function bankLogoHtml(string $code, string $label): string
{
    foreach (['svg', 'png', 'webp'] as $ext) {
        if (is_file(__DIR__ . '/assets/banks/' . $code . '.' . $ext)) {
            return '<img src="assets/banks/' . htmlspecialchars($code . '.' . $ext) . '" alt="' . htmlspecialchars($label) . '" loading="lazy">';
        }
    }
    return '<span class="bank-wordmark">' . htmlspecialchars(trim(str_replace('Virtual Account', '', $label))) . '</span>';
}

$domesticCouriers      = ['jne' => 'JNE'];
// Internasional: satu kurir, tarif flat (lihat config/intl_shipping.php)
$internationalCouriers = intlCouriers();
$isBuyNow  = $checkoutMode === 'buy_now';
$pageTitle = $isBuyNow ? 'Checkout Now' : 'Checkout';

$backToProductUrl = 'shop.php';
if (!empty($cartItems)) {
    $first = $cartItems[0];
    $firstSlug = (string)($first['slug'] ?? '');
    if ($firstSlug !== '') {
        $backToProductUrl = 'product.php?slug=' . urlencode($firstSlug);
    }
}
siteHeadOpen([
    'title'       => $payOrder ? 'Payment' : $pageTitle,
    'description' => 'Secure checkout — ' . BRAND_NAME . '.',
    'noindex'     => true,
]);
?>
<style>
:root{--muted:var(--mute)}

/* ── kerangka: form kiri, ringkasan kanan ───────────── */
.co-top{display:flex;align-items:baseline;justify-content:space-between;gap:16px;margin-bottom:clamp(20px,3vw,36px)}
.co-top .page-title{margin:0}
.co-back{color:var(--ink-2);text-decoration:underline;text-underline-offset:3px;text-decoration-thickness:1px;white-space:nowrap}
.co-back:hover{color:var(--ink)}
.co-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,380px);gap:clamp(40px,6vw,88px);align-items:start}
.co-form{counter-reset:step;min-width:0}
.co-summary{position:sticky;top:calc(var(--nav-h) + 24px);background:var(--tile);padding:24px}

/* ── langkah / section ──────────────────────────────── */
.sec{margin-bottom:36px}
.step-title,.sec-title{display:flex;align-items:center;gap:10px;font-size:13px;font-weight:500;letter-spacing:-.01em;margin-bottom:16px}
.step-title::before{counter-increment:step;content:counter(step);display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;border:1px solid var(--ink);font-size:10px;line-height:1;font-weight:600}
.sec-sub{color:var(--ink-2);margin:-8px 0 16px}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.stack{display:flex;flex-direction:column;gap:14px}
.field-hint{font-size:11px;color:var(--ink-2);line-height:1.6}
.field .field-hint{margin-top:2px}

/* ── field opsional (dropdown) ──────────────────────── */
details.opt>summary{list-style:none;cursor:pointer;display:inline-flex;align-items:center;gap:8px;min-height:32px;font-weight:500;text-decoration:underline;text-underline-offset:3px;text-decoration-thickness:1px}
details.opt>summary::-webkit-details-marker{display:none}
details.opt>summary::before{content:'+';text-decoration:none;display:inline-block;width:10px;font-size:14px;line-height:1}
details.opt[open]>summary::before{content:'−'}
details.opt[open]>summary{margin-bottom:10px}

/* ── pengiriman ─────────────────────────────────────── */
.shipping-options-box{background:var(--tile);padding:18px 16px;text-align:center;color:var(--ink-2)}
.shipping-options-box:has(.shipping-option){padding:0;background:#fff;text-align:left;border:1px solid var(--line)}
.shipping-option{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:14px 16px;min-height:56px;cursor:pointer;border-top:1px solid var(--line);margin:0}
.shipping-option:first-child{border-top:none}
.shipping-option:has(input:checked){background:var(--tile);box-shadow:inset 0 0 0 1px var(--ink)}
.shipping-option input[type=radio]{width:16px;height:16px;accent-color:var(--ink);flex-shrink:0}
.shipping-option-left{display:flex;align-items:center;gap:12px}
.shipping-service-name{font-weight:500;font-size:12px}
.shipping-service-price{font-weight:500;font-size:12px;font-variant-numeric:tabular-nums}
.shipping-service-etd{color:var(--ink-2);margin-top:2px}
.shipping-courier-badge{padding:2px 8px;border:1px solid var(--line);color:var(--ink-2);font-size:10px}

/* ── promo, aksi, catatan ───────────────────────────── */
.promo-row{display:flex;gap:10px;align-items:flex-end}
.promo-row .field{flex:1;min-width:0}
.promo-row .btn{padding:0 20px}
#promoStatus{min-height:1.4em;margin-top:6px}
#promoStatus.ok{color:var(--ok)}
#promoStatus.err{color:var(--err)}
#promo_code{text-transform:uppercase}
.form-actions{margin-top:8px}
.secure-note{color:var(--ink-2);margin-top:12px}
.back-row{margin-top:16px}
.co-form .alert{margin-bottom:20px}
.co-form .btn:disabled{background:#cfcfcf;border-color:#cfcfcf;color:#fff}

/* ── ringkasan pesanan ──────────────────────────────── */
.co-item{display:grid;grid-template-columns:64px 1fr auto;gap:14px;align-items:start;margin-bottom:18px}
.co-thumb-wrap{position:relative;width:64px;height:80px}
.co-thumb{width:64px;height:80px;object-fit:cover;background:#fff}
.co-qty-badge{position:absolute;top:-6px;right:-6px;min-width:20px;height:20px;padding:0 6px;border-radius:10px;background:var(--ink);color:#fff;font-size:10px;font-weight:600;display:flex;align-items:center;justify-content:center}
.co-item-name{font-weight:500;line-height:1.35}
.co-item-meta{color:var(--ink-2);margin-top:2px}
.co-item-price{text-align:right;font-variant-numeric:tabular-nums}
.checkout-qty-ctrl{display:inline-flex;align-items:center;margin-top:8px;border:1px solid var(--line);background:#fff}
.checkout-qty-ctrl button{width:32px;height:32px;font-size:15px}
.checkout-qty-ctrl button:hover:not(:disabled){background:var(--tile)}
.checkout-qty-ctrl button:disabled{opacity:.4;cursor:not-allowed}
.checkout-qty-ctrl span{min-width:26px;text-align:center;font-size:12px;font-variant-numeric:tabular-nums}
.cart-item-remove-checkout{color:var(--ink-2);text-decoration:underline;text-underline-offset:3px;margin-left:10px;min-height:32px;padding:0 4px}
.cart-item-remove-checkout:hover{color:var(--err)}
/* bar toggle hanya tampil di mobile */
.summary-toggle{display:none}
.summary-body{display:grid;grid-template-rows:1fr}
.summary-inner{min-height:0;overflow:visible}
.totals-block{margin-top:20px;padding-top:16px;border-top:1px solid var(--line)}
.total-row{display:flex;justify-content:space-between;align-items:baseline;gap:12px;padding:5px 0;font-size:12px}
.total-row span:last-child{text-align:right;font-variant-numeric:tabular-nums}
.total-row.muted{color:var(--ink-2);font-size:11px}
.total-row.grand{padding-top:14px;font-size:14px;font-weight:500}
.total-row.grand .cur{font-size:10px;color:var(--ink-2);margin-right:6px;font-weight:400}
.total-row.grand strong{font-size:18px;font-weight:500;letter-spacing:-.02em}
.empty-cart{padding:12px 0;color:var(--ink-2)}

/* ── daftar metode bayar ────────────────────────────── */
.pay-list{border:1px solid var(--line)}
.pay-opt{border-top:1px solid var(--line);position:relative}
.pay-opt:first-child{border-top:none}
.pm-radio{position:absolute;opacity:0;width:1px;height:1px}
.pay-row{display:flex;align-items:center;gap:12px;padding:14px 16px;cursor:pointer;background:#fff;min-height:56px}
.pay-row:hover{background:#fafafa}
.rdot{width:16px;height:16px;border-radius:50%;border:1px solid #aaa;background:#fff;flex-shrink:0;transition:border-width .12s,border-color .12s}
.pay-name{flex:1;font-size:12px}
.pay-name.bold{font-weight:500}
.pay-logos{display:flex;align-items:center;gap:6px;flex-wrap:wrap;justify-content:flex-end}
.chip{display:inline-flex;align-items:center;justify-content:center;height:28px;min-width:44px;padding:0 7px;background:#fff;border:1px solid var(--line)}
.chip img{height:16px;width:auto;max-width:56px;object-fit:contain}
.chip.pp img{height:20px}
.chip .bank-wordmark{font-size:10px}
.chip-more{color:var(--ink-2);padding:0 4px}
.pm-radio:checked+.pay-row{background:var(--tile);box-shadow:inset 0 0 0 1px var(--ink)}
.pm-radio:checked+.pay-row .rdot{border:5px solid var(--ink)}
.pm-radio:focus-visible+.pay-row{outline:2px solid var(--ink);outline-offset:-4px}
.pay-panel{display:none;padding:18px 16px 20px;background:#fff;border-top:1px solid var(--line)}
.pm-radio:checked~.pay-panel{display:block}
.pay-panel .pay-note{margin-bottom:14px}
.va-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(96px,1fr));gap:8px;margin-bottom:14px}
.va-tile{position:relative;display:block;cursor:pointer}
.va-tile input{position:absolute;opacity:0;width:1px;height:1px}
.va-tile .tile{display:flex;align-items:center;justify-content:center;height:52px;padding:6px 8px;background:#fff;border:1px solid var(--line);transition:border-color .12s,box-shadow .12s}
.va-tile img{max-height:24px;max-width:78px;width:auto;object-fit:contain}
.va-tile .bank-wordmark{font-size:10px}
.va-tile input:checked+.tile{border-color:var(--ink);box-shadow:0 0 0 1px var(--ink)}
.va-tile input:focus-visible+.tile{outline:2px solid var(--ink);outline-offset:2px}
.bank-wordmark{font-weight:600;text-align:center}
.bank-row{display:flex;justify-content:space-between;gap:12px;padding:10px 0;border-top:1px solid var(--line);font-size:12px}
.bank-row:first-of-type{border-top:none;padding-top:0}
.bank-key{font-weight:600}
.bank-value{text-align:right;font-weight:500;word-break:break-all}
.bank-value small{display:block;font-weight:400;color:var(--ink-2);font-size:11px}
.upload-zone{border:1px dashed #bbb;padding:16px;margin:14px 0;background:#fff}
.file-input{width:100%;font-size:12px}
#paypal-button-container,#apple-pay-button,#google-pay-button{max-width:440px}
apple-pay-button{--apple-pay-button-width:100%;--apple-pay-button-height:44px;--apple-pay-button-border-radius:0;--apple-pay-button-padding:0;display:block}
.pay-opt[hidden],#express-status:empty{display:none}
.copy-status{font-size:11px;color:var(--ok);min-height:16px;margin-top:6px}
.pay-note{font-size:12px;color:var(--ink-2);line-height:1.7}

/* ── tampilan langsung Kasera (QR / VA) ─────────────── */
.pay-head{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:16px}
.pay-head .sec-title{margin:0}
.countdown{font-variant-numeric:tabular-nums;font-size:13px;font-weight:500;background:var(--tile);padding:6px 12px}
.countdown.warn{color:var(--err);animation:blink 1s step-end infinite}
@keyframes blink{50%{opacity:.35}}
.amount-box,.code-box{display:flex;justify-content:space-between;align-items:center;gap:12px;border:1px solid var(--line);padding:14px 16px;margin-bottom:14px;background:#fff}
.amount-box strong{font-size:18px;font-weight:500;letter-spacing:-.02em}
.code-box strong{font-size:18px;font-weight:500;letter-spacing:.04em;word-break:break-all;font-variant-numeric:tabular-nums}
.qr-wrap{display:flex;flex-direction:column;align-items:center;gap:12px;margin:8px 0 18px}
#qrbox canvas,#qrbox img{width:240px!important;height:240px!important;border:1px solid var(--line);padding:8px;background:#fff}
.pay-instr{color:var(--ink-2);line-height:1.8;margin-top:18px;border-top:1px solid var(--line);padding-top:16px}
.pay-instr strong{color:var(--ink);font-weight:500}
.pay-instr ol{margin:8px 0 0 18px}
.btn--sm{min-height:40px;padding:0 16px}

/* ── mobile: ringkasan di atas, bisa diciutkan ──────── */
@media(max-width:900px){
  .co-grid{display:flex;flex-direction:column;align-items:stretch;gap:32px}
  .co-summary{order:-1;position:static;padding:0;margin:0 calc(var(--gutter) * -1)}
  .co-top{margin-bottom:20px}
  .summary-toggle{display:flex;align-items:center;justify-content:space-between;gap:12px;width:100%;min-height:56px;padding:0 var(--gutter);text-align:left;font-size:12px}
  .summary-toggle-l{display:inline-flex;align-items:center;gap:8px}
  .summary-toggle .chev{transition:transform .4s var(--ease)}
  .summary-toggle[aria-expanded=true] .chev{transform:rotate(180deg)}
  .summary-toggle-r{display:inline-flex;align-items:baseline;gap:6px}
  .summary-toggle-r .cur{font-size:10px;color:var(--ink-2)}
  .summary-toggle-r strong{font-size:15px;font-weight:500}
  .summary-body{grid-template-rows:0fr;transition:grid-template-rows .4s var(--ease)}
  .summary-body.is-open{grid-template-rows:1fr}
  .summary-inner{overflow:hidden;padding:0 var(--gutter);opacity:0;transition:opacity .25s ease}
  .summary-body.is-open .summary-inner{opacity:1;padding-bottom:20px;padding-top:4px}
  .summary-body:not(.is-open) .summary-inner{visibility:hidden;transition:opacity .2s ease,visibility 0s .4s}
  .checkout-qty-ctrl button{width:40px;height:40px;font-size:17px}
  .cart-item-remove-checkout{min-height:40px}
  .totals-block{margin-top:12px}
}
@media(max-width:600px){
  .grid2{grid-template-columns:1fr}
  .va-grid{grid-template-columns:repeat(3,1fr)}
  .pay-logos .chip:nth-child(n+3),.pay-logos .chip-more{display:none}
  /* ≥16px agar iOS tidak zoom saat fokus */
  .co-form input,.co-form select,.co-form textarea{font-size:16px}
  .promo-row{flex-direction:column;align-items:stretch}
}
@media(prefers-reduced-motion:reduce){
  .summary-body,.summary-toggle .chev,.summary-inner{transition-duration:.01ms!important}
  .countdown.warn{animation:none}
}
</style>
<?php
siteBodyOpen(['bodyClass' => 'page-checkout', 'searchAction' => 'shop.php']);
?>
<main class="page-main">
<div class="page-wrap">

  <div class="co-top">
    <h1 class="page-title"><?= $payOrder ? 'Payment' : htmlspecialchars($pageTitle) ?></h1>
    <a href="<?= $payOrder ? 'index.php' : htmlspecialchars($backToProductUrl) ?>" class="co-back">← <?= $payOrder ? 'Shop' : 'Product' ?></a>
  </div>

  <div class="co-grid">

    <!-- ── KIRI: form / pembayaran ─────────────────────── -->
    <div class="co-form">

      <?php if ($error !== ''): ?>
        <div class="alert alert--error" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <?php if ($message !== ''): ?>
        <div class="alert alert--ok" role="status"><?= htmlspecialchars($message) ?></div>
      <?php endif; ?>

      <?php if ($payOrder): ?>
      <!-- ═════════ PAYMENT ═════════ -->
      <section class="sec" aria-label="Payment">
        <?php if ($payIsPaid): ?>
          <p class="pay-note">This order has been paid. Thank you!</p>
        <?php else: ?>
        <?php if ($payError !== ''): ?>
          <div class="alert alert--error" role="alert"><?= htmlspecialchars($payError) ?></div>
        <?php endif; ?>

        <?php if ($showKasera): ?>
        <?php
          $kPay      = $kData['payment'];
          $kIsQris   = $kMethod === 'qris';
          $kLabel    = kaseraPaymentMethods()[$kMethod];
          $kSteps    = is_array($kData['instructions']['steps'] ?? null) ? $kData['instructions']['steps'] : [];
        ?>
        <div class="pay-head">
          <h2 class="sec-title"><?= htmlspecialchars($kLabel) ?></h2>
          <span class="countdown" id="countdown" data-expires="<?= htmlspecialchars($kExpiresAt) ?>">--:--</span>
        </div>
        <div id="kaseraPay">
          <div id="expiredMsg" class="alert alert--error" style="display:none">This payment has expired. Please choose a payment method again.</div>
          <div id="payLive">
            <div class="amount-box">
              <div><span class="field-hint">Amount to pay</span><br><strong><?= htmlspecialchars(ddFormatMoney((float)$kAmountIdr, 'IDR')) ?></strong></div>
              <button type="button" class="btn btn--sm" data-copy="<?= (int)$kAmountIdr ?>">Copy</button>
            </div>
            <?php if ($payCurrency !== 'IDR'): ?>
              <p class="field-hint" style="margin:-4px 0 14px">Your order total of <?= htmlspecialchars(ddFormatMoney((float)$payOrder['grand_total'], $payCurrency)) ?> is settled in Indonesian Rupiah by this bank / QRIS payment method (1 USD = <?= htmlspecialchars(ddFormatMoney(ddRateFromIdr(), 'IDR')) ?>).</p>
            <?php endif; ?>
            <?php if ($kIsQris): ?>
              <div class="qr-wrap">
                <div id="qrbox"></div>
                <div class="copy-status" id="qrFallback" style="color:var(--err)"></div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;justify-content:center">
                  <button type="button" class="btn btn--sm" id="qrDownload">Download QR</button>
                  <button type="button" class="btn btn--sm" data-copy="<?= htmlspecialchars((string)($kPay['qr_string'] ?? '')) ?>">Copy QR code text</button>
                </div>
              </div>
            <?php else: ?>
              <div style="display:flex;justify-content:center;margin-bottom:14px"><span class="chip" style="height:44px;padding:0 14px"><?= bankLogoHtml($kMethod, $kLabel) ?></span></div>
              <div class="code-box">
                <div><span class="field-hint">Virtual account number</span><br><strong><?= htmlspecialchars((string)($kPay['payment_code'] ?? '')) ?></strong></div>
                <button type="button" class="btn btn--sm" data-copy="<?= htmlspecialchars((string)($kPay['payment_code'] ?? '')) ?>">Copy</button>
              </div>
              <p class="pay-note">Transfer the exact amount shown above.</p>
            <?php endif; ?>
            <?php if (!empty($kData['instructions']['title']) || $kSteps): ?>
              <div class="pay-instr">
                <strong><?= htmlspecialchars((string)($kData['instructions']['title'] ?? 'How to pay')) ?></strong>
                <?php if ($kSteps): ?><ol><?php foreach ($kSteps as $st): ?><li><?= htmlspecialchars(is_array($st) ? (string)($st['text'] ?? $st['description'] ?? json_encode($st)) : (string)$st) ?></li><?php endforeach; ?></ol><?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
          <div class="copy-status" id="copyStatus"></div>
          <div class="back-row"><a href="checkout.php?order=<?= urlencode((string)$payOrder['order_number']) ?>&change=1" class="btn btn--sm">← Choose another method</a></div>
        </div>

        <?php else: ?>
        <p class="sec-sub">All transactions are secure and encrypted.</p>
        <form id="payerForm" class="payer-form" autocomplete="on" novalidate>
          <input type="hidden" name="save_payer" value="1">
          <p class="field-hint" style="margin:0 0 10px">Payer details (optional)</p>
          <?= ckField('payer_name', 'Name', (string)$payOrder['payer_name'], ['autocomplete' => 'name']) ?>
          <?= ckField('payer_email', 'Email', (string)$payOrder['payer_email'], ['type' => 'email', 'autocomplete' => 'email']) ?>
          <?= ckField('payer_phone', 'Phone', (string)$payOrder['payer_phone'], ['type' => 'tel', 'autocomplete' => 'tel']) ?>
          <div class="copy-status" id="payerStatus"></div>
        </form>
        <?php if (KASERA_ENABLED && $payCurrency !== 'IDR'): ?>
          <p class="field-hint" style="margin:-4px 0 14px">QRIS, Virtual Account and e-wallets settle in Indonesian Rupiah: <strong><?= htmlspecialchars(ddFormatMoney((float)kaseraAmountIdr($payOrder), 'IDR')) ?></strong> (1 USD = <?= htmlspecialchars(ddFormatMoney(ddRateFromIdr(), 'IDR')) ?>).</p>
        <?php endif; ?>
        <div class="pay-list">
          <?php $kEnabled = KASERA_ENABLED ? kaseraEnabledMethods() : []; $vaBanks = array_diff_key($kEnabled, ['qris' => 1]); ?>

          <?php if (isset($kEnabled['qris'])): ?>
          <div class="pay-opt">
            <input type="radio" class="pm-radio" name="paymethod" id="pm_qris">
            <label for="pm_qris" class="pay-row"><span class="rdot"></span><span class="pay-name">QRIS</span><span class="pay-logos"><span class="chip"><?= bankLogoHtml('qris', 'QRIS') ?></span></span></label>
            <div class="pay-panel">
              <p class="pay-note">Scan with any e-wallet or mobile banking app. Confirmed automatically.</p>
              <form method="POST" data-pay>
                <input type="hidden" name="kasera_method" value="qris">
                <button type="submit" class="btn btn--solid btn--block">Pay with QRIS</button>
              </form>
            </div>
          </div>
          <?php endif; ?>

          <?php if ($vaBanks): ?>
          <div class="pay-opt">
            <input type="radio" class="pm-radio" name="paymethod" id="pm_va">
            <label for="pm_va" class="pay-row">
              <span class="rdot"></span><span class="pay-name">Virtual Account</span>
              <span class="pay-logos">
                <?php foreach (array_slice($vaBanks, 0, 3, true) as $code => $label): ?><span class="chip"><?= bankLogoHtml($code, $label) ?></span><?php endforeach; ?>
                <?php if (count($vaBanks) > 3): ?><span class="chip-more">+<?= count($vaBanks) - 3 ?></span><?php endif; ?>
              </span>
            </label>
            <div class="pay-panel">
              <p class="pay-note">Choose your bank. You will get a virtual account number. Confirmed automatically.</p>
              <form method="POST" data-pay data-needs="bank" id="vaForm">
                <div class="va-grid">
                  <?php foreach ($vaBanks as $code => $label): ?>
                    <label class="va-tile" title="<?= htmlspecialchars($label) ?>">
                      <input type="radio" name="kasera_method" value="<?= htmlspecialchars($code) ?>" data-label="<?= htmlspecialchars(trim(str_replace('Virtual Account', '', $label))) ?>">
                      <span class="tile"><?= bankLogoHtml($code, $label) ?></span>
                    </label>
                  <?php endforeach; ?>
                </div>
                <button type="submit" class="btn btn--solid btn--block" id="vaBtn" disabled>Select a bank</button>
              </form>
            </div>
          </div>
          <?php endif; ?>

          <?php if (KASERA_ENABLED): ?>
          <div class="pay-opt">
            <input type="radio" class="pm-radio" name="paymethod" id="pm_other">
            <label for="pm_other" class="pay-row"><span class="rdot"></span><span class="pay-name bold">E-wallet &amp; other methods</span></label>
            <div class="pay-panel">
              <p class="pay-note">You will be redirected to a secure page to choose another payment method.</p>
              <form method="POST" data-pay>
                <input type="hidden" name="kasera_method" value="checkout">
                <button type="submit" class="btn btn--solid btn--block">Continue</button>
              </form>
            </div>
          </div>
          <?php endif; ?>

          <!-- Apple Pay & Google Pay (lewat PayPal): baris muncul hanya jika perangkat & akun mendukung -->
          <div class="pay-opt" data-express="apple" hidden>
            <input type="radio" class="pm-radio" name="paymethod" id="pm_apple">
            <label for="pm_apple" class="pay-row"><span class="rdot"></span><span class="pay-name">Apple Pay</span></label>
            <div class="pay-panel">
              <p class="pay-note">Pay with Touch ID or Face ID. Your card details are never shared with us.</p>
              <div id="apple-pay-button"></div>
            </div>
          </div>
          <div class="pay-opt" data-express="google" hidden>
            <input type="radio" class="pm-radio" name="paymethod" id="pm_google">
            <label for="pm_google" class="pay-row"><span class="rdot"></span><span class="pay-name">Google Pay</span></label>
            <div class="pay-panel">
              <p class="pay-note">Pay with a card saved to your Google account.</p>
              <div id="google-pay-button"></div>
            </div>
          </div>
          <div class="copy-status" id="express-status" style="color:var(--err)"></div>

          <!-- PayPal (selalu USD) -->
          <div class="pay-opt">
            <input type="radio" class="pm-radio" name="paymethod" id="pm_paypal">
            <label for="pm_paypal" class="pay-row"><span class="rdot"></span><span class="pay-name">PayPal</span><span class="pay-logos"><span class="chip pp"><?= bankLogoHtml('paypal', 'PayPal') ?></span></span></label>
            <div class="pay-panel">
              <p class="pay-note">
                Pay securely with PayPal or a credit/debit card.
                <?php if ($payCurrency === 'IDR'): ?>PayPal does not support IDR, so you will be charged <strong>$<?= htmlspecialchars($payCharge['value']) ?> USD</strong>.<?php endif; ?>
              </p>
              <div id="paypal-button-container"></div>
              <div class="copy-status" id="paypal-status" style="color:var(--err)"></div>
            </div>
          </div>

          <?php if (false): /* Manual bank transfer disembunyikan sementara; ubah ke true untuk mengaktifkan lagi */ ?>
          <!-- Manual bank transfer + upload proof -->
          <div class="pay-opt">
            <input type="radio" class="pm-radio" name="paymethod" id="pm_manual">
            <label for="pm_manual" class="pay-row"><span class="rdot"></span><span class="pay-name bold">Bank Transfer (Manual)</span></label>
            <div class="pay-panel">
              <p class="pay-note">Transfer the exact total to one of the accounts below, then upload your proof. We verify manually.</p>
              <div class="bank-row"><span class="bank-key">BNI</span><span class="bank-value">1239697127<small>Muhammad Arief Rahman</small></span></div>
              <div class="bank-row"><span class="bank-key">BCA</span><span class="bank-value">8470620006<small>Muhammad Arief Rahman</small></span></div>
              <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <div class="upload-zone">
                  <label for="payment_proof" class="field-hint" style="display:block;margin:0 0 8px">Select file (JPG, PNG, or PDF)</label>
                  <input type="file" name="payment_proof" id="payment_proof" class="file-input" accept=".jpg,.jpeg,.png,.pdf" required>
                  <p class="field-hint">Amount, date, and reference number must be clearly visible.</p>
                </div>
                <button type="submit" class="btn btn--solid btn--block" id="uploadBtn">Submit payment proof</button>
              </form>
              <p class="field-hint" style="margin-top:10px">After submission, your payment status will change to "Waiting Verification".</p>
            </div>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; /* showKasera */ ?>
        <?php endif; /* payIsPaid */ ?>
      </section>

      <?php else: ?>
      <!-- ═════════ CHECKOUT FORM ═════════ -->
      <form method="POST" action="checkout.php<?= $isBuyNow ? '?mode=buy_now' : '' ?>" id="checkoutForm">
        <input type="hidden" name="checkout_mode" value="<?= htmlspecialchars($checkoutMode) ?>">
        <input type="hidden" name="currency" value="USD">

        <section class="sec" aria-label="Contact information">
          <h2 class="step-title">Contact</h2>
          <div class="stack">
            <?= ckField('email', 'Email', $form['email'], ['type' => 'email', 'autocomplete' => 'email', 'required' => true]) ?>
            <?= ckField('phone', 'Phone', $form['phone'], ['type' => 'tel', 'autocomplete' => 'tel', 'required' => true]) ?>
          </div>
        </section>

        <section class="sec" aria-label="Delivery address">
          <h2 class="step-title">Delivery</h2>
          <div class="stack">
            <div class="field">
              <label for="shipping_mode">Country/Region</label>
              <select id="shipping_mode" name="shipping_mode">
                <option value="domestic" <?= $form['shipping_mode'] === 'domestic' ? 'selected' : '' ?>>Indonesia (Domestic)</option>
                <?php if ($internationalShippingEnabled): ?>
                <option value="international" <?= $form['shipping_mode'] === 'international' ? 'selected' : '' ?>>International</option>
                <?php endif; ?>
              </select>
              <?php if (!$internationalShippingEnabled): ?><p class="field-hint">International shipping is currently unavailable.</p><?php endif; ?>
            </div>
            <div class="field">
              <label for="destination_search" id="destinationSearchLabel">City / Destination</label>
              <input type="text" id="destination_search" autocomplete="off">
            </div>
            <div class="field">
              <label for="destination_select">Select destination</label>
              <select id="destination_select"><option value="">Choose destination</option></select>
              <p class="field-hint">Make sure your address is accurate — incorrect addresses may cause delivery failure.</p>
            </div>
            <?= ckField('full_name', 'Full name', $form['full_name'], ['autocomplete' => 'name', 'required' => true]) ?>
            <?= ckField('address_line_1', 'Address', $form['address_line_1'], ['autocomplete' => 'address-line1', 'required' => true]) ?>
            <details class="opt"<?= $form['address_line_2'] !== '' ? ' open' : '' ?>>
              <summary>Add apartment, suite, etc.</summary>
              <?= ckField('address_line_2', 'Apartment, suite, etc.', $form['address_line_2'], ['autocomplete' => 'address-line2']) ?>
            </details>
            <div class="grid2">
              <?= ckField('city', 'City', $form['city'], ['autocomplete' => 'address-level2', 'required' => true]) ?>
              <?= ckField('postal_code', 'Postal code', $form['postal_code'], ['autocomplete' => 'postal-code', 'required' => true]) ?>
            </div>
            <details class="opt"<?= $form['state'] !== '' ? ' open' : '' ?>>
              <summary>Add province / state</summary>
              <?= ckField('state', 'Province / State', $form['state'], ['autocomplete' => 'address-level1']) ?>
            </details>
          </div>
        </section>

        <section class="sec" aria-label="Shipping method">
          <h2 class="step-title">Shipping method</h2>
          <div class="stack">
            <div class="field">
              <label for="courier_picker">Courier</label>
              <select id="courier_picker"></select>
            </div>
            <div id="shippingOptions" class="shipping-options-box">Enter your shipping address to view available shipping methods.</div>
            <details class="opt" id="promoBox"<?= $form['promo_code'] !== '' ? ' open' : '' ?>>
              <summary>Have a promo code?</summary>
              <div class="promo-row">
                <div class="field">
                  <label for="promo_code">Promo code (optional)</label>
                  <input type="text" id="promo_code" name="promo_code" maxlength="32"
                         autocomplete="off" value="<?= htmlspecialchars($form['promo_code']) ?>">
                </div>
                <button type="button" class="btn btn--sm" id="applyPromoBtn">Apply</button>
              </div>
              <p class="field-hint" id="promoStatus" role="status"></p>
            </details>
            <details class="opt"<?= $form['notes'] !== '' ? ' open' : '' ?>>
              <summary>Add order note</summary>
              <div class="field">
                <label for="notes">Special instructions</label>
                <textarea id="notes" name="notes"><?= htmlspecialchars($form['notes']) ?></textarea>
              </div>
            </details>
          </div>

          <!-- Hidden fields -->
          <input type="hidden" name="country_code"      id="country_code"      value="<?= htmlspecialchars($form['country_code']) ?>">
          <input type="hidden" name="country_name"      id="country_name"      value="<?= htmlspecialchars($form['country_name']) ?>">
          <input type="hidden" name="courier_code"      id="courier_code"      value="<?= htmlspecialchars($form['courier_code']) ?>">
          <input type="hidden" name="courier_service"   id="courier_service"   value="<?= htmlspecialchars($form['courier_service']) ?>">
          <input type="hidden" name="shipping_cost"     id="shipping_cost"     value="<?= htmlspecialchars($form['shipping_cost']) ?>">
          <input type="hidden" name="shipping_cost_idr" id="shipping_cost_idr" value="0">
          <input type="hidden" name="destination_id"    id="destination_id"    value="">
        </section>

        <div class="form-actions">
          <button type="submit" class="btn btn--solid btn--block" id="submitBtn">Proceed to payment</button>
          <p class="secure-note">Your data is encrypted and secure. We never store your payment details.</p>
        </div>
      </form>
      <?php endif; ?>
    </div>

    <!-- ── RIGHT: SUMMARY ─────────────────────────────── -->
    <aside class="co-summary" aria-label="Order summary">
      <span id="cartCountHeader" hidden><?= count($cartItems) ?></span>
      <?php
        // Ringkasan collapsible di mobile: total selalu terlihat di bar, detail produk dibuka dengan sekali tap.
        $sumHasToggle = $payOrder || !empty($cartItems);
        $sumCur   = $payOrder ? $payCurrency : $currency;
        $sumTotal = $payOrder
            ? ddFormatMoney((float)$payOrder['grand_total'], $payCurrency)
            : ddFormatMoney($grandTotal, $currency);
      ?>
      <?php if ($sumHasToggle): ?>
      <button type="button" class="summary-toggle" id="summaryToggle" aria-expanded="false" aria-controls="summaryBody">
        <span class="summary-toggle-l"><span id="summaryToggleLabel">Show order summary</span>
          <svg class="chev" width="12" height="8" viewBox="0 0 12 8" aria-hidden="true"><path d="M1 1.5l5 5 5-5" fill="none" stroke="currentColor" stroke-width="1.6"/></svg></span>
        <span class="summary-toggle-r"><span class="cur"><?= htmlspecialchars((string)$sumCur) ?></span><strong id="heroGrandTotal"><?= htmlspecialchars($sumTotal) ?></strong></span>
      </button>
      <?php else: ?>
      <span id="heroGrandTotal" hidden></span>
      <?php endif; ?>
      <div class="summary-body<?= $sumHasToggle ? '' : ' is-open' ?>" id="summaryBody"><div class="summary-inner">
      <?php if ($payOrder): ?>
        <?php foreach ($payItems as $pi): ?>
          <div class="co-item">
            <div class="co-thumb-wrap">
              <img class="co-thumb" src="<?= htmlspecialchars((string)$pi['thumbnail']) ?>" alt="<?= htmlspecialchars((string)$pi['product_name']) ?>" loading="lazy">
              <span class="co-qty-badge"><?= (int)$pi['quantity'] ?></span>
            </div>
            <div>
              <div class="co-item-name"><?= htmlspecialchars((string)$pi['product_name']) ?></div>
              <?php if (!empty($pi['size_name'])): ?><div class="co-item-meta"><?= htmlspecialchars((string)$pi['size_name']) ?></div><?php endif; ?>
            </div>
            <div class="co-item-price"><?= htmlspecialchars(ddFormatMoney((float)$pi['line_total'], $payCurrency)) ?></div>
          </div>
        <?php endforeach; ?>
        <div class="totals-block">
          <div class="total-row"><span>Subtotal</span><span><?= htmlspecialchars(ddFormatMoney((float)$payOrder['subtotal'], $payCurrency)) ?></span></div>
          <div class="total-row"><span>Shipping</span><span><?= htmlspecialchars(ddFormatMoney((float)$payOrder['shipping_cost'], $payCurrency)) ?></span></div>
          <?php if ((float)($payOrder['discount_amount'] ?? 0) > 0): ?>
            <div class="total-row"><span>Discount<?= !empty($payOrder['promo_code']) ? ' (' . htmlspecialchars($payOrder['promo_code']) . ')' : '' ?></span><span>−<?= htmlspecialchars(ddFormatMoney((float)$payOrder['discount_amount'], $payCurrency)) ?></span></div>
          <?php endif; ?>
          <div class="total-row grand"><span>Total</span><span><span class="cur"><?= $payCurrency ?></span><strong><?= htmlspecialchars(ddFormatMoney((float)$payOrder['grand_total'], $payCurrency)) ?></strong></span></div>
          <div class="total-row muted"><span>Order <?= htmlspecialchars($payOrder['order_number']) ?></span><span><?= htmlspecialchars($payStatusLabel) ?></span></div>
        </div>
      <?php elseif (!$cartItems): ?>
        <div class="empty-cart">No items in cart.</div>
      <?php else: ?>
        <?php foreach ($cartItems as $item): ?>
          <div class="co-item" id="row_psid_<?= $item['product_size_id'] ?>">
            <div class="co-thumb-wrap">
              <img class="co-thumb" src="<?= htmlspecialchars($item['thumbnail']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" loading="lazy">
              <span class="co-qty-badge" id="qty_badge_<?= $item['product_size_id'] ?>"><?= (int)$item['quantity'] ?></span>
            </div>
            <div>
              <div class="co-item-name"><?= htmlspecialchars($item['name']) ?></div>
              <?php if (strtolower((string)($item['category_slug'] ?? 'fashion')) === 'fashion'): ?>
                <div class="co-item-meta"><?= htmlspecialchars((string)$item['size_name']) ?></div>
              <?php endif; ?>
              <div>
                <span class="checkout-qty-ctrl">
                  <button type="button" aria-label="Decrease" onclick="updateCheckoutQty(<?= $item['product_size_id'] ?>, -1, <?= $item['stock'] ?>)">−</button>
                  <span id="qty_display_<?= $item['product_size_id'] ?>"><?= (int)$item['quantity'] ?></span>
                  <button type="button" aria-label="Increase" onclick="updateCheckoutQty(<?= $item['product_size_id'] ?>, 1, <?= $item['stock'] ?>)">+</button>
                </span>
                <button type="button" class="cart-item-remove-checkout" aria-label="Remove item" onclick="removeCheckoutItem(<?= $item['product_size_id'] ?>)">Remove</button>
              </div>
            </div>
            <div class="co-item-price" id="line_total_display_<?= $item['product_size_id'] ?>"><?= htmlspecialchars(ddFormatMoney((float)$item['line_total'], $item['currency'])) ?></div>
          </div>
        <?php endforeach; ?>

        <div class="totals-block">
          <div class="total-row"><span>Subtotal</span><span id="subtotalDisplay"><?= htmlspecialchars(ddFormatMoney($subtotal, $currency)) ?></span></div>
          <div class="total-row"><span>Shipping</span><span id="shippingDisplay" style="color:var(--muted)">Enter shipping address</span></div>
          <div class="total-row" id="discountRow" style="display:none"><span id="discountLabel">Discount</span><span id="discountDisplay"></span></div>
          <div class="total-row grand"><span>Total</span><span><span class="cur"><?= htmlspecialchars($currency) ?></span><strong id="grandTotalDisplay"><?= htmlspecialchars(ddFormatMoney($grandTotal, $currency)) ?></strong></span></div>
        </div>
      <?php endif; ?>
      </div></div>
    </aside>
    <script>
    (function () {
      var btn = document.getElementById('summaryToggle'), body = document.getElementById('summaryBody');
      if (!btn || !body) return;
      var label = document.getElementById('summaryToggleLabel');
      btn.addEventListener('click', function () {
        var open = body.classList.toggle('is-open');
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (label) label.textContent = open ? 'Hide order summary' : 'Show order summary';
      });
    })();
    </script>

  </div>
</div>
</main>
<?php
siteBodyClose();
?>
  <?php if ($payOrder): ?>
  <?php if (!$payIsPaid && !$showKasera): ?>
  <script src="https://www.paypal.com/sdk/js?client-id=<?= htmlspecialchars(PAYPAL_CLIENT_ID) ?>&components=buttons,applepay,googlepay&currency=<?= htmlspecialchars($payCharge['currency']) ?>&intent=capture"></script>
  <script src="https://applepay.cdn-apple.com/jsapi/v1.1.0/apple-pay-sdk.js" async></script>
  <script src="https://pay.google.com/gp/p/js/pay.js" async></script>
  <script>
  window.EXPRESS_PAY = <?= json_encode([
      'orderNumber' => (string)$payOrder['order_number'],
      'amount'      => $payCharge['value'],
      'currency'    => $payCharge['currency'],
      'shop'        => BRAND_NAME,
      'sandbox'     => PAYPAL_MODE === 'sandbox',
  ], JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  </script>
  <script src="<?= esc(siteAsset('assets/js/express-pay.js')) ?>" defer></script>
  <script>
  (function () {
    'use strict';
    const ORDER_NUMBER = <?= json_encode($payOrder['order_number']) ?>;

    /* Detail pembayar (opsional): tersimpan otomatis saat field berubah */
    const payerForm = document.getElementById('payerForm');
    if (payerForm) {
      const payerStatus = document.getElementById('payerStatus');
      payerForm.addEventListener('submit', e => e.preventDefault());
      payerForm.addEventListener('change', async () => {
        try {
          const res = await fetch('checkout.php?order=' + encodeURIComponent(ORDER_NUMBER), {
            method: 'POST', body: new FormData(payerForm)
          });
          const data = await res.json();
          payerStatus.textContent = data.ok ? 'Saved' : (data.error || 'Could not save.');
        } catch (err) {
          payerStatus.textContent = 'Could not save.';
        }
      });
    }

    /* Tombol submit dinonaktifkan setelah klik (cegah order ganda) & di-reset saat kembali dari bfcache */
    document.querySelectorAll('form[data-pay]').forEach(f => {
      const btn = f.querySelector('button[type=submit]');
      const label = btn.textContent;
      f.addEventListener('submit', () => { btn.disabled = true; btn.textContent = 'Processing...'; });
      window.addEventListener('pageshow', () => {
        btn.textContent = label;
        btn.disabled = f.dataset.needs === 'bank' && !f.querySelector('input[name=kasera_method]:checked');
      });
    });
    const vaForm = document.getElementById('vaForm');
    if (vaForm) {
      const vaBtn = vaForm.querySelector('button[type=submit]');
      vaForm.addEventListener('change', () => {
        const r = vaForm.querySelector('input[name=kasera_method]:checked');
        vaBtn.disabled = !r;
        vaBtn.textContent = r ? 'Pay with ' + r.dataset.label : 'Select a bank';
      });
    }

    const uploadForm = document.getElementById('uploadForm');
    const uploadBtn  = document.getElementById('uploadBtn');
    if (uploadForm && uploadBtn) uploadForm.addEventListener('submit', function () { uploadBtn.disabled = true; uploadBtn.textContent = 'Uploading...'; });

    /* PayPal: dirender saat baris PayPal pertama kali dipilih (container baru terlihat saat itu) */
    const box = document.getElementById('paypal-button-container');
    const status = document.getElementById('paypal-status');
    const fail = msg => { if (status) status.textContent = msg; };
    let paypalRendered = false;
    function renderPayPal() {
      if (paypalRendered || !box) return;
      paypalRendered = true;
      if (!window.paypal) { box.textContent = 'PayPal could not be loaded. Please refresh the page.'; return; }
      paypal.Buttons({
        createOrder: function () {
          return fetch('paypal-create-order.php?order=' + encodeURIComponent(ORDER_NUMBER), { method: 'POST' })
            .then(r => r.json())
            .then(d => { if (!d.id) throw new Error(d.error || 'Could not start PayPal payment.'); return d.id; });
        },
        onApprove: function (data) {
          return fetch('paypal-capture-order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_number: ORDER_NUMBER, paypal_order_id: data.orderID })
          })
            .then(r => r.json())
            .then(d => {
              if (d.status === 'COMPLETED') {
                window.location.href = 'index.php?payment_uploaded=1&invoice_email=' +
                  encodeURIComponent(d.invoiceMail || 'failed') + '&order=' + encodeURIComponent(ORDER_NUMBER);
              } else {
                fail(d.error || 'Payment could not be completed.');
              }
            });
        },
        onError: function () { fail('PayPal error. Please try again.'); }
      }).render('#paypal-button-container');
    }
    const ppRadio = document.getElementById('pm_paypal');
    if (ppRadio) ppRadio.addEventListener('change', () => { if (ppRadio.checked) renderPayPal(); });
  })();
  </script>
  <?php endif; ?>
  <?php if ($showKasera): ?>
  <?php if ($kIsQris): ?><script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script><?php endif; ?>
  <script>
  (function () {
    'use strict';
    const ORDER_NUMBER = <?= json_encode($payOrder['order_number']) ?>;
    const QR_STRING    = <?= json_encode((string)($kPay['qr_string'] ?? '')) ?>;
    const status = document.getElementById('copyStatus');
    const say = m => { if (status) { status.textContent = m; setTimeout(() => status.textContent = '', 2200); } };

    async function copyText(text) {
      try {
        if (navigator.clipboard && window.isSecureContext) await navigator.clipboard.writeText(text);
        else { const t = document.createElement('textarea'); t.value = text; t.style.cssText = 'position:fixed;left:-9999px'; document.body.appendChild(t); t.select(); document.execCommand('copy'); t.remove(); }
        say('Copied ✓');
      } catch (e) { say('Failed to copy.'); }
    }
    document.querySelectorAll('[data-copy]').forEach(b => b.addEventListener('click', () => copyText(b.dataset.copy)));

    /* QR dirender di browser dari payload EMV mentah */
    const qrbox = document.getElementById('qrbox');
    if (qrbox) {
      if (window.QRCode && QR_STRING) {
        new QRCode(qrbox, { text: QR_STRING, width: 480, height: 480, correctLevel: QRCode.CorrectLevel.M });
        const dl = document.getElementById('qrDownload');
        dl.addEventListener('click', () => {
          const c = qrbox.querySelector('canvas'); if (!c) return;
          const a = document.createElement('a'); a.href = c.toDataURL('image/png'); a.download = 'qris-' + ORDER_NUMBER + '.png'; a.click();
        });
      } else {
        document.getElementById('qrFallback').textContent = 'QR could not be displayed. Please copy the QR code text below and use it in your payment app.';
      }
    }

    /* hitung mundur */
    const cd = document.getElementById('countdown');
    const exp = cd && cd.dataset.expires ? new Date(cd.dataset.expires).getTime() : 0;
    let expired = false;
    function tickCountdown() {
      if (!exp) { cd.textContent = ''; return; }
      const left = Math.max(0, exp - Date.now());
      const m = Math.floor(left / 60000), s = Math.floor(left % 60000 / 1000);
      cd.textContent = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
      cd.classList.toggle('warn', left > 0 && left < 300000);
      if (left === 0 && !expired) {
        expired = true;
        document.getElementById('payLive').style.display = 'none';
        document.getElementById('expiredMsg').style.display = 'block';
      }
    }
    tickCountdown(); setInterval(tickCountdown, 1000);

    /* polling status tiap 4 dtk; tiap ~12 dtk minta sinkron ke Kasera (cadangan webhook) */
    let n = 0;
    setInterval(async () => {
      if (expired) return;
      n++;
      try {
        const r = await fetch('checkout.php?order=' + encodeURIComponent(ORDER_NUMBER) + '&status=1' + (n % 3 === 0 ? '&sync=1' : ''), { cache: 'no-store' });
        const d = await r.json();
        if (d.status === 'paid' || d.status === 'expired') window.location.reload();
      } catch (e) {}
    }, 4000);
  })();
  </script>
  <?php endif; ?>
  <?php else: ?>
  <!-- ── JAVASCRIPT ────────────────────────────────---- -->
  <script>
  (function () {
    'use strict';

    /* ── toast ──────────────────────────────────────── */
    function showToast(msg) { Site.showToast(msg); }

    /* ── PHP vars ───────────────────────────────────── */
    let subtotal                = <?= json_encode((float)$subtotal) ?>;
    let totalWeightGram         = <?= (int)$totalWeightGram ?>;
    const CUR_RATE_FROM_IDR     = <?= ddRateFromIdr() ?>; // 1 USD = ... IDR (ongkir kurir dalam IDR)
    const domesticCouriers      = <?= json_encode($domesticCouriers) ?>;
    const internationalCouriers = <?= json_encode($internationalCouriers) ?>;

    /* ── DOM refs ───────────────────────────────────── */
    const shippingModeEl       = document.getElementById('shipping_mode');
    const destSearch           = document.getElementById('destination_search');
    const destSelect           = document.getElementById('destination_select');
    const courierPicker        = document.getElementById('courier_picker');
    const shippingOptionsEl    = document.getElementById('shippingOptions');
    const countryCodeInput     = document.getElementById('country_code');
    const countryNameInput     = document.getElementById('country_name');
    const courierCodeInput     = document.getElementById('courier_code');
    const courierServiceInput  = document.getElementById('courier_service');
    const shippingCostInput    = document.getElementById('shipping_cost');
    const shippingCostIdrInput = document.getElementById('shipping_cost_idr');
    const destinationIdInput   = document.getElementById('destination_id');
    const shippingDisplay      = document.getElementById('shippingDisplay');
    const grandTotalDisplay    = document.getElementById('grandTotalDisplay');
    const heroGrandTotal       = document.getElementById('heroGrandTotal');
    const heroGrandTotalTop    = document.getElementById('heroGrandTotalTop');
    const submitBtn            = document.getElementById('submitBtn');

    function formatCurrencyJS(amount) {
      const a = Number(amount) || 0;
      return '$' + a.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    function convertFromIdr(amountIdr) {
      return Math.round(Number(amountIdr) / CUR_RATE_FROM_IDR * 100) / 100;
    }

    /* ── PROMO ──────────────────────────────────────── */
    const MIN_PAYABLE = 0.01;
    let appliedPromo = null; // {code, type, label, itemDiscount}
    const promoInput    = document.getElementById('promo_code');
    const promoStatus   = document.getElementById('promoStatus');
    const discountRow   = document.getElementById('discountRow');
    const discountLabel = document.getElementById('discountLabel');
    const discountValue = document.getElementById('discountDisplay');

    /** Potongan yang berlaku untuk ongkir tertentu (rumus sama dengan promoDiscounts() di PHP). */
    function promoDiscountFor(shippingUsd) {
      if (!appliedPromo) return 0;
      const item = appliedPromo.itemDiscount;
      const ship = appliedPromo.type === 'free_shipping' ? shippingUsd : 0;
      const maxTotal = Math.max(subtotal + shippingUsd - MIN_PAYABLE, 0);
      return Math.min(item + ship, maxTotal);
    }

    function setPromoStatus(text, ok) {
      if (!promoStatus) return;
      promoStatus.textContent = text;
      promoStatus.className = 'field-hint ' + (text ? (ok ? 'ok' : 'err') : '');
    }

    async function applyPromo() {
      const code = promoInput.value.trim();
      if (!code) { appliedPromo = null; setPromoStatus('', true); updateGrandTotal(Number(shippingCostIdrInput.value || 0)); return; }

      const fd = new FormData();
      fd.append('ajax_apply_promo', '1');
      fd.append('promo_code', code);
      fd.append('shipping_usd', convertFromIdr(Number(shippingCostIdrInput.value || 0)));
      fd.append('checkout_mode', document.querySelector('input[name="checkout_mode"]').value);

      try {
        const res  = await fetch(window.location.href, { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
          appliedPromo = { code: data.code, type: data.type, label: data.label, itemDiscount: Number(data.item_discount) || 0 };
          promoInput.value = data.code;
          setPromoStatus(data.message, true);
        } else {
          appliedPromo = null;
          setPromoStatus(data.message || 'Could not apply this code.', false);
        }
      } catch (err) {
        appliedPromo = null;
        setPromoStatus('Could not check the promo code. Please try again.', false);
      }
      updateGrandTotal(Number(shippingCostIdrInput.value || 0));
    }

    const applyPromoBtn = document.getElementById('applyPromoBtn');
    if (applyPromoBtn) applyPromoBtn.addEventListener('click', applyPromo);
    if (promoInput) promoInput.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); applyPromo(); } });

    function updateGrandTotal(shippingCostIdr) {
      const shippingConverted = convertFromIdr(shippingCostIdr);
      const subtotalConverted = subtotal; 
      const discount = promoDiscountFor(shippingConverted);
      const total = subtotalConverted + shippingConverted - discount;
      if (discountRow) {
        discountRow.style.display = discount > 0 ? '' : 'none';
        if (discount > 0) {
          discountLabel.textContent = 'Discount (' + appliedPromo.code + ')';
          discountValue.textContent = '−' + formatCurrencyJS(discount);
        }
      }
      // Belum ada layanan kurir dipilih → tampilkan petunjuk, bukan "RP 0"
      const hasService = courierServiceInput.value !== '';
      shippingDisplay.textContent   = hasService ? formatCurrencyJS(shippingConverted) : 'Enter shipping address';
      shippingDisplay.style.color   = hasService ? '' : 'var(--muted)';
      grandTotalDisplay.textContent = formatCurrencyJS(total);
      heroGrandTotal.textContent    = formatCurrencyJS(total);
      if (heroGrandTotalTop) heroGrandTotalTop.textContent = formatCurrencyJS(total);
    }

    /** Samakan panel keranjang bersama (Site.cart) dengan localStorage yang baru diubah di halaman ini. */
    function syncSiteCart(items) {
      if (!window.Site || !Site.cart) return;
      Site.cart.items.length = 0;
      items.filter(i => i && i.currency === 'USD').forEach(i => Site.cart.items.push(i));
      Site.cart.render();
    }

    /* ── AJAX QTY UPDATE LOGIC ────────────────────────── */
    window.updateCheckoutQty = async function(psid, change, maxStock) {
        const displayEl = document.getElementById('qty_display_' + psid);
        if (!displayEl) return;
        
        const currentQty = parseInt(displayEl.textContent, 10) || 1;
        const newQty = currentQty + change;

        if (newQty < 1) return;
        if (newQty > maxStock) {
            showToast('Sisa stok hanya ' + maxStock);
            return;
        }

        const btnCtrl = displayEl.parentElement;
        const btns = btnCtrl.querySelectorAll('button');
        btns.forEach(b => b.disabled = true);
        displayEl.style.opacity = '0.5';

        const fd = new FormData();
        fd.append('ajax_update_qty', '1');
        fd.append('product_size_id', psid);
        fd.append('qty', newQty);
        fd.append('checkout_mode', document.querySelector('input[name="checkout_mode"]').value);

        try {
            const res = await fetch(window.location.href, { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                displayEl.textContent = data.new_qty;
                const badge = document.getElementById('qty_badge_' + psid);
                if (badge) badge.textContent = data.new_qty;
                document.getElementById('line_total_display_' + psid).textContent = formatCurrencyJS(data.new_line_total);

                subtotal = parseFloat(data.new_subtotal);
                document.getElementById('subtotalDisplay').textContent = formatCurrencyJS(subtotal);
                totalWeightGram = parseInt(data.new_total_weight, 10) || totalWeightGram;
                if (appliedPromo) applyPromo(); // subtotal berubah → syarat & nilai promo dihitung ulang

                // Berat berubah → ongkir lama tidak valid lagi, hitung ulang
                if (destSelect.value && courierPicker.value) {
                    calculateShipping();
                } else {
                    updateGrandTotal(Number(shippingCostIdrInput.value || 0));
                }

                if (document.querySelector('input[name="checkout_mode"]').value !== 'buy_now') {
                    let lsCart = JSON.parse(localStorage.getItem('ppf_cart') || '[]');
                    let updated = false;
                    for (let i = 0; i < lsCart.length; i++) {
                        if (parseInt(lsCart[i].size_id) === parseInt(psid)) {
                            lsCart[i].qty = newQty;
                            updated = true;
                            break;
                        }
                    }
                    if (updated) {
                        localStorage.setItem('ppf_cart', JSON.stringify(lsCart));
                        syncSiteCart(lsCart);
                        const totalCount = lsCart.reduce((sum, item) => sum + item.qty, 0);
                        const hdrEl = document.getElementById('cartCountHeader');
                        if(hdrEl) hdrEl.textContent = totalCount;
                    }
                }
            } else {
                showToast(data.message || 'Update failed');
            }
        } catch (err) {
            showToast('Error connecting to server');
        } finally {
            btns.forEach(b => b.disabled = false);
            displayEl.style.opacity = '1';
        }
    };
    /* ─────────────────────────────────────────────────── */

    /* ── AJAX REMOVE ITEM LOGIC ───────────────────────── */
    window.removeCheckoutItem = async function(psid) {
        if (!confirm('Apakah Anda yakin ingin menghapus produk ini dari keranjang?')) return;
        
        const fd = new FormData();
        fd.append('ajax_remove_item', '1');
        fd.append('product_size_id', psid);
        fd.append('checkout_mode', document.querySelector('input[name="checkout_mode"]').value);

        try {
            const res = await fetch(window.location.href, { method: 'POST', body: fd });
            const data = await res.json();
            
            if (data.success) {
                if (document.querySelector('input[name="checkout_mode"]').value !== 'buy_now') {
                    let lsCart = JSON.parse(localStorage.getItem('ppf_cart') || '[]');
                    lsCart = lsCart.filter(item => parseInt(item.size_id) !== parseInt(psid));
                    localStorage.setItem('ppf_cart', JSON.stringify(lsCart));
                }
                
                window.location.reload();
            } else {
                showToast('Gagal menghapus produk.');
            }
        } catch (err) {
            showToast('Terjadi kesalahan server saat menghapus.');
        }
    };
    /* ─────────────────────────────────────────────────── */

    function renderCourierOptions() {
      const map = shippingModeEl.value === 'domestic' ? domesticCouriers : internationalCouriers;
      courierPicker.innerHTML = '<option value="">Select courier</option>';
      Object.entries(map).forEach(([code, label]) => {
        const o = document.createElement('option');
        o.value = code; o.textContent = label;
        courierPicker.appendChild(o);
      });
      // Internasional hanya punya satu kurir: dipilih otomatis, tanpa field.
      const isIntl = shippingModeEl.value !== 'domestic';
      courierPicker.closest('.field').style.display = isIntl ? 'none' : '';
      if (isIntl) courierPicker.value = Object.keys(map)[0];
    }

    function resetShipping() {
      shippingOptionsEl.textContent = 'Select destination and courier to see shipping options.';
      courierCodeInput.value = ''; courierServiceInput.value = '';
      shippingCostInput.value = '0'; shippingCostIdrInput.value = '0';
      destinationIdInput.value = '';
      updateGrandTotal(0);
    }

    function updateModeUI() {
      const isDom = shippingModeEl.value === 'domestic';
      document.getElementById('destinationSearchLabel').textContent =
        isDom ? 'City / District (type to search)' : 'Country (type to search)';
      // placeholder tetap ' ' agar label mengambang bekerja; teks petunjuk ada di label
      if (isDom) { countryCodeInput.value = 'ID'; countryNameInput.value = 'Indonesia'; }
      else if (countryNameInput.value === 'Indonesia') { countryCodeInput.value = ''; countryNameInput.value = ''; }
      destSearch.value = '';
      destSelect.innerHTML = '<option value="">Choose destination</option>';
      renderCourierOptions();
      resetShipping();
    }

    async function loadDestinations() {
      if (destSearch.value.trim().length < 2) {
        destSelect.innerHTML = '<option value="">Type at least 2 characters</option>';
        return;
      }
      const endpoint = shippingModeEl.value === 'domestic'
        ? 'config/get-domestic-destinations.php'
        : 'config/get-intl-countries.php';
      try {
        const res    = await fetch(endpoint + '?search=' + encodeURIComponent(destSearch.value.trim()) + '&limit=10');
        const result = await res.json();
        if (!result.success) { destSelect.innerHTML = '<option value="">Error loading destination</option>'; return; }
        const rows = Array.isArray(result.raw?.data) ? result.raw.data : [];
        destSelect.innerHTML = '<option value="">Choose destination</option>';
        if (!rows.length) { destSelect.innerHTML = '<option value="">No result found</option>'; return; }
        rows.forEach(item => {
          const o = document.createElement('option');
          if (shippingModeEl.value === 'domestic') {
            const id    = item.id || item.subdistrict_id || item.destination_id || item.destination || '';
            const label = item.label || [item.subdistrict_name, item.district_name, item.city_name, item.province_name].filter(Boolean).join(', ') || 'Unknown';
            o.value = id; o.textContent = label; o.dataset.name = label;
          } else {
            o.value = item.country_id || '';
            o.textContent = item.country_name || 'Unknown';
            o.dataset.name = item.country_name || '';
          }
          destSelect.appendChild(o);
        });
      } catch {
        destSelect.innerHTML = '<option value="">Error loading destination</option>';
      }
    }

    destSelect.addEventListener('change', function () {
      const sel = this.options[this.selectedIndex];
      if (shippingModeEl.value === 'domestic') {
        countryCodeInput.value = 'ID'; countryNameInput.value = 'Indonesia';
      } else {
        countryCodeInput.value = sel.value || '';
        countryNameInput.value = sel.dataset.name || '';
      }
      calculateShipping();
    });

    courierPicker.addEventListener('change', calculateShipping);

    async function calculateShipping() {
      const destination = destSelect.value;
      const courier     = courierPicker.value;

      destinationIdInput.value = destination;
      courierCodeInput.value = ''; courierServiceInput.value = '';
      shippingCostInput.value = '0'; shippingCostIdrInput.value = '0';
      updateGrandTotal(0);

      if (!destination || !courier) {
        shippingOptionsEl.textContent = 'Select destination and courier to see shipping options.';
        return;
      }

      shippingOptionsEl.innerHTML = '<span>Loading shipping options...</span>';

      const endpoint = shippingModeEl.value === 'domestic'
        ? 'config/calculate-domestic-shipping.php'
        : 'config/calculate-intl-shipping.php';

      const fd = new FormData();
      fd.append('destination', destination);
      fd.append('courier', courier);
      fd.append('weight', String(Math.max(1, totalWeightGram)));
      fd.append('price', 'lowest');

      try {
        const res     = await fetch(endpoint, { method: 'POST', body: fd });
        const rawText = await res.text();
        let result;
        try { result = JSON.parse(rawText); }
        catch { throw new Error('Server error — check shipping endpoint for PHP errors.'); }
        if (!result.success) throw new Error(result.message || 'Failed to calculate shipping.');

        const rows = Array.isArray(result.services) ? result.services : [];
        if (!rows.length) { shippingOptionsEl.textContent = 'No shipping services found.'; return; }

        shippingOptionsEl.innerHTML = '';
        rows.forEach((row, idx) => {
          const costIdr  = Number(row.cost || 0);
          const costShown = convertFromIdr(costIdr);
          const service  = row.service || 'Service';
          const etdText  = row.etd ? `<div class="shipping-service-etd">ETD: ${row.etd}</div>` : '';
          const wrapper  = document.createElement('label');
          wrapper.className = 'shipping-option';
          wrapper.innerHTML = `
            <div class="shipping-option-left">
              <input type="radio" class="shipping-radio" name="shipping_service_option" value="${idx}">
              <div>
                <div class="shipping-service-name">${service}</div>
                <div class="shipping-service-price">${formatCurrencyJS(costShown)}</div>
                ${etdText}
              </div>
            </div>
            ${shippingModeEl.value === 'domestic' ? `<div class="shipping-courier-badge">${courier.toUpperCase()}</div>` : ''}`;
          wrapper.querySelector('input').addEventListener('change', () => {
            courierCodeInput.value     = courier;
            courierServiceInput.value  = service;
            shippingCostIdrInput.value = costIdr;
            shippingCostInput.value    = costShown.toFixed(2);
            updateGrandTotal(costIdr);
            showToast(service + ' selected');
          });
          shippingOptionsEl.appendChild(wrapper);
        });
      } catch (err) {
        shippingOptionsEl.innerHTML = `<span style="color:#b3261e">${err.message}</span>`;
        showToast(err.message);
      }
    }

    document.getElementById('checkoutForm').addEventListener('submit', function () {
      submitBtn.disabled = true;
      submitBtn.textContent = 'Processing...';
    });

    /* ── init ───────────────────────────────────────── */
    shippingModeEl.addEventListener('change', updateModeUI);
    let destTimer = null;
    destSearch.addEventListener('input', () => { clearTimeout(destTimer); destTimer = setTimeout(loadDestinations, 350); });
    updateModeUI();
    updateGrandTotal(Number(shippingCostInput.value || 0));

  })();
  </script>
  <?php endif; ?>
<?php siteDocClose();
