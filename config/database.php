<?php
// config/database.php

require_once __DIR__ . '/env.php';

// Lokal (XAMPP) pakai nilai default; di hosting isi DB_* di config/secrets.php.
$host    = ddSecret('DB_HOST', 'localhost');
$db      = ddSecret('DB_NAME', 'simple');
$user    = ddSecret('DB_USER', 'root');
$pass    = ddSecret('DB_PASS', '');
$charset = 'utf8mb4';

$dsn = "mysql:host={$host};dbname={$db};charset={$charset}";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // Detail (host/user DB) hanya ke error_log, jangan ke pengunjung
    error_log('[database] connection failed: ' . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(503);
        header('Retry-After: 120');
        header('Content-Type: text/html; charset=utf-8');
    }
    exit('<!doctype html><meta charset="utf-8"><title>Temporarily unavailable</title>'
        . '<p style="font-family:sans-serif;text-align:center;margin-top:20vh">'
        . 'We are temporarily unavailable. Please try again in a few minutes.</p>');
}

// Migrasi otomatis (migrations/*.php): deploy kode saja, tidak perlu jalankan SQL manual.
require_once __DIR__ . '/../includes/migrate.php';
ddRunMigrations($pdo, __DIR__ . '/../migrations');

/**
 * Get database connection instance
 * @return PDO Database connection
 */
function db()
{
    global $pdo;
    return $pdo;
}

/**
 * Get global settings from the database
 * @return array Associative array of settings
 */
function getSettings(): array
{
    global $pdo;
    
    try {
        $stmt = $pdo->query('SELECT * FROM settings LIMIT 1');
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Return default values if no settings found
        if (!$settings) {
            return [
                'brand_name' => 'BRAND',
                'meta_title' => 'Lookbook',
                'meta_description' => ''
            ];
        }
        
        return $settings;
    } catch (PDOException $e) {
        // Return defaults on database error
        return [
            'brand_name' => 'BRAND',
            'meta_title' => 'Lookbook',
            'meta_description' => ''
        ];
    }
}

/*
|--------------------------------------------------------------------------
| PayPal settings
|--------------------------------------------------------------------------
| Mode 'sandbox' untuk testing, 'live' untuk produksi.
| Client ID & Secret ambil dari PayPal Developer Dashboard.
*/

const PAYPAL_MODE = 'live'; // ganti ke 'sandbox' untuk testing
define('PAYPAL_CLIENT_ID',     ddSecret('PAYPAL_CLIENT_ID'));
define('PAYPAL_CLIENT_SECRET', ddSecret('PAYPAL_CLIENT_SECRET'));
