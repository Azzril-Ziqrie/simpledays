<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/stripe.php';
require_once __DIR__ . '/order_payment.php';
require_once __DIR__ . '/email_helpers.php';

/** Panggil Stripe REST API (tanpa SDK). Melempar RuntimeException kalau gagal. */
function stripeRequest(string $method, string $path, array $params = []): array
{
    if (!STRIPE_ENABLED) throw new RuntimeException('Stripe is not configured.');

    $ch = curl_init('https://api.stripe.com/v1/' . ltrim($path, '/'));
    $opts = [
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_USERPWD        => STRIPE_SECRET_KEY . ':',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 25,
    ];
    if ($params) {
        if (strtoupper($method) === 'GET') $opts[CURLOPT_URL] .= '?' . http_build_query($params);
        else $opts[CURLOPT_POSTFIELDS] = http_build_query($params);
    }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    if ($raw === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Stripe connection failed: ' . $err);
    }
    curl_close($ch);

    $data = json_decode((string)$raw, true);
    if (!is_array($data)) throw new RuntimeException('Invalid response from Stripe.');
    if (isset($data['error'])) throw new RuntimeException('Stripe: ' . (string)($data['error']['message'] ?? 'request failed'));
    return $data;
}

/** Nominal tagihan Stripe dalam sen USD, dihitung dari database (bukan dari browser). */
function ddStripeAmountCents(array $order): int
{
    $charge = ddPaypalCharge($order);
    return (int)round(((float)$charge['value']) * 100);
}

/**
 * Validasi PaymentIntent terhadap order, lalu tandai lunas (idempotent) dan kirim invoice.
 * @return array{ok: bool, error: ?string, invoice_mail: string}
 */
function ddStripeFinalizeIntent(PDO $pdo, array $order, array $pi): array
{
    if (($pi['status'] ?? '') !== 'succeeded') {
        return ['ok' => false, 'error' => 'Payment is not completed.', 'invoice_mail' => 'failed'];
    }
    if ((string)($pi['metadata']['order_number'] ?? '') !== (string)$order['order_number']) {
        return ['ok' => false, 'error' => 'Payment does not belong to this order.', 'invoice_mail' => 'failed'];
    }
    if (strtolower((string)($pi['currency'] ?? '')) !== 'usd' || (int)($pi['amount_received'] ?? 0) !== ddStripeAmountCents($order)) {
        return ['ok' => false, 'error' => 'Payment amount does not match the order total.', 'invoice_mail' => 'failed'];
    }

    $piId = (string)$pi['id'];
    $paid = ddMarkOrderPaid($pdo, (int)$order['id'], 'stripe', $piId, 'Paid via Stripe (Apple Pay / Google Pay). PaymentIntent: ' . $piId);

    $mail = 'failed';
    if (!$paid['already_paid']) {
        $st = $pdo->prepare('SELECT * FROM orders WHERE id = :id LIMIT 1');
        $st->execute(['id' => $order['id']]);
        $mail = sendDDInvoiceEmail($pdo, $st->fetch(PDO::FETCH_ASSOC) ?: $order) ? 'sent' : 'failed';
    }
    return ['ok' => true, 'error' => null, 'invoice_mail' => $mail];
}
