<?php
/**
 * Kasera Pay API client.
 * Docs: https://pay.kasera.id/docs
 *
 * Dipakai oleh checkout.php (buat payment request, tampilkan QRIS/VA, cek status)
 * dan kasera-webhook.php (verifikasi signature).
 */

require_once __DIR__ . '/kasera.php';
require_once __DIR__ . '/../includes/currency_helper.php';

class KaseraApiException extends Exception
{
    public array $errorData = [];
    public int $httpStatus = 0;
}

function kaseraApiRequest(string $method, string $path, ?array $body = null, ?string $idempotencyKey = null): array
{
    $url = rtrim(KASERA_API_BASE_URL, '/') . '/' . ltrim($path, '/');

    $headers = [
        'Authorization: Bearer ' . KASERA_API_KEY,
        'Content-Type: application/json',
    ];
    if ($idempotencyKey !== null && $idempotencyKey !== '') {
        $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    $raw    = curl_exec($ch);
    $errno  = curl_errno($ch);
    $errmsg = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0) {
        throw new KaseraApiException('Kasera Pay connection error: ' . $errmsg);
    }

    $decoded = json_decode((string)$raw, true);
    if (!is_array($decoded)) {
        $e = new KaseraApiException('Kasera Pay returned an invalid response (HTTP ' . $status . ').');
        $e->httpStatus = $status;
        throw $e;
    }

    if ($status >= 400) {
        $message = $decoded['message'] ?? ($decoded['error']['message'] ?? 'Kasera Pay request failed.');
        $e = new KaseraApiException('Kasera Pay error (' . $status . '): ' . $message);
        $e->httpStatus = $status;
        $e->errorData  = $decoded;
        throw $e;
    }

    return $decoded;
}

/** POST /v1/transactions */
function kaseraCreateTransaction(array $payload, string $idempotencyKey): array
{
    return kaseraApiRequest('POST', '/transactions', $payload, $idempotencyKey);
}

/** GET /v1/transactions/{id} */
function kaseraGetTransaction(string $paymentRequestId): array
{
    return kaseraApiRequest('GET', '/transactions/' . rawurlencode($paymentRequestId));
}

/**
 * Verifikasi header Kasera-Signature-V1: "t=<unix>,v1=<hex>[,v1=<hex>]".
 * v1 = HMAC-SHA256(secret, "<t>.<rawBody>"). Menolak selisih waktu > toleransi
 * (anti-replay) dan menerima kalau SALAH SATU v1 cocok (masa tenggang rotasi secret).
 */
function kaseraVerifyWebhookSignatureV1(string $rawBody, string $headerValue, string $secret, int $toleranceSeconds = 300): bool
{
    $t = null;
    $signatures = [];
    foreach (explode(',', $headerValue) as $part) {
        $part = trim($part);
        if (str_starts_with($part, 't=')) {
            $t = (int)substr($part, 2);
        } elseif (str_starts_with($part, 'v1=')) {
            $signatures[] = substr($part, 3);
        }
    }
    if ($t === null || !$signatures) return false;
    if (abs(time() - $t) > $toleranceSeconds) return false;

    $expected = hash_hmac('sha256', $t . '.' . $rawBody, $secret);
    foreach ($signatures as $sig) {
        if (hash_equals($expected, $sig)) return true;
    }
    return false;
}

/** Fallback header lama (deprecated di sisi Kasera): HMAC-SHA256 polos atas raw body, tanpa timestamp. */
function kaseraVerifyWebhookSignatureLegacy(string $rawBody, string $headerValue, string $secret): bool
{
    $expected = hash_hmac('sha256', $rawBody, $secret);
    return hash_equals($expected, trim($headerValue));
}

/** Best-effort normalisasi nomor telepon Indonesia ke format E.164 (+62...). */
function kaseraNormalizePhoneE164(string $phone, string $defaultCountryDialCode = '62'): string
{
    $digits = preg_replace('/[^\d+]/', '', trim($phone)) ?? '';
    if ($digits === '') return '';
    if ($digits[0] === '+') return $digits;
    if (str_starts_with($digits, '0')) return '+' . $defaultCountryDialCode . substr($digits, 1);
    if (str_starts_with($digits, $defaultCountryDialCode)) return '+' . $digits;
    return '+' . $defaultCountryDialCode . $digits;
}

/**
 * Buat (atau buat ulang) payment request Kasera Pay untuk sebuah order, lalu
 * simpan payment_request_id & checkout_url ke tabel orders.
 *
 * Kasera Pay hanya menerima IDR — order dalam USD (mata uang toko) dikonversi
 * ke IDR lewat kaseraAmountIdr() (kurs ddRateFromIdr(), sama dengan checkout.php),
 * supaya nilai yang di-charge sinkron dengan yang dilihat pembeli.
 *
 * Idempotency-Key dikunci ke nomor order, jadi memanggil ini dua kali untuk
 * order yang sama (misal saat retry) mengembalikan payment request yang sama,
 * bukan membuat tagihan baru.
 */
