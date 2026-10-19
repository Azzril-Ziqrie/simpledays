<?php
session_start();
require __DIR__ . '/includes/site_gate.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/currency_helper.php';
require_once __DIR__ . '/includes/layout.php';

if (isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$error   = '';
$message = '';

function old(string $key): string
{
    return htmlspecialchars($_POST[$key] ?? '', ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName        = trim($_POST['full_name']        ?? '');
    $email           = trim($_POST['email']            ?? '');
    $password        = $_POST['password']              ?? '';
    $confirmPassword = $_POST['confirm_password']      ?? '';

    if ($fullName === '' || $email === '' || $password === '' || $confirmPassword === '') {
        $error = 'Please complete all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Password confirmation does not match.';
    } else {
        try {
            $check = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
            $check->execute(['email' => $email]);

            if ($check->fetch()) {
                $error = 'This email is already registered.';
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                $insert = $pdo->prepare(
                    'INSERT INTO users (full_name, email, password, role, is_active)
                     VALUES (:full_name, :email, :password, :role, :is_active)'
                );
                $insert->execute([
                    'full_name' => $fullName,
                    'email'     => $email,
                    'password'  => $hashedPassword,
                    'role'      => 'customer',
                    'is_active' => 1,
                ]);

                $userId = (int)$pdo->lastInsertId();

                $_SESSION['user'] = [
                    'id'        => $userId,
                    'full_name' => $fullName,
                    'email'     => $email,
                    'role'      => 'customer',
                ];

                header('Location: index.php');
                exit;
            }
        } catch (Throwable $e) {
            $error = 'Failed to register account: ' . $e->getMessage();
        }
    }
}

/* pre-fill dari redirect index.php account modal */
$prefillName  = htmlspecialchars(trim($_GET['name']  ?? ''), ENT_QUOTES, 'UTF-8');
$prefillEmail = htmlspecialchars(trim($_GET['email'] ?? ''), ENT_QUOTES, 'UTF-8');
siteHeadOpen(['title' => 'Create account', 'noindex' => true]);
?>
  <style>
    .auth-wrap { max-width: 420px; margin: 0 auto; padding: clamp(32px,6vw,72px) var(--gutter) clamp(56px,8vw,112px); }
    .auth-card { padding: clamp(22px,4vw,36px); }
    .auth-card .page-title { margin-bottom: 8px; }
    .auth-sub { font-size: 12px; color: var(--mute); line-height: 1.7; margin-bottom: 24px; }
    .auth-card .alert { margin-bottom: 18px; }
    .auth-form { display: grid; gap: 16px; }
    .input.input-error { border-color: var(--err); }
    .pw-strength { display: flex; gap: 3px; margin-top: 6px; }
    .pw-bar { flex: 1; height: 2px; background: var(--line); transition: background-color .2s; }
    .pw-hint { font-size: 11px; color: var(--mute); min-height: 16px; margin-top: 4px; }
    .auth-form .btn { margin-top: 4px; }
    .auth-foot { margin-top: 22px; font-size: 12px; color: var(--mute); display: grid; gap: 8px; }
    .auth-foot a { color: var(--ink); text-decoration: underline; text-underline-offset: 3px; }
  </style>
<?php
siteBodyOpen(['bodyClass' => 'page-register', 'searchAction' => 'shop.php']);
?>
  <main class="page-main">
    <div class="auth-wrap">
      <div class="card auth-card">
        <h1 class="page-title">Create account</h1>
        <p class="auth-sub">Track your orders and check out faster. It only takes a moment.</p>

        <?php if ($error !== ''): ?>
          <div class="alert alert--error" role="alert"><?= esc($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="" id="registerForm" class="auth-form" novalidate>
          <div class="field">
            <label for="full_name">Full name</label>
            <input class="input" type="text" id="full_name" name="full_name"
                   value="<?= $prefillName ?: old('full_name') ?>" autocomplete="name" required>
          </div>

          <div class="field">
            <label for="email">Email address</label>
            <input class="input" type="email" id="email" name="email"
                   value="<?= $prefillEmail ?: old('email') ?>" placeholder="you@email.com" autocomplete="email" required>
          </div>

          <div class="field">
            <label for="password">Password</label>
            <input class="input" type="password" id="password" name="password"
                   placeholder="Min. 6 characters" autocomplete="new-password" required>
            <div class="pw-strength" aria-hidden="true">
              <div class="pw-bar" id="bar1"></div>
              <div class="pw-bar" id="bar2"></div>
              <div class="pw-bar" id="bar3"></div>
            </div>
            <div class="pw-hint" id="pwHint" aria-live="polite"></div>
          </div>

          <div class="field">
            <label for="confirm_password">Confirm password</label>
            <input class="input" type="password" id="confirm_password" name="confirm_password"
                   autocomplete="new-password" required>
          </div>

          <button type="submit" class="btn btn--solid btn--block" id="submitBtn">Create account</button>
        </form>

        <div class="auth-foot">
          <span>Already have an account? <a href="login.php">Log in</a></span>
          <a href="index.php">Back to store</a>
        </div>
      </div>
    </div>
  </main>
<?php
siteBodyClose();
?>
  <script>
  (function () {
    'use strict';

    const pwInput   = document.getElementById('password');
    const confInput = document.getElementById('confirm_password');
    const bars      = [document.getElementById('bar1'), document.getElementById('bar2'), document.getElementById('bar3')];
    const pwHint    = document.getElementById('pwHint');

    /* password strength */
    function getPwStrength(pw) {
      let score = 0;
      if (pw.length >= 6)  score++;
      if (pw.length >= 10) score++;
      if (/[A-Z]/.test(pw) || /[0-9]/.test(pw)) score++;
      return score;
    }

    const strengthColors = ['#b3261e', '#c77700', '#2e7d32'];
    const strengthLabels = ['Too short', 'Could be stronger', 'Strong password'];

    pwInput.addEventListener('input', function () {
      const pw = this.value;
      const score = getPwStrength(pw);
      bars.forEach((bar, i) => {
        bar.style.background = i < score ? strengthColors[score - 1] : '';
      });
      pwHint.textContent = pw.length === 0 ? '' : strengthLabels[Math.min(score - 1, 2)] || 'Too short';
      pwHint.style.color = pw.length === 0 ? '' : strengthColors[Math.min(score - 1, 2)] || strengthColors[0];
    });

    /* confirm password live validation */
    confInput.addEventListener('input', function () {
      const mismatch = pwInput.value && this.value && this.value !== pwInput.value;
      this.classList.toggle('input-error', !!mismatch);
    });

    /* submit */
    document.getElementById('registerForm').addEventListener('submit', function (e) {
      if (pwInput.value !== confInput.value) {
        e.preventDefault();
        confInput.classList.add('input-error');
        confInput.focus();
        return;
      }

      const btn = document.getElementById('submitBtn');
      btn.disabled = true;
      btn.textContent = 'Creating account...';
    });
  })();
  </script>
<?php siteDocClose();
