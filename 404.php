<?php
// Alamat yang tidak ada (/asdasd) → beranda, dengan pesan kecil di sana (index.php membaca ?notfound=1).
// 302 (bukan 301) supaya mesin pencari tidak menganggap beranda sebagai pengganti permanen.
$base = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
header('Location: ' . $base . '/?notfound=1', true, 302);
exit;
