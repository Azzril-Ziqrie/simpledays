<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/campaign_mail.php';

$email = strtolower(trim((string)($_GET['email'] ?? '')));
$token = (string)($_GET['token'] ?? '');
$valid = filter_var($email, FILTER_VALIDATE_EMAIL) !== false
    && hash_equals(campaignUnsubscribeToken($email), $token);

if ($valid) {
    campaignEnsureSchema($pdo);
    $pdo->prepare('INSERT IGNORE INTO email_unsubscribes (email) VALUES (:email)')->execute(['email' => $email]);
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Unsubscribe — <?= htmlspecialchars(BRAND_NAME) ?></title>
</head>
<body style="font-family:Helvetica,Arial,sans-serif;max-width:480px;margin:80px auto;padding:0 20px;">
  <?php if ($valid): ?>
    <h1>You are unsubscribed</h1>
    <p><?= htmlspecialchars($email) ?> will no longer receive marketing emails from <?= htmlspecialchars(BRAND_NAME) ?>.</p>
  <?php else: ?>
    <h1>Invalid link</h1>
    <p>This unsubscribe link is not valid.</p>
  <?php endif; ?>
</body>
</html>
