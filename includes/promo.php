<?php
/**
 * Kode promo untuk checkout (semua nominal dalam USD).
 *
 * Tipe:
 *   percent        potongan % dari subtotal (opsional batas potongan max_discount)
 *   fixed          potongan nominal USD dari subtotal
 *   free_shipping  ongkir digratiskan
 * Syarat umum: aktif, dalam rentang tanggal, belum melewati batas pemakaian, subtotal >= min_subtotal.
 *
 * Fungsi promoCheck / promoDiscounts murni (tanpa database) supaya mudah diuji: tests/promo_test.php.
 */

require_once __DIR__ . '/db_schema.php';

const PROMO_TYPES = [
    'percent'       => 'Percent off',
    'fixed'         => 'Fixed amount off',
    'free_shipping' => 'Free shipping',
];

/** Sisa minimal yang harus dibayar pembeli: gateway (PayPal) menolak total 0. */
const PROMO_MIN_PAYABLE = 0.01;

/** Buat tabel promo_codes & kolom promo di orders kalau belum ada (idempotent, sekali per request). */
function promoEnsureSchema(PDO $pdo): void
{
    static $done = false;
    if ($done) return;

    try {
        if (!tableExists($pdo, 'promo_codes')) {
            $pdo->exec(
                "CREATE TABLE promo_codes (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    code VARCHAR(32) NOT NULL,
                    description VARCHAR(190) NULL,
                    type ENUM('percent','fixed','free_shipping') NOT NULL,
                    value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    max_discount DECIMAL(10,2) NULL,
                    min_subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    starts_at DATETIME NULL,
                    ends_at DATETIME NULL,
                    usage_limit INT UNSIGNED NULL,
                    used_count INT UNSIGNED NOT NULL DEFAULT 0,
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uniq_promo_code (code)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
        if (!columnExists($pdo, 'orders', 'promo_code')) {
            $pdo->exec('ALTER TABLE orders ADD COLUMN promo_code VARCHAR(32) NULL');
        }
        if (!columnExists($pdo, 'orders', 'discount_amount')) {
            $pdo->exec('ALTER TABLE orders ADD COLUMN discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00');
        }
        $done = true;
    } catch (Throwable $e) {
        error_log('[promo] ensure schema failed: ' . $e->getMessage());
    }
}

/** Kode dinormalkan: huruf besar, tanpa spasi pinggir. */
function promoNormalizeCode(string $code): string
{
    return strtoupper(trim($code));
}

/** Kode valid untuk disimpan: 3–32 karakter A-Z 0-9 _ - */
function promoIsValidCodeFormat(string $code): bool
{
    return (bool) preg_match('/^[A-Z0-9_-]{3,32}$/', $code);
}

function promoFind(PDO $pdo, string $code): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM promo_codes WHERE code = :c LIMIT 1');
    $stmt->execute(['c' => promoNormalizeCode($code)]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** Teks singkat untuk pembeli, mis. "10% off", "$5.00 off", "Free shipping". */
function promoLabel(array $promo): string
{
    switch ($promo['type']) {
        case 'percent':
            $label = rtrim(rtrim(number_format((float)$promo['value'], 2, '.', ''), '0'), '.') . '% off';
            if ((float)($promo['max_discount'] ?? 0) > 0) $label .= ' (up to ' . ddFormatMoney((float)$promo['max_discount']) . ')';
            return $label;
        case 'fixed':
            return ddFormatMoney((float)$promo['value']) . ' off';
        default:
            return 'Free shipping';
    }
}

/**
 * Cek syarat promo. Mengembalikan pesan error untuk pembeli, atau null kalau boleh dipakai.
 */
function promoCheck(array $promo, float $subtotal, ?int $now = null): ?string
{
    $now = $now ?? time();

    if ((int)$promo['is_active'] !== 1) return 'This promo code is not active.';

    $start = !empty($promo['starts_at']) ? strtotime((string)$promo['starts_at']) : false;
    $end   = !empty($promo['ends_at'])   ? strtotime((string)$promo['ends_at'])   : false;
    if ($start !== false && $now < $start) return 'This promo code is not valid yet.';
    if ($end   !== false && $now > $end)   return 'This promo code has expired.';

    $limit = $promo['usage_limit'];
    if ($limit !== null && $limit !== '' && (int)$promo['used_count'] >= (int)$limit) {
        return 'This promo code has reached its usage limit.';
    }

    $min = (float)$promo['min_subtotal'];
    if ($subtotal + 0.00001 < $min) {
        return 'Spend at least ' . ddFormatMoney($min) . ' to use this promo code.';
    }
    return null;
}

/**
 * Potongan yang didapat dari sebuah promo.
 *
 * @return array{item: float, shipping: float, total: float}
 *   item = potongan harga barang, shipping = potongan ongkir, total = keduanya.
 *   Total tidak pernah membuat tagihan di bawah PROMO_MIN_PAYABLE.
 */
function promoDiscounts(array $promo, float $subtotal, float $shippingCost): array
{
    $item = 0.0;
    $shipping = 0.0;

    if ($promo['type'] === 'percent') {
        $item = $subtotal * ((float)$promo['value'] / 100);
        $cap  = (float)($promo['max_discount'] ?? 0);
        if ($cap > 0) $item = min($item, $cap);
    } elseif ($promo['type'] === 'fixed') {
        $item = (float)$promo['value'];
    } elseif ($promo['type'] === 'free_shipping') {
        $shipping = max($shippingCost, 0.0);
    }

    $item     = round(min($item, $subtotal), 2);
    $shipping = round($shipping, 2);

    // Sisakan minimal yang harus dibayar.
    $maxTotal = max($subtotal + max($shippingCost, 0.0) - PROMO_MIN_PAYABLE, 0.0);
    if ($item + $shipping > $maxTotal) {
        $over = $item + $shipping - $maxTotal;
        $item = max($item - $over, 0.0);
        if ($item + $shipping > $maxTotal) $shipping = max($maxTotal - $item, 0.0);
    }

    $item = round($item, 2);
    $shipping = round($shipping, 2);
    return ['item' => $item, 'shipping' => $shipping, 'total' => round($item + $shipping, 2)];
}

/**
 * Cari + cek + hitung dalam satu langkah (dipakai checkout & AJAX apply).
 *
 * @return array{promo: ?array, error: ?string, discounts: array{item: float, shipping: float, total: float}}
 */
function promoResolve(PDO $pdo, string $code, float $subtotal, float $shippingCost): array
{
    $none = ['item' => 0.0, 'shipping' => 0.0, 'total' => 0.0];
    $code = promoNormalizeCode($code);
    if ($code === '') return ['promo' => null, 'error' => null, 'discounts' => $none];

    $promo = promoFind($pdo, $code);
    if (!$promo) return ['promo' => null, 'error' => 'Promo code not found.', 'discounts' => $none];

    $error = promoCheck($promo, $subtotal);
    if ($error !== null) return ['promo' => null, 'error' => $error, 'discounts' => $none];

    return ['promo' => $promo, 'error' => null, 'discounts' => promoDiscounts($promo, $subtotal, $shippingCost)];
}

/**
 * Pakai 1 kuota promo secara atomik (aman dari balapan dua pembeli di kuota terakhir).
 * Panggil di dalam transaksi pembuatan order. false = kuota habis / promo nonaktif.
 */
function promoConsume(PDO $pdo, int $promoId): bool
{
    $stmt = $pdo->prepare(
        'UPDATE promo_codes SET used_count = used_count + 1
         WHERE id = :id AND is_active = 1 AND (usage_limit IS NULL OR used_count < usage_limit)'
    );
    $stmt->execute(['id' => $promoId]);
    return $stmt->rowCount() === 1;
}

/**
 * Keterangan potongan sebuah order untuk ditampilkan (email, invoice, admin), atau null kalau tidak ada.
 * @return array{label: string, amount: float}|null
 */
function promoOrderDiscount(array $order): ?array
{
    $amount = (float)($order['discount_amount'] ?? 0);
    if ($amount <= 0) return null;

    $code = trim((string)($order['promo_code'] ?? ''));
    return ['label' => $code !== '' ? 'Discount (' . $code . ')' : 'Discount', 'amount' => $amount];
}
