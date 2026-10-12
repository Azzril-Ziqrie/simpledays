<?php
/**
 * Simpan gambar upload admin dalam bentuk ringan.
 *
 * - JPG/PNG/WEBP: diperkecil (lebar maks) lalu disimpan sebagai WEBP
 *   (kalau GD hosting tanpa WEBP: JPEG, atau PNG untuk PNG).
 * - GIF: disimpan apa adanya (animasi dipertahankan), dibatasi ukurannya.
 * - Kalau GD sama sekali tidak ada, file asli disimpan tanpa kompresi.
 */

const MEDIA_WEBP_QUALITY    = 76;
const MEDIA_GIF_MAX_BYTES   = 8 * 1024 * 1024;
const MEDIA_ALLOWED_MIME    = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
];

function uploadedFileMime(string $tmpPath): string
{
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = (string)finfo_file($finfo, $tmpPath);
    finfo_close($finfo);
    return $mime;
}

/** GD image resource dari file upload, dengan orientasi EXIF dikoreksi. */
function loadGdImage(string $path, string $mime)
{
    $image = @imagecreatefromstring((string)file_get_contents($path));
    if (!$image) {
        return null;
    }
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $orientation = (int)(@exif_read_data($path)['Orientation'] ?? 1);
        $angles = [3 => 180, 6 => -90, 8 => 90];
        if (isset($angles[$orientation])) {
            $rotated = imagerotate($image, $angles[$orientation], 0);
            if ($rotated) {
                $image = $rotated;
            }
        }
    }
    return $image;
}

function resizeToWidth($image, int $maxWidth)
{
    $width = imagesx($image);
    if ($width <= $maxWidth) {
        return $image;
    }
    $height  = (int)round(imagesy($image) * $maxWidth / $width);
    $resized = imagecreatetruecolor($maxWidth, $height);
    imagealphablending($resized, false);
    imagesavealpha($resized, true);
    imagecopyresampled($resized, $image, 0, 0, 0, 0, $maxWidth, $height, $width, imagesy($image));
    return $resized;
}

/**
 * Perkecil lalu simpan: WEBP kalau GD mendukung, kalau tidak JPEG (PNG tetap PNG).
 * @return string|null ekstensi file yang ditulis, null kalau gagal (pemanggil menyimpan file asli)
 */
function saveResizedImage(string $tmpPath, string $mime, string $targetBase, int $maxWidth): ?string
{
    $image = loadGdImage($tmpPath, $mime);
    if (!$image) {
        return null;
    }
    $image = resizeToWidth($image, $maxWidth);
    imagepalettetotruecolor($image);

    if (function_exists('imagewebp')) {
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $ok  = imagewebp($image, $targetBase . '.webp', MEDIA_WEBP_QUALITY);
        $ext = 'webp';
    } elseif ($mime === 'image/png') {
        imagesavealpha($image, true);
        $ok  = imagepng($image, $targetBase . '.png', 8);
        $ext = 'png';
    } else {
        $ok  = imagejpeg($image, $targetBase . '.jpg', 80);
        $ext = 'jpg';
    }
    imagedestroy($image);
    return $ok ? $ext : null;
}

/**
 * @param array  $file    entri $_FILES
 * @param string $dirAbs  folder tujuan (absolut, berakhiran /)
 * @param string $dirWeb  folder yang sama relatif terhadap root web (berakhiran /)
 * @return string|null    path relatif untuk disimpan di DB, null kalau tidak ada file
 * @throws RuntimeException kalau upload gagal / tipe tidak diizinkan
 */
function storeUploadedImage(array $file, string $dirAbs, string $dirWeb, string $prefix, int $maxWidth = 2000): ?string
{
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed (code ' . (int)$error . ').');
    }

    $mime = uploadedFileMime($file['tmp_name']);
    if (!isset(MEDIA_ALLOWED_MIME[$mime])) {
        throw new RuntimeException('Only JPG, PNG, WEBP and GIF files are allowed.');
    }
    if (!is_dir($dirAbs) && !@mkdir($dirAbs, 0775, true)) {
        throw new RuntimeException('Upload folder is not writable.');
    }

    $basename = uniqid($prefix, true);

    if ($mime === 'image/gif' && (int)$file['size'] > MEDIA_GIF_MAX_BYTES) {
        throw new RuntimeException('GIF is too large (max 8 MB). Please compress it first.');
    }

    if ($mime !== 'image/gif' && function_exists('imagecreatefromstring')) {
        $saved = saveResizedImage($file['tmp_name'], $mime, $dirAbs . $basename, $maxWidth);
        if ($saved !== null) {
            return $dirWeb . $basename . '.' . $saved;
        }
    }

    $target = $basename . '.' . MEDIA_ALLOWED_MIME[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dirAbs . $target)) {
        throw new RuntimeException('Failed to save uploaded image.');
    }
    return $dirWeb . $target;
}

/** Hapus file media di bawah assets/images saja (tidak pernah keluar folder itu). */
function deleteMediaFile(?string $relativePath): void
{
    if (!$relativePath || strpos($relativePath, 'assets/images/') !== 0 || strpos($relativePath, '..') !== false) {
        return;
    }
    $absolute = __DIR__ . '/../' . $relativePath;
    if (is_file($absolute)) {
        @unlink($absolute);
    }
}

/** [lebar, tinggi] gambar tersimpan, atau [null, null]. */
function storedImageSize(string $relativePath): array
{
    $info = @getimagesize(__DIR__ . '/../' . $relativePath);
    return $info ? [(int)$info[0], (int)$info[1]] : [null, null];
}
