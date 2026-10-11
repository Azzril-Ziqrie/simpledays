<?php
/**
 * Toko ini hanya memakai SATU mata uang: USD.
 *
 * Kurs diambil otomatis dari exchangerate-api.com (EXCHANGERATE_API_KEY di config/secrets.php), di-cache 6 jam.
 * Harga produk disimpan di products.price_usd. Kalau price_usd sebuah produk masih 0,
 * harganya dihitung dari kolom lama price_idr memakai kurs di bawah. Biaya kurir (RajaOngkir)
 * dan gateway pembayaran Indonesia (Kasera) bekerja dalam IDR, jadi nominalnya dikonversi
 * lewat ddConvertFromIdr() / ddConvertToIdr() dengan kurs yang sama. Kurs bisa diubah dengan
 * define() di config/database.php, contoh:
 *   define('USD_RATE_FROM_IDR', 16000);   // 1 USD = 16.000 IDR
 */

require_once __DIR__ . '/../config/env.php';

const DD_BASE_CURRENCY = 'USD';
const DD_BASE_DECIMALS = 2;

/**
 * Berapa IDR untuk 1 USD: kurs live (ddExchangeRates), kalau tidak tersedia pakai
 * USD_RATE_FROM_IDR dari config, kalau tidak ada juga 16000.
 */
function ddRateFromIdr(): float
{
    static $rate = null;
    if ($rate !== null) return $rate;

    $live = (float)(ddExchangeRates()['IDR'] ?? 0);
    if ($live > 0) return $rate = $live;

    $configured = defined('USD_RATE_FROM_IDR') ? (float)constant('USD_RATE_FROM_IDR') : 0.0;
    return $rate = $configured > 0 ? $configured : 16000.0;
}

/** Konversi nominal IDR (ongkir kurir, harga lama) ke USD. */
function ddConvertFromIdr(float $amountIdr): float
{
    return round($amountIdr / ddRateFromIdr(), DD_BASE_DECIMALS);
}

/** Konversi nominal USD ke IDR (hanya untuk gateway pembayaran yang menagih dalam rupiah). */
function ddConvertToIdr(float $amountUsd): float
{
    return round($amountUsd * ddRateFromIdr());
}

/**
 * Format nominal dalam USD. Kode mata uang lain dipakai hanya untuk menampilkan order lama
 * (sebelum toko dijadikan USD saja) apa adanya: IDR sebagai rupiah, SGD/MYR dengan kode di depan.
 */
function ddFormatMoney(float $amount, string $currency = DD_BASE_CURRENCY): string
{
    $code = strtoupper(trim($currency));
    if ($code === 'IDR') return 'RP ' . number_format($amount, 0, ',', '.');
    if ($code !== '' && $code !== DD_BASE_CURRENCY) return $code . ' ' . number_format($amount, 2, '.', ',');
    return '$' . number_format($amount, DD_BASE_DECIMALS, '.', ',');
}

/** Mata uang sebuah order: 'IDR' hanya untuk order lama, selain itu selalu USD. */
function ddOrderCurrency(array $order): string
{
    return strtoupper(trim((string)($order['currency'] ?? ''))) === 'IDR' ? 'IDR' : DD_BASE_CURRENCY;
}

/** Harga dasar produk dalam USD; kalau price_usd kosong (0), dikonversi dari price_idr lama. */
function ddProductBasePrice(array $product): float
{
    $direct = (float)($product['price_usd'] ?? 0);
    if ($direct > 0) return $direct;
    return ddConvertFromIdr((float)($product['price_idr'] ?? 0));
}

/* ─────────────────────────────────────────────────────────────────────────────
 * Mata uang TAMPILAN (estimasi). Tagihan/order tetap USD; ini hanya memperlihatkan
 * perkiraan harga dalam mata uang lokal pengunjung memakai kurs exchangerate-api.com.
 * ──────────────────────────────────────────────────────────────────────────── */

/**
 * Semua kurs USD→X dari exchangerate-api.com (latest/USD), di-cache 6 jam. Gagal → pakai cache lama.
 * @return array<string,float>|null  null kalau belum ada API key / belum pernah berhasil
 */
