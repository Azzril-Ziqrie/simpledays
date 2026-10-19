<?php
// Halaman 404 mandiri: tanpa koneksi DB (menu kategori kosong).
header('HTTP/1.0 404 Not Found');
session_start();
require __DIR__ . '/includes/site_gate.php';

require_once __DIR__ . '/includes/currency_helper.php';
require_once __DIR__ . '/includes/layout.php';

siteHeadOpen([
    'title'       => 'Page not found',
    'description' => 'The page you are looking for could not be found.',
    'noindex'     => true,
]);
?>
<style>
  .nf{min-height:calc(100vh - var(--nav-h));display:flex;flex-direction:column;align-items:center;justify-content:center;gap:14px;padding:var(--gutter);text-align:center}
  .nf h1{font-size:clamp(40px,7vw,72px);font-weight:500;letter-spacing:-.02em;line-height:1}
  .nf p{color:var(--mute)}
  .nf a{margin-top:10px;color:var(--ink)}
</style>
<?php
siteBodyOpen(['bodyClass' => 'page-404']);
?>
<main class="page-main">
  <div class="nf">
    <h1>404</h1>
    <p>This page could not be found.</p>
    <a class="link-u" href="index.php">Back to home</a>
  </div>
</main>
<?php
siteBodyClose();
siteDocClose();
