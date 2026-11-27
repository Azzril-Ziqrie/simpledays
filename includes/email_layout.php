<?php
declare(strict_types=1);

/**
 * Layout email bersama (campaign, invoice, resi) — gaya Apple:
 * font sistem, tracking sesuai ukuran (judul rapat, isi normal), hierarki dari weight + size,
 * kartu putih membulat di atas latar abu lembut, tombol 44px, dan dukungan dark mode
 * (Apple Mail / iOS Mail; Gmail mengabaikan media query dan tetap tampil terang).
 *
 * Semua gaya inline karena email client membuang <style> eksternal.
 */

require_once __DIR__ . '/brand.php';

const EMAIL_FONT      = "-apple-system,BlinkMacSystemFont,'SF Pro Text','Helvetica Neue',Helvetica,Arial,sans-serif";
const EMAIL_INK       = '#1d1d1f'; // teks utama
const EMAIL_INK_2     = '#6e6e73'; // teks sekunder
const EMAIL_LINE      = '#e8e8ed'; // pemisah halus
const EMAIL_CANVAS    = '#f5f5f7'; // latar di luar kartu
const EMAIL_SURFACE   = '#ffffff'; // kartu
const EMAIL_FILL      = '#f5f5f7'; // blok sorotan di dalam kartu

function ddEmailEsc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function ddEmailSiteUrl(): string
{
    return rtrim(defined('DD_SITE_URL') ? (string)DD_SITE_URL : BRAND_URL, '/');
}

/**
 * Dokumen email lengkap.
 * $preheader : teks pratinjau di daftar inbox (tidak terlihat di badan email).
 * $content   : HTML badan (pakai helper ddEmail* di bawah).
 * $footerNote: baris tambahan di footer, mis. link unsubscribe (HTML, sudah di-escape pemanggil).
 */
