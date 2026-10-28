<?php
declare(strict_types=1);

require_once __DIR__ . '/email_helpers.php';
require_once __DIR__ . '/db_schema.php';

const CAMPAIGN_BATCH_SIZE = 20;

function campaignEnsureSchema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS email_unsubscribes (
            email VARCHAR(150) NOT NULL PRIMARY KEY,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

/** Pembeli unik dari tabel orders (email valid, belum berhenti berlangganan), urut stabil. */
function campaignRecipients(PDO $pdo): array
{
    // Pelanggan yang pernah order + pendaftar newsletter di footer (jika tabelnya sudah ada).
    $sources = ["SELECT email, full_name FROM orders"];
    if (tableExists($pdo, 'newsletter_subscribers')) {
        $sources[] = "SELECT email, '' AS full_name FROM newsletter_subscribers";
    }
    $rows = $pdo->query(
        "SELECT LOWER(TRIM(c.email)) COLLATE utf8mb4_unicode_ci AS email, MAX(c.full_name) AS full_name
         FROM (" . implode(' UNION ALL ', $sources) . ") c
         LEFT JOIN email_unsubscribes u ON u.email = LOWER(TRIM(c.email)) COLLATE utf8mb4_unicode_ci
         WHERE u.email IS NULL AND TRIM(c.email) <> ''
         GROUP BY LOWER(TRIM(c.email))
         ORDER BY email ASC"
    )->fetchAll(PDO::FETCH_ASSOC);

    return array_values(array_filter(
        $rows,
        static fn(array $r): bool => filter_var($r['email'], FILTER_VALIDATE_EMAIL) !== false
    ));
}

function campaignUnsubscribeToken(string $email): string
{
    return hash_hmac('sha256', strtolower($email), DD_SMTP_PASSWORD . '|unsubscribe');
}

function campaignUnsubscribeUrl(string $email): string
{
    return rtrim(DD_SITE_URL, '/') . '/unsubscribe.php?email=' . urlencode($email)
        . '&token=' . campaignUnsubscribeToken($email);
}

function campaignRenderBody(string $message, string $name, string $unsubscribeUrl): string
{
    $text = str_replace('{{name}}', $name, $message);
    $body = nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
    $unsub = htmlspecialchars($unsubscribeUrl, ENT_QUOTES, 'UTF-8');

    return '<div style="font-family:Helvetica,Arial,sans-serif;color:#111;line-height:1.7;max-width:560px;margin:0 auto;padding:32px 20px;">'
        . $body
        . '<p style="margin-top:36px;padding-top:16px;border-top:1px solid #ddd;font-size:11px;color:#888;">'
        . 'You receive this email because you ordered from ' . htmlspecialchars(BRAND_NAME, ENT_QUOTES, 'UTF-8') . '. '
        . '<a href="' . $unsub . '" style="color:#888;">Unsubscribe</a></p></div>';
}

function campaignCreateMailer(): \PHPMailer\PHPMailer\PHPMailer
{
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = DD_SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->SMTPKeepAlive = true;
    $mail->Username = DD_SMTP_USERNAME;
    $mail->Password = DD_SMTP_PASSWORD;
    $mail->SMTPSecure = DD_SMTP_PORT === 465
        ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
        : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = DD_SMTP_PORT;
    $mail->CharSet = 'UTF-8';
    $mail->isHTML(true);
    $mail->setFrom(DD_SMTP_FROM_EMAIL, DD_SMTP_FROM_NAME);
    return $mail;
}

/** Kirim satu batch penerima. Mengembalikan jumlah terkirim & daftar email yang gagal. */
function campaignSendBatch(array $recipients, string $subject, string $message): array
{
    $mail = campaignCreateMailer();
    $sent = 0;
    $failed = [];

    foreach ($recipients as $recipient) {
        $email = $recipient['email'];
        $name = trim((string)$recipient['full_name']) ?: 'there';
        $unsubscribeUrl = campaignUnsubscribeUrl($email);

        try {
            $mail->clearAddresses();
            $mail->clearCustomHeaders();
            $mail->addAddress($email, $name);
            $mail->addCustomHeader('List-Unsubscribe', '<' . $unsubscribeUrl . '>');
            $mail->Subject = $subject;
            $mail->Body = campaignRenderBody($message, $name, $unsubscribeUrl);
            $mail->AltBody = strip_tags(str_replace('{{name}}', $name, $message)) . "\n\nUnsubscribe: " . $unsubscribeUrl;
            $mail->send();
            $sent++;
        } catch (Throwable $e) {
            error_log('[campaign] ' . $email . ': ' . $e->getMessage());
            $failed[] = $email;
        }
    }

    $mail->smtpClose();
    return ['sent' => $sent, 'failed' => $failed];
}
