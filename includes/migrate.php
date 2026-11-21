<?php
/**
 * Migrasi database otomatis — tidak perlu buka phpMyAdmin saat deploy.
 *
 * Setiap file migrations/*.php mengembalikan fungsi function (PDO $pdo): void.
 * File dijalankan sekali, berurutan menurut nama, lalu dicatat di tabel schema_migrations.
 * Dipanggil dari config/database.php, jadi cukup deploy kodenya: request pertama yang masuk
 * menyelesaikan migrasi. Migrasi harus idempotent (aman dijalankan ulang di database yang
 * sebagian sudah diubah). Kalau satu migrasi gagal, errornya masuk error_log, request tetap
 * jalan, dan migrasi itu dicoba lagi di request berikutnya.
 */
function ddRunMigrations(PDO $pdo, string $dir): void
{
    static $alreadyRan = false;
    if ($alreadyRan) {
        return;
    }
    $alreadyRan = true;

    $files = glob(rtrim($dir, '/') . '/*.php') ?: [];
    if (!$files) {
        return;
    }
    sort($files, SORT_STRING);

    try {
        $pending = ddPendingMigrations($pdo, $files);
        if ($pending) {
            ddApplyMigrations($pdo, $pending);
        }
    } catch (Throwable $e) {
        error_log('[migrate] ' . $e->getMessage());
    }
}

/** @return string[] migrasi yang belum tercatat; membuat tabel pencatat kalau belum ada. */
function ddPendingMigrations(PDO $pdo, array $files): array
{
    $applied = ddAppliedMigrations($pdo);
    return array_values(array_filter($files, static fn(string $file): bool => !isset($applied[basename($file)])));
}

/** @return array<string,int> nama migrasi yang sudah jalan (sebagai key). */
function ddAppliedMigrations(PDO $pdo): array
{
    try {
        $names = $pdo->query('SELECT name FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        // Tabel pencatat belum ada (deploy pertama).
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                name VARCHAR(190) NOT NULL PRIMARY KEY,
                applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        $names = [];
    }
    return array_flip($names);
}

function ddApplyMigrations(PDO $pdo, array $pending): void
{
    // Kunci supaya dua request bersamaan tidak menjalankan ALTER yang sama.
    if ((int) $pdo->query("SELECT GET_LOCK('dd_migrations', 20)")->fetchColumn() !== 1) {
        return;
    }
    try {
        // Baca ulang: request lain mungkin baru selesai saat kita menunggu kunci.
        $applied = ddAppliedMigrations($pdo);
        foreach ($pending as $file) {
            $name = basename($file);
            if (isset($applied[$name])) {
                continue;
            }
            $migration = require $file;
            if (!is_callable($migration)) {
                throw new RuntimeException("Migration {$name} must return a callable.");
            }
            $migration($pdo);
            $pdo->prepare('INSERT INTO schema_migrations (name) VALUES (:name)')->execute(['name' => $name]);
        }
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('dd_migrations')")->fetchColumn();
    }
}
