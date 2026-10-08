<?php
/**
 * Konfigurasi Kasera Pay.
 *
 * PALING AMAN: isi KASERA_API_KEY & KASERA_WEBHOOK_SECRET lewat environment
 * variable server (misalnya di .env yang di-load sebelum file ini, atau di
 * konfigurasi vhost/panel hosting). Kalau tidak bisa, isi langsung di bawah —
 * TAPI JANGAN commit file ini ke repo publik kalau begitu.
 *
 * Ambil API key & webhook secret di dashboard: Developer > API keys / Webhooks.
 * https://pay.kasera.id/app/login
 */

if (!defined('KASERA_API_BASE_URL')) {
    define('KASERA_API_BASE_URL', 'https://pay.kasera.id/v1');
}

if (!defined('KASERA_API_KEY')) {
    // kp_live_... untuk transaksi sungguhan, kp_test_... untuk mode tes.
    define('KASERA_API_KEY', getenv('KASERA_API_KEY') ?: 'kp_live_Q2mv_F0UuJqGJS8djxpH60Rj0WcG2T2fTb2eg2gzSxs');
}

if (!defined('KASERA_WEBHOOK_SECRET')) {
    // Signing secret webhook (beda untuk mode live & tes — pastikan sesuai mode).
    define('KASERA_WEBHOOK_SECRET', getenv('KASERA_WEBHOOK_SECRET') ?: 'whsec_-Wz93onueE6BuRscCafKcFakTCGx3Wa991H1bhEQgBQ');
}

/**
 * Base URL situs Anda sendiri, dipakai untuk membentuk return_url ke Kasera Pay
 * (misal: https://deepdimes.com). Set via env SITE_BASE_URL kalau berbeda,
 * kalau tidak akan ditebak otomatis dari request saat ini.
 */
if (!defined('SITE_BASE_URL')) {
    define('SITE_BASE_URL', 'https://deepdimes.com');
}