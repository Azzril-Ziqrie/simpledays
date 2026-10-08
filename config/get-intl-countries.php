<?php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/intl_shipping.php';

$search = trim((string)($_GET['search'] ?? ''));
$limit  = (int)($_GET['limit'] ?? 10);
if ($limit <= 0) $limit = 10;

// Bentuk respons disamakan dengan endpoint lama: frontend membaca result.raw.data
try {
    $countries = intlSearchCountries($search, $limit);
} catch (Throwable $e) {
    http_response_code(502);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

echo json_encode([
    'success'   => true,
    'http_code' => 200,
    'message'   => 'OK',
    'raw'       => ['data' => $countries],
], JSON_PRETTY_PRINT);
