<?php
/*
 * Ongkir internasional via API RajaOngkir (Komerce) — sama dengan domestik, memakai berat produk
 * (products.weight_gram × qty) dan origin di config/rajaongkir.php.
 *
 *   GET  /destination/international-destination?search=...   → daftar negara
 *   POST /calculate/international-cost                        → layanan + ongkir (IDR)
 *
 * Kurir yang melayani pengiriman internasional di akun ini: JNE dan POS Indonesia
 * (lion / expedito / ray dicoba dan mengembalikan 404).
 */

function intlConfig(): array
{
    static $config = null;
    if ($config === null) $config = require __DIR__ . '/rajaongkir.php';
    return $config;
}

/** Kurir internasional yang ditawarkan (kode RajaOngkir => label). Hanya POS Indonesia. */
function intlCouriers(): array
{
    return ['pos' => 'POS Indonesia'];
}

/** Panggil API RajaOngkir; lempar RuntimeException dengan pesan yang layak ditampilkan kalau gagal. */
function intlApi(string $method, string $path, ?array $fields = null): array
{
    $config = intlConfig();
    $ch = curl_init(rtrim((string)$config['base_url'], '/') . $path);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER     => ['key: ' . $config['api_key'], 'Accept: application/json'],
    ];
    if (strtoupper($method) === 'POST') {
        $opts[CURLOPT_POST]       = true;
        $opts[CURLOPT_POSTFIELDS] = http_build_query((array)$fields);
        $opts[CURLOPT_HTTPHEADER][] = 'Content-Type: application/x-www-form-urlencoded';
    }
    curl_setopt_array($ch, $opts);
    $raw  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($raw === false) throw new RuntimeException('Could not reach the shipping service. Please try again.');
    $data = json_decode((string)$raw, true);
    if (!is_array($data)) throw new RuntimeException('Shipping service returned an invalid response.');
    if ($code < 200 || $code >= 300) {
        $msg = (string)($data['meta']['message'] ?? 'Shipping service error.');
        error_log('[intl-shipping] ' . $path . ' HTTP ' . $code . ': ' . $msg . ($err ? ' / ' . $err : ''));
        throw new RuntimeException($msg);
    }
    return $data;
}

/** Cache JSON sederhana di folder temp (TTL detik). */
function intlCacheGet(string $key, int $ttl)
{
    $file = sys_get_temp_dir() . '/dd-intl-' . md5($key) . '.json';
    if (is_file($file) && (time() - (int)filemtime($file)) < $ttl) {
        $d = json_decode((string)file_get_contents($file), true);
        if ($d !== null) return $d;
    }
    return null;
}

function intlCacheSet(string $key, $value): void
{
    @file_put_contents(sys_get_temp_dir() . '/dd-intl-' . md5($key) . '.json', json_encode($value));
}

/** Cari negara; hasil berbentuk [{country_id, country_name}]. API menolak pencarian kosong. */
function intlSearchCountries(string $search, int $limit = 10): array
{
    $search = trim($search);
    if (mb_strlen($search) < 2) return [];
    $limit = max(1, min($limit, 50));

    $key = 'search:' . strtolower($search) . ':' . $limit;
    $cached = intlCacheGet($key, 600);
    if (is_array($cached)) return $cached;

    $res  = intlApi('GET', '/destination/international-destination?search=' . urlencode($search) . '&limit=' . $limit);
    $rows = [];
    foreach (($res['data'] ?? []) as $r) {
        if (isset($r['country_id'], $r['country_name'])) {
            $rows[] = ['country_id' => (string)$r['country_id'], 'country_name' => (string)$r['country_name']];
        }
    }
    intlCacheSet($key, $rows);
    return $rows;
}

/** Semua negara (id => nama), dikumpulkan dari pencarian per huruf vokal lalu di-cache 7 hari. */
function intlAllCountries(): array
{
    $cached = intlCacheGet('all-countries', 7 * 86400);
    if (is_array($cached) && $cached) return $cached;

    $all = [];
    try {
        foreach (['a', 'e', 'i', 'o', 'u'] as $v) {
            $res = intlApi('GET', '/destination/international-destination?search=' . $v . '&limit=500');
            foreach (($res['data'] ?? []) as $r) {
                if (isset($r['country_id'], $r['country_name'])) $all[(string)$r['country_id']] = (string)$r['country_name'];
            }
        }
    } catch (Throwable $e) {
        error_log('[intl-shipping] country list failed: ' . $e->getMessage());
    }
    if ($all) intlCacheSet('all-countries', $all);
    return $all;
}

/** Negara berdasarkan id RajaOngkir → ['code' => id, 'name' => nama]; null kalau id tidak dikenal. */
function intlFindCountry(string $id): ?array
{
    $id = trim($id);
    if ($id === '' || !ctype_digit($id)) return null;
    $name = intlAllCountries()[$id] ?? null;
    return $name !== null ? ['code' => $id, 'name' => $name] : null;
}

/**
 * Daftar layanan + ongkir (IDR) ke satu negara, dihitung dari berat paket (gram).
 * Return null kalau negara/kurir tidak valid. Lempar RuntimeException kalau API menolak
 * (mis. berat melebihi batas) — pesannya aman ditampilkan ke pembeli.
 */
function intlShippingServices(string $countryId, string $courier, int $weightGram): ?array
{
    $courier = strtolower(trim($courier));
    if (!isset(intlCouriers()[$courier]) || !intlFindCountry($countryId)) return null;

    $config = intlConfig();
    if ($weightGram <= 0) $weightGram = (int)($config['default_weight_gram'] ?? 1000);

    $res = intlApi('POST', '/calculate/international-cost', [
        'origin'      => (int)$config['origin_city_id'],
        'destination' => $countryId,
        'weight'      => $weightGram,
        'courier'     => $courier,
        'price'       => 'lowest',
    ]);

    // Jenis "Document" khusus surat/dokumen, bukan barang → tidak ditawarkan.
    $rows = array_values(array_filter($res['data'] ?? [], fn($r) => strcasecmp((string)($r['description'] ?? ''), 'Document') !== 0));

    $counts = [];
    foreach ($rows as $r) $counts[(string)($r['service'] ?? '')] = ($counts[(string)($r['service'] ?? '')] ?? 0) + 1;

    $out = [];
    foreach ($rows as $r) {
        $service = (string)($r['service'] ?? 'Service');
        $desc    = trim((string)($r['description'] ?? ''));
        // Beberapa layanan bernama sama → bedakan dengan deskripsinya supaya label unik.
        if (($counts[$service] ?? 0) > 1 && $desc !== '') $service .= ' (' . $desc . ')';
        $out[] = [
            'service'     => $service,
            'code'        => $r['code'] ?? $courier,
            'description' => $r['description'] ?? null,
            'cost'        => (float)($r['cost'] ?? 0),
            'currency'    => 'IDR',
            'etd'         => $r['etd'] ?? null,
        ];
    }
    return $out;
}
