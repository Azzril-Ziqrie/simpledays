<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/brand.php';

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
    $itemRows = '';

    foreach ($items as $item) {
        $pName = htmlspecialchars((string)($item['product_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $sName = trim((string)($item['size_name'] ?? ''));
        $qty = (int)($item['quantity'] ?? 0);
        $unitPrice = formatDDEmailMoney($item['price'] ?? 0, (string)($order['currency'] ?? 'USD'));
        $lineTotal = formatDDEmailMoney($item['line_total'] ?? 0, (string)($order['currency'] ?? 'USD'));
        $thumbRaw = trim((string)($item['thumbnail'] ?? ''));
        $thumbSrc = '';
        if ($thumbRaw !== '') {
            if (preg_match('#^https?://#i', $thumbRaw)) {
                $thumbSrc = $thumbRaw;
            } else {
                $thumbSrc = rtrim(DD_SITE_URL, '/') . '/' . ltrim($thumbRaw, '/');
            }
        }

        $sizeHtml = '';
        if ($sName !== '' && !in_array(strtolower($sName), ['one size', 'default'], true)) {
            $sizeHtml = '<div style="font-size:10px;font-weight:600;letter-spacing:.1em;color:#888;margin-top:4px;">SIZE: ' . htmlspecialchars($sName, ENT_QUOTES, 'UTF-8') . '</div>';
        }

        $thumbHtml = $thumbSrc !== ''
            ? '<img src="' . htmlspecialchars($thumbSrc, ENT_QUOTES, 'UTF-8') . '" alt="' . $pName . '" width="70" height="90" style="display:block;width:70px;height:90px;object-fit:cover;background:#fff;border:0;">'
            : '<div style="width:70px;height:90px;background:#f9f9f9;"></div>';

        $itemRows .= '
        <tr>
          <td style="padding:18px 0;border-bottom:1px solid #eee;">
            <table cellpadding="0" cellspacing="0" border="0" role="presentation" style="border-collapse:collapse;">
              <tr>
                <td style="vertical-align:top;padding-right:18px;">' . $thumbHtml . '</td>
                <td style="vertical-align:top;">
                  <div style="font-size:11px;font-weight:700;letter-spacing:.05em;color:#111;margin-bottom:6px;">' . $pName . '</div>
                  ' . $sizeHtml . '
                  <div style="font-size:10px;font-weight:600;letter-spacing:.1em;color:#888;margin-top:8px;">QTY: ' . $qty . '</div>
                  <div style="font-size:11px;font-weight:600;letter-spacing:.05em;color:#111;margin-top:6px;">' . $unitPrice . '</div>
                </td>
              </tr>
            </table>
          </td>
          <td style="padding:18px 0;border-bottom:1px solid #eee;text-align:right;vertical-align:top;font-size:11px;font-weight:600;letter-spacing:.05em;color:#111;white-space:nowrap;">' . $lineTotal . '</td>
        </tr>';
    }

    if ($itemRows === '') {
        $itemRows = '<tr><td colspan="2" style="padding:18px 0;color:#999;font-size:11px;font-weight:600;letter-spacing:.1em;text-align:center;">ORDER ITEMS ARE BEING PROCESSED.</td></tr>';
    }

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

        $orderNumber = htmlspecialchars((string)($order['order_number'] ?? ''), ENT_QUOTES, 'UTF-8');
        $fullName = htmlspecialchars((string)($order['full_name'] ?? 'Customer'), ENT_QUOTES, 'UTF-8');
        $subtotal = formatDDEmailMoney($order['subtotal'] ?? 0, (string)($order['currency'] ?? 'USD'));
        $shipping = formatDDEmailMoney($order['shipping_cost'] ?? 0, (string)($order['currency'] ?? 'USD'));
        $grandTotal = formatDDEmailMoney($order['grand_total'] ?? 0, (string)($order['currency'] ?? 'USD'));
        $discountRow = '';
        if ($orderDiscount = promoOrderDiscount($order)) {
            $discountRow = '<tr>
                  <td style="padding:8px 0;color:#555;font-size:11px;font-weight:600;letter-spacing:.1em;">' . htmlspecialchars($orderDiscount['label'], ENT_QUOTES, 'UTF-8') . '</td>
                  <td style="padding:8px 0;text-align:right;font-size:11px;font-weight:600;letter-spacing:.08em;">-' . formatDDEmailMoney($orderDiscount['amount'], (string)($order['currency'] ?? 'USD')) . '</td>
                </tr>';
        }
        $address = htmlspecialchars(trim((string)($order['address_line_1'] ?? '') . ' ' . (string)($order['address_line_2'] ?? '')), ENT_QUOTES, 'UTF-8');
        $cityLine = htmlspecialchars(trim((string)($order['city'] ?? '') . ', ' . (string)($order['state'] ?? '') . ' ' . (string)($order['postal_code'] ?? '')), ENT_QUOTES, 'UTF-8');
        $country = htmlspecialchars((string)($order['country_name'] ?? 'Indonesia'), ENT_QUOTES, 'UTF-8');

        $mail->isHTML(true);
        $mail->Subject = BRAND_NAME . ' Order Invoice — ' . (string)($order['order_number'] ?? '');
        $mail->Body = '
        <div style="font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;color:#111;background:#fafafa;padding:40px 20px;line-height:1.6;letter-spacing:.02em;">
          <div style="max-width:600px;margin:0 auto;background:#fff;border:1px solid #eee;">

            <!-- Header -->
            <div style="padding:40px 30px;text-align:center;border-bottom:1px solid #eee;">
              <img src="' . rtrim(DD_SITE_URL, '/') . '/' . BRAND_LOGO . '" alt="' . BRAND_NAME . '" style="height:40px;width:auto;display:inline-block;">
              <div style="font-size:10px;font-weight:600;color:#888;margin-top:15px;letter-spacing:.15em;">Order Invoice</div>
            </div>

            <div style="padding:40px 30px;">
              <p style="margin:0 0 8px;font-size:11px;font-weight:700;letter-spacing:.1em;color:#111;">Hi ' . $fullName . ',</p>
              <p style="margin:0 0 30px;color:#555;font-size:11px;font-weight:500;letter-spacing:.08em;">Your payment has been received &mdash; your order is now being processed.</p>

              <div style="background:#fafafa;border:1px solid #eee;padding:20px;margin-bottom:35px;text-align:center;">
                <div style="font-size:10px;font-weight:600;color:#888;letter-spacing:.15em;">Order Number</div>
                <div style="font-size:16px;font-weight:700;margin-top:8px;letter-spacing:.1em;color:#111;">' . $orderNumber . '</div>
              </div>

              <div style="font-size:11px;font-weight:700;letter-spacing:.1em;color:#111;padding-bottom:12px;border-bottom:2px solid #111;margin-bottom:8px;">Items</div>
              <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-bottom:30px;">' . $itemRows . '</table>

              <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;border-top:1px solid #eee;padding-top:15px;">
                <tr>
                  <td style="padding:8px 0;color:#555;font-size:11px;font-weight:600;letter-spacing:.1em;">Subtotal</td>
                  <td style="padding:8px 0;text-align:right;font-size:11px;font-weight:600;letter-spacing:.08em;">' . $subtotal . '</td>
                </tr>
                <tr>
                  <td style="padding:8px 0;color:#555;font-size:11px;font-weight:600;letter-spacing:.1em;">Shipping</td>
                  <td style="padding:8px 0;text-align:right;font-size:11px;font-weight:600;letter-spacing:.08em;">' . $shipping . '</td>
                </tr>' . $discountRow . '
                <tr>
                  <td style="padding:20px 0 0;border-top:1px solid #eee;font-weight:700;font-size:12px;letter-spacing:.1em;color:#111;">Grand Total</td>
                  <td style="padding:20px 0 0;border-top:1px solid #eee;text-align:right;font-weight:700;font-size:14px;letter-spacing:.05em;color:#111;">' . $grandTotal . '</td>
                </tr>
              </table>

              <div style="margin-top:35px;padding:20px;border:1px solid #eee;background:#fafafa;">
                <div style="font-size:10px;font-weight:700;color:#111;letter-spacing:.15em;margin-bottom:12px;">Shipping Address</div>
                <div style="font-size:11px;font-weight:500;line-height:1.8;letter-spacing:.08em;color:#555;">' . $address . '<br>' . $cityLine . '<br>' . $country . '</div>
              </div>

              <p style="margin:35px 0 0;color:#555;font-size:11px;font-weight:600;letter-spacing:.1em;">Thank you for shopping with us.</p>
              <p style="margin:10px 0 0;font-size:12px;font-weight:900;letter-spacing:.1em;color:#111;">' . BRAND_NAME . '</p>
            </div>

            <!-- Footer -->
            <div style="border-top:1px solid #eee;padding:25px 30px;background:#fafafa;text-align:center;">
              <div style="font-size:10px;font-weight:500;letter-spacing:.1em;color:#888;">
                &copy; ' . date('Y') . ' ' . BRAND_NAME . '<br><br>
              </div>
            </div>

          </div>
        </div>';

        $mail->AltBody = BRAND_NAME . ' Order Invoice — ' . (string)($order['order_number'] ?? '') . '. Total: ' . $grandTotal . '.';
        $mail->send();
        return true;
    } catch (Throwable $e) {
        error_log('Invoice email failed: ' . $e->getMessage());
        return false;
    }
}