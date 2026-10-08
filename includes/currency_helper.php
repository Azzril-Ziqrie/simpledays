<?php
/**
 * Mata uang yang didukung toko: IDR, USD, SGD, MYR.
 *
 * Harga produk disimpan per mata uang (products.price_idr / price_usd / price_sgd / price_myr).
 * Kalau harga SGD/MYR/USD sebuah produk masih 0, harganya dihitung dari harga IDR memakai kurs
 * di bawah. Kurs bisa diubah dengan define() di config/database.php, contoh:
 *   define('SGD_RATE_FROM_IDR', 12500);   // 1 SGD = 12.500 IDR
 */

const DD_CURRENCIES = [
    'IDR' => ['symbol' => 'RP ', 'decimals' => 0, 'column' => 'price_idr', 'name' => 'Indonesian Rupiah'],
    'USD' => ['symbol' => '$',   'decimals' => 2, 'column' => 'price_usd', 'name' => 'US Dollar'],
    'SGD' => ['symbol' => 'S$',  'decimals' => 2, 'column' => 'price_sgd', 'name' => 'Singapore Dollar'],
    'MYR' => ['symbol' => 'RM ', 'decimals' => 2, 'column' => 'price_myr', 'name' => 'Malaysian Ringgit'],
];

function ddCurrencyCodes(): array
{
    return array_keys(DD_CURRENCIES);
}

function ddNormalizeCurrency($currency, string $default = 'IDR'): string
{
    $cur = strtoupper(trim((string)$currency));
    return isset(DD_CURRENCIES[$cur]) ? $cur : $default;
}

/** Berapa IDR untuk 1 unit mata uang ini (IDR = 1). */
function ddRateFromIdr(string $currency): float
{
    $defaults = ['USD' => 16000.0, 'SGD' => 12500.0, 'MYR' => 3700.0];
    if ($currency === 'IDR' || !isset($defaults[$currency])) return 1.0;
    $const = $currency . '_RATE_FROM_IDR';
    $rate  = defined($const) ? (float)constant($const) : $defaults[$currency];
    return $rate > 0 ? $rate : $defaults[$currency];
}

function ddConvertFromIdr(float $amountIdr, string $currency): float
{
    if ($currency === 'IDR') return $amountIdr;
    return round($amountIdr / ddRateFromIdr($currency), DD_CURRENCIES[$currency]['decimals']);
}

function ddFormatMoney(float $amount, string $currency = 'IDR'): string
{
    $m = DD_CURRENCIES[ddNormalizeCurrency($currency)];
    if ($m['decimals'] === 0) return $m['symbol'] . number_format($amount, 0, ',', '.');
    return $m['symbol'] . number_format($amount, $m['decimals'], '.', ',');
}

/** Harga dasar produk di mata uang tertentu; kalau kolomnya kosong (0), dikonversi dari IDR. */
function ddProductBasePrice(array $product, string $currency = 'IDR'): float
{
    $currency = ddNormalizeCurrency($currency);
    $direct   = (float)($product[DD_CURRENCIES[$currency]['column']] ?? 0);
    if ($direct > 0 || $currency === 'IDR') return $direct;
    return ddConvertFromIdr((float)($product['price_idr'] ?? 0), $currency);
}

/** Snippet JS: window.ddFormatMoney(amount, currency) — sama persis dengan ddFormatMoney() PHP. */
function ddCurrencyJs(): string
{
    $map = [];
    foreach (DD_CURRENCIES as $code => $m) $map[$code] = ['s' => $m['symbol'], 'd' => $m['decimals']];
    return 'window.DD_CUR=' . json_encode($map) . ';'
        . 'window.ddFormatMoney=function(a,c){var m=window.DD_CUR[c]||window.DD_CUR.IDR;a=parseFloat(a);if(!isFinite(a))a=0;'
        . 'if(m.d===0)return m.s+Math.round(a).toLocaleString("id-ID").replace(/,/g,".");'
        . 'return m.s+a.toFixed(m.d).replace(/\B(?=(\d{3})+(?!\d))/g,",");};';
}

/** Pastikan kolom harga SGD/MYR/USD ada di tabel products (sekali per sesi). */
function ddEnsureCurrencyColumns(PDO $pdo): void
{
    if (!empty($_SESSION['dd_currency_cols_ok'])) return;
    try {
        $have = [];
        foreach ($pdo->query('SHOW COLUMNS FROM products')->fetchAll(PDO::FETCH_ASSOC) as $c) $have[$c['Field']] = true;
        foreach (['price_usd', 'price_sgd', 'price_myr'] as $col) {
            if (empty($have[$col])) $pdo->exec("ALTER TABLE products ADD COLUMN {$col} DECIMAL(12,2) NOT NULL DEFAULT 0.00");
        }
        $_SESSION['dd_currency_cols_ok'] = true;
    } catch (Throwable $e) {
        error_log('[currency] ensure columns failed: ' . $e->getMessage());
    }
}

if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO && session_status() === PHP_SESSION_ACTIVE) {
    ddEnsureCurrencyColumns($GLOBALS['pdo']);
}
