<?php
declare(strict_types=1);

require_once __DIR__ . '/email_helpers.php';
require_once __DIR__ . '/email_layout.php';
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

/**
 * Penerima custom dari input admin (dipisah koma, titik koma, spasi, atau baris baru).
 * Email tidak valid dibuang, duplikat digabung, dan yang sudah unsubscribe tetap dilewati.
 * Nama diambil dari data pelanggan bila emailnya dikenal.
 */
function campaignCustomRecipients(PDO $pdo, string $input, array $knownRecipients): array
{
    $emails = campaignWithoutUnsubscribed($pdo, campaignParseEmails($input));
    $names = array_column($knownRecipients, 'full_name', 'email');

    return array_map(
        static fn(string $email): array => ['email' => $email, 'full_name' => (string)($names[$email] ?? '')],
        $emails
    );
}

/** @return string[] email valid, huruf kecil, tanpa duplikat */
function campaignParseEmails(string $input): array
{
    $tokens = preg_split('/[\s,;]+/', strtolower($input), -1, PREG_SPLIT_NO_EMPTY);
    $valid = array_filter($tokens, static fn(string $t): bool => filter_var($t, FILTER_VALIDATE_EMAIL) !== false);
    return array_values(array_unique($valid));
}

/** @param string[] $emails @return string[] */
function campaignWithoutUnsubscribed(PDO $pdo, array $emails): array
{
    if (!$emails) return [];

    $placeholders = implode(',', array_fill(0, count($emails), '?'));
    $stmt = $pdo->prepare("SELECT LOWER(email) FROM email_unsubscribes WHERE email IN ($placeholders)");
    $stmt->execute($emails);
    return array_values(array_diff($emails, $stmt->fetchAll(PDO::FETCH_COLUMN)));
}

function campaignPersonalize(string $message, string $name): string
{
    return str_replace('{{name}}', $name, $message);
}

/** Template email campaign: layout bersama, pesan dipecah per paragraf, footer + unsubscribe. */
function campaignRenderBody(string $message, string $name, string $unsubscribeUrl): string
{
    $text = campaignPersonalize($message, $name);

    // Baris kosong = paragraf baru; baris tunggal = <br> di dalam paragraf.
    $paragraphs = preg_split('/\R{2,}/', trim($text), -1, PREG_SPLIT_NO_EMPTY);
    $content = implode('', array_map(
        static fn(string $p): string => ddEmailParagraph(nl2br(ddEmailEsc(trim($p)))),
        $paragraphs
    )) . ddEmailSignoff();

    $preheader = mb_substr(preg_replace('/\s+/', ' ', $text), 0, 110);
    $footer = 'You are receiving this email from ' . ddEmailEsc(BRAND_NAME) . '. '
        . '<a href="' . ddEmailEsc($unsubscribeUrl) . '" class="em-link-2" style="color:' . EMAIL_INK_2 . ';text-decoration:underline;">Unsubscribe</a>';

    return ddEmailLayout(BRAND_NAME, $preheader, $content, $footer);
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
            $mail->AltBody = strip_tags(campaignPersonalize($message, $name)) . "\n\nUnsubscribe: " . $unsubscribeUrl;
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
