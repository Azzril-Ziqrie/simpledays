<?php
/**
 * Pembayaran lewat Wise (transfer manual).
 *
 * Wise tidak punya gateway checkout untuk toko, jadi alurnya: pembeli transfer ke rekening Wise
 * (account details USD/EUR/GBP/...) atau lewat link pembayaran Wise, lalu upload bukti transfer.
 * Admin mengecek di aplikasi Wise lalu approve order seperti bukti transfer biasa.
 *
 * Diatur dari admin (admin/payment-wise.php), disimpan sebagai JSON di admin_settings (key "wise_payment").
 */

require_once __DIR__ . '/db_schema.php';
require_once __DIR__ . '/currency_helper.php';

const WISE_SETTINGS_KEY  = 'wise_payment';
const WISE_MAX_ACCOUNTS  = 8;
const WISE_DEFAULTS = [
    'enabled'  => false,
    'pay_link' => '',   // opsional, mis. https://wise.com/pay/me/namamu
    'note'     => '',   // instruksi tambahan untuk pembeli
    'accounts' => [],   // [{currency: 'USD', details: "Account holder: ...\nAccount number: ..."}]
];

/** Pengaturan tersimpan, digabung dengan default; selalu array lengkap. */
function wiseSettings(PDO $pdo): array
{
    try {
        if (!tableExists($pdo, 'admin_settings')) return WISE_DEFAULTS;
        $stmt = $pdo->prepare('SELECT setting_value FROM admin_settings WHERE setting_key = :k LIMIT 1');
        $stmt->execute(['k' => WISE_SETTINGS_KEY]);
        $saved = json_decode((string)$stmt->fetchColumn(), true);
    } catch (Throwable $e) {
        error_log('[wise] read settings failed: ' . $e->getMessage());
        return WISE_DEFAULTS;
    }
    return is_array($saved) ? wiseNormalize($saved) : WISE_DEFAULTS;
}

/** Rapikan input (form admin atau JSON tersimpan) ke bentuk yang aman dipakai. */
function wiseNormalize(array $in): array
{
    $accounts = [];
    $currencies = (array)($in['accounts']['currency'] ?? array_column((array)($in['accounts'] ?? []), 'currency'));
    $details    = (array)($in['accounts']['details']  ?? array_column((array)($in['accounts'] ?? []), 'details'));
    foreach ($currencies as $i => $cur) {
        $cur = strtoupper(trim((string)$cur));
        $det = trim(str_replace("\r\n", "\n", (string)($details[$i] ?? '')));
        if (!preg_match('/^[A-Z]{3}$/', $cur) || $det === '') continue;
        $accounts[] = ['currency' => $cur, 'details' => mb_substr($det, 0, 1000)];
        if (count($accounts) >= WISE_MAX_ACCOUNTS) break;
    }

    $link = trim((string)($in['pay_link'] ?? ''));
    if ($link !== '' && !preg_match('#^https://#i', $link)) $link = '';

    return [
        'enabled'  => !empty($in['enabled']),
        'pay_link' => mb_substr($link, 0, 300),
        'note'     => mb_substr(trim((string)($in['note'] ?? '')), 0, 500),
        'accounts' => $accounts,
    ];
}

function wiseSave(PDO $pdo, array $settings): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS admin_settings (
            setting_key VARCHAR(50) NOT NULL,
            setting_value TEXT NOT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
    $pdo->prepare(
        'INSERT INTO admin_settings (setting_key, setting_value) VALUES (:k, :v)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    )->execute(['k' => WISE_SETTINGS_KEY, 'v' => json_encode($settings, JSON_UNESCAPED_UNICODE)]);
}

/** Wise tampil di checkout hanya kalau aktif dan ada minimal satu rekening atau link pembayaran. */
function wiseIsAvailable(array $settings): bool
{
    return $settings['enabled'] && ($settings['accounts'] || $settings['pay_link'] !== '');
}

/**
 * Baris "Label: Nilai" dari isian detail rekening.
 * @return list<array{0: string, 1: string}>  [label, nilai]; label kosong kalau baris tanpa ":".
 */
function wiseDetailRows(string $details): array
{
    $rows = [];
    foreach (preg_split('/\n/', $details) ?: [] as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $pos = strpos($line, ':');
        $rows[] = $pos === false ? ['', $line] : [trim(substr($line, 0, $pos)), trim(substr($line, $pos + 1))];
    }
    return $rows;
}

/**
 * Nominal yang harus ditransfer dalam mata uang rekening.
 * Order dalam USD; mata uang lain dikonversi dengan kurs live (dibulatkan ke atas), null kalau kurs tidak tersedia.
 */
function wiseAmountFor(float $amountUsd, string $orderCurrency, string $accountCurrency): ?float
{
    if ($accountCurrency === $orderCurrency) return round($amountUsd, 2);
    if ($orderCurrency !== 'USD') return null; // order IDR lama: tampilkan apa adanya
    $rates = ddExchangeRates();
    $rate  = (float)($rates[$accountCurrency] ?? 0);
    if ($rate <= 0) return null;
    return ceil($amountUsd * $rate * 100) / 100;
}

/**
 * Kode bayar pendek untuk kolom reference/berita transfer, mis. "SD79DE5639".
 * Nomor order (±28 karakter) terlalu panjang untuk sebagian bank (mis. transfer lokal Inggris maks. 18),
 * jadi dipakai bagian acak di ujung nomor order: 8 karakter hex, unik per order.
 * Dicari di dalam teks reference, jadi tetap cocok walaupun pembeli menambahkan kata lain.
 */
function wisePaymentReference(string $orderNumber): string
{
    $parts = explode('-', strtoupper(trim($orderNumber)));
    $tail  = preg_replace('/[^A-Z0-9]/', '', (string)end($parts));
    if (strlen($tail) < 6) $tail = strtoupper(substr(hash('sha256', $orderNumber), 0, 8)); // format nomor order tak terduga
    return 'SD' . $tail;
}

