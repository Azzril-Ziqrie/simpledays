<?php
/**
 * Pemanggil RajaOngkir (Komerce) dengan cache + batas request per IP, supaya kuota API hemat.
 *
 * - Pencarian tujuan (kecamatan/kota) hampir tidak pernah berubah → cache 30 hari.
 * - Ongkir per (tujuan, kurir, berat) → cache 12 jam. Saat order dibuat, server mengecek ulang
 *   ongkir dan biasanya kena cache dari pratinjau, jadi tidak memakai kuota lagi.
 * - Hanya respons sukses (HTTP 2xx) yang disimpan; error selalu dicoba ulang.
 * - Endpoint publik (config/get-domestic-destinations.php, calculate-domestic-shipping.php)
 *   dibatasi per IP supaya bot tidak bisa menghabiskan kuota.
 *
 * Cache disimpan sebagai file JSON di folder temp server (sama seperti kurs di currency_helper.php).
 */

const RO_TTL_DESTINATION = 2592000; // 30 hari
const RO_TTL_COST        = 43200;   // 12 jam
const RO_RATE_MAX        = 60;      // request API sungguhan (bukan dari cache) per IP ...
const RO_RATE_WINDOW     = 600;     // ... per 10 menit

function roCacheDir(): string
{
    // Per user proses PHP: folder temp bisa dipakai bersama (mis. CLI vs Apache), dan folder 0700 milik user lain tidak bisa ditulis
    $owner = function_exists('posix_geteuid') ? (string)posix_geteuid() : preg_replace('/\W/', '', get_current_user());
    $dir = sys_get_temp_dir() . '/sd-rajaongkir-' . $owner;
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    return $dir;
}

function roCacheFile(string $method, string $path, array $params): string
{
    ksort($params);
    return roCacheDir() . '/' . hash('sha256', $method . ' ' . $path . ' ' . json_encode($params)) . '.json';
}

/**
 * Apakah IP ini masih boleh memicu request API sungguhan. Hit cache tidak dihitung.
 * Jendela tetap per IP, disimpan di folder temp.
 */
function roRateLimitAllows(): bool
{
    $ip   = (string)($_SERVER['REMOTE_ADDR'] ?? 'cli');
    $file = roCacheDir() . '/rate-' . hash('sha256', $ip) . '.json';
    $now  = time();
    $fh   = @fopen($file, 'c+');
    if (!$fh) return true; // gagal menulis file → jangan blokir pembeli
    flock($fh, LOCK_EX);
    $state = json_decode((string)stream_get_contents($fh), true);
    if (!is_array($state) || $now - (int)($state['start'] ?? 0) >= RO_RATE_WINDOW) {
        $state = ['start' => $now, 'count' => 0];
    }
    $allowed = $state['count'] < RO_RATE_MAX;
    if ($allowed) $state['count']++;
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, json_encode($state));
    flock($fh, LOCK_UN);
    fclose($fh);
    return $allowed;
}

/**
 * Panggil RajaOngkir, pakai cache kalau ada.
 *
 * @param string $method 'GET' atau 'POST'
 * @param array  $params query (GET) atau body form (POST)
 * @param bool   $limitByIp true untuk endpoint publik: tolak kalau IP melewati batas
 * @return array{code: int, data: ?array, error: string, cached: bool}
 *   code 429 = batas request IP tercapai, 0 = gagal konek.
 */
function rajaongkirRequest(string $method, string $path, array $params, int $ttl, bool $limitByIp = false): array
{
    $file = roCacheFile($method, $path, $params);
    if (is_file($file) && time() - (int)filemtime($file) < $ttl) {
        $cached = json_decode((string)@file_get_contents($file), true);
        if (is_array($cached)) return ['code' => 200, 'data' => $cached, 'error' => '', 'cached' => true];
    }

    if ($limitByIp && !roRateLimitAllows()) {
        return ['code' => 429, 'data' => null, 'error' => 'Too many requests. Please wait a few minutes and try again.', 'cached' => false];
    }

    $config = require __DIR__ . '/../config/rajaongkir.php';
    $url    = rtrim((string)$config['base_url'], '/') . $path;
    $headers = ['key: ' . $config['api_key'], 'Accept: application/json'];

    if ($method === 'GET') {
        $ch = curl_init($url . '?' . http_build_query($params));
    } else {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $response = curl_exec($ch);
    $code     = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        error_log('[rajaongkir] ' . $path . ' connect failed: ' . $curlErr);
        return ['code' => 0, 'data' => null, 'error' => 'Failed to connect to the shipping service.', 'cached' => false];
    }
    $data = json_decode((string)$response, true);
    if (!is_array($data)) {
        error_log('[rajaongkir] ' . $path . ' invalid JSON (HTTP ' . $code . ')');
        return ['code' => $code ?: 502, 'data' => null, 'error' => 'Invalid response from the shipping service.', 'cached' => false];
    }

    if ($code >= 200 && $code < 300) {
        @file_put_contents($file, json_encode($data), LOCK_EX);
    } else {
        error_log('[rajaongkir] ' . $path . ' HTTP ' . $code . ': ' . substr((string)$response, 0, 300));
    }
    return ['code' => $code, 'data' => $data, 'error' => '', 'cached' => false];
}
