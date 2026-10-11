<?php
// Atur mata uang TAMPILAN (estimasi harga). Tagihan & order tetap USD.
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/currency_helper.php';

$to    = strtoupper(trim((string)($_GET['to'] ?? 'USD')));
$rates = ddExchangeRates();
$_SESSION['display_currency'] = ($to !== 'USD' && $rates && !empty($rates[$to])) ? $to : 'USD';
$_SESSION['currency_prompt_done'] = true; // jangan tanya lagi di sesi ini

// Kembali ke halaman asal (hanya kalau satu domain), tanpa ?choose_currency supaya pop-up tidak terbuka lagi.
$back = 'index.php';
$ref  = (string)($_SERVER['HTTP_REFERER'] ?? '');
if ($ref !== '' && (parse_url($ref, PHP_URL_HOST) ?? '') === ($_SERVER['HTTP_HOST'] ?? '')) {
    $back = preg_replace('/([?&])choose_currency(=[^&#]*)?(&|$)/', '$1', $ref);
    $back = rtrim((string)$back, '?&');
}
header('Location: ' . $back);
exit;
