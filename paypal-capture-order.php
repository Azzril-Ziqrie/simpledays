<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/bootstrap-paypal.php';
require_once __DIR__ . '/includes/email_helpers.php';
require_once __DIR__ . '/includes/order_payment.php';

function ppJsonOut(int $code, array $body): void
{
    http_response_code($code);
    echo json_encode($body);
    exit;
}

try {
    $input = json_decode((string)file_get_contents('php://input'), true) ?: [];

    $orderNumber   = trim((string)($input['order_number'] ?? ''));
    $paypalOrderId = trim((string)($input['paypal_order_id'] ?? ''));
    if ($orderNumber === '' || $paypalOrderId === '') {
        ppJsonOut(422, ['status' => 'ERROR', 'error' => 'Order number and PayPal order ID are required.']);
    }

    $stmt = $pdo->prepare('SELECT * FROM orders WHERE order_number = :n LIMIT 1');
    $stmt->execute(['n' => $orderNumber]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) ppJsonOut(404, ['status' => 'ERROR', 'error' => 'Order not found.']);

    if (strtolower((string)$order['payment_status']) === 'paid') {
        ppJsonOut(200, ['status' => 'COMPLETED', 'message' => 'Order already paid.']);
    }

    $apiResponse = $paypalClient->getOrdersController()->captureOrder([
        'id'     => $paypalOrderId,
        'prefer' => 'return=representation',
    ]);
    $result = $apiResponse->getResult();

    $unit      = ($result->getPurchaseUnits() ?? [])[0] ?? null;
    $capture   = $unit && $unit->getPayments() ? (($unit->getPayments()->getCaptures() ?? [])[0] ?? null) : null;
    $amount    = $capture ? $capture->getAmount() : null;

    // Jangan percaya begitu saja: pastikan transaksi ini memang untuk order ini dan nominalnya pas.
    $problem = ddValidatePaypalCapture(
        $order,
        $result->getStatus(),
        $unit ? $unit->getReferenceId() : null,
        $capture ? $capture->getStatus() : null,
        $amount ? $amount->getValue() : null,
        $amount ? $amount->getCurrencyCode() : null
    );
    if ($problem !== null) {
        error_log('PAYPAL CAPTURE REJECTED order ' . $orderNumber . ': ' . $problem . ' (PayPal order ' . $paypalOrderId . ')');
        ppJsonOut(422, ['status' => 'ERROR', 'error' => $problem]);
    }

    $captureId = $capture->getId() ?: $paypalOrderId;
    $paid = ddMarkOrderPaid(
        $pdo,
        (int)$order['id'],
        'paypal',
        $captureId,
        'Paid via PayPal. PayPal Order ID: ' . $paypalOrderId . ', Capture ID: ' . $captureId
    );

    $emailSent = false;
    if (!$paid['already_paid']) {
        $refresh = $pdo->prepare('SELECT * FROM orders WHERE id = :id LIMIT 1');
        $refresh->execute(['id' => $order['id']]);
        $emailSent = sendDDInvoiceEmail($pdo, $refresh->fetch(PDO::FETCH_ASSOC) ?: $order);
    }

    ppJsonOut(200, [
        'status'      => 'COMPLETED',
        'message'     => 'Payment captured successfully.',
        'invoiceMail' => $emailSent ? 'sent' : 'failed',
    ]);
} catch (Throwable $e) {
    error_log('PAYPAL CAPTURE ERROR: ' . $e->getMessage());
    ppJsonOut(500, ['status' => 'ERROR', 'error' => 'Failed to capture PayPal payment.']);
}
