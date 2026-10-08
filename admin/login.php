<?php
require_once __DIR__ . '/_auth.php';

ddAdminStartSession();
$next  = ddAdminSafeNext($_GET['next'] ?? $_POST['next'] ?? '');
$error = '';

if (ddAdminIsLoggedIn()) { header('Location: ' . $next); exit; }

if (empty($_SESSION['login_csrf'])) $_SESSION['login_csrf'] = bin2hex(random_bytes(16));

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $locked = ddAdminLockedSeconds();
    if ($locked > 0) {
        $error = 'Too many attempts. Try again in ' . ceil($locked / 60) . ' minute(s).';
    } elseif (!hash_equals((string)$_SESSION['login_csrf'], (string)($_POST['csrf'] ?? ''))) {
        $error = 'Session expired. Please try again.';
        $_SESSION['login_csrf'] = bin2hex(random_bytes(16));
    } else {
        $hash = ddAdminCurrentHash();
        $ok   = $hash !== '' && $hash !== '__HASH__' && password_verify((string)($_POST['password'] ?? ''), $hash);
        if ($ok) {
            ddAdminClearFailures();
            ddAdminLoginOk();
            header('Location: ' . $next);
            exit;
        }
        ddAdminRegisterFailure();
        usleep(500000); // perlambat tebak-tebakan
        $locked = ddAdminLockedSeconds();
        $error  = $locked > 0 ? 'Too many attempts. Try again in ' . ceil($locked / 60) . ' minute(s).' : 'Incorrect password.';
    }
}

$host      = $_SERVER['HTTP_HOST'] ?? '';
$isLocal   = preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/', $host) === 1;
$warnHttps = !ddAdminIsHttps() && !$isLocal;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex,nofollow">
  <title>Sign in — Cantum Studio Admin</title>
  <style>
    *{box-sizing:border-box}
    body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#fff;color:#111;font-family:Arial,Helvetica,sans-serif;padding:20px}
    .box{width:100%;max-width:360px;border:1px solid #d8d5ce;background:#faf9f5;padding:34px 30px}
    .logo{display:block;height:46px;margin:0 auto 22px}
    .eyebrow{font-size:12px;text-transform:uppercase;letter-spacing:.7px;text-align:center;margin-bottom:22px}
    label{display:block;font-size:11px;text-transform:uppercase;letter-spacing:.6px;margin-bottom:8px;color:#333}
    input[type=password]{width:100%;height:46px;border:1px solid #d8d5ce;background:#fff;padding:0 14px;font-size:14px;outline:none}
    input[type=password]:focus{border-color:#111}
    button{width:100%;height:48px;margin-top:16px;border:1px solid #111;background:#111;color:#fff;text-transform:uppercase;font-size:12px;letter-spacing:.6px;cursor:pointer}
    button:hover{background:#000}
    .err{margin-bottom:16px;padding:12px 14px;background:#fff;border:1px solid #c0392b;color:#c0392b;font-size:13px}
    .warn{margin-top:16px;font-size:12px;color:#7a5b00;background:#fff8e1;border:1px solid #f0d98c;padding:10px 12px;line-height:1.5}
  </style>
  <link rel="stylesheet" href="admin-theme.css?v=1">
</head>
<body>
  <form class="box" method="POST" action="login.php" autocomplete="off">
    <div class="brand"><span class="brand__name">Cantum Studio</span></div>
    <?php if ($error !== ''): ?><div class="err" role="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <input type="hidden" name="csrf" value="<?= htmlspecialchars((string)$_SESSION['login_csrf']) ?>">
    <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>">
    <label for="password">Password</label>
    <input type="password" id="password" name="password" autofocus required>
    <button type="submit">Sign in</button>
    <?php if ($warnHttps): ?><div class="warn">This connection is not HTTPS. Open the admin over https:// so the password is encrypted.</div><?php endif; ?>
  </form>
</body>
</html>
