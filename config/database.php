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
    die('Koneksi database gagal: ' . $e->getMessage());
}

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