function ddEmailLayout(string $title, string $preheader, string $content, string $footerNote = ''): string
{
    $site  = ddEmailSiteUrl();
    $brand = ddEmailEsc(BRAND_NAME);
    $font  = EMAIL_FONT;

    $links = '<a href="' . ddEmailEsc($site) . '" class="em-link-2" style="color:' . EMAIL_INK_2 . ';text-decoration:none;">Shop</a>';
    if (BRAND_INSTAGRAM !== '') {
        $links .= '&nbsp;&nbsp;·&nbsp;&nbsp;<a href="' . ddEmailEsc(BRAND_INSTAGRAM) . '" class="em-link-2" style="color:' . EMAIL_INK_2 . ';text-decoration:none;">Instagram</a>';
    }
    if (BRAND_EMAIL !== '') {
        $links .= '&nbsp;&nbsp;·&nbsp;&nbsp;<a href="mailto:' . ddEmailEsc(BRAND_EMAIL) . '" class="em-link-2" style="color:' . EMAIL_INK_2 . ';text-decoration:none;">Contact</a>';
    }

    return '<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="color-scheme" content="light dark">
<meta name="supported-color-schemes" content="light dark">
<title>' . ddEmailEsc($title) . '</title>
<style>
  :root { color-scheme: light dark; supported-color-schemes: light dark; }
  @media (prefers-color-scheme: dark) {
    .em-canvas  { background:#000000 !important; }
    .em-card    { background:#1c1c1e !important; }
    .em-fill    { background:#2c2c2e !important; }
    .em-ink     { color:#f5f5f7 !important; }
    .em-ink-2, .em-link-2 { color:#a1a1a6 !important; }
    .em-line    { border-color:#38383a !important; }
    .em-logo    { filter:invert(1) !important; }
    .em-btn     { background:#f5f5f7 !important; }
    .em-btn a   { color:#1d1d1f !important; }
  }
  @media (max-width:620px) {
    .em-pad { padding-left:24px !important; padding-right:24px !important; }
  }
</style>
</head>
<body class="em-canvas" style="margin:0;padding:0;background:' . EMAIL_CANVAS . ';-webkit-text-size-adjust:100%;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">' . ddEmailEsc($preheader) . '&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="em-canvas" style="background:' . EMAIL_CANVAS . ';">
  <tr><td align="center" style="padding:40px 16px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;">
      <tr><td align="center" style="padding:0 0 28px;">
        <a href="' . ddEmailEsc($site) . '"><img src="' . ddEmailEsc($site . '/' . BRAND_LOGO) . '" alt="' . $brand . '" height="32" class="em-logo" style="display:block;height:32px;width:auto;border:0;"></a>
      </td></tr>
      <tr><td class="em-card em-pad" style="background:' . EMAIL_SURFACE . ';border-radius:18px;padding:48px 48px 44px;font-family:' . $font . ';">
        ' . $content . '
      </td></tr>
      <tr><td align="center" class="em-pad" style="padding:28px 48px 0;font-family:' . $font . ';font-size:12px;line-height:1.6;color:' . EMAIL_INK_2 . ';">
        <div class="em-ink-2" style="margin-bottom:8px;">' . $links . '</div>
        <div class="em-ink-2">&copy; ' . date('Y') . ' ' . $brand . '</div>
        ' . ($footerNote !== '' ? '<div class="em-ink-2" style="margin-top:8px;">' . $footerNote . '</div>' : '') . '
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>';
}

/** Judul besar: tracking negatif & leading rapat untuk ukuran display. */
function ddEmailHeading(string $text): string
{
    return '<h1 class="em-ink" style="margin:0 0 12px;font-size:28px;line-height:1.14;font-weight:600;letter-spacing:-0.02em;color:' . EMAIL_INK . ';">' . ddEmailEsc($text) . '</h1>';
}

/** Paragraf isi. $html sudah aman (di-escape pemanggil). */
function ddEmailParagraph(string $html, bool $secondary = false): string
{
    $color = $secondary ? EMAIL_INK_2 : EMAIL_INK;
    $class = $secondary ? 'em-ink-2' : 'em-ink';
    return '<p class="' . $class . '" style="margin:0 0 16px;font-size:16px;line-height:1.55;color:' . $color . ';">' . $html . '</p>';
}

/** Blok sorotan: label kecil + nilai besar (nomor order, nomor resi). */
function ddEmailHighlight(string $label, string $value): string
{
    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:28px 0;">
      <tr><td class="em-fill" style="background:' . EMAIL_FILL . ';border-radius:12px;padding:20px 24px;">
        <div class="em-ink-2" style="font-size:13px;line-height:1.4;color:' . EMAIL_INK_2 . ';">' . ddEmailEsc($label) . '</div>
        <div class="em-ink" style="margin-top:4px;font-size:22px;line-height:1.25;font-weight:600;letter-spacing:-0.01em;color:' . EMAIL_INK . ';">' . ddEmailEsc($value) . '</div>
      </td></tr>
    </table>';
}

/**
 * Daftar label → nilai (rata kiri/kanan), dipisah garis tipis.
 * @param array<int,array{0:string,1:string,2?:bool}> $rows [label, nilai HTML aman, tebal?]
 */
function ddEmailDetails(array $rows): string
{
    $html = '';
    $last = count($rows) - 1;
    foreach (array_values($rows) as $i => $row) {
        [$label, $valueHtml] = $row;
        $strong = !empty($row[2]);
        $border = $i < $last ? 'border-bottom:1px solid ' . EMAIL_LINE . ';' : '';
        $weight = $strong ? 'font-weight:600;' : '';
        $html .= '<tr>
          <td class="em-ink-2 em-line" style="padding:12px 0;' . $border . 'font-size:15px;line-height:1.4;color:' . EMAIL_INK_2 . ';' . $weight . '">' . ddEmailEsc($label) . '</td>
          <td class="em-ink em-line" align="right" style="padding:12px 0;' . $border . 'font-size:15px;line-height:1.4;color:' . EMAIL_INK . ';' . $weight . '">' . $valueHtml . '</td>
        </tr>';
    }
    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;margin:8px 0 24px;">' . $html . '</table>';
}

/** Subjudul bagian. */
function ddEmailSection(string $text): string
{
    return '<h2 class="em-ink" style="margin:32px 0 4px;font-size:17px;line-height:1.3;font-weight:600;letter-spacing:-0.01em;color:' . EMAIL_INK . ';">' . ddEmailEsc($text) . '</h2>';
}

/** Tombol aksi utama: tinggi 44px (target sentuh), sudut membulat penuh. */
function ddEmailButton(string $label, string $url): string
{
    return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:28px 0 8px;">
      <tr><td class="em-btn" align="center" style="background:' . EMAIL_INK . ';border-radius:980px;">
        <a href="' . ddEmailEsc($url) . '" style="display:inline-block;padding:12px 28px;font-family:' . EMAIL_FONT . ';font-size:15px;line-height:20px;font-weight:500;color:#ffffff;text-decoration:none;">' . ddEmailEsc($label) . '</a>
      </td></tr>
    </table>';
}

/** Penutup surat. */
function ddEmailSignoff(string $text = 'Thank you,'): string
{
    return '<p class="em-ink-2" style="margin:32px 0 0;font-size:16px;line-height:1.55;color:' . EMAIL_INK_2 . ';">' . ddEmailEsc($text) . '<br><span class="em-ink" style="color:' . EMAIL_INK . ';font-weight:600;">' . ddEmailEsc(BRAND_NAME) . '</span></p>';
}
