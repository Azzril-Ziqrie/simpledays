<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/bootstrap-paypal.php';
require_once __DIR__ . '/includes/order_payment.php';

use PaypalServerSdkLib\Models\Address;
use PaypalServerSdkLib\Models\AmountWithBreakdown;
use PaypalServerSdkLib\Models\CheckoutPaymentIntent;
use PaypalServerSdkLib\Models\Name;
use PaypalServerSdkLib\Models\OrderRequest;
use PaypalServerSdkLib\Models\Payer;
use PaypalServerSdkLib\Models\PurchaseUnitRequest;

function ppJsonError(int $code, string $message): void
{
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit;
}

/**
 * Data pembeli untuk mengisi otomatis form kartu debit/kredit PayPal (nama, email, alamat & negara).
 * Negara diambil dari alamat order; kalau kosong, dari IP pengunjung.
 */
function ppBuildPayer(array $order): ?Payer
{
    $country = ppPayerCountry($order);
    if ($country === null) return null;

    $payer = new Payer();
    $email = trim((string)($order['email'] ?? ''));
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) $payer->setEmailAddress($email);
    $payer->setName(ppPayerName((string)($order['full_name'] ?? '')));
    $payer->setAddress(ppPayerAddress($order, $country));
    return $payer;
}

/** Kode negara ISO-2: dari alamat order, cadangannya dari IP pengunjung. */
function ppPayerCountry(array $order): ?string
{
    $isIso2 = static fn(string $c): bool => (bool)preg_match('/^[A-Z]{2}$/', $c);

    $country = strtoupper(trim((string)($order['country_code'] ?? '')));
    if ($isIso2($country)) return $country;

    $geo = function_exists('ddDetectVisitorCurrency') ? ddDetectVisitorCurrency() : null;
    $country = strtoupper((string)($geo['country_code'] ?? ''));
    return $isIso2($country) ? $country : null;
}

/** "John Ronald Smith" → given "John Ronald", surname "Smith". */
function ppPayerName(string $fullName): ?Name
{
    $parts = preg_split('/\s+/', trim($fullName), -1, PREG_SPLIT_NO_EMPTY);
    if (!$parts) return null;

    $name = new Name();
    if (count($parts) > 1) $name->setSurname(mb_substr(array_pop($parts), 0, 140));
    $name->setGivenName(mb_substr(implode(' ', $parts), 0, 140));
    return $name;
}

function ppPayerAddress(array $order, string $country): Address
{
    $field = static function (string $key, int $max) use ($order): ?string {
        $v = trim((string)($order[$key] ?? ''));
        return $v !== '' ? mb_substr($v, 0, $max) : null;
    };
    $address = new Address($country);
    $address->setAddressLine1($field('address_line_1', 300));
    $address->setAddressLine2($field('address_line_2', 300));
    $address->setAdminArea2($field('city', 120));
    $address->setAdminArea1($field('state', 300));
    $address->setPostalCode($field('postal_code', 60));
    return $address;
}

try {
    $orderNumber = trim((string)($_GET['order'] ?? $_POST['order'] ?? ''));
    if ($orderNumber === '') ppJsonError(422, 'Order number is required.');

    $stmt = $pdo->prepare('SELECT * FROM orders WHERE order_number = :n LIMIT 1');
    $stmt->execute(['n' => $orderNumber]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) ppJsonError(404, 'Order not found.');

    if (strtolower((string)$order['payment_status']) === 'paid') ppJsonError(409, 'Order is already paid.');

    $grandTotal = (float)$order['grand_total'];
    if ($grandTotal <= 0) ppJsonError(422, 'Invalid grand total.');

    // Nominal selalu dari database, bukan dari browser.
    // Selalu USD.
    $charge = ddPaypalCharge($order);
    $amount = new AmountWithBreakdown($charge['currency'], $charge['value']);

    $purchaseUnit = new PurchaseUnitRequest($amount);
    $purchaseUnit->setReferenceId((string)$order['order_number']);
    $purchaseUnit->setDescription('Order ' . $order['order_number']);

    $orderRequest = new OrderRequest(CheckoutPaymentIntent::CAPTURE, [$purchaseUnit]);
    $payer = ppBuildPayer($order);
    if ($payer !== null) $orderRequest->setPayer($payer);

    $createOrder = static fn() => $paypalClient->getOrdersController()->createOrder(['body' => $orderRequest]);
    try {
        $apiResponse = $createOrder();
    } catch (Throwable $e) {
        if ($payer === null) throw $e;
        // Alamat ditolak PayPal (mis. format kode pos) → tetap lanjut tanpa isi otomatis.
        error_log('PAYPAL CREATE payer prefill rejected, retrying without: ' . $e->getMessage());
        $orderRequest->setPayer(null);
        $apiResponse = $createOrder();
    }
    $paypalOrder = $apiResponse->getResult();

    echo json_encode([
        'id'      => $paypalOrder->getId(),
        'orderID' => $paypalOrder->getId(),
        'status'  => $paypalOrder->getStatus(),
    ]);
} catch (Throwable $e) {
    error_log('PAYPAL CREATE ERROR: ' . $e->getMessage());
    ppJsonError(500, 'Failed to create PayPal order.');
}
