<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/currency_helper.php';
require_once __DIR__ . '/includes/promo.php';

header('Content-Type: application/json; charset=utf-8');

function ppfRespond(int $status, bool $ok, string $message = '', array $extra = []): void
{
    http_response_code($status);
    echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ppfRespond(405, false, 'Method not allowed.');
}

$action = isset($_POST['action']) ? (string) $_POST['action'] : '';
if (!in_array($action, ['login', 'register'], true)) {
    // Jangan log body mentah: bisa berisi password
    error_log('[auth_api] Unknown action. POST keys: ' . implode(',', array_keys($_POST)));
    ppfRespond(400, false, 'Unknown action.');
}

/* ── batas percobaan login: per IP dan per email, disimpan di folder temp ── */
const PPF_LOGIN_MAX_ATTEMPTS = 5;
const PPF_LOGIN_LOCK_SECONDS = 900; // 15 menit

function ppfAttemptFile(string $key): string
{
    return sys_get_temp_dir() . '/sd-login-' . hash('sha256', $key) . '.json';
}

function ppfAttemptState(string $key): array
{
    $f = ppfAttemptFile($key);
    $d = is_file($f) ? json_decode((string)@file_get_contents($f), true) : null;
    return is_array($d) ? $d + ['count' => 0, 'locked_until' => 0] : ['count' => 0, 'locked_until' => 0];
}

/** Sisa detik penguncian terlama dari semua kunci (0 = boleh mencoba). */
function ppfLockedSeconds(array $keys): int
{
    $max = 0;
    foreach ($keys as $k) $max = max($max, (int)ppfAttemptState($k)['locked_until'] - time());
    return $max;
}

function ppfRegisterFailure(array $keys): void
{
    foreach ($keys as $k) {
        $s = ppfAttemptState($k);
        $s['count']++;
        if ($s['count'] >= PPF_LOGIN_MAX_ATTEMPTS) {
            $s['locked_until'] = time() + PPF_LOGIN_LOCK_SECONDS;
            $s['count'] = 0;
        }
        @file_put_contents(ppfAttemptFile($k), json_encode($s), LOCK_EX);
    }
}

function ppfClearFailures(array $keys): void
{
    foreach ($keys as $k) @unlink(ppfAttemptFile($k));
}

try {
    if ($action === 'login') {
        $email    = trim((string)($_POST['email']    ?? ''));
        $password = (string)($_POST['password']      ?? '');

        if ($email === '' || $password === '') {
            ppfRespond(422, false, 'Please enter your email and password.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            ppfRespond(422, false, 'Please enter a valid email address.');
        }

        $attemptKeys = [
            'ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'x'),
            'email:' . strtolower($email),
        ];
        $locked = ppfLockedSeconds($attemptKeys);
        if ($locked > 0) {
            ppfRespond(429, false, 'Too many sign-in attempts. Please try again in ' . (int)ceil($locked / 60) . ' minute(s).');
        }

        $stmt = $pdo->prepare(
            'SELECT id, full_name, email, password, role, is_active
             FROM users WHERE email = :email LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Satu pesan untuk semua kegagalan, supaya tidak membocorkan email mana yang terdaftar
        $valid = $user
            && (int)($user['is_active'] ?? 0) === 1
            && password_verify($password, (string)$user['password']);
        if (!$valid) {
            ppfRegisterFailure($attemptKeys);
            ppfRespond(401, false, 'Invalid email or password.');
        }

        ppfClearFailures($attemptKeys);
        session_regenerate_id(true); // cegah session fixation
        $_SESSION['user'] = [
            'id'        => (int)$user['id'],
            'full_name' => $user['full_name'],
            'email'     => $user['email'],
            'role'      => $user['role'],
        ];

        $redirect = ($user['role'] === 'admin') ? 'admin/dashboard.php' : 'index.php';
        ppfRespond(200, true, 'Signed in.', [
            'user'     => $_SESSION['user'],
            'redirect' => $redirect,
        ]);
    }

    // register
    $fullName        = trim((string)($_POST['full_name']        ?? ''));
    $email           = trim((string)($_POST['email']            ?? ''));
    $password        = (string)($_POST['password']              ?? '');
    $confirmPassword = (string)($_POST['confirm_password']      ?? '');

    if ($fullName === '' || $email === '' || $password === '' || $confirmPassword === '') {
        ppfRespond(422, false, 'Please complete all required fields.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        ppfRespond(422, false, 'Please enter a valid email address.');
    }
    if (strlen($password) < 6) {
        ppfRespond(422, false, 'Password must be at least 6 characters.');
    }
    if ($password !== $confirmPassword) {
        ppfRespond(422, false, 'Password confirmation does not match.');
    }

    $check = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $check->execute(['email' => $email]);
    if ($check->fetch()) {
        ppfRespond(409, false, 'This email is already registered.');
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $insert = $pdo->prepare(
        'INSERT INTO users (full_name, email, password, role, is_active)
         VALUES (:full_name, :email, :password, :role, :is_active)'
    );
    $insert->execute([
        'full_name' => $fullName,
        'email'     => $email,
        'password'  => $hashed,
        'role'      => 'customer',
        'is_active' => 1,
    ]);

    session_regenerate_id(true); // cegah session fixation
    $_SESSION['user'] = [
        'id'        => (int)$pdo->lastInsertId(),
        'full_name' => $fullName,
        'email'     => $email,
        'role'      => 'customer',
    ];

    // Reward akun baru (kode promo pilihan admin), kalau sedang aktif
    $reward = promoGrantSignupReward($pdo, $email, $fullName);

    ppfRespond(200, true, 'Account created.', [
        'user'     => $_SESSION['user'],
        'redirect' => 'index.php',
        'reward'   => $reward,
    ]);
} catch (Throwable $e) {
    error_log('[auth_api] ' . $e->getMessage());
    ppfRespond(500, false, 'Server error. Please try again.');
}
