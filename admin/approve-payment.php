<?php
require_once __DIR__ . '/_guard.php';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/email_helpers.php';

$adminId = (int) ($_SESSION['admin']['id'] ?? 0);
if ($adminId <= 0) {
    $adminId = 1;
}

$orderId = (int) ($_POST['order_id'] ?? 0);
if ($orderId <= 0) {
    die('Invalid order id.');
}

try {
    $pdo->beginTransaction();

    $orderStmt = $pdo->prepare("
        SELECT *
        FROM orders
        WHERE id = :id
        LIMIT 1
        FOR UPDATE
    ");
    $orderStmt->execute(['id' => $orderId]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception('Order not found.');
    }

    $paymentStatus = strtolower(trim((string) ($order['payment_status'] ?? '')));
    if ($paymentStatus === '') {
        $paymentStatus = 'pending';
    }

    if ($paymentStatus === 'paid') {
        throw new Exception('Order already approved.');
    }

    if (!in_array($paymentStatus, ['pending', 'waiting_verification'], true)) {
        throw new Exception('Order cannot be approved from current status.');
    }

    $itemStmt = $pdo->prepare("
        SELECT *
        FROM order_items
        WHERE order_id = :order_id
        ORDER BY id ASC
    ");
    $itemStmt->execute(['order_id' => $order['id']]);
    $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$items) {
        throw new Exception('Order items not found.');
    }

    // Admin sudah memverifikasi uang masuk, jadi stok kurang tidak boleh menggagalkan approve:
    // order tetap lunas dan kekurangan stok dicatat sebagai peringatan untuk ditangani manual.
    $stockStmt = $pdo->prepare("
        UPDATE product_sizes
        SET stock = stock - :qty_update,
            updated_at = NOW()
        WHERE id = :product_size_id
          AND is_active = 1
          AND stock >= :qty_check
    ");

    $warnings = [];
    foreach ($items as $item) {
        $label = $item['product_name'] . ' size ' . ($item['size_name'] ?? '-');
        $sizeId = (int) ($item['product_size_id'] ?? 0);

        if ($sizeId <= 0) {
            $warnings[] = $label . ' has no size record (stock not deducted)';
            continue;
        }

        $stockStmt->execute([
            'qty_update' => (int) $item['quantity'],
            'qty_check' => (int) $item['quantity'],
            'product_size_id' => $sizeId,
        ]);

        if ($stockStmt->rowCount() === 0) {
            $warnings[] = 'Stock not enough for ' . $label . ' (not deducted)';
        }
    }

    $updateOrder = $pdo->prepare("
        UPDATE orders
        SET payment_status = 'paid',
            fulfillment_status = 'processing',
            payment_verified_at = NOW(),
            payment_verified_by = :payment_verified_by,
            updated_at = NOW()
        WHERE id = :id
    ");
    $updateOrder->execute([
        'payment_verified_by' => $adminId,
        'id' => $order['id'],
    ]);

    $insertLog = $pdo->prepare("
        INSERT INTO order_status_logs (order_id, status_type, status_value, notes)
        VALUES (:order_id, :status_type, :status_value, :notes)
    ");
    $insertLog->execute([
        'order_id' => $order['id'],
        'status_type' => 'payment',
        'status_value' => 'paid',
        'notes' => 'Payment approved by admin.' . ($warnings
            ? ' WARNING: ' . implode('; ', $warnings)
            : ' Stock deducted.'),
    ]);

    $pdo->commit();

    // Email konfirmasi ke pembeli; gagal kirim tidak membatalkan approve.
    $order['payment_status'] = 'paid';
    $emailSent = sendDDInvoiceEmail($pdo, $order);

    $result = $warnings ? 'approved_warning' : 'approved';
    header('Location: order-detail.php?id=' . $order['id'] . '&success=' . $result . '&email=' . ($emailSent ? 'sent' : 'failed'));
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('[admin] approve payment failed: ' . $e->getMessage());
    http_response_code(500);
    die('Could not approve this payment: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
