<?php
/**
 * Menyajikan file upload yang disimpan di luar public_html (lihat includes/storage.php).
 * Dipanggil lewat .htaccess hanya kalau file yang diminta tidak ada di public_html,
 * jadi URL gambar tetap sama: /assets/images/products/x.jpg.
 */

require_once __DIR__ . '/includes/storage.php';

const MEDIA_TYPES = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'webp' => 'image/webp',
    'gif'  => 'image/gif',
    'svg'  => 'image/svg+xml',
    'mp4'  => 'video/mp4',
    'webm' => 'video/webm',
    'pdf'  => 'application/pdf',
];

$relative = ltrim((string)($_GET['p'] ?? ''), '/');
$ext      = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
$file     = isset(MEDIA_TYPES[$ext]) ? ddUploadedFile($relative) : null;

if ($file === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    exit('Not found');
}

$mtime = (int)filemtime($file);
$etag  = '"' . dechex($mtime) . '-' . dechex((int)filesize($file)) . '"';

header('Content-Type: ' . MEDIA_TYPES[$ext]);
header('X-Content-Type-Options: nosniff');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
header('ETag: ' . $etag);
// Nama file upload selalu unik (uniqid), jadi aman di-cache lama.
header('Cache-Control: public, max-age=31536000, immutable');

if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
    http_response_code(304);
    exit;
}

header('Content-Length: ' . filesize($file));
readfile($file);
