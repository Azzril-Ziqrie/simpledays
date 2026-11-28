<?php
/**
 * Lokasi file upload (foto produk, hero, home, lookbook, blog, bukti bayar).
 *
 * Auto-deploy Git Hostinger membuat ulang folder public_html dari isi repo, sehingga file
 * yang di-upload lewat admin (tidak ada di git) ikut terhapus setiap push. Karena itu,
 * di hosting file upload disimpan di folder `storage/` di SAMPING public_html
 * (…/domains/<domain>/storage/), yang tidak disentuh deploy.
 *
 * Path di database tetap sama (mis. assets/images/products/x.jpg). URL-nya juga sama:
 * kalau file tidak ada di public_html, .htaccess meneruskan request ke media.php,
 * yang mengambil file dari storage. File lama yang ter-commit di git tetap dilayani langsung.
 *
 * Lokal (XAMPP) folder project bukan public_html, jadi file tetap disimpan di folder project
 * seperti sebelumnya. Bisa ditimpa lewat secret UPLOAD_ROOT (path absolut).
 */

require_once __DIR__ . '/../config/env.php';

/** Folder yang boleh berisi file upload, relatif terhadap root web. */
const DD_UPLOAD_DIRS = [
    'assets/images/products/',
    'assets/images/lookbook/',
    'assets/images/hero/',
    'assets/images/home/',
    'uploads/blogs/',
    'uploads/payment-proofs/',
];

/** Root project (folder yang berisi index.php), berakhiran /. */
function ddProjectRoot(): string
{
    return str_replace('\\', '/', dirname(__DIR__)) . '/';
}

/** Root tempat file upload ditulis, berakhiran /. */
function ddStorageRoot(): string
{
    static $root = null;
    if ($root !== null) return $root;

    $configured = trim(ddSecret('UPLOAD_ROOT'));
    if ($configured !== '') {
        $root = rtrim(str_replace('\\', '/', $configured), '/') . '/';
    } elseif (basename(rtrim(ddProjectRoot(), '/')) === 'public_html') {
        $root = dirname(rtrim(ddProjectRoot(), '/')) . '/storage/';
        if (!is_dir($root)) @mkdir($root, 0775, true);
        if (!is_dir($root) || !is_writable($root)) {
            // Jangan sampai upload gagal total; file tetap tersimpan (tapi bisa terhapus saat deploy).
            error_log('[storage] cannot use ' . $root . ', falling back to public_html');
            $root = ddProjectRoot();
        }
    } else {
        $root = ddProjectRoot();
    }
    return $root;
}

/** True kalau $relative adalah file di salah satu folder upload (tanpa "..", tanpa subfolder). */
function ddIsUploadPath(string $relative): bool
{
    if (strpos($relative, '..') !== false || strpos($relative, "\0") !== false) return false;
    foreach (DD_UPLOAD_DIRS as $dir) {
        if (strpos($relative, $dir) === 0 && preg_match('#^[A-Za-z0-9._-]+$#', substr($relative, strlen($dir)))) {
            return true;
        }
    }
    return false;
}

/** Folder absolut untuk menulis upload baru, mis. ddUploadDir('assets/images/products/'). Dibuat kalau belum ada. */
function ddUploadDir(string $relativeDir): string
{
    $abs = ddStorageRoot() . trim($relativeDir, '/') . '/';
    if (!is_dir($abs)) @mkdir($abs, 0775, true);
    return $abs;
}

/**
 * Path absolut file upload yang sudah ada: dicari di storage dulu, lalu di folder project
 * (file lama yang ter-commit). Null kalau tidak ada.
 */
function ddUploadedFile(?string $relative): ?string
{
    $relative = ltrim((string)$relative, '/');
    if ($relative === '' || !ddIsUploadPath($relative)) return null;
    foreach (array_unique([ddStorageRoot(), ddProjectRoot()]) as $root) {
        if (is_file($root . $relative)) return $root . $relative;
    }
    return null;
}

/** Hapus file upload (di storage atau project). Path di luar folder upload diabaikan. */
function ddDeleteUploadedFile(?string $relative): void
{
    $abs = ddUploadedFile($relative);
    if ($abs !== null) @unlink($abs);
}
