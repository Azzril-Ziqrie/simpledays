<?php
// Pencarian tujuan domestik (kecamatan/kota) untuk checkout. Hasil di-cache 30 hari: lihat includes/rajaongkir_cache.php.
header('Content-Type: application/json; charset=UTF-8');
ini_set('display_errors', '0');

require_once __DIR__ . '/../includes/rajaongkir_cache.php';

function roRespond(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body);
    exit;
}

// Kata kunci dinormalkan supaya "Surabaya", " surabaya " dan "SURABAYA" memakai cache yang sama
$search = mb_strtolower(preg_replace('/\s+/u', ' ', trim((string)($_GET['search'] ?? ''))));
$limit  = max(1, min(20, (int)($_GET['limit'] ?? 10)));
$offset = max(0, (int)($_GET['offset'] ?? 0));

if (mb_strlen($search) < 3) {
    roRespond(422, ['success' => false, 'message' => 'Type at least 3 characters.']);
}

try {
    $res = rajaongkirRequest('GET', '/destination/domestic-destination',
        ['search' => $search, 'limit' => $limit, 'offset' => $offset], RO_TTL_DESTINATION, true);
} catch (Throwable $e) {
    error_log('[get-domestic-destinations] ' . $e->getMessage());
    roRespond(500, ['success' => false, 'message' => 'Could not load destinations.']);
}

if ($res['data'] === null) {
    roRespond($res['code'] === 429 ? 429 : 502, ['success' => false, 'message' => $res['error']]);
}

// Bentuk respons sama seperti sebelumnya: frontend membaca result.raw.data
roRespond(200, [
    'success'   => $res['code'] >= 200 && $res['code'] < 300,
    'http_code' => $res['code'],
    'message'   => $res['data']['meta']['message'] ?? ($res['data']['message'] ?? 'Response received'),
    'raw'       => ['data' => $res['data']['data'] ?? []],
]);
