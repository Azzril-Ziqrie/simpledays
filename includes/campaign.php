<?php
/**
 * Campaign hadiah gratis: belanja minimal X USD → dapat 1 produk hadiah gratis.
 *
 * - Admin mengatur campaign di admin/campaigns.php: minimum belanja, 1–2 produk hadiah,
 *   tanggal (opsional), kuota (opsional), aktif/nonaktif.
 * - Di checkout, kalau subtotal barang (sebelum ongkir & diskon promo) >= minimum, pembeli otomatis
 *   mendapat kode acak (mis. GIFT-7K3QX9) dan memilih varian hadiah (warna/ukuran) yang masih ada stoknya.
 * - Hadiah masuk ke order_items dengan harga 0, jadi stok terpotong saat order dibayar,
 *   dan otomatis tampil di detail order admin, invoice, dan email.
 * - Campaign berhenti sendiri kalau stok semua varian hadiah habis, kuota habis, atau tanggal lewat.
 * - Bisa digabung dengan kode promo biasa (promo_codes), karena hadiah tidak memengaruhi diskon.
 *
 * Kalau ada beberapa campaign aktif, yang dipakai adalah yang paling baru dibuat.
 * Tabel dibuat oleh migrations/2026-11-26-campaigns.php.
 */

require_once __DIR__ . '/db_schema.php';
require_once __DIR__ . '/variants.php';

const CAMPAIGN_MAX_PRODUCTS  = 20; // product_ids VARCHAR(100): 20 id × 4 digit + koma muat
const CAMPAIGN_CODE_PREFIX   = 'GIFT-';
const CAMPAIGN_CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // tanpa 0/O/1/I supaya mudah dibaca