function ddExchangeRates(): ?array
{
    static $rates = false;
    if ($rates !== false) return $rates;
    $rates = null;

    $key = trim(ddSecret('EXCHANGERATE_API_KEY'));
    if ($key === '') return $rates;

    $cache = sys_get_temp_dir() . '/dd-rates-usd-' . md5($key) . '.json';
    $data  = is_file($cache) ? json_decode((string)@file_get_contents($cache), true) : null;
    $age   = is_file($cache) ? time() - (int)filemtime($cache) : PHP_INT_MAX;
    if (is_array($data) && $data && $age < 21600) return $rates = $data;

    try {
        $ch = curl_init('https://v6.exchangerate-api.com/v6/' . rawurlencode($key) . '/latest/USD');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5, CURLOPT_CONNECTTIMEOUT => 3]);
        $res  = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $json = $res !== false && $code === 200 ? json_decode((string)$res, true) : null;
        if (is_array($json) && ($json['result'] ?? '') === 'success' && is_array($json['conversion_rates'] ?? null)) {
            @file_put_contents($cache, json_encode($json['conversion_rates']));
            return $rates = $json['conversion_rates'];
        }
    } catch (Throwable $e) {
        error_log('[currency] rates failed: ' . $e->getMessage());
    }
    if (is_array($data) && $data) { @touch($cache); return $rates = $data; }
    return $rates;
}

/** Mata uang tampilan pengunjung (kode 3 huruf); USD kalau belum memilih / kurs tak tersedia. */
function ddDisplayCurrency(): string
{
    $code = strtoupper((string)($_SESSION['display_currency'] ?? DD_BASE_CURRENCY));
    if ($code === DD_BASE_CURRENCY) return $code;
    $rates = ddExchangeRates();
    return ($rates && !empty($rates[$code]) && (float)$rates[$code] > 0) ? $code : DD_BASE_CURRENCY;
}

/** @return array{code:string,rate:float,decimals:int} */
function ddDisplayInfo(): array
{
    $code = ddDisplayCurrency();
    $rate = $code === DD_BASE_CURRENCY ? 1.0 : (float)(ddExchangeRates()[$code] ?? 1.0);
    $zeroDecimal = ['IDR', 'JPY', 'KRW', 'VND', 'CLP', 'HUF', 'ISK', 'UGX', 'XAF', 'XOF', 'PYG', 'RWF', 'KMF', 'DJF', 'GNF', 'VUV', 'XPF'];
    return ['code' => $code, 'rate' => $rate, 'decimals' => in_array($code, $zeroDecimal, true) ? 0 : 2];
}

/** Harga USD → teks untuk ditampilkan di toko (perkiraan bila bukan USD). */
function ddFormatDisplay(float $usd): string
{
    $d = ddDisplayInfo();
    if ($d['code'] === DD_BASE_CURRENCY) return ddFormatMoney($usd);
    $v = round($usd * $d['rate'], $d['decimals']);
    return $d['code'] . ' ' . number_format($v, $d['decimals'], '.', ',');
}

/** Snippet JS: window.ddFormatDisplay(usd) — sama dengan ddFormatDisplay() PHP. */
function ddDisplayJs(): string
{
    return 'window.DD_DISPLAY=' . json_encode(ddDisplayInfo()) . ';'
        . 'window.ddFormatDisplay=function(a){a=parseFloat(a);if(!isFinite(a))a=0;var d=window.DD_DISPLAY;'
        . 'var v=d.code==="USD"?a:Math.round(a*d.rate*Math.pow(10,d.decimals))/Math.pow(10,d.decimals);'
        . 'var t=v.toFixed(d.decimals).replace(/\B(?=(\d{3})+(?!\d))/g,",");'
        . 'return d.code==="USD"?"$"+t:d.code+" "+t;};';
}