/**
 * Metode pembayaran Kasera yang ditawarkan di payment.php (kode → label).
 * Kode harus sesuai dokumentasi Kasera (payment_methods). Hapus baris untuk menyembunyikan metode.
 */
function kaseraPaymentMethods(): array
{
    return [
        'qris'       => 'QRIS',
        'va_bca'     => 'BCA Virtual Account',
        'va_bri'     => 'BRI Virtual Account',
        'va_bni'     => 'BNI Virtual Account',
        'va_mandiri' => 'Mandiri Virtual Account',
        'va_permata' => 'Permata Virtual Account',
        'va_cimb'    => 'CIMB Niaga Virtual Account',
        'va_danamon' => 'Danamon Virtual Account',
        'va_maybank' => 'Maybank Virtual Account',
    ];
}

/**
 * Metode yang benar-benar aktif di akun Kasera (GET /v1/payment_methods), di-cache 10 menit.
 * Dipotong ke daftar kaseraPaymentMethods(). Kalau API gagal, pakai daftar statis.
 * @return array<string,string> kode => label
 */
function kaseraEnabledMethods(): array
{
    $all   = kaseraPaymentMethods();
    $cache = sys_get_temp_dir() . '/kasera-methods-' . md5(KASERA_API_BASE_URL . KASERA_API_KEY) . '.json';
    if (is_file($cache) && (time() - (int)filemtime($cache)) < 600) {
        $codes = json_decode((string)file_get_contents($cache), true);
    } else {
        $codes = null;
        try {
            $res = kaseraApiRequest('GET', '/payment_methods');
            $codes = array_values(array_filter(array_map(fn($m) => $m['code'] ?? null, $res['data'] ?? [])));
            if ($codes) @file_put_contents($cache, json_encode($codes));
        } catch (Throwable $e) {
            error_log('[kasera] could not load payment methods: ' . $e->getMessage());
        }
    }
    if (!is_array($codes) || !$codes) return $all;
    return array_intersect_key($all, array_flip($codes));
}

/** Batas nominal (IDR) per metode langsung: [min, max]. */
function kaseraAmountLimits(string $method): array
{
    return $method === 'qris' ? [1000, 10000000] : [10000, 10000000];
}

/** Status Kasera yang berarti sudah dibayar. */
function kaseraIsPaidStatus(string $status): bool
{
    return in_array(strtolower($status), ['succeeded', 'paid', 'success', 'settlement', 'completed'], true);
}

/** customer.name VA: maks 30 karakter ASCII, hanya [A-Za-z0-9 .,'-]. */
function kaseraAsciiName(string $name): string
{
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
    $ascii = preg_replace("/[^A-Za-z0-9 .,'-]/", '', (string)$ascii) ?? '';
    $ascii = trim(substr(trim($ascii), 0, 30));
    return $ascii !== '' ? $ascii : 'Customer';
}

/** Buat kolom penyimpan data pembayaran langsung kalau belum ada. */
function kaseraEnsureColumns(PDO $pdo): void
{
    try {
        $have = [];
        foreach ($pdo->query('SHOW COLUMNS FROM orders')->fetchAll(PDO::FETCH_ASSOC) as $c) $have[$c['Field']] = true;
        if (empty($have['kasera_payment_method'])) $pdo->exec('ALTER TABLE orders ADD COLUMN kasera_payment_method VARCHAR(40) NULL');
        if (empty($have['kasera_payment_data']))   $pdo->exec('ALTER TABLE orders ADD COLUMN kasera_payment_data MEDIUMTEXT NULL');
    } catch (Throwable $e) {
        error_log('[kasera] ensure columns failed: ' . $e->getMessage());
    }
}

/**
 * Nominal order dalam IDR (bilangan bulat) untuk ditagihkan lewat Kasera.
 * Order USD dikonversi memakai kurs toko (ddRateFromIdr); order lama bermata uang IDR dipakai apa adanya.
 */
function kaseraAmountIdr(array $order): int
{
    $total = (float)($order['grand_total'] ?? 0);
    return (int)round(ddOrderCurrency($order) === 'IDR' ? $total : ddConvertToIdr($total));
}

/**
 * Susun payload payment request. $paymentMethod (mis. 'qris', 'va_bca') membuat halaman
 * Kasera langsung menampilkan metode itu; null = pembeli memilih sendiri di halaman Kasera.
 */
function kaseraBuildPayload(array $order, ?string $paymentMethod = null): array
{
    $amountIdr = kaseraAmountIdr($order);

    $payload = [
        'amount'       => $amountIdr,
        'description'  => 'Order ' . $order['order_number'],
        'external_id'  => $order['order_number'],
        'merchant_ref' => $order['order_number'],
        'customer'     => [
            'name'  => (string)$order['full_name'],
            'email' => (string)$order['email'],
            'phone' => kaseraNormalizePhoneE164((string)$order['phone']),
        ],
        'return_url'         => SITE_BASE_URL . '/index.php?kasera_verify=1&order=' . urlencode($order['order_number']),
        'expires_in_minutes' => 60,
    ];
    if ($paymentMethod !== null && $paymentMethod !== '') {
        // Metode langsung (QRIS / VA): QR atau nomor VA ditampilkan di halaman kita, tanpa checkout.steps.
        $payload['payment_methods'] = [$paymentMethod];
        if (str_starts_with($paymentMethod, 'va_')) {
            $payload['customer']['name'] = kaseraAsciiName((string)$order['full_name']);
        }
    } else {
        // Tanpa metode: pembeli memilih sendiri di halaman Kasera Checkout; data pembeli kita sudah ada.
        $payload['checkout'] = ['steps' => ['payment_method', 'payment']];
    }
    return $payload;
}

