<?php
declare(strict_types=1);

require_once __DIR__ . '/_guard.php';
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../bootstrap-paypal.php';
require_once __DIR__ . '/../includes/custom_payment.php';

function cpJsonOut(int $code, array $body): void
{
    http_response_code($code);
    echo json_encode($body);
    exit;
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') cpJsonOut(405, ['status' => 'ERROR', 'error' => 'POST only.']);
    if (!hash_equals(ddAdminCsrf(), (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) {
        cpJsonOut(403, ['status' => 'ERROR', 'error' => 'Session expired. Reload the page.']);
    }

    $input    = json_decode((string)file_get_contents('php://input'), true) ?: [];
    $ref      = trim((string)($input['reference'] ?? ''));
    $ppOrder  = trim((string)($input['paypal_order_id'] ?? ''));
    if ($ref === '' || $ppOrder === '') cpJsonOut(422, ['status' => 'ERROR', 'error' => 'Reference and PayPal order ID are required.']);

    cpEnsureSchema($pdo);
    $payment = cpFind($pdo, $ref);
    if (!$payment) cpJsonOut(404, ['status' => 'ERROR', 'error' => 'Payment not found.']);
    if ($payment['status'] === 'paid') cpJsonOut(200, ['status' => 'COMPLETED', 'message' => 'Already paid.']);

    // Hanya order PayPal yang memang dibuat untuk pembayaran ini.
    if ((string)$payment['paypal_order_id'] !== $ppOrder) {
        cpJsonOut(422, ['status' => 'ERROR', 'error' => 'PayPal order does not belong to this payment.']);
    }

    $result  = $paypalClient->getOrdersController()->captureOrder(['id' => $ppOrder, 'prefer' => 'return=representation'])->getResult();
    $unit    = ($result->getPurchaseUnits() ?? [])[0] ?? null;
    $capture = $unit && $unit->getPayments() ? (($unit->getPayments()->getCaptures() ?? [])[0] ?? null) : null;
    $amount  = $capture ? $capture->getAmount() : null;

    $problem = null;
    if (strtoupper((string)$result->getStatus()) !== 'COMPLETED' || !$capture || strtoupper((string)$capture->getStatus()) !== 'COMPLETED') {
        $problem = 'PayPal payment is not completed.';
    } elseif ((string)($unit ? $unit->getReferenceId() : '') !== (string)$payment['reference']) {
        $problem = 'PayPal transaction does not belong to this payment.';
    } elseif (!$amount || strtoupper((string)$amount->getCurrencyCode()) !== 'USD'
        || number_format((float)$amount->getValue(), 2, '.', '') !== number_format((float)$payment['amount_usd'], 2, '.', '')) {
        $problem = 'PayPal amount does not match this payment.';
    }
    if ($problem !== null) {
        error_log('CUSTOM PAYMENT CAPTURE REJECTED ' . $ref . ': ' . $problem);
        cpJsonOut(422, ['status' => 'ERROR', 'error' => $problem]);
    }

    $pdo->prepare(
        "UPDATE custom_payments SET status = 'paid', paypal_capture_id = :c, paid_at = NOW()
         WHERE id = :id AND status <> 'paid'"
    )->execute(['c' => $capture->getId(), 'id' => $payment['id']]);

    cpJsonOut(200, ['status' => 'COMPLETED', 'message' => 'Payment captured successfully.']);
} catch (Throwable $e) {
    error_log('CUSTOM PAYMENT CAPTURE ERROR: ' . $e->getMessage());
    cpJsonOut(500, ['status' => 'ERROR', 'error' => 'Failed to capture PayPal payment.']);
}
