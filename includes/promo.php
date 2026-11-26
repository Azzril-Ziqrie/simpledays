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
        // Batasan per pelanggan (dipakai juga untuk reward pendaftaran akun)
        if (!columnExists($pdo, 'promo_codes', 'members_only')) {
            $pdo->exec('ALTER TABLE promo_codes ADD COLUMN members_only TINYINT(1) NOT NULL DEFAULT 0');
        }
        if (!columnExists($pdo, 'promo_codes', 'once_per_customer')) {
            $pdo->exec('ALTER TABLE promo_codes ADD COLUMN once_per_customer TINYINT(1) NOT NULL DEFAULT 0');
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

/** Status order yang tidak dihitung sebagai "sudah memakai" promo (order batal/gagal). */
const PROMO_UNUSED_ORDER_STATUSES = ['failed', 'expired', 'cancelled'];

/**
 * Cek syarat per pelanggan: members_only (harus login) dan once_per_customer (sekali per akun).
 * Mengembalikan pesan error untuk pembeli, atau null kalau boleh dipakai.
 */
function promoCheckCustomer(PDO $pdo, array $promo, ?int $userId): ?string
{
    $membersOnly = (int)($promo['members_only'] ?? 0) === 1;
    $oncePerUser = (int)($promo['once_per_customer'] ?? 0) === 1;
    if (!$membersOnly && !$oncePerUser) return null;

    if ($userId === null || $userId <= 0) {
        return 'Please sign in to your account to use this promo code.';
    }
    if ($oncePerUser) {
        $placeholders = implode(',', array_fill(0, count(PROMO_UNUSED_ORDER_STATUSES), '?'));
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM orders
             WHERE user_id = ? AND promo_code = ? AND payment_status NOT IN ($placeholders)"
        );
        $stmt->execute(array_merge([$userId, $promo['code']], PROMO_UNUSED_ORDER_STATUSES));
        if ((int)$stmt->fetchColumn() > 0) return 'You have already used this promo code.';
    }
    return null;
}

/**
 * Cari + cek + hitung dalam satu langkah (dipakai checkout & AJAX apply).
 * $userId = id akun yang login (null untuk tamu), untuk syarat members_only / once_per_customer.
 *
 * @return array{promo: ?array, error: ?string, discounts: array{item: float, shipping: float, total: float}}
 */
function promoResolve(PDO $pdo, string $code, float $subtotal, float $shippingCost, ?int $userId = null): array
{
    $none = ['item' => 0.0, 'shipping' => 0.0, 'total' => 0.0];
    $code = promoNormalizeCode($code);
    if ($code === '') return ['promo' => null, 'error' => null, 'discounts' => $none];

    $promo = promoFind($pdo, $code);
    if (!$promo) return ['promo' => null, 'error' => 'Promo code not found.', 'discounts' => $none];

    $error = promoCheck($promo, $subtotal) ?? promoCheckCustomer($pdo, $promo, $userId);
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

/* ════════════════════════════════════════════════════════════
   REWARD PENDAFTARAN AKUN
   Admin memilih satu kode promo (admin/promo-codes.php). Setiap akun baru mendapat kode itu:
   ditampilkan setelah daftar, dikirim lewat email, dan terisi otomatis di checkout.
   Disimpan sebagai JSON di admin_settings (key "signup_reward").
═══════════════════════════════════════════════════════════════ */

const PROMO_SIGNUP_REWARD_KEY = 'signup_reward';

/** @return array{enabled: bool, promo_id: int} */
function promoSignupRewardSettings(PDO $pdo): array
{
    $settings = ['enabled' => false, 'promo_id' => 0];
    try {
        if (!tableExists($pdo, 'admin_settings')) return $settings;
        $stmt = $pdo->prepare('SELECT setting_value FROM admin_settings WHERE setting_key = :k LIMIT 1');
        $stmt->execute(['k' => PROMO_SIGNUP_REWARD_KEY]);
        $saved = json_decode((string)$stmt->fetchColumn(), true);
    } catch (Throwable $e) {
        error_log('[promo] signup reward read failed: ' . $e->getMessage());
        return $settings;
    }
    if (!is_array($saved)) return $settings;
    return ['enabled' => !empty($saved['enabled']), 'promo_id' => max(0, (int)($saved['promo_id'] ?? 0))];
}

function promoSaveSignupReward(PDO $pdo, bool $enabled, int $promoId): void
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
    )->execute(['k' => PROMO_SIGNUP_REWARD_KEY, 'v' => json_encode(['enabled' => $enabled, 'promo_id' => $promoId])]);
}

