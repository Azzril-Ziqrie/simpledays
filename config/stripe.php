<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';

/*
| Stripe — dipakai KHUSUS untuk Apple Pay & Google Pay (Express Checkout Element).
| Kunci diambil dari config/secrets.php / secrets.local.php atau environment variable:
|   STRIPE_SECRET_KEY, STRIPE_PUBLISHABLE_KEY, STRIPE_WEBHOOK_SECRET (opsional, untuk stripe-webhook.php)
| sk_test_/pk_test_ = sandbox. Ganti ke sk_live_/pk_live_ saat produksi.
*/
if (!defined('STRIPE_SECRET_KEY')) {
    define('STRIPE_SECRET_KEY',      ddSecret('STRIPE_SECRET_KEY'));
    define('STRIPE_PUBLISHABLE_KEY', ddSecret('STRIPE_PUBLISHABLE_KEY'));
    define('STRIPE_WEBHOOK_SECRET',  ddSecret('STRIPE_WEBHOOK_SECRET'));
    define('STRIPE_ENABLED', STRIPE_SECRET_KEY !== '' && STRIPE_PUBLISHABLE_KEY !== '');
}
