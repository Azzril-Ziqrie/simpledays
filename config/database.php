<?php
// config/database.php

$host    = 'localhost';
$db      = 'simple';
$user    = 'root';
$pass    = '';
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

const PAYPAL_MODE          = 'live'; // ganti ke 'live' saat go-live
const PAYPAL_CLIENT_ID     = 'BAATGMaHhx2z3Mi-MYXW4JKjs_Mgj8p7R9uG7J8fdwE7Ytd_4Mikz6BXc-QnOTFfHlEwhg8lGd1NMmHBlE';
const PAYPAL_CLIENT_SECRET = 'EH2MYMpLWTVXKt0rm1J0RhB4OxTgKw3wk36jIj56rdGE8r3hptQSUMSU4wNhXuj-weDNpHXVctpkiHIl';
