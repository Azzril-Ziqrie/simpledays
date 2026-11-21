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
