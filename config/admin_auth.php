<?php
/**
 * Konfigurasi login admin (password tunggal).
 *
 * password_hash = hasil password_hash(), BUKAN password asli. Cara mengganti password:
 *   php -r 'echo password_hash("PASSWORD_BARU", PASSWORD_DEFAULT), PHP_EOL;'
 * lalu tempel hasilnya di bawah (atau set environment variable ADMIN_PASSWORD_HASH).
 */
return [
    'password_hash' => getenv('ADMIN_PASSWORD_HASH') ?: '$2y$10$eY.IASFCF3zfKMaMMAi0DuHje4cuy/JUYhXQMvixQr6i72zqrfGM2',
    'session_ttl'   => 8 * 3600,  // login otomatis kedaluwarsa setelah 8 jam
    'max_attempts'  => 5,         // salah password berapa kali sebelum dikunci
    'lock_seconds'  => 15 * 60,   // lama penguncian per IP
];
