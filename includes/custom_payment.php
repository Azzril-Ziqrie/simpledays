<?php
declare(strict_types=1);

/**
 * Pembayaran manual dari dashboard admin ("Payments"): admin memasukkan nominal
 * (USD atau mata uang lain, dikonversi ke USD), lalu membayar lewat PayPal.
 *
 * Nominal USD dihitung di SERVER saat pembayaran dibuat dan disimpan; PayPal selalu
 * ditagih dalam USD dari nilai tersimpan itu, bukan dari kiriman browser.
 */

require_once __DIR__ . '/db_schema.php';
require_once __DIR__ . '/currency_helper.php';

const CP_MAX_USD = 100000.00;

/** Detail pembayar (opsional): kolom → panjang maksimum. */
const CP_PAYER_FIELDS = [
    'payer_name'  => 150,
    'payer_email' => 150,
    'payer_phone' => 50,
];

function cpEnsureSchema(PDO $pdo): void
{
    static $done = false;
    if ($done) return;

    if (!tableExists($pdo, 'custom_payments')) {
        $pdo->exec(
            "CREATE TABLE custom_payments (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                reference VARCHAR(32) NOT NULL,
                title VARCHAR(190) NOT NULL,
                amount_usd DECIMAL(12,2) NOT NULL,
                source_amount DECIMAL(14,2) NOT NULL,
                source_currency CHAR(3) NOT NULL DEFAULT 'USD',
                exchange_rate DECIMAL(18,6) NOT NULL DEFAULT 1,
                status ENUM('pending','paid','failed') NOT NULL DEFAULT 'pending',
                paypal_order_id VARCHAR(64) NULL,
                paypal_capture_id VARCHAR(64) NULL,
                paid_at DATETIME NULL,
                payer_name VARCHAR(150) NULL,
                payer_email VARCHAR(150) NULL,
                payer_phone VARCHAR(50) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_cp_reference (reference)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
    foreach (CP_PAYER_FIELDS as $column => $length) {
        if (!columnExists($pdo, 'custom_payments', $column)) {
            $pdo->exec("ALTER TABLE custom_payments ADD COLUMN {$column} VARCHAR({$length}) NULL");
        }
    }
    $done = true;
}

/**
 * Mata uang yang bisa dipilih: USD + semua kurs live. Tanpa API key hanya USD (+ IDR dari kurs cadangan).
 * @return array<string,float>  kode → jumlah per 1 USD
 */
function cpCurrencies(): array
{
    $rates = ddExchangeRates() ?: [];
    $rates = array_filter($rates, fn($r) => is_numeric($r) && (float)$r > 0);
    if (!isset($rates['IDR'])) $rates['IDR'] = ddRateFromIdr();
    $rates['USD'] = 1.0;
    ksort($rates);
    return array_map('floatval', $rates);
}

/**
 * Konversi nominal mata uang apa pun ke USD (2 desimal).
 * @return array{usd: float, rate: float}|null  null kalau mata uang tidak dikenal
 */
function cpConvertToUsd(float $amount, string $currency): ?array
{
    $code  = strtoupper(trim($currency));
    $rates = cpCurrencies();
    if (!isset($rates[$code])) return null;
    $rate = $rates[$code];
    return ['usd' => round($amount / $rate, 2), 'rate' => $rate];
}

/**
 * Bersihkan input pembayar: kosong → NULL. @return array{0: ?array, 1: ?string} [data, error]
 */
function cpCleanPayer(array $input): array
{
    $payer = [];
    foreach (CP_PAYER_FIELDS as $column => $length) {
        $value = mb_substr(trim((string)($input[$column] ?? '')), 0, $length);
        $payer[$column] = $value === '' ? null : $value;
    }
    if ($payer['payer_email'] !== null && !filter_var($payer['payer_email'], FILTER_VALIDATE_EMAIL)) {
        return [null, 'Please enter a valid email address.'];
    }
    return [$payer, null];
}

/** Buat pembayaran pending. @return array{0: ?array, 1: ?string} [baris, error] */
function cpCreate(PDO $pdo, string $title, float $amount, string $currency, array $payerInput = []): array
{
    [$payer, $payerError] = cpCleanPayer($payerInput);
    if ($payer === null) return [null, $payerError];

    $title = trim($title);
    if ($title === '') return [null, 'Please enter what this payment is for.'];
    if (!is_finite($amount) || $amount <= 0) return [null, 'Amount must be greater than 0.'];

    $conv = cpConvertToUsd($amount, $currency);
    if ($conv === null) return [null, 'Unknown currency.'];
    if ($conv['usd'] < 0.01) return [null, 'Amount is too small (minimum is $0.01 USD).'];
    if ($conv['usd'] > CP_MAX_USD) return [null, 'Amount is too large (maximum is $' . number_format(CP_MAX_USD, 2) . ' USD).'];

    $reference = 'PAY-' . strtoupper(bin2hex(random_bytes(5)));
    $pdo->prepare(
        'INSERT INTO custom_payments
            (reference, title, amount_usd, source_amount, source_currency, exchange_rate, payer_name, payer_email, payer_phone)
         VALUES (:ref, :title, :usd, :src, :cur, :rate, :payer_name, :payer_email, :payer_phone)'
    )->execute($payer + [
        'ref'   => $reference,
        'title' => mb_substr($title, 0, 190),
        'usd'   => number_format($conv['usd'], 2, '.', ''),
        'src'   => number_format($amount, 2, '.', ''),
        'cur'   => strtoupper(trim($currency)),
        'rate'  => $conv['rate'],
    ]);

    $stmt = $pdo->prepare('SELECT * FROM custom_payments WHERE reference = :r LIMIT 1');
    $stmt->execute(['r' => $reference]);
    return [$stmt->fetch(PDO::FETCH_ASSOC) ?: null, null];
}

function cpFind(PDO $pdo, string $reference): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM custom_payments WHERE reference = :r LIMIT 1');
    $stmt->execute(['r' => $reference]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