/** IP pengunjung (publik) atau null, mis. localhost / jaringan privat. */
function ddClientIp(): ?string
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $h) {
        if (empty($_SERVER[$h])) continue;
        $ip = trim(explode(',', (string)$_SERVER[$h])[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return $ip;
    }
    return null;
}

/** Kode negara (ISO-2) → mata uang lokal, untuk negara yang umum. */
function ddCountryCurrencyMap(): array
{
    $m = [
        'ID'=>'IDR','MY'=>'MYR','SG'=>'SGD','TH'=>'THB','VN'=>'VND','PH'=>'PHP','BN'=>'BND','KH'=>'KHR','LA'=>'LAK','MM'=>'MMK',
        'JP'=>'JPY','KR'=>'KRW','CN'=>'CNY','HK'=>'HKD','TW'=>'TWD','IN'=>'INR','PK'=>'PKR','BD'=>'BDT','LK'=>'LKR','NP'=>'NPR',
        'AU'=>'AUD','NZ'=>'NZD','GB'=>'GBP','CH'=>'CHF','NO'=>'NOK','SE'=>'SEK','DK'=>'DKK','PL'=>'PLN','CZ'=>'CZK','HU'=>'HUF',
        'RO'=>'RON','TR'=>'TRY','RU'=>'RUB','UA'=>'UAH','IL'=>'ILS','SA'=>'SAR','AE'=>'AED','QA'=>'QAR','KW'=>'KWD','EG'=>'EGP',
        'ZA'=>'ZAR','NG'=>'NGN','KE'=>'KES','MA'=>'MAD','CA'=>'CAD','MX'=>'MXN','BR'=>'BRL','AR'=>'ARS','CL'=>'CLP','CO'=>'COP',
        'PE'=>'PEN','US'=>'USD',
    ];
    foreach (['AT','BE','CY','DE','EE','ES','FI','FR','GR','HR','IE','IT','LT','LU','LV','MT','NL','PT','SI','SK'] as $c) $m[$c] = 'EUR';
    return $m;
}

/**
 * Negara & mata uang lokal pengunjung dari IP (ipwho.is), dicari sekali per sesi.
 * @return array{country:string,country_code:string,currency:string}|null
 */
function ddDetectVisitorCurrency(): ?array
{
    if (isset($_SESSION['dd_geo'])) return $_SESSION['dd_geo'] ?: null;
    $geo = null;
    $ip  = ddClientIp();
    if ($ip !== null) {
        try {
            $ch = curl_init('https://ipwho.is/' . rawurlencode($ip) . '?fields=success,country,country_code');
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 3, CURLOPT_CONNECTTIMEOUT => 2]);
            $res = curl_exec($ch);
            curl_close($ch);
            $j = $res !== false ? json_decode((string)$res, true) : null;
            if (is_array($j) && !empty($j['success']) && !empty($j['country_code'])) {
                $cc  = strtoupper((string)$j['country_code']);
                $cur = ddCountryCurrencyMap()[$cc] ?? '';
                if ($cur !== '') $geo = ['country' => (string)($j['country'] ?? $cc), 'country_code' => $cc, 'currency' => $cur];
            }
        } catch (Throwable $e) {
            error_log('[currency] geo lookup failed: ' . $e->getMessage());
        }
    }
    $_SESSION['dd_geo'] = $geo ?: [];
    return $geo;
}

/** Pastikan kolom price_usd ada di tabel products (sekali per sesi). */
function ddEnsureCurrencyColumns(PDO $pdo): void
{
    if (!empty($_SESSION['dd_currency_cols_ok'])) return;
    try {
        $have = [];
        foreach ($pdo->query('SHOW COLUMNS FROM products')->fetchAll(PDO::FETCH_ASSOC) as $c) $have[$c['Field']] = true;
        if (empty($have['price_usd'])) {
            $pdo->exec('ALTER TABLE products ADD COLUMN price_usd DECIMAL(12,2) NOT NULL DEFAULT 0.00');
        }
        $_SESSION['dd_currency_cols_ok'] = true;
    } catch (Throwable $e) {
        error_log('[currency] ensure columns failed: ' . $e->getMessage());
    }
}

if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO && session_status() === PHP_SESSION_ACTIVE) {
    ddEnsureCurrencyColumns($GLOBALS['pdo']);
}
