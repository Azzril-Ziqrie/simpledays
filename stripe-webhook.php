<?php
declare(strict_types=1);

/**
 * Cadangan: kalau pembeli menutup tab sebelum kembali ke toko, Stripe tetap memberi tahu kita di sini.
 * Daftarkan https://DOMAIN/stripe-webhook.php di Stripe Dashboard → Developers → Webhooks,
 * event: payment_intent.succeeded, lalu simpan signing secret (whsec_...) sebagai STRIPE_WEBHOOK_SECRET.
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/stripe_helper.php';

$payload = (string)file_get_contents('php://input');
$sig     = (string)($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');

if (STRIPE_WEBHOOK_SECRET === '') { http_response_code(503); exit('Webhook secret not set'); }

$timestamp = ''; $signatures = [];
foreach (explode(',', $sig) as $part) {
    $kv = explode('=', trim($part), 2);
    if (count($kv) !== 2) continue;
    if ($kv[0] === 't') $timestamp = $kv[1];
    if ($kv[0] === 'v1') $signatures[] = $kv[1];
}
$expected = hash_hmac('sha256', $timestamp . '.' . $payload, STRIPE_WEBHOOK_SECRET);
$valid = false;
foreach ($signatures as $s) { if (hash_equals($expected, $s)) $valid = true; }
if (!$valid || $timestamp === '' || abs(time() - (int)$timestamp) > 600) { http_response_code(400); exit('Bad signature'); }

$event = json_decode($payload, true);
if (is_array($event) && ($event['type'] ?? '') === 'payment_intent.succeeded') {
    try {
        $pi = $event['data']['object'] ?? [];
        $on = (string)($pi['metadata']['order_number'] ?? '');
        if ($on !== '') {
            $st = $pdo->prepare('SELECT * FROM orders WHERE order_number = :n LIMIT 1');
            $st->execute(['n' => $on]);
            $order = $st->fetch(PDO::FETCH_ASSOC);
            if ($order) {
                $r = ddStripeFinalizeIntent($pdo, $order, $pi);
                if (!$r['ok']) error_log('[stripe] webhook rejected ' . $on . ': ' . $r['error']);
            }
        }
    } catch (Throwable $e) {
        error_log('[stripe] webhook failed: ' . $e->getMessage());
        http_response_code(500);
        exit;
    }
}
http_response_code(200);
echo 'ok';
