<?php
/**
 * Konfigurasi Kasera Pay.
 *
 * KASERA_API_KEY & KASERA_WEBHOOK_SECRET dibaca dari environment variable server
 * atau dari config/secrets.php (tidak di-commit) — lihat config/env.php.
 *
 * Ambil API key & webhook secret di dashboard: Developer > API keys / Webhooks.
 * https://pay.kasera.id/app/login
 */

require_once __DIR__ . '/env.php';

if (!defined('KASERA_API_BASE_URL')) {
    define('KASERA_API_BASE_URL', 'https://pay.kasera.id/v1');
}

if (!defined('KASERA_ENABLED')) {
    // Sakelar pembayaran Kasera (QRIS, Virtual Account, e-wallet). Mati kecuali KASERA_ENABLED=1.
    define('KASERA_ENABLED', ddSecret('KASERA_ENABLED') === '1');
}

if (!defined('KASERA_API_KEY')) {
    // kp_live_... untuk transaksi sungguhan, kp_test_... untuk mode tes.
    define('KASERA_API_KEY', ddSecret('KASERA_API_KEY'));
}

if (!defined('KASERA_WEBHOOK_SECRET')) {
    // Signing secret webhook (beda untuk mode live & tes — pastikan sesuai mode).
    define('KASERA_WEBHOOK_SECRET', ddSecret('KASERA_WEBHOOK_SECRET'));
}

/**
 * Base URL situs Anda sendiri, dipakai untuk membentuk return_url ke Kasera Pay
 * (misal: https://thesimpledays.co). Set via env SITE_BASE_URL kalau berbeda,
 * kalau tidak akan ditebak otomatis dari request saat ini.
 */
if (!defined('SITE_BASE_URL')) {
    define('SITE_BASE_URL', 'https://thesimpledays.co');
}