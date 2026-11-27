<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/brand.php';
require_once __DIR__ . '/email_layout.php';

// Konstanta Konfigurasi SMTP & URL
if (!defined('DD_SMTP_HOST')) {
    define('DD_SMTP_HOST', ddSecret('SMTP_HOST', 'smtp.hostinger.com'));
}
if (!defined('DD_SMTP_PORT')) {
    define('DD_SMTP_PORT', (int)ddSecret('SMTP_PORT', '465')); // 465 = SSL, 587 = STARTTLS
}
if (!defined('DD_SMTP_USERNAME')) {
    define('DD_SMTP_USERNAME', ddSecret('SMTP_USERNAME', 'info@thesimpledays.co'));
}
if (!defined('DD_SMTP_PASSWORD')) {
    define('DD_SMTP_PASSWORD', ddSecret('SMTP_PASSWORD'));
}
if (!defined('DD_SMTP_FROM_EMAIL')) {
    define('DD_SMTP_FROM_EMAIL', ddSecret('SMTP_FROM_EMAIL', 'info@thesimpledays.co'));
}
if (!defined('DD_SMTP_FROM_NAME')) {
    define('DD_SMTP_FROM_NAME', BRAND_NAME);
}
if (!defined('DD_ADMIN_EMAIL')) {
    define('DD_ADMIN_EMAIL', '');
}
if (!defined('DD_SITE_URL')) {
    // Sesuaikan URL ini dengan domain asli Anda
    define('DD_SITE_URL', 'https://thesimpledays.co');
}

