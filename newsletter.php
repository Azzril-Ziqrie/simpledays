<?php
// Endpoint kotak newsletter di footer: simpan email ke newsletter_subscribers.
// Jawaban selalu sama untuk email baru maupun yang sudah ada (tidak membocorkan siapa yang berlangganan).
require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json; charset=utf-8');

function newsletterReply(int $status, bool $ok, string $message): never
{
    http_response_code($status);
    echo json_encode(['success' => $ok, 'message' => $message]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    newsletterReply(405, false, 'Method not allowed.');
}

// honeypot: kolom ini tersembunyi untuk manusia; bot biasanya mengisinya
if (trim((string)($_POST['company'] ?? '')) !== '') {
    newsletterReply(200, true, "Thanks, you're subscribed.");
}

$email = trim((string)($_POST['email'] ?? ''));
if ($email === '' || strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    newsletterReply(422, false, 'Please enter a valid email address.');
}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS newsletter_subscribers (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(190) NOT NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_newsletter_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->prepare('INSERT IGNORE INTO newsletter_subscribers (email) VALUES (:email)')
        ->execute(['email' => mb_strtolower($email)]);
} catch (Throwable $e) {
    error_log('[newsletter] ' . $e->getMessage());
    newsletterReply(500, false, 'Something went wrong. Please try again.');
}

newsletterReply(200, true, "Thanks, you're subscribed.");
