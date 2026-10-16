<?php
require_once __DIR__ . '/_guard.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/campaign_mail.php';

campaignEnsureSchema($pdo);
$recipients = campaignRecipients($pdo);

/* ── AJAX: kirim satu batch ─────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    $subject = trim((string)($_POST['subject'] ?? ''));
    $message = trim((string)($_POST['message'] ?? ''));
    $offset  = max(0, (int)($_POST['offset'] ?? 0));

    if (!hash_equals(ddAdminCsrf(), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid session. Reload the page.']);
        exit;
    }
    if ($subject === '' || $message === '') {
        echo json_encode(['error' => 'Subject and message are required.']);
        exit;
    }
    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        echo json_encode(['error' => 'PHPMailer is not installed.']);
        exit;
    }

    set_time_limit(120);
    $batch  = array_slice($recipients, $offset, CAMPAIGN_BATCH_SIZE);
    $result = campaignSendBatch($batch, $subject, $message);

    echo json_encode($result + [
        'next'  => $offset + count($batch),
        'total' => count($recipients),
        'done'  => $offset + count($batch) >= count($recipients),
    ]);
    exit;
}

$csrf = ddAdminCsrf();
$h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Email Campaign — <?= $h(BRAND_NAME) ?> Admin</title>
  <link rel="stylesheet" href="admin-theme.css?v=1">
  <style>
    body { font-family: Arial, Helvetica, sans-serif; margin: 0; color: #111; }
    main { max-width: 720px; margin: 0 auto; padding: 32px 20px; }
    label { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: .6px; margin: 18px 0 8px; }
    input[type=text], textarea { width: 100%; padding: 12px 14px; border: 1px solid #d8d5ce; background: #faf9f5; font: inherit; box-sizing: border-box; }
    textarea { min-height: 220px; }
    button { height: 48px; padding: 0 22px; margin-top: 20px; border: 1px solid #111; background: #111; color: #fff; text-transform: uppercase; font-size: 12px; cursor: pointer; }
    button:disabled { opacity: .5; cursor: default; }
    .hint { font-size: 12px; color: #666; margin-top: 6px; }
    #status { margin-top: 18px; font-size: 13px; line-height: 1.7; }
  </style>
</head>
<body>
<main>
  <p><a href="dashboard.php">← Back to Dashboard</a></p>
  <h1>Email Campaign</h1>
  <p class="hint">
    Sent to <strong><?= count($recipients) ?></strong> unique customer emails from past orders
    (unsubscribed addresses are skipped automatically).
  </p>

  <form id="campaignForm">
    <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
    <label for="subject">Subject</label>
    <input type="text" id="subject" name="subject" maxlength="150" required>
    <label for="message">Message</label>
    <textarea id="message" name="message" required></textarea>
    <div class="hint">Use <code>{{name}}</code> for the customer's name. An unsubscribe link is added automatically.</div>
    <button type="submit" id="sendBtn">Send campaign</button>
  </form>
  <div id="status"></div>
</main>

<script>
  const form = document.getElementById('campaignForm');
  const btn = document.getElementById('sendBtn');
  const status = document.getElementById('status');

  async function sendBatch(offset) {
    const body = new FormData(form);
    body.append('offset', offset);
    const res = await fetch('campaign-email.php', { method: 'POST', body });
    return res.json();
  }

  form.addEventListener('submit', async function (e) {
    e.preventDefault();
    if (!confirm('Send this campaign to all customers now?')) return;

    btn.disabled = true;
    let offset = 0, sent = 0;
    const failed = [];

    try {
      while (true) {
        const r = await sendBatch(offset);
        if (r.error) { status.textContent = 'Error: ' + r.error; break; }
        sent += r.sent;
        failed.push(...r.failed);
        offset = r.next;
        status.textContent = 'Sent ' + sent + ' / ' + r.total + (failed.length ? ' (failed: ' + failed.length + ')' : '');
        if (r.done) {
          status.textContent += ' — finished.' + (failed.length ? ' Failed: ' + failed.join(', ') : '');
          break;
        }
      }
    } catch (err) {
      status.textContent = 'Stopped at ' + offset + ': ' + err.message;
    }
    btn.disabled = false;
  });
</script>
</body>
</html>