function campaignCreateTables(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS campaigns (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            min_subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            product_ids VARCHAR(100) NOT NULL DEFAULT '',
            starts_at DATETIME NULL,
            ends_at DATETIME NULL,
            usage_limit INT UNSIGNED NULL,
            used_count INT UNSIGNED NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS campaign_claims (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            campaign_id INT UNSIGNED NOT NULL,
            code VARCHAR(32) NOT NULL,
            order_id INT UNSIGNED NOT NULL,
            order_number VARCHAR(64) NOT NULL,
            product_size_id INT UNSIGNED NOT NULL,
            gift_label VARCHAR(190) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_campaign_code (code),
            KEY idx_campaign (campaign_id),
            KEY idx_order (order_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function campaignTablesReady(PDO $pdo): bool
{
    static $ready = null;
    if ($ready === null) {
        try {
            $ready = tableExists($pdo, 'campaigns') && tableExists($pdo, 'campaign_claims');
        } catch (Throwable $e) {
            $ready = false;
        }
    }
    return $ready;
}

/** @return int[] id produk hadiah, urut sesuai pilihan admin, maksimal CAMPAIGN_MAX_PRODUCTS. */
function campaignProductIds(array $campaign): array
{
    $ids = array_map('intval', explode(',', (string)($campaign['product_ids'] ?? '')));
    $ids = array_values(array_unique(array_filter($ids, static fn(int $id): bool => $id > 0)));
    return array_slice($ids, 0, CAMPAIGN_MAX_PRODUCTS);
}

/** Alasan campaign tidak berjalan (null = berjalan). Tanpa cek stok; murni supaya mudah diuji. */
function campaignInactiveReason(array $campaign, ?int $now = null): ?string
{
    $now ??= time();
    if (empty($campaign['is_active'])) return 'Off';
    if (!campaignProductIds($campaign)) return 'No gift product';
    if (!empty($campaign['starts_at']) && strtotime((string)$campaign['starts_at']) > $now) return 'Scheduled';
    if (!empty($campaign['ends_at']) && strtotime((string)$campaign['ends_at']) < $now) return 'Ended';
    if ($campaign['usage_limit'] !== null && $campaign['usage_limit'] !== ''
        && (int)$campaign['used_count'] >= (int)$campaign['usage_limit']) {
        return 'Limit reached';
    }
    return null;
}

/**
 * Varian hadiah yang masih bisa dipilih (produk & varian aktif, stok > 0).
 * @return list<array{product_size_id:int, product_id:int, product_name:string, product_slug:string,
 *                    thumbnail:string, variant:string, color_hex:?string, stock:int}>
 */
function campaignGiftOptions(PDO $pdo, array $campaign): array
{
    $ids = campaignProductIds($campaign);
    if (!$ids) return [];

    $hasColor = ddVariantColumnsReady($pdo);
    $in = implode(',', $ids); // sudah int
    $rows = $pdo->query(
        'SELECT ps.id AS product_size_id, p.id AS product_id, p.name AS product_name, p.slug AS product_slug,
                p.thumbnail, ' . ddSizeLabelSql($pdo) . ' AS variant,
                ' . ($hasColor ? 'ps.color_hex' : 'NULL') . ' AS color_hex, ps.stock
         FROM product_sizes ps
         INNER JOIN products p ON p.id = ps.product_id
         INNER JOIN sizes s ON s.id = ps.size_id
         WHERE p.id IN (' . $in . ') AND p.is_active = 1 AND ps.is_active = 1 AND ps.stock > 0
         ORDER BY FIELD(p.id, ' . $in . '), s.sort_order, s.id' . ($hasColor ? ', ps.color_name' : '')
    )->fetchAll(PDO::FETCH_ASSOC);

    return array_map(static fn(array $r): array => [
        'product_size_id' => (int)$r['product_size_id'],
        'product_id'      => (int)$r['product_id'],
        'product_name'    => (string)$r['product_name'],
        'product_slug'    => (string)$r['product_slug'],
        'thumbnail'       => (string)$r['thumbnail'],
        'variant'         => campaignVariantLabel((string)$r['variant']),
        'color_hex'       => $r['color_hex'] !== null ? (string)$r['color_hex'] : null,
        'stock'           => (int)$r['stock'],
    ], $rows);
}

/** "One size / Black" → "Black": ukuran tunggal tidak perlu ditampilkan, cukup warnanya. */
function campaignVariantLabel(string $variant): string
{
    $parts = array_map('trim', explode('/', $variant));
    $parts = array_filter($parts, static fn(string $p): bool =>
        $p !== '' && !in_array(strtolower($p), ['one size', 'onesize', 'default'], true));
    return implode(' / ', $parts);
}

/**
 * Produk hadiah tidak dijual: selama ada campaign aktif (is_active = 1) yang memakai produk ini,
 * produk tidak bisa dimasukkan ke cart, hanya diklaim di checkout.
 * @return float|null minimum belanja campaign-nya, atau null kalau produk dijual biasa.
 */
function campaignGiftOnlyMinimum(PDO $pdo, int $productId): ?float
{
    if (!campaignTablesReady($pdo)) return null;
    try {
        $rows = $pdo->query('SELECT product_ids, min_subtotal FROM campaigns WHERE is_active = 1 ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('[campaign] gift-only lookup failed: ' . $e->getMessage());
        return null;
    }
    foreach ($rows as $row) {
        if (in_array($productId, campaignProductIds($row), true)) return (float)$row['min_subtotal'];
    }
    return null;
}

/**
 * Campaign yang sedang berjalan dan masih punya stok hadiah (yang terbaru kalau ada beberapa),
 * dengan key tambahan 'gifts' = campaignGiftOptions(). Null kalau tidak ada.
 */
function campaignCurrent(PDO $pdo): ?array
{
    if (!campaignTablesReady($pdo)) return null;
    try {
        $rows = $pdo->query('SELECT * FROM campaigns WHERE is_active = 1 ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $campaign) {
            if (campaignInactiveReason($campaign) !== null) continue;
            $gifts = campaignGiftOptions($pdo, $campaign);
            if ($gifts) return $campaign + ['gifts' => $gifts];
        }
    } catch (Throwable $e) {
        error_log('[campaign] lookup failed: ' . $e->getMessage());
    }
    return null;
}

/** Subtotal barang (USD, sebelum ongkir & diskon) memenuhi minimum campaign. */
function campaignQualifies(array $campaign, float $subtotal): bool
{
    return round($subtotal, 2) >= round((float)$campaign['min_subtotal'], 2);
}

function campaignFindGift(array $campaign, int $productSizeId): ?array
{
    foreach ($campaign['gifts'] ?? [] as $gift) {
        if ($gift['product_size_id'] === $productSizeId) return $gift;
    }
    return null;
}

function campaignGiftLabel(array $gift): string
{
    return $gift['product_name'] . ($gift['variant'] !== '' ? ' — ' . $gift['variant'] : '');
}

function campaignRandomCode(): string
{
    $alphabet = CAMPAIGN_CODE_ALPHABET;
    $code = '';
    for ($i = 0; $i < 6; $i++) {
        $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return CAMPAIGN_CODE_PREFIX . $code;
}

/** Kode acak untuk sesi checkout ini; tetap sama sampai order dibuat (lalu diganti baru). */
function campaignSessionCode(int $campaignId): string
{
    $key = 'campaign_code_' . $campaignId;
    if (empty($_SESSION[$key])) $_SESSION[$key] = campaignRandomCode();
    return (string)$_SESSION[$key];
}

function campaignForgetSessionCode(int $campaignId): void
{
    unset($_SESSION['campaign_code_' . $campaignId]);
}

/**
 * Klaim hadiah untuk order yang sedang dibuat. Dipanggil DI DALAM transaksi pembuatan order:
 * melempar RuntimeException kalau kuota atau stok habis, sehingga order ikut dibatalkan.
 * Menambah baris order_items (harga 0) dan mencatat kodenya di campaign_claims.
 * @return string kode yang tersimpan
 */
function campaignClaim(PDO $pdo, array $campaign, array $gift, string $code, int $orderId, string $orderNumber): string
{
    $used = $pdo->prepare(
        'UPDATE campaigns SET used_count = used_count + 1
         WHERE id = :id AND is_active = 1 AND (usage_limit IS NULL OR used_count < usage_limit)'
    );
    $used->execute(['id' => (int)$campaign['id']]);
    if ($used->rowCount() === 0) {
        throw new RuntimeException('The free gift campaign has just ended. Please reload the page and try again.');
    }

    $stock = $pdo->prepare('SELECT stock FROM product_sizes WHERE id = :id AND is_active = 1 FOR UPDATE');
    $stock->execute(['id' => $gift['product_size_id']]);
    if ((int)$stock->fetchColumn() < 1) {
        throw new RuntimeException('Your free gift (' . campaignGiftLabel($gift) . ') just sold out. Please choose another option.');
    }

    $pdo->prepare(
        'INSERT INTO order_items (order_id,product_id,product_size_id,product_name,
         product_slug,size_name,quantity,price,line_total,thumbnail)
         VALUES (:oid,:pid,:psid,:pn,:ps,:sn,1,0,0,:th)'
    )->execute([
        'oid' => $orderId, 'pid' => $gift['product_id'], 'psid' => $gift['product_size_id'],
        'pn' => $gift['product_name'] . ' (Free gift)', 'ps' => $gift['product_slug'],
        'sn' => $gift['variant'] !== '' ? $gift['variant'] : null, 'th' => $gift['thumbnail'],
    ]);

    $insertClaim = $pdo->prepare(
        'INSERT INTO campaign_claims (campaign_id, code, order_id, order_number, product_size_id, gift_label)
         VALUES (:cid, :code, :oid, :on, :psid, :label)'
    );
    // Kode dari sesi hampir pasti unik; kalau kebetulan bentrok, buat yang baru.
    for ($attempt = 0; $attempt < 5; $attempt++) {
        try {
            $insertClaim->execute([
                'cid' => (int)$campaign['id'], 'code' => $code, 'oid' => $orderId, 'on' => $orderNumber,
                'psid' => $gift['product_size_id'], 'label' => campaignGiftLabel($gift),
            ]);
            return $code;
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') throw $e;
            $code = campaignRandomCode();
        }
    }
    throw new RuntimeException('Could not create a gift code. Please try again.');
}

/** Hadiah campaign yang tercatat untuk order (untuk ditampilkan di admin), atau null. */
function campaignClaimForOrder(PDO $pdo, int $orderId): ?array
{
    if (!campaignTablesReady($pdo)) return null;
    $stmt = $pdo->prepare(
        'SELECT cc.*, c.name AS campaign_name FROM campaign_claims cc
         LEFT JOIN campaigns c ON c.id = cc.campaign_id WHERE cc.order_id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $orderId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