/**
 * Promo reward yang sedang berlaku untuk akun baru, atau null (nonaktif, kode dihapus,
 * dinonaktifkan, kedaluwarsa, atau kuota habis).
 */
function promoActiveSignupReward(PDO $pdo): ?array
{
    $settings = promoSignupRewardSettings($pdo);
    if (!$settings['enabled'] || $settings['promo_id'] <= 0) return null;
    try {
        promoEnsureSchema($pdo);
        $stmt = $pdo->prepare('SELECT * FROM promo_codes WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $settings['promo_id']]);
        $promo = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        error_log('[promo] signup reward lookup failed: ' . $e->getMessage());
        return null;
    }
    // Syarat minimum belanja dicek nanti di checkout; di sini cukup aktif/tanggal/kuota.
    if (!$promo || promoCheck(array_merge($promo, ['min_subtotal' => 0]), 0.0) !== null) return null;
    return $promo;
}

/**
 * Dipanggil sekali setelah akun baru dibuat (sesi sudah berisi user baru).
 * Simpan kode di sesi (diisi otomatis di checkout) dan kirim email. Tidak pernah melempar error.
 *
 * @return array{code: string, label: string}|null  info untuk ditampilkan, atau null kalau tidak ada reward
 */
function promoGrantSignupReward(PDO $pdo, string $email, string $name): ?array
{
    $promo = promoActiveSignupReward($pdo);
    if (!$promo) return null;

    $info = ['code' => (string)$promo['code'], 'label' => promoLabel($promo)];
    $_SESSION['signup_reward_code'] = $info['code'];

    try {
        promoSendSignupRewardEmail($promo, $email, $name);
    } catch (Throwable $e) {
        error_log('[promo] signup reward email failed: ' . $e->getMessage());
    }
    return $info;
}

function promoSendSignupRewardEmail(array $promo, string $email, string $name): void
{
    require_once __DIR__ . '/../vendor/autoload.php'; // PHPMailer
    require_once __DIR__ . '/campaign_mail.php';      // campaignCreateMailer() + konstanta SMTP
    require_once __DIR__ . '/currency_helper.php';

    $e = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    $label = promoLabel($promo);
    $conditions = [];
    if ((float)$promo['min_subtotal'] > 0) $conditions[] = 'Minimum spend ' . ddFormatMoney((float)$promo['min_subtotal']) . '.';
    if (!empty($promo['ends_at']))         $conditions[] = 'Valid until ' . date('d M Y', strtotime((string)$promo['ends_at'])) . '.';
    if ((int)($promo['once_per_customer'] ?? 0) === 1) $conditions[] = 'One use per account.';
    $conditions[] = 'Sign in to your account at checkout to use it.';

    $shopUrl = rtrim(DD_SITE_URL, '/') . '/shop';

    $mail = campaignCreateMailer();
    $mail->SMTPKeepAlive = false;
    $mail->Timeout = 10; // jangan bikin pendaftaran menggantung kalau SMTP lambat
    $mail->addAddress($email, $name);
    $mail->Subject = 'Welcome to ' . BRAND_NAME . ': your ' . $label . ' code';
    $mail->Body =
        '<div style="font-family:Helvetica,Arial,sans-serif;color:#111;line-height:1.7;max-width:560px;margin:0 auto;padding:32px 20px;">'
        . '<p>Hi ' . $e($name) . ',</p>'
        . '<p>Thanks for creating an account with ' . $e(BRAND_NAME) . '. Here is your welcome reward:</p>'
        . '<p style="margin:24px 0;text-align:center;"><span style="display:inline-block;padding:14px 22px;border:1px dashed #111;'
        . 'font:700 20px/1 ui-monospace,Menlo,Consolas,monospace;letter-spacing:2px;">' . $e($promo['code']) . '</span><br>'
        . '<span style="font-size:13px;color:#555;">' . $e($label) . '</span></p>'
        . '<p style="font-size:13px;color:#555;">' . $e(implode(' ', $conditions)) . '</p>'
        . '<p><a href="' . $e($shopUrl) . '" style="color:#111;">Start shopping</a></p></div>';
    $mail->AltBody = "Hi {$name},\n\nYour welcome code: {$promo['code']} ({$label}).\n"
        . implode(' ', $conditions) . "\n\n{$shopUrl}";
    $mail->send();
}

