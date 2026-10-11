<?php
/**
 * Pembaca rahasia (API key, password, token) untuk seluruh aplikasi.
 *
 * Urutan: environment variable server → config/secrets.php (tidak di-commit) → $default.
 * Salin config/secrets.example.php menjadi config/secrets.php lalu isi nilainya.
 */

function ddSecret(string $key, string $default = ''): string
{
    static $fileValues = null;

    $fromEnv = getenv($key);
    if ($fromEnv !== false && $fromEnv !== '') return (string)$fromEnv;

    if ($fileValues === null) {
        $path = __DIR__ . '/secrets.php';
        $loaded = is_file($path) ? require $path : [];
        $fileValues = is_array($loaded) ? $loaded : [];
    }
    $value = $fileValues[$key] ?? '';
    return $value !== '' ? (string)$value : $default;
}
