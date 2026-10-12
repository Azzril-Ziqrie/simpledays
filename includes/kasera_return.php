<?php
/**
 * Verifikasi pembayaran Kasera saat browser pembeli kembali ke index.php?kasera_verify=1.
 *
 * Webhook Kasera bisa lebih dulu menandai order "paid" sebelum pembeli diarahkan balik;
 * karena itu order yang sudah paid tetap diarahkan ke pop-up sukses (tanpa verifikasi
 * ulang dan tanpa kirim email ulang).
 *
 * Membutuhkan: kaseraGetTransaction() (config/kasera_client.php) dan
 * sendDDInvoiceEmail() (includes/email_helpers.php).
 */

const KASERA_PAID_STATUSES = ['succeeded', 'paid'];

function redirectToPaymentPopup(string $orderNumber, bool $emailSent): void
{
    header('Location: index.php?payment_uploaded=1&invoice_email=' . ($emailSent ? 'sent' : 'failed') . '&order=' . urlencode($orderNumber));
    exit;
}

function findOrderByNumber(PDO $pdo, string $orderNumber): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE order_number = :on LIMIT 1');
    $stmt->execute(['on' => $orderNumber]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** Tandai order paid. Return true hanya kalau baris benar-benar berubah (bukan double-process). */
function markOrderPaid(PDO $pdo, array $order): bool
{
    $pdo->beginTransaction();

    $update = $pdo->prepare('UPDATE orders SET payment_status = :status, updated_at = NOW() WHERE id = :id');
    $update->execute(['status' => 'paid', 'id' => $order['id']]);
    $wasJustPaid = $update->rowCount() > 0;

    if ($wasJustPaid) {
        $pdo->prepare(
            'INSERT INTO order_status_logs (order_id, status_type, status_value, notes)
             VALUES (:oid, :st, :sv, :n)'
        )->execute([
            'oid' => $order['id'],
            'st'  => 'payment',
            'sv'  => 'paid',
            'n'   => 'Verified via Kasera return redirect on index.',
        ]);
    }

    $pdo->commit();
    return $wasJustPaid;
}

function handleKaseraReturn(PDO $pdo): void
{
    if (($_GET['kasera_verify'] ?? '') !== '1') {
        return;
    }

    $orderNumber      = trim((string)($_GET['order'] ?? ''));
    $paymentRequestId = trim((string)($_GET['id'] ?? ''));
    if ($orderNumber === '' || $paymentRequestId === '') {
        return;
    }

    $order = findOrderByNumber($pdo, $orderNumber);
    if (!$order) {
        return;
    }

    if ($order['payment_status'] === 'paid') {
        redirectToPaymentPopup($orderNumber, true);
    }

    try {
        $transaction = kaseraGetTransaction($paymentRequestId);
        $status      = strtolower((string)($transaction['status'] ?? ''));

        // Belum paid: biarkan halaman render normal; webhook atau refresh berikutnya yang menuntaskan.
        if (!in_array($status, KASERA_PAID_STATUSES, true)) {
            return;
        }

        $wasJustPaid = markOrderPaid($pdo, $order);
        $emailSent   = $wasJustPaid ? sendDDInvoiceEmail($pdo, $order) : true;
        redirectToPaymentPopup($orderNumber, $emailSent);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[kasera index verify] failed: ' . $e->getMessage());
    }
}
