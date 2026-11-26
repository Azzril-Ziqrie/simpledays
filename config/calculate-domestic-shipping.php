<?php
// Ongkir domestik untuk checkout. Hasil di-cache 12 jam: lihat includes/rajaongkir_cache.php.
header('Content-Type: application/json; charset=UTF-8');
ini_set('display_errors', '0');

require_once __DIR__ . '/../includes/rajaongkir_cache.php';

function roRespond(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body);
    exit;
}

/** Baris layanan dari respons RajaOngkir → bentuk seragam untuk frontend. */
function normalizeDomesticServices(array $raw): array
{
    $rows = $raw['data'] ?? ($raw['results'] ?? ($raw['rajaongkir']['results'] ?? []));
    $normalized = [];
    foreach ((array)$rows as $row) {
        if (!is_array($row)) continue;
        $cost = 0.0;
        if (isset($row['cost']) && !is_array($row['cost']))  $cost = (float)$row['cost'];
        elseif (isset($row['cost'][0]['value']))             $cost = (float)$row['cost'][0]['value'];
        elseif (isset($row['value']))                        $cost = (float)$row['value'];

        $normalized[] = [
            'service'     => $row['service'] ?? ($row['description'] ?? ($row['name'] ?? ($row['code'] ?? 'Service'))),
            'code'        => $row['code'] ?? null,
            'description' => $row['description'] ?? null,
            'cost'        => $cost,
            'currency'    => 'IDR',
            'etd'         => $row['etd'] ?? ($row['estimated'] ?? null),
        ];
    }
    return $normalized;
}

$config      = require __DIR__ . '/rajaongkir.php';
$destination = trim((string)($_POST['destination'] ?? ''));
$courier     = strtolower(trim((string)($_POST['courier'] ?? '')));
$weight      = (int)($_POST['weight'] ?? 0);

if ($destination === '' || $courier === '' || !preg_match('/^[0-9]+$/', $destination) || !preg_match('/^[a-z0-9:_-]{2,40}$/', $courier)) {
    roRespond(422, ['success' => false, 'message' => 'Destination and courier are required.']);
}
if ($weight <= 0) $weight = (int)($config['default_weight_gram'] ?? 1000);

try {
    // Parameter HARUS sama persis dengan lookupShippingCostIdr() di checkout.php supaya cek ulang saat order kena cache
    $res = rajaongkirRequest('POST', '/calculate/domestic-cost', [
        'origin'      => (int)$config['origin_city_id'],
        'destination' => $destination,
        'weight'      => $weight,
        'courier'     => $courier,
        'price'       => 'lowest',
    ], RO_TTL_COST, true);
} catch (Throwable $e) {
    error_log('[calculate-domestic-shipping] ' . $e->getMessage());
    roRespond(500, ['success' => false, 'message' => 'Failed to calculate shipping.']);
}

if ($res['data'] === null) {
    roRespond($res['code'] === 429 ? 429 : 502, ['success' => false, 'message' => $res['error']]);
}
$ok = $res['code'] >= 200 && $res['code'] < 300;
roRespond(200, [
    'success'       => $ok,
    'http_code'     => $res['code'],
    'message'       => $ok ? 'OK' : ($res['data']['meta']['message'] ?? 'Failed to calculate shipping.'),
    'base_currency' => 'IDR',
    'services'      => $ok ? normalizeDomesticServices($res['data']) : [],
]);
