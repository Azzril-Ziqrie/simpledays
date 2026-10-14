<?php
/**
 * Fungsi login admin (sesi + batas percobaan). Dipakai oleh _guard.php, login.php, logout.php.
 * File ini hanya mendefinisikan fungsi dan tidak menampilkan apa pun.
 */

require_once __DIR__ . '/../includes/brand.php';

function ddAdminConfig(): array
{
    static $cfg = null;
    if ($cfg === null) $cfg = require __DIR__ . '/../config/admin_auth.php';
    return $cfg;
}

function ddAdminIsHttps(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

/** Sesi khusus admin: nama & path cookie terpisah dari sesi toko, HttpOnly + SameSite. */
function ddAdminStartSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $path = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/admin/x.php')), '/') . '/';
    session_name('DDADMINSESS');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => $path,
        'secure'   => ddAdminIsHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/* ── password: disimpan di DB (admin_settings) supaya bisa diganti dari dashboard; fallback ke config ── */

function ddAdminDb(): ?PDO
{
    global $pdo;
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        require_once __DIR__ . '/../config/database.php';
    }
    return (isset($pdo) && $pdo instanceof PDO) ? $pdo : null;
}

/** Hash password yang berlaku: baris di admin_settings kalau ada, kalau tidak hash di config/admin_auth.php. */
function ddAdminCurrentHash(bool $refresh = false): string
{
    static $hash = null;
    if ($hash !== null && !$refresh) return $hash;
    $hash = (string)(ddAdminConfig()['password_hash'] ?? '');
    try {
        $db = ddAdminDb();
        if ($db) {
            $st = $db->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = 'password_hash' LIMIT 1");
            $st->execute();
            $v = $st->fetchColumn();
            if (is_string($v) && $v !== '') $hash = $v;
        }
    } catch (Throwable $e) {
        // tabel belum ada (belum pernah ganti password) → pakai hash di config
    }
    return $hash;
}

/** Sidik jari hash saat ini; sesi login yang tidak cocok (password sudah diganti) dianggap kedaluwarsa. */
function ddAdminPwVersion(): string
{
    return substr(hash('sha256', ddAdminCurrentHash()), 0, 24);
}

/** Simpan password baru (di-hash). Return null kalau sukses, atau pesan error. */
function ddAdminSetPassword(string $newPlain): ?string
{
    try {
        $db = ddAdminDb();
        if (!$db) return 'Database is not available.';
        $db->exec("CREATE TABLE IF NOT EXISTS admin_settings (
            setting_key   VARCHAR(50) NOT NULL PRIMARY KEY,
            setting_value TEXT NOT NULL,
            updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->prepare("INSERT INTO admin_settings (setting_key, setting_value) VALUES ('password_hash', :h)
                      ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")
           ->execute(['h' => password_hash($newPlain, PASSWORD_DEFAULT)]);
        ddAdminCurrentHash(true);
        return null;
    } catch (Throwable $e) {
        error_log('[admin] set password failed: ' . $e->getMessage());
        return 'Could not save the new password. Please try again.';
    }
}

function ddAdminCsrf(): string
{
    ddAdminStartSession();
    if (empty($_SESSION['admin_csrf'])) $_SESSION['admin_csrf'] = bin2hex(random_bytes(16));
    return (string)$_SESSION['admin_csrf'];
}

function ddAdminIsLoggedIn(): bool
{
    ddAdminStartSession();
    if (empty($_SESSION['admin_ok'])) return false;
    $ttl = (int)(ddAdminConfig()['session_ttl'] ?? 28800);
    $expired = time() - (int)($_SESSION['admin_last'] ?? 0) > $ttl;
    // Password sudah diganti sejak sesi ini login → paksa login ulang
    $stale = !hash_equals((string)($_SESSION['admin_pw_ver'] ?? ''), ddAdminPwVersion());
    if ($expired || $stale) {
        ddAdminLogout();
        ddAdminStartSession();
        return false;
    }
    $_SESSION['admin_last'] = time();
    return true;
}

function ddAdminLoginOk(): void
{
    ddAdminStartSession();
    session_regenerate_id(true);
    $_SESSION['admin_ok']     = true;
    $_SESSION['admin_last']   = time();
    $_SESSION['admin_pw_ver'] = ddAdminPwVersion();
    // approve-payment.php / reject-payment.php membaca id admin dari sini (dicatat sebagai verifier)
    $_SESSION['admin'] = ['id' => 1, 'via' => 'password'];
}

function ddAdminLogout(): void
{
    ddAdminStartSession();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/* ── batas percobaan login (per IP, disimpan di folder temp) ── */
function ddAdminAttemptFile(): string
{
    return sys_get_temp_dir() . '/dd-admin-login-' . md5($_SERVER['REMOTE_ADDR'] ?? 'x') . '.json';
}

function ddAdminAttemptState(): array
{
    $f = ddAdminAttemptFile();
    $d = is_file($f) ? json_decode((string)file_get_contents($f), true) : null;
    return is_array($d) ? $d + ['count' => 0, 'locked_until' => 0] : ['count' => 0, 'locked_until' => 0];
}

/** Sisa detik penguncian (0 = tidak terkunci). */
function ddAdminLockedSeconds(): int
{
    $s = ddAdminAttemptState();
    return max(0, (int)$s['locked_until'] - time());
}

function ddAdminRegisterFailure(): void
{
    $cfg = ddAdminConfig();
    $s = ddAdminAttemptState();
    $s['count']++;
    if ($s['count'] >= (int)$cfg['max_attempts']) {
        $s['locked_until'] = time() + (int)$cfg['lock_seconds'];
        $s['count'] = 0;
    }
    @file_put_contents(ddAdminAttemptFile(), json_encode($s));
}

function ddAdminClearFailures(): void
{
    @unlink(ddAdminAttemptFile());
}

/** Hanya izinkan redirect ke file .php di folder admin yang sama (cegah open redirect). */
function ddAdminSafeNext(?string $next): string
{
    $next = (string)$next;
    return preg_match('/^[A-Za-z0-9_\-]+\.php(\?[A-Za-z0-9_=&%.\-]*)?$/', $next) ? $next : 'dashboard.php';
}
