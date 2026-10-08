<?php
// Halaman pembayaran sekarang ada di checkout.php. File ini hanya meneruskan link lama.
$order = trim((string)($_GET['order'] ?? ''));
header('Location: checkout.php' . ($order !== '' ? '?order=' . urlencode($order) : ''), true, 301);
exit;
