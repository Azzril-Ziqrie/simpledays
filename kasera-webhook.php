<?php
/**
 * Endpoint Webhook Kasera Pay (Debug Mode)
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/kasera_client.php';
require_once __DIR__ . '/includes/order_payment.php';

// 1. Ambil data mentah dari Kasera Pay
$rawBody = file_get_contents('php://input');

// Simpan payload ke log untuk inspeksi format JSON asli dari Kasera Pay
file_put_contents(__DIR__ . '/webhook_debug.log', date('[Y-m-d H:i:s] ') . $rawBody . PHP_EOL, FILE_APPEND);

if (empty($rawBody)) {
    http_response_code(400);
    exit('Empty Payload');
}

// 2. Ambil Header Signature (Mendukung Apache/Nginx/cPanel)
$signatureHeaderV1 = $_SERVER['HTTP_KASERA_SIGNATURE_V1'] 
    ?? $_SERVER['KASERA_SIGNATURE_V1'] 
    ?? getallheaders()['Kasera-Signature-V1'] 
    ?? getallheaders()['kasera-signature-v1'] 
    ?? '';

$signatureHeaderLegacy = $_SERVER['HTTP_KASERA_SIGNATURE'] 
    ?? $_SERVER['KASERA_SIGNATURE'] 
    ?? getallheaders()['Kasera-Signature'] 
    ?? getallheaders()['kasera-signature'] 
    ?? '';

// 3. Verifikasi Signature
$secret  = defined('KASERA_WEBHOOK_SECRET') ? KASERA_WEBHOOK_SECRET : '';
$isValid = false;

// Tanpa secret yang terkonfigurasi, signature tidak mungkin diverifikasi: tolak (fail closed).
if ($secret === '') {
    error_log('[kasera-webhook] KASERA_WEBHOOK_SECRET is not configured');
    http_response_code(500);
    exit('Webhook is not configured');
}

if (!empty($signatureHeaderV1)) {
    $isValid = kaseraVerifyWebhookSignatureV1($rawBody, $signatureHeaderV1, $secret);
} elseif (!empty($signatureHeaderLegacy)) {
    $isValid = kaseraVerifyWebhookSignatureLegacy($rawBody, $signatureHeaderLegacy, $secret);
}

// JIKA DARI EVENT PERCOBAAN / TEST: Tolak dengan aman jika signature tidak valid
if (!$isValid) {
    // Catat kegagalan signature ke debug log
    file_put_contents(__DIR__ . '/webhook_debug.log', date('[Y-m-d H:i:s] ') . 'Signature Invalid!' . PHP_EOL, FILE_APPEND);
    
    http_response_code(400);
    exit('Invalid Signature Verification');
}

// 4. Parse JSON
$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    http_response_code(400);
    exit('Invalid JSON');
}

// 5. Penanganan Event Percobaan / Payload Dummy Kasera
// Event percobaan biasanya memakai id dummy atau external_id dummy
$event = $payload['event'] ?? $payload['type'] ?? '';
$data  = $payload['data'] ?? $payload;
$orderNumber = $data['external_id'] ?? $data['merchant_ref'] ?? '';

// Jika ini event tes dari tombol Dashboard Kasera (tidak ada orderNumber di database)
if (empty($orderNumber) || str_contains(strtolower((string)$orderNumber), 'test')) {
    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Test event received successfully']);
    exit;
}

// 6. Proses Transaksi Asli ke Database
try {
    $pdo = db();
    
    $txStatus = strtolower((string)($data['status'] ?? ''));
    $isPaid = in_array($event, ['payment.paid', 'payment.success', 'transaction.paid', 'transaction.completed', 'paid'], true)
        || kaseraIsPaidStatus($txStatus);
    $isExpired = in_array($event, ['payment.expired', 'transaction.expired', 'expired'], true) || $txStatus === 'expired';

    if ($isPaid) {
        $ordStmt = $pdo->prepare('SELECT id FROM orders WHERE order_number = :on LIMIT 1');
        $ordStmt->execute(['on' => $orderNumber]);
        $ordId = (int)$ordStmt->fetchColumn();
        if ($ordId > 0) {
            // Idempotent: webhook yang dikirim ulang tidak memproses order yang sudah lunas lagi.
            $paid = ddMarkOrderPaid($pdo, $ordId, 'kasera_pay', (string)($data['id'] ?? ''), 'Payment confirmed via Kasera Webhook');
            if (!$paid['already_paid']) kaseraSendInvoiceSafe($pdo, $ordId);
        }
    } elseif ($isExpired) {
        $pdo->prepare("UPDATE orders SET payment_status = 'expired', updated_at = NOW() WHERE order_number = :on AND payment_status = 'pending'")
            ->execute(['on' => $orderNumber]);
    }

    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Webhook processed']);

} catch (Throwable $e) {
    file_put_contents(__DIR__ . '/webhook_debug.log', date('[Y-m-d H:i:s] ') . 'DB Error: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
    http_response_code(500);
    exit('Internal Server Error');
}