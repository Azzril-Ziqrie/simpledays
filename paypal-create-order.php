<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/bootstrap-paypal.php';
require_once __DIR__ . '/includes/order_payment.php';

use PaypalServerSdkLib\Models\AmountWithBreakdown;
use PaypalServerSdkLib\Models\CheckoutPaymentIntent;
use PaypalServerSdkLib\Models\OrderRequest;
use PaypalServerSdkLib\Models\PurchaseUnitRequest;

function ppJsonError(int $code, string $message): void
{
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit;
}

try {
    $orderNumber = trim((string)($_GET['order'] ?? $_POST['order'] ?? ''));
    if ($orderNumber === '') ppJsonError(422, 'Order number is required.');

    $stmt = $pdo->prepare('SELECT * FROM orders WHERE order_number = :n LIMIT 1');
    $stmt->execute(['n' => $orderNumber]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) ppJsonError(404, 'Order not found.');

    if (strtolower((string)$order['payment_status']) === 'paid') ppJsonError(409, 'Order is already paid.');

    $grandTotal = (float)$order['grand_total'];
    if ($grandTotal <= 0) ppJsonError(422, 'Invalid grand total.');

    // Nominal selalu dari database, bukan dari browser.
    // USD/SGD/MYR ditagih dalam mata uangnya; IDR tidak didukung PayPal, jadi dikonversi ke USD.
    $charge = ddPaypalCharge($order);
    $amount = new AmountWithBreakdown($charge['currency'], $charge['value']);

    $purchaseUnit = new PurchaseUnitRequest($amount);
    $purchaseUnit->setReferenceId((string)$order['order_number']);
    $purchaseUnit->setDescription('Order ' . $order['order_number']);

    $orderRequest = new OrderRequest(CheckoutPaymentIntent::CAPTURE, [$purchaseUnit]);

    $apiResponse = $paypalClient->getOrdersController()->createOrder(['body' => $orderRequest]);
    $paypalOrder = $apiResponse->getResult();

    echo json_encode([
        'id'      => $paypalOrder->getId(),
        'orderID' => $paypalOrder->getId(),
        'status'  => $paypalOrder->getStatus(),
    ]);
} catch (Throwable $e) {
    error_log('PAYPAL CREATE ERROR: ' . $e->getMessage());
    ppJsonError(500, 'Failed to create PayPal order.');
}
