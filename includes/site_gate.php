<?php
/**
 * Gate untuk index.php: password → pilih tujuan (Storefront / Admin).
 * Dipanggil sebelum apa pun dirender; mengakhiri request sendiri jika belum selesai.
 */
require_once __DIR__ . '/../config/env.php';

// Gate hanya aktif kalau SITE_LOCK=1 (di config/secrets.local.php komputer lokal).
// Di hosting nilainya kosong → beranda langsung terbuka untuk publik.
if (ddSecret('SITE_LOCK') !== '1') {
    return;
}

const SITE_LOCK_PASSWORD = 'simpledays';
const SITE_GATE_BG       = 'assets/images/hero/sd-hero-1.webp';

$lockError = false;

if (($_POST['site_password'] ?? null) !== null) {
    if (hash_equals(SITE_LOCK_PASSWORD, (string)$_POST['site_password'])) {
        session_regenerate_id(true);
        $_SESSION['site_unlocked'] = true;
        unset($_SESSION['site_entered']);
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }
    $lockError = true;
}

$unlocked = !empty($_SESSION['site_unlocked']);

// Pilihan "Storefront": tandai sesi, lalu masuk ke beranda.
if ($unlocked && ($_GET['enter'] ?? '') === 'store') {
    $_SESSION['site_entered'] = true;
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

if ($unlocked && !empty($_SESSION['site_entered'])) {
    return; // lanjut render beranda
}

http_response_code($unlocked ? 200 : 401);
header('Cache-Control: no-store');
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Simple Days</title>
  <link rel="preload" as="image" href="<?= SITE_GATE_BG ?>">
  <style>
    :root {
      --spring: cubic-bezier(.2, .9, .25, 1.05);
      --ease:   cubic-bezier(.22, 1, .36, 1);
      --glass:  rgba(255, 255, 255, .16);
      --glass-strong: rgba(255, 255, 255, .26);
      --edge:   rgba(255, 255, 255, .38);
    }
    * { box-sizing: border-box; }
    html, body { height: 100%; margin: 0; }
    body {
      position: relative; overflow: hidden;
      display: flex; align-items: center; justify-content: center;
      padding: 24px;
      font: 15px/1.5 -apple-system, BlinkMacSystemFont, "SF Pro Text", "Helvetica Neue", system-ui, sans-serif;
      color: #fff; background: #111;
      -webkit-font-smoothing: antialiased;
    }

    /* hero banner as backdrop */
    .bg {
      position: fixed; inset: -4%;
      background: url('<?= SITE_GATE_BG ?>') center / cover no-repeat;
      animation: drift 28s ease-in-out infinite alternate;
      z-index: 0;
    }
    .veil {
      position: fixed; inset: 0; z-index: 1;
      background:
        radial-gradient(120% 90% at 50% 100%, rgba(0,0,0,.55), transparent 60%),
        linear-gradient(rgba(0,0,0,.22), rgba(0,0,0,.38));
    }
    @keyframes drift { from { transform: scale(1.02) translate3d(0,0,0); } to { transform: scale(1.09) translate3d(-1.2%, -1%, 0); } }

    .stage { position: relative; z-index: 2; width: min(760px, 100%); text-align: center; }

    .mark {
      font-size: 13px; font-weight: 600; letter-spacing: .32em; text-transform: uppercase;
      opacity: .92; margin-bottom: 18px;
    }
    h1 {
      margin: 0 0 6px;
      font-size: clamp(2rem, 5.4vw, 3.25rem); line-height: 1.04;
      letter-spacing: -.028em; font-weight: 700;
    }
    .lede { margin: 0 0 32px; font-size: 16px; opacity: .82; letter-spacing: -.005em; }

    /* materialise on enter */
    .stage > * { animation: arrive .7s var(--ease) both; }
    .stage > *:nth-child(2) { animation-delay: .04s; }
    .stage > *:nth-child(3) { animation-delay: .08s; }
    .stage > *:nth-child(4) { animation-delay: .14s; }
    @keyframes arrive {
      from { opacity: 0; transform: translateY(14px) scale(.985); filter: blur(10px); }
      to   { opacity: 1; transform: none; filter: blur(0); }
    }

    /* password */
    .lock {
      display: flex; gap: 8px; width: min(340px, 100%); margin: 0 auto;
      padding: 6px; border-radius: 18px;
      background: var(--glass); border: 1px solid var(--edge);
      backdrop-filter: blur(24px) saturate(180%); -webkit-backdrop-filter: blur(24px) saturate(180%);
      box-shadow: 0 18px 50px rgba(0,0,0,.28), inset 0 1px 0 rgba(255,255,255,.35);
      transition: box-shadow .3s var(--ease), transform .3s var(--ease);
    }
    .lock:focus-within { transform: translateY(-1px); box-shadow: 0 24px 60px rgba(0,0,0,.34), inset 0 1px 0 rgba(255,255,255,.45), 0 0 0 3px rgba(255,255,255,.28); }
    .lock input {
      flex: 1; min-width: 0; padding: 12px 14px; border: 0; outline: 0; background: transparent;
      color: #fff; font: inherit; font-size: 16px; letter-spacing: .02em;
    }
    .lock input::placeholder { color: rgba(255,255,255,.7); }
    .lock button {
      flex: none; padding: 0 18px; border: 0; border-radius: 13px; cursor: pointer;
      background: #fff; color: #111; font: inherit; font-weight: 600;
      transition: transform .12s ease-out, opacity .2s;
    }
    .lock button:active { transform: scale(.95); }
    .err { margin-top: 14px; font-size: 13px; color: #ffd7d7; text-shadow: 0 1px 8px rgba(0,0,0,.4); }
    .shake { animation: shake .42s var(--ease); }
    @keyframes shake { 20%{transform:translateX(-7px)} 45%{transform:translateX(6px)} 70%{transform:translateX(-3px)} }

    /* choices */
    .choices { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
    .choice {
      position: relative; display: flex; flex-direction: column; align-items: flex-start; gap: 6px;
      padding: 22px 22px 20px; min-height: 172px; text-align: left; text-decoration: none; color: #fff;
      border-radius: 26px;
      background: var(--glass); border: 1px solid var(--edge);
      backdrop-filter: blur(26px) saturate(180%); -webkit-backdrop-filter: blur(26px) saturate(180%);
      box-shadow: 0 20px 60px rgba(0,0,0,.26), inset 0 1px 0 rgba(255,255,255,.4);
      transition: transform .45s var(--spring), background .25s, box-shadow .35s var(--ease);
      -webkit-tap-highlight-color: transparent;
    }
    .choice:hover, .choice:focus-visible {
      transform: translateY(-4px);
      background: var(--glass-strong);
      box-shadow: 0 30px 70px rgba(0,0,0,.34), inset 0 1px 0 rgba(255,255,255,.5);
      outline: 0;
    }
    .choice:focus-visible { box-shadow: 0 30px 70px rgba(0,0,0,.34), 0 0 0 3px rgba(255,255,255,.7); }
    .choice:active { transform: scale(.97); transition-duration: .1s; }
    .choice__icon {
      width: 46px; height: 46px; border-radius: 14px; display: grid; place-items: center;
      background: rgba(255,255,255,.9); color: #111; margin-bottom: auto;
    }
    .choice__icon svg { width: 24px; height: 24px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
    .choice__title { font-size: 21px; font-weight: 650; letter-spacing: -.02em; margin-top: 22px; }
    .choice__desc  { font-size: 14px; opacity: .8; line-height: 1.35; }
    .choice__go {
      position: absolute; top: 22px; right: 22px; width: 18px; height: 18px; opacity: .65;
      fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
      transition: transform .4s var(--spring), opacity .2s;
    }
    .choice:hover .choice__go { transform: translateX(4px); opacity: 1; }

    .foot { margin-top: 26px; font-size: 12px; opacity: .6; letter-spacing: .02em; }
    .foot a { color: inherit; }

    @media (max-width: 560px) {
      .choices { grid-template-columns: 1fr; }
      .choice { min-height: 132px; }
    }
    @media (prefers-reduced-motion: reduce) {
      .bg, .stage > *, .shake { animation: none; }
      .choice, .lock, .lock button, .choice__go { transition: none; }
    }
    @media (prefers-reduced-transparency: reduce) {
      .lock, .choice { backdrop-filter: none; -webkit-backdrop-filter: none; background: rgba(20,20,20,.82); }
    }
  </style>
</head>
<body>
  <div class="bg" aria-hidden="true"></div>
  <div class="veil" aria-hidden="true"></div>

  <main class="stage">
<?php if (!$unlocked): ?>
    <div class="mark">S/DAYS</div>
    <h1>Simple Days</h1>
    <p class="lede">Enter the password to continue.</p>
    <form method="post" autocomplete="off" class="lock<?= $lockError ? ' shake' : '' ?>">
      <input type="password" name="site_password" placeholder="Password" autofocus required aria-label="Password">
      <button type="submit">Enter</button>
    </form>
    <?php if ($lockError): ?><div class="err" role="alert">Wrong password. Try again.</div><?php endif; ?>
<?php else: ?>
    <div class="mark">S/DAYS</div>
    <h1>Where to?</h1>
    <p class="lede">Choose where you want to go.</p>
    <div class="choices">
      <a class="choice" href="?enter=store">
        <span class="choice__icon"><svg viewBox="0 0 24 24"><path d="M5 8h14l-1 12H6z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg></span>
        <svg class="choice__go" viewBox="0 0 24 24"><path d="M9 6l6 6-6 6"/></svg>
        <span class="choice__title">Storefront</span>
        <span class="choice__desc">The shop as customers see it.</span>
      </a>
      <a class="choice" href="admin/dashboard.php">
        <span class="choice__icon"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg></span>
        <svg class="choice__go" viewBox="0 0 24 24"><path d="M9 6l6 6-6 6"/></svg>
        <span class="choice__title">Admin dashboard</span>
        <span class="choice__desc">Orders, products and content.</span>
      </a>
    </div>
<?php endif; ?>
  </main>
</body>
</html>
<?php
exit;
