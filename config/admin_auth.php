<?php
/**
 * Konfigurasi login admin (password tunggal).
 *
 * password_hash = hasil password_hash(), BUKAN password asli. Cara mengganti password:
 *   php -r 'echo password_hash("PASSWORD_BARU", PASSWORD_DEFAULT), PHP_EOL;'
 * lalu simpan hasilnya sebagai ADMIN_PASSWORD_HASH di config/secrets.php (atau environment variable).
 */
require_once __DIR__ . '/env.php';

return [
    'password_hash' => ddSecret('ADMIN_PASSWORD_HASH'),
    'session_ttl'   => 8 * 3600,  // login otomatis kedaluwarsa setelah 8 jam
    'max_attempts'  => 5,         // salah password berapa kali sebelum dikunci
    'lock_seconds'  => 15 * 60,   // lama penguncian per IP
];