function kaseraEnsureTransactionForOrder(PDO $pdo, array $order, ?string $paymentMethod = null): array
{
    if ($paymentMethod !== null && $paymentMethod !== '') {
        if (!isset(kaseraPaymentMethods()[$paymentMethod])) throw new RuntimeException('Invalid payment method.');
        // Nominal selalu dari database (bukan dari browser); batas per metode dicek di sini.
        $amountIdr = kaseraAmountIdr($order);
        [$min, $max] = kaseraAmountLimits($paymentMethod);
        if ($amountIdr < $min || $amountIdr > $max) {
            throw new RuntimeException('Amount is outside the allowed range for this payment method.');
        }
    }
    kaseraEnsureColumns($pdo);
    $payload = kaseraBuildPayload($order, $paymentMethod);

    // Idempotency per metode: memilih QRIS dua kali memakai tagihan yang sama,
    // tapi pindah ke VA membuat tagihan baru (key yang sama akan mengembalikan tagihan lama).
    $idempotencyKey = 'order-' . $order['order_number'] . ($paymentMethod ? '-' . $paymentMethod : '');
    $response = kaseraCreateTransaction($payload, $idempotencyKey);

    $paymentData = $paymentMethod ? json_encode([
        'payment'      => $response['payment'] ?? null,
        'instructions' => $response['instructions'] ?? null,
        'expires_at'   => $response['expires_at'] ?? null,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;

    $pdo->prepare(
        'UPDATE orders
         SET kasera_payment_request_id = :prid,
             kasera_checkout_url       = :curl,
             kasera_payment_method     = :pm,
             kasera_payment_data       = :pd,
             payment_provider          = :prov,
             updated_at                = NOW()
         WHERE id = :id'
    )->execute([
        'prid' => $response['id'] ?? null,
        'curl' => $response['checkout_url'] ?? null,
        'pm'   => $paymentMethod ?: null,
        'pd'   => $paymentData,
        'prov' => 'kasera_pay',
        'id'   => $order['id'],
    ]);

    return $response;
}

/**
 * Cadangan webhook: tanya Kasera langsung status transaksi order ini dan sinkronkan.
 * Endpoint pemanggilnya publik, jadi dibatasi 1x per 10 detik per order (lock file).
 * @return string payment_status order setelah sinkronisasi
 */
function kaseraSyncOrderStatus(PDO $pdo, array $order): string
{
    $current = strtolower((string)$order['payment_status']);
    $prid    = (string)($order['kasera_payment_request_id'] ?? '');
    if ($current !== 'pending' || $prid === '') return $current;

    $lock = sys_get_temp_dir() . '/kasera-sync-' . md5((string)$order['order_number']);
    if (is_file($lock) && (time() - (int)filemtime($lock)) < 10) return $current;
    @touch($lock);

    try {
        $tx     = kaseraGetTransaction($prid);
        $status = strtolower((string)($tx['status'] ?? ''));
        if (kaseraIsPaidStatus($status)) {
            require_once __DIR__ . '/../includes/order_payment.php';
            $paid = ddMarkOrderPaid($pdo, (int)$order['id'], 'kasera_pay', $prid, 'Payment confirmed via Kasera status check.');
            if (!$paid['already_paid']) kaseraSendInvoiceSafe($pdo, (int)$order['id']);
            return 'paid';
        }
        if (in_array($status, ['expired', 'failed', 'cancelled', 'canceled', 'rejected'], true)) {
            $pdo->prepare("UPDATE orders SET payment_status = 'expired', updated_at = NOW() WHERE id = :id AND payment_status = 'pending'")
                ->execute(['id' => $order['id']]);
            return 'expired';
        }
    } catch (Throwable $e) {
        error_log('[kasera] sync failed for order ' . $order['order_number'] . ': ' . $e->getMessage());
    }
    return $current;
}

/** Kirim invoice tanpa pernah menggagalkan konfirmasi pembayaran; kegagalan dicatat ke log. */
function kaseraSendInvoiceSafe(PDO $pdo, int $orderId): void
{
    try {
        require_once __DIR__ . '/../includes/email_helpers.php';
        $st = $pdo->prepare('SELECT * FROM orders WHERE id = :id LIMIT 1');
        $st->execute(['id' => $orderId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row && !sendDDInvoiceEmail($pdo, $row)) error_log('[kasera] invoice email failed for order ' . $row['order_number']);
    } catch (Throwable $e) {
        error_log('[kasera] invoice email error for order id ' . $orderId . ': ' . $e->getMessage());
    }
}