<?php
/**
 * Varian warna per ukuran.
 *
 * Satu baris product_sizes = satu varian (ukuran + warna) dengan stok sendiri.
 * Karena keranjang, checkout, order, dan pengurangan stok semuanya memakai product_sizes.id,
 * warna ikut terbawa tanpa mengubah alur tersebut. Produk tanpa warna memakai color_name = ''.
 *
 * Kolomnya dibuat oleh migrations/2026-11-21-product-variant-colors.php (otomatis, lihat includes/migrate.php).
 */

/** True kalau product_sizes sudah punya kolom warna. */
function ddVariantColumnsReady(PDO $pdo): bool
{
    static $ready = false;
    if (!$ready) {
        try {
            $ready = $pdo->query("SHOW COLUMNS FROM product_sizes LIKE 'color_name'")->fetch() !== false;
        } catch (Throwable $e) {
            return false;
        }
    }
    return $ready;
}

/** Tambah kolom warna dan ganti unique (product_id, size_id) menjadi (product_id, size_id, color_name). */
function ddAddVariantColumns(PDO $pdo): void
{
    if (ddVariantColumnsReady($pdo)) {
        return;
    }
    // Satu ALTER, karena indeks lama juga dipakai foreign key product_id.
    $dropOldIndex = ddUniqueIndexOn($pdo, 'product_sizes', ['product_id', 'size_id']);
    $pdo->exec(
        "ALTER TABLE product_sizes
         ADD COLUMN color_name VARCHAR(60) NOT NULL DEFAULT '' AFTER size_id,
         ADD COLUMN color_hex VARCHAR(7) NULL AFTER color_name"
        . ($dropOldIndex !== null ? ', DROP INDEX `' . str_replace('`', '', $dropOldIndex) . '`' : '')
        . ', ADD UNIQUE KEY unique_product_variant (product_id, size_id, color_name)'
    );
}

/** Nama unique index (selain PRIMARY) yang kolomnya persis $columns (berurutan), atau null. */
function ddUniqueIndexOn(PDO $pdo, string $table, array $columns): ?string
{
    $indexes = [];
    foreach ($pdo->query('SHOW INDEX FROM `' . str_replace('`', '', $table) . '`')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ((int) $row['Non_unique'] === 0 && $row['Key_name'] !== 'PRIMARY') {
            $indexes[$row['Key_name']][(int) $row['Seq_in_index']] = $row['Column_name'];
        }
    }
    foreach ($indexes as $name => $indexColumns) {
        ksort($indexColumns);
        if (array_values($indexColumns) === $columns) {
            return (string) $name;
        }
    }
    return null;
}

/** Ekspresi SQL label varian, mis. "M / Black" (atau "M" kalau tanpa warna). */
function ddSizeLabelSql(PDO $pdo, string $size = 's', string $ps = 'ps'): string
{
    if (!ddVariantColumnsReady($pdo)) {
        return "{$size}.name";
    }
    return "CONCAT({$size}.name, IF({$ps}.color_name <> '', CONCAT(' / ', {$ps}.color_name), ''))";
}

/** Normalisasi nilai hex dari input admin ("#aabbcc" atau kosong). */
function ddNormalizeHex(?string $hex): ?string
{
    $hex = strtolower(trim((string) $hex));
    return preg_match('/^#[0-9a-f]{6}$/', $hex) ? $hex : null;
}

/**
 * Urutan ukuran pakaian dari namanya: XS < S < M < L < XL < XXL, juga gabungan ("S/M" di antara S dan M).
 * Null kalau bukan ukuran huruf (mis. "28", "One Size") → pemanggil memakai sort_order / urutan alami.
 */
function ddSizeRank(string $name): ?float
{
    static $ranks = [
        'xxs' => 0, 'xx-small' => 0, 'xs' => 1, 'x-small' => 1, 's' => 2, 'small' => 2, 'm' => 3, 'medium' => 3,
        'l' => 4, 'large' => 4, 'xl' => 5, 'x-large' => 5, 'xxl' => 6, '2xl' => 6, 'xx-large' => 6,
        'xxxl' => 7, '3xl' => 7, 'xxx-large' => 7,
    ];
    $parts = preg_split('/\s*[\/,|]\s*/', strtolower(trim($name)));
    $values = [];
    foreach ($parts as $part) {
        if (!isset($ranks[$part])) return null;
        $values[] = $ranks[$part];
    }
    return $values ? array_sum($values) / count($values) : null;
}

/** Pembanding untuk usort baris ukuran (butuh key 'name', opsional 'sort_order'). */
function ddCompareSizes(array $a, array $b): int
{
    $ra = ddSizeRank((string)($a['name'] ?? ''));
    $rb = ddSizeRank((string)($b['name'] ?? ''));
    if ($ra !== null && $rb !== null && $ra !== $rb) return $ra <=> $rb;
    $orderCmp = ((int)($a['sort_order'] ?? 0)) <=> ((int)($b['sort_order'] ?? 0));
    if ($orderCmp !== 0) return $orderCmp;
    return strnatcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
}
