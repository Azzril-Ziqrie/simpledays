<?php
/**
 * Announcement bar hitam di paling atas storefront. Diatur dari admin (admin/dashboard-announcement.php),
 * disimpan sebagai satu baris JSON di admin_settings (key "announcement").
 */

const ANNOUNCEMENT_KEY = 'announcement';

const ANNOUNCEMENT_ANIMATIONS = [
    'marquee' => 'Scrolling ticker',
    'slide'   => 'Slide up, one message at a time',
    'fade'    => 'Fade, one message at a time',
    'static'  => 'No animation',
];

const ANNOUNCEMENT_DEFAULTS = [
    'enabled'   => false,
    'messages'  => [],
    'link'      => '',
    'animation' => 'marquee',
    'speed'     => 25,
];

/** Pengaturan tersimpan, digabung dengan default; selalu array lengkap. */
function announcementSettings(PDO $pdo): array
{
    $settings = ANNOUNCEMENT_DEFAULTS;
    if (!tableExists($pdo, 'admin_settings')) {
        return $settings;
    }
    try {
        $stmt = $pdo->prepare('SELECT setting_value FROM admin_settings WHERE setting_key = :k LIMIT 1');
        $stmt->execute(['k' => ANNOUNCEMENT_KEY]);
        $saved = json_decode((string)$stmt->fetchColumn(), true);
    } catch (Throwable $e) {
        error_log('[announcement] read failed: ' . $e->getMessage());
        return $settings;
    }
    return is_array($saved) ? announcementNormalize($saved) : $settings;
}

/** Rapikan input (form atau JSON tersimpan) ke bentuk yang aman dipakai. */
function announcementNormalize(array $in): array
{
    $messages = $in['messages'] ?? [];
    if (is_string($messages)) {
        $messages = preg_split('/\R/', $messages) ?: [];
    }
    $messages = array_values(array_filter(array_map(
        static fn($m) => mb_substr(trim((string)$m), 0, 160),
        (array)$messages
    ), static fn($m) => $m !== ''));

    $animation = (string)($in['animation'] ?? '');

    return [
        'enabled'   => !empty($in['enabled']),
        'messages'  => array_slice($messages, 0, 10),
        'link'      => trim((string)($in['link'] ?? '')),
        'animation' => isset(ANNOUNCEMENT_ANIMATIONS[$animation]) ? $animation : ANNOUNCEMENT_DEFAULTS['animation'],
        'speed'     => max(2, min(120, (int)($in['speed'] ?? ANNOUNCEMENT_DEFAULTS['speed']))),
    ];
}

function announcementSave(PDO $pdo, array $settings): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS admin_settings (
            setting_key VARCHAR(50) NOT NULL,
            setting_value TEXT NOT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
    $pdo->prepare(
        'INSERT INTO admin_settings (setting_key, setting_value) VALUES (:k, :v)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    )->execute(['k' => ANNOUNCEMENT_KEY, 'v' => json_encode($settings, JSON_UNESCAPED_UNICODE)]);
}

/** Pengaturan yang siap tampil, atau null bila bar mati / tidak ada pesan. */
function announcementForDisplay(PDO $pdo): ?array
{
    $settings = announcementSettings($pdo);
    return $settings['enabled'] && $settings['messages'] ? $settings : null;
}

/** HTML bar. $a berasal dari announcementForDisplay(). */
function renderAnnouncement(array $a): string
{
    $esc  = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    $link = $a['link'];
    $item = static function (string $text) use ($esc, $link): string {
        return $link !== ''
            ? '<a class="announce__msg" href="' . $esc($link) . '">' . $esc($text) . '</a>'
            : '<span class="announce__msg">' . $esc($text) . '</span>';
    };
    $animation = $a['animation'];
    $speed     = (int)$a['speed'];
    $out       = '<div class="announce announce--' . $esc($animation) . '" role="region" aria-label="Announcement" style="--announce-speed:' . $speed . 's">';

    if ($animation === 'marquee') {
        // Dua grup identik; keduanya bergeser selebar dirinya sendiri → putaran mulus tanpa JS.
        $group = '';
        foreach ($a['messages'] as $m) {
            $group .= $item($m) . '<span class="announce__sep" aria-hidden="true">/</span>';
        }
        $out .= '<div class="announce__track"><div class="announce__group">' . $group . '</div>'
              . '<div class="announce__group" aria-hidden="true">' . $group . '</div></div>';
    } else {
        $out .= '<div class="announce__stage">';
        foreach ($a['messages'] as $i => $m) {
            $out .= '<div class="announce__slide' . ($i === 0 ? ' is-active' : '') . '">' . $item($m) . '</div>';
        }
        $out .= '</div>';
        if (in_array($animation, ['slide', 'fade'], true) && count($a['messages']) > 1) {
            $ms   = $speed * 1000;
            $out .= <<<HTML
<script>
(function () {
  var bar = document.currentScript.parentNode;
  var slides = bar.querySelectorAll('.announce__slide'), cur = 0;
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  setInterval(function () {
    var next = slides[(cur + 1) % slides.length];
    next.classList.add('no-t'); next.classList.remove('is-out'); void next.offsetWidth; next.classList.remove('no-t');
    slides[cur].classList.remove('is-active'); slides[cur].classList.add('is-out');
    next.classList.add('is-active');
    cur = (cur + 1) % slides.length;
  }, $ms);
})();
</script>
HTML;
        }
    }
    return $out . "</div>\n";
}
