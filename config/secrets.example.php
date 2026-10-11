<?php
/**
 * Contoh isi config/secrets.php. Salin file ini ke config/secrets.php dan isi nilai aslinya.
 * config/secrets.php sudah masuk .gitignore — JANGAN di-commit.
 * Nilai yang sama juga bisa diset lewat environment variable server dengan nama kunci yang sama.
 */
return [
    // PayPal (developer.paypal.com → Apps & Credentials)
    'PAYPAL_CLIENT_ID'     => '',
    'PAYPAL_CLIENT_SECRET' => '',

    // Kasera Pay (pay.kasera.id → Developer)
    'KASERA_API_KEY'        => '',
    'KASERA_WEBHOOK_SECRET' => '',

    // RajaOngkir / Komerce
    'RAJAONGKIR_API_KEY' => '',

    // exchangerate-api.com
    'EXCHANGERATE_API_KEY' => '',

    // SMTP email invoice & resi
    'SMTP_PASSWORD'       => '',   // dipakai includes/email_helpers.php
    'ADMIN_SMTP_PASSWORD' => '',   // dipakai admin/send-resi.php

    // Hash password admin: php -r 'echo password_hash("PASSWORD_BARU", PASSWORD_DEFAULT), PHP_EOL;'
    'ADMIN_PASSWORD_HASH' => '',
];
