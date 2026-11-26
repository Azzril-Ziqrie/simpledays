<?php
/**
 * Pengaturan password lock storefront (includes/site_gate.php). Diatur dari admin/dashboard-site-lock.php,
 * disimpan sebagai satu baris JSON di admin_settings (key "site_lock"). Password disimpan sebagai hash.
 *
 * Kalau belum pernah disimpan dari dashboard, perilaku lama dipakai: aktif bila SITE_LOCK=1 di secrets,
 * dengan password bawaan SITE_LOCK_LEGACY_PASSWORD.
 */
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/db_schema.php';

const SITE_LOCK_KEY             = 'site_lock';
const SITE_LOCK_LEGACY_PASSWORD = 'simpledays';
const SITE_LOCK_MIN_LENGTH      = 4;
const SITE_LOCK_MAX_LENGTH      = 72; // batas bcrypt

/** @return array{enabled: bool, password_hash: string, version: int, saved: bool} */
function siteLockSettings(PDO $pdo): array
{
    $fallback = [
        'enabled'       => ddSecret('SITE_LOCK') === '1',
        'password_hash' => '',
        'version'       => 0,
        'saved'         => false,
    ];
    if (!tableExists($pdo, 'admin_settings')) {
        return $fallback;
    }
    try {
        $stmt = $pdo->prepare('SELECT setting_value FROM admin_settings WHERE setting_key = :k LIMIT 1');
        $stmt->execute(['k' => SITE_LOCK_KEY]);
        $saved = json_decode((string)$stmt->fetchColumn(), true);
    } catch (Throwable $e) {
        error_log('[site_lock] read failed: ' . $e->getMessage());
        return $fallback;
    }
    if (!is_array($saved)) {
        return $fallback;
    }
    return [
        'enabled'       => !empty($saved['enabled']),
        'password_hash' => (string)($saved['password_hash'] ?? ''),
        'version'       => (int)($saved['version'] ?? 0),
        'saved'         => true,
    ];
}

/** Cek password pengunjung terhadap hash tersimpan (atau password bawaan kalau belum pernah diatur). */
function siteLockVerify(array $settings, string $password): bool
{
    if ($settings['password_hash'] === '') {
        return hash_equals(SITE_LOCK_LEGACY_PASSWORD, $password);
    }
    return password_verify($password, $settings['password_hash']);
}

/**
 * Simpan pengaturan. $newPassword null = password tidak diganti.
 * Mengganti password menaikkan version → semua pengunjung yang sudah masuk harus memasukkan password baru.
 */
function siteLockSave(PDO $pdo, bool $enabled, ?string $newPassword): void
{
    $current = siteLockSettings($pdo);
    $hash    = $current['password_hash'];
    $version = $current['version'];
    if ($newPassword !== null) {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $version++;
    }

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
    )->execute(['k' => SITE_LOCK_KEY, 'v' => json_encode([
        'enabled'       => $enabled,
        'password_hash' => $hash,
        'version'       => $version,
    ])]);
}
