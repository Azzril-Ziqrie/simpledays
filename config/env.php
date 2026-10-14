<?php
/**
 * Pembaca rahasia (API key, password, token) untuk seluruh aplikasi.
 *
 * Urutan: environment variable server → config/secrets.local.php (override lokal, tidak di-commit)
 *         → config/secrets.php (nilai produksi, repo private) → $default.
 */

function ddSecret(string $key, string $default = ''): string
{
    static $fileValues = null;

    $fromEnv = getenv($key);
    if ($fromEnv !== false && $fromEnv !== '') return (string)$fromEnv;

    if ($fileValues === null) {
        $fileValues = [];
        foreach (['secrets.php', 'secrets.local.php'] as $name) { // yang belakangan menimpa
            $path = __DIR__ . '/' . $name;
            $loaded = is_file($path) ? require $path : [];
            if (is_array($loaded)) $fileValues = array_replace($fileValues, array_filter($loaded, fn($v) => $v !== ''));
        }
    }
    $value = $fileValues[$key] ?? '';
    return $value !== '' ? (string)$value : $default;
}
