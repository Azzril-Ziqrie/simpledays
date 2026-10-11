<?php
declare(strict_types=1);

require_once __DIR__ . '/currency_helper.php';

/**
 * Tandai order sebagai LUNAS secara atomik dan idempotent.
 * Meniru admin/approve-payment.php: kurangi stok, set payment_status = paid,
 * fulfillment_status = processing, dan catat di order_status_logs.
 *
 * Beda dengan approve-payment: kalau stok tidak cukup, order TETAP ditandai lunas
 * (uang sudah masuk) dan peringatan dicatat di log supaya admin menangani manual,
 * bukan membuang catatan pembayaran.
 *
 * @return array{already_paid: bool, warnings: string[]}
 */
function ddMarkOrderPaid(PDO $pdo, int $orderId, string $provider, ?string $reference, string $logNote): array
{
    $warnings = [];

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = :id LIMIT 1 FOR UPDATE');
        $stmt->execute(['id' => $orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) throw new RuntimeException('Order not found.');

        if (strtolower((string)$order['payment_status']) === 'paid') {
            $pdo->rollBack();
            return ['already_paid' => true, 'warnings' => []];
        }

        $itemStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = :oid ORDER BY id ASC');
        $itemStmt->execute(['oid' => $orderId]);

        $stockStmt = $pdo->prepare(
            'UPDATE product_sizes SET stock = stock - :q1, updated_at = NOW()
             WHERE id = :psid AND is_active = 1 AND stock >= :q2'
        );
        foreach ($itemStmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $qty = (int)$item['quantity'];
            $stockStmt->execute(['q1' => $qty, 'q2' => $qty, 'psid' => (int)$item['product_size_id']]);
            if ($stockStmt->rowCount() === 0) {
                $warnings[] = 'Stock not enough for ' . $item['product_name'] . ' size ' . ($item['size_name'] ?? '-') . ' (not deducted)';
            }
        }

        $pdo->prepare(
            "UPDATE orders
             SET payment_status = 'paid', fulfillment_status = 'processing',
                 payment_provider = :prov, payment_reference = :ref, updated_at = NOW()
             WHERE id = :id"
        )->execute(['prov' => $provider, 'ref' => $reference, 'id' => $orderId]);

        $note = $logNote . ($warnings ? ' | WARNING: ' . implode('; ', $warnings) : '');
        $pdo->prepare(
            'INSERT INTO order_status_logs (order_id, status_type, status_value, notes)
             VALUES (:oid, :st, :sv, :n)'
        )->execute(['oid' => $orderId, 'st' => 'payment', 'sv' => 'paid', 'n' => $note]);

        $pdo->commit();
        return ['already_paid' => false, 'warnings' => $warnings];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/**
 * Mata uang & nominal yang ditagihkan PayPal untuk sebuah order: selalu USD.
 * Order lama bermata uang IDR (sebelum toko USD saja) dikonversi memakai kurs ddRateFromIdr.
 * @return array{currency: string, value: string}
 */
function ddPaypalCharge(array $order): array
{
    $total = (float)$order['grand_total'];
    if (ddOrderCurrency($order) === 'IDR') $total = ddConvertFromIdr($total);
    return ['currency' => 'USD', 'value' => number_format($total, 2, '.', '')];
}

/**
 * Validasi hasil capture PayPal terhadap order kita. Mengembalikan pesan error, atau null kalau valid.
 * Mencegah capture milik order lain / nominal salah dipakai untuk melunasi order ini.
 */
function ddValidatePaypalCapture(
    array $order,
    ?string $orderStatus,
    ?string $referenceId,
    ?string $captureStatus,
    ?string $amountValue,
    ?string $amountCurrency
): ?string {
    if (strtoupper((string)$orderStatus) !== 'COMPLETED') return 'PayPal order is not completed.';
    if ((string)$referenceId !== (string)$order['order_number']) return 'PayPal transaction does not belong to this order.';
    if (strtoupper((string)$captureStatus) !== 'COMPLETED') return 'PayPal capture is not completed.';
    // Mata uang & nominal yang diharapkan dihitung ulang dari order di database (lihat ddPaypalCharge).
    $expected = ddPaypalCharge($order);
    if (strtoupper((string)$amountCurrency) !== $expected['currency']) return 'PayPal currency does not match the order.';
    if (number_format((float)$amountValue, 2, '.', '') !== $expected['value']) {
        return 'PayPal amount does not match the order total.';
    }
    return null;
}
