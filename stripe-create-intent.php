<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/stripe_helper.php';

function stJson(int $code, array $body): void
{
    http_response_code($code);
    echo json_encode($body);
    exit;
}

try {
    if (!STRIPE_ENABLED) stJson(503, ['error' => 'Apple Pay / Google Pay is not available right now.']);

    $input       = json_decode((string)file_get_contents('php://input'), true) ?: [];
    $orderNumber = trim((string)($input['order'] ?? ''));
    if ($orderNumber === '') stJson(422, ['error' => 'Order number is required.']);

    $stmt = $pdo->prepare('SELECT * FROM orders WHERE order_number = :n LIMIT 1');
    $stmt->execute(['n' => $orderNumber]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) stJson(404, ['error' => 'Order not found.']);
    if (strtolower((string)$order['payment_status']) === 'paid') stJson(409, ['error' => 'Order is already paid.']);

    $cents = ddStripeAmountCents($order);
    if ($cents < 50) stJson(422, ['error' => 'Invalid order total.']);

    $pi = stripeRequest('POST', 'payment_intents', [
        'amount'      => $cents,
        'currency'    => 'usd',
        'description' => 'Order ' . $order['order_number'],
        'automatic_payment_methods' => ['enabled' => 'true'],
        'metadata'    => ['order_number' => (string)$order['order_number']],
    ]);

    stJson(200, ['client_secret' => $pi['client_secret']]);
} catch (Throwable $e) {
    error_log('[stripe] create-intent failed: ' . $e->getMessage());
    stJson(500, ['error' => 'Could not start the payment. Please try again.']);
}
