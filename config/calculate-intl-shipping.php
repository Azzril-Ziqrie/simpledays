<?php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/intl_shipping.php';

$destination = trim((string)($_POST['destination'] ?? ''));
$courier     = trim((string)($_POST['courier'] ?? ''));
$weight      = (int)($_POST['weight'] ?? 0);

if ($destination === '' || $courier === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Destination and courier are required.']);
    exit;
}

try {
    $services = intlShippingServices($destination, $courier, $weight);
} catch (Throwable $e) {
    http_response_code(502);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
if ($services === null) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Shipping to this destination with this courier is not available.']);
    exit;
}

echo json_encode([
    'success'       => true,
    'http_code'     => 200,
    'message'       => 'OK',
    'base_currency' => 'IDR',
    'services'      => $services,
], JSON_PRETTY_PRINT);