function getDDOrderItemsForEmail(PDO $pdo, int $orderId): array
{
    $stmt = $pdo->prepare("
        SELECT oi.product_name, oi.size_name, oi.quantity, oi.price, oi.line_total,
               (SELECT thumbnail FROM products WHERE name = oi.product_name LIMIT 1) AS thumbnail
        FROM order_items oi
        WHERE oi.order_id = :order_id
        ORDER BY oi.id ASC
    ");
    $stmt->execute(['order_id' => $orderId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

require_once __DIR__ . '/currency_helper.php';
require_once __DIR__ . '/promo.php';

function formatDDEmailMoney($amount, string $currency = 'USD'): string
{
    return ddFormatMoney((float)$amount, $currency);
}

function sendDDInvoiceEmail(PDO $pdo, array $order): bool
{
    $customerEmail = trim((string)($order['email'] ?? ''));
    if ($customerEmail === '' || !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
        error_log('Invoice email skipped: invalid email.');
        return false;
    }

    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        error_log('Invoice email failed: PHPMailer not installed.');
        return false;
    }

    $items = getDDOrderItemsForEmail($pdo, (int)($order['id'] ?? 0));

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = DD_SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = DD_SMTP_USERNAME;
        $mail->Password = DD_SMTP_PASSWORD;
        $mail->SMTPSecure = DD_SMTP_PORT === 465
            ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = DD_SMTP_PORT;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom(DD_SMTP_FROM_EMAIL, DD_SMTP_FROM_NAME);
        $mail->addAddress($customerEmail, (string)($order['full_name'] ?? 'Customer'));

        $adminEmail = trim(DD_ADMIN_EMAIL);
        if ($adminEmail !== '' && filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $mail->addBCC($adminEmail, BRAND_NAME . ' Admin');
        }

        $grandTotal = formatDDEmailMoney($order['grand_total'] ?? 0, (string)($order['currency'] ?? 'USD'));

        $mail->isHTML(true);
        $mail->Subject = BRAND_NAME . ' Order Invoice — ' . (string)($order['order_number'] ?? '');
        $mail->Body = ddInvoiceEmailHtml($order, $items);

        $mail->AltBody = BRAND_NAME . ' Order Invoice — ' . (string)($order['order_number'] ?? '') . '. Total: ' . $grandTotal . '.';
        $mail->send();
        return true;
    } catch (Throwable $e) {
        error_log('Invoice email failed: ' . $e->getMessage());
        return false;
    }
}

/** HTML email invoice: sapaan, nomor order, daftar item, ringkasan biaya, alamat kirim. */
function ddInvoiceEmailHtml(array $order, array $items): string
{
    $currency = (string)($order['currency'] ?? 'USD');
    $money = static fn($amount): string => ddEmailEsc(formatDDEmailMoney($amount ?? 0, $currency));
    $firstName = strtok(trim((string)($order['full_name'] ?? '')), ' ') ?: 'there';
    $orderNumber = (string)($order['order_number'] ?? '');

    $summary = [['Subtotal', $money($order['subtotal'] ?? 0)], ['Shipping', $money($order['shipping_cost'] ?? 0)]];
    if ($discount = promoOrderDiscount($order)) {
        $summary[] = [$discount['label'], '−' . $money($discount['amount'])];
    }
    $summary[] = ['Total', $money($order['grand_total'] ?? 0), true];

    $content = ddEmailHeading('Thanks for your order, ' . $firstName . '.')
        . ddEmailParagraph('Your payment has been received and we’re preparing your order. We’ll email you again with tracking details once it ships.', true)
        . ddEmailHighlight('Order number', $orderNumber)
        . ddEmailSection('Items')
        . ddInvoiceItemsHtml($items, $money)
        . ddEmailDetails($summary)
        . ddEmailSection('Shipping to')
        . ddEmailParagraph(ddInvoiceAddressHtml($order), true)
        . ddEmailButton('Visit ' . BRAND_NAME, ddEmailSiteUrl())
        . ddEmailSignoff();

    return ddEmailLayout(
        BRAND_NAME . ' Order ' . $orderNumber,
        'Payment received for order ' . $orderNumber . '. Total ' . formatDDEmailMoney($order['grand_total'] ?? 0, $currency) . '.',
        $content
    );
}

/** Baris item: foto, nama, ukuran × jumlah, total baris. */
function ddInvoiceItemsHtml(array $items, callable $money): string
{
    if (!$items) {
        return ddEmailParagraph('Your order items are being processed.', true);
    }

    $rows = '';
    foreach ($items as $item) {
        $name = ddEmailEsc((string)($item['product_name'] ?? ''));
        $size = trim((string)($item['size_name'] ?? ''));
        $meta = 'Qty ' . (int)($item['quantity'] ?? 0);
        if ($size !== '' && !in_array(strtolower($size), ['one size', 'default'], true)) {
            $meta = 'Size ' . ddEmailEsc($size) . ' · ' . $meta;
        }

        $rows .= '<tr>
          <td class="em-line" width="72" style="padding:16px 16px 16px 0;border-bottom:1px solid ' . EMAIL_LINE . ';vertical-align:top;">' . ddInvoiceThumbHtml($item, $name) . '</td>
          <td class="em-line" style="padding:16px 0;border-bottom:1px solid ' . EMAIL_LINE . ';vertical-align:top;">
            <div class="em-ink" style="font-size:15px;line-height:1.4;font-weight:600;color:' . EMAIL_INK . ';">' . $name . '</div>
            <div class="em-ink-2" style="margin-top:4px;font-size:13px;line-height:1.4;color:' . EMAIL_INK_2 . ';">' . $meta . '</div>
          </td>
          <td class="em-ink em-line" align="right" style="padding:16px 0 16px 12px;border-bottom:1px solid ' . EMAIL_LINE . ';vertical-align:top;font-size:15px;line-height:1.4;color:' . EMAIL_INK . ';white-space:nowrap;">' . $money($item['line_total'] ?? 0) . '</td>
        </tr>';
    }
    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;margin:4px 0 8px;">' . $rows . '</table>';
}

function ddInvoiceThumbHtml(array $item, string $altEscaped): string
{
    $raw = trim((string)($item['thumbnail'] ?? ''));
    if ($raw === '') {
        return '<div class="em-fill" style="width:72px;height:90px;border-radius:10px;background:' . EMAIL_FILL . ';"></div>';
    }
    $src = preg_match('#^https?://#i', $raw) ? $raw : ddEmailSiteUrl() . '/' . ltrim($raw, '/');
    return '<img src="' . ddEmailEsc($src) . '" alt="' . $altEscaped . '" width="72" height="90" style="display:block;width:72px;height:90px;object-fit:cover;border-radius:10px;border:0;background:' . EMAIL_FILL . ';">';
}

function ddInvoiceAddressHtml(array $order): string
{
    $lines = [
        trim((string)($order['full_name'] ?? '')),
        trim((string)($order['address_line_1'] ?? '') . ' ' . (string)($order['address_line_2'] ?? '')),
        trim(implode(', ', array_filter([trim((string)($order['city'] ?? '')), trim((string)($order['state'] ?? '') . ' ' . (string)($order['postal_code'] ?? ''))]))),
        trim((string)($order['country_name'] ?? '')),
    ];
    return implode('<br>', array_map('ddEmailEsc', array_filter($lines, 'strlen')));
}