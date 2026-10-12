<?php
declare(strict_types=1);

require_once __DIR__ . '/_guard.php';
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../bootstrap-paypal.php';
require_once __DIR__ . '/../includes/custom_payment.php';

use PaypalServerSdkLib\Models\AmountWithBreakdown;
use PaypalServerSdkLib\Models\CheckoutPaymentIntent;
use PaypalServerSdkLib\Models\OrderRequest;
use PaypalServerSdkLib\Models\PurchaseUnitRequest;

function cpJsonError(int $code, string $message): void
{
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit;
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') cpJsonError(405, 'POST only.');
    if (!hash_equals(ddAdminCsrf(), (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) cpJsonError(403, 'Session expired. Reload the page.');

    cpEnsureSchema($pdo);
    $payment = cpFind($pdo, trim((string)($_GET['ref'] ?? '')));
    if (!$payment) cpJsonError(404, 'Payment not found.');
    if ($payment['status'] === 'paid') cpJsonError(409, 'This payment is already paid.');

    // Nominal selalu dari database (USD), bukan dari browser.
    $amount = new AmountWithBreakdown('USD', number_format((float)$payment['amount_usd'], 2, '.', ''));
    $unit   = new PurchaseUnitRequest($amount);
    $unit->setReferenceId((string)$payment['reference']);
    $unit->setDescription(mb_substr((string)$payment['title'], 0, 120));

    $request  = new OrderRequest(CheckoutPaymentIntent::CAPTURE, [$unit]);
    $response = $paypalClient->getOrdersController()->createOrder(['body' => $request]);
    $order    = $response->getResult();

    $pdo->prepare('UPDATE custom_payments SET paypal_order_id = :o WHERE id = :id')
        ->execute(['o' => $order->getId(), 'id' => $payment['id']]);

    echo json_encode(['id' => $order->getId()]);
} catch (Throwable $e) {
    error_log('CUSTOM PAYMENT CREATE ERROR: ' . $e->getMessage());
    cpJsonError(500, 'Failed to create PayPal order.');
}
