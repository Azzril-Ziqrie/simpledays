<?php
/**
 * Pengecekan skema database yang dipakai banyak halaman (tabel / kolom ada atau tidak).
 * Semua fungsi mengembalikan nilai aman (false / []) kalau query gagal.
 */

function tableExists(PDO $pdo, string $table): bool
{
    try {
        // information_schema, bukan SHOW TABLES LIKE ?: MariaDB menolak placeholder di SHOW.
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t'
        );
        $stmt->execute(['t' => $table]);
        return ((int) $stmt->fetchColumn()) > 0;
    } catch (Throwable $e) {
        return false;
    }
}

/** @return string[] nama kolom tabel, atau [] kalau tabel tidak ada */
function tableColumns(PDO $pdo, string $table): array
{
    try {
        $stmt = $pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`');
        return array_map(static fn(array $row): string => $row['Field'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Throwable $e) {
        return [];
    }
}

function columnExists(PDO $pdo, string $table, string $column): bool
{
    try {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c'
        );
        $stmt->execute(['t' => $table, 'c' => $column]);
        return ((int) $stmt->fetchColumn()) > 0;
    } catch (Throwable $e) {
        return false;
    }
}
