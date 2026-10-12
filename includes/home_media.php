<?php
/**
 * Satu baris foto / GIF di homepage (di bawah hero dan di bawah produk), diatur dari admin (admin/dashboard-home-media.php).
 * Membutuhkan tableExists() dari includes/db_schema.php.
 */

const HOME_MEDIA_PLACEMENTS = [
    'top'    => 'Above products',
    'bottom' => 'Below products',
];

/** Teks brand di atas video pertama di homepage (item bisa menimpanya dengan caption sendiri). */
const HOME_VIDEO_CAPTION = [
    'title'     => 'Inspired by Breakin’. Tools for the Creative Mind.',
    'text'      => 'S/DAYS creates tools to equip you for your creative journey. We’ll worry about the gears so you can focus on your craft.',
    'more'      => 'Check out our story and approach ',
    'link_text' => 'here',
    'link'      => 'lookbook.php',
];

function isVideoItem(array $item): bool
{
    return strtolower(pathinfo((string)$item['media'], PATHINFO_EXTENSION)) === 'gif';
}

function ensureHomeMediaTable(PDO $pdo): bool
{
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS home_media (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                media VARCHAR(500) NOT NULL,
                placement VARCHAR(10) NOT NULL DEFAULT 'top',
                title VARCHAR(150) DEFAULT NULL,
                subtitle VARCHAR(255) DEFAULT NULL,
                link VARCHAR(500) DEFAULT NULL,
                width SMALLINT UNSIGNED DEFAULT NULL,
                height SMALLINT UNSIGNED DEFAULT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_placement_sort (placement, is_active, sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        return true;
    } catch (Throwable $e) {
        error_log('[home_media] create table failed: ' . $e->getMessage());
        return false;
    }
}

/** Item aktif untuk satu penempatan, urut sesuai sort_order. */
function fetchHomeMedia(PDO $pdo, string $placement): array
{
    if (!tableExists($pdo, 'home_media')) {
        return [];
    }
    try {
        $stmt = $pdo->prepare(
            'SELECT id, media, title, subtitle, link, width, height
               FROM home_media
              WHERE is_active = 1 AND placement = :placement
              ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute(['placement' => $placement]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_values(array_filter($rows, static fn(array $row): bool => is_file(__DIR__ . '/../' . ltrim((string)$row['media'], '/'))));
    } catch (Throwable $e) {
        error_log('[home_media] query failed: ' . $e->getMessage());
        return [];
    }
}

function mediaHtmlEscape($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function renderVideoCaption(array $item): string
{
    $caption = HOME_VIDEO_CAPTION;
    $title   = $item['title'] ?: $caption['title'];
    $text    = $item['subtitle'] ?: $caption['text'];

    return '<figcaption>'
        . '<span class="media-title">' . mediaHtmlEscape($title) . '</span>'
        . '<span class="media-text">' . mediaHtmlEscape($text) . '</span>'
        . '<span class="media-more">' . mediaHtmlEscape($caption['more'])
        . '<a class="link-u" href="' . mediaHtmlEscape($caption['link']) . '">' . mediaHtmlEscape($caption['link_text']) . '</a>.</span>'
        . '</figcaption>';
}

/** ID video (GIF) pertama dari urutan baris-baris homepage; hanya video ini yang diberi teks brand. */
function firstVideoId(array ...$rows): ?int
{
    foreach ($rows as $items) {
        foreach ($items as $item) {
            if (isVideoItem($item)) {
                return (int)$item['id'];
            }
        }
    }
    return null;
}

function renderMediaItem(array $item, string $fallbackAlt, string $priority = 'auto', bool $brandCaption = false): string
{
    $alt  = mediaHtmlEscape($item['title'] ?: ($brandCaption ? HOME_VIDEO_CAPTION['title'] : $fallbackAlt));
    $dims = ($item['width'] && $item['height'])
        ? ' width="' . (int)$item['width'] . '" height="' . (int)$item['height'] . '"'
        : '';

    // Eager: gambar di baris geser yang di luar layar tidak boleh menunggu lazy-load (Safari/iOS sering tidak memuatnya).
    $classes = 'media-item' . (isVideoItem($item) ? ' is-video' : '') . ($brandCaption ? ' has-overlay' : '');
    $html  = '<figure class="' . $classes . '">';
    $html .= '<img src="' . mediaHtmlEscape($item['media']) . '" alt="' . $alt . '"' . $dims . ' loading="eager" decoding="async" fetchpriority="' . mediaHtmlEscape($priority) . '">';
    if ($brandCaption) {
        $html .= renderVideoCaption($item);
    } elseif ($item['title'] || $item['subtitle']) {
        $html .= '<figcaption>';
        if ($item['title'])    { $html .= '<span class="media-title">' . mediaHtmlEscape($item['title']) . '</span>'; }
        if ($item['subtitle']) { $html .= '<span class="media-sub">' . mediaHtmlEscape($item['subtitle']) . '</span>'; }
        $html .= '</figcaption>';
    }
    $html .= '</figure>';

    // Caption brand punya link sendiri; jangan bungkus <a> di dalam <a>.
    if (!empty($item['link']) && !$brandCaption) {
        $html = '<a class="media-link" href="' . mediaHtmlEscape($item['link']) . '">' . $html . '</a>';
    }
    return $html;
}

/** Satu baris foto/GIF yang bisa digeser. Kosong kalau tidak ada item. */
function renderHomeMedia(array $items, string $fallbackAlt, string $priority = 'auto', ?int $brandCaptionId = null): string
{
    if (!$items) {
        return '';
    }
    $html = '<div class="media-strip">';
    foreach ($items as $item) {
        $html .= renderMediaItem($item, $fallbackAlt, $priority, (int)$item['id'] === $brandCaptionId);
    }
    return $html . '</div>';
}
