<?php
/**
 * Analytics sederhana milik sendiri: pengunjung, halaman populer, funnel pembelian, negara asal.
 * Satu baris per kejadian di analytics_events; dibaca admin/dashboard-analytics.php.
 *
 * Pengunjung = sesi PHP (id acak di $_SESSION), tanpa cookie tambahan dan tanpa menyimpan IP.
 * Membutuhkan ddDetectVisitorCurrency() / ddClientIp() dari includes/currency_helper.php.
 */

const ANALYTICS_BOT_PATTERN = '/bot|crawl|spider|slurp|preview|fetch|monitor|curl|wget|python|httpclient|headless|lighthouse/i';

function ensureAnalyticsTable(PDO $pdo): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS analytics_events (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                visitor_id CHAR(16) NOT NULL,
                event VARCHAR(20) NOT NULL,
                label VARCHAR(150) NOT NULL DEFAULT '',
                product_id INT UNSIGNED DEFAULT NULL,
                country_code CHAR(2) NOT NULL DEFAULT '',
                PRIMARY KEY (id),
                KEY idx_event_time (event, created_at),
                KEY idx_visitor (visitor_id, event)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        return $ready = true;
    } catch (Throwable $e) {
        error_log('[analytics] create table failed: ' . $e->getMessage());
        return $ready = false;
    }
}

function analyticsIsBot(): bool
{
    $agent = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    return $agent === '' || preg_match(ANALYTICS_BOT_PATTERN, $agent) === 1
        || ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD';
}

function analyticsVisitorId(): string
{
    if (empty($_SESSION['sd_vid'])) {
        $_SESSION['sd_vid'] = bin2hex(random_bytes(8));
    }
    return (string)$_SESSION['sd_vid'];
}

/** Kode negara ISO-2 pengunjung ('' bila tidak diketahui): header Cloudflare dulu, lalu lookup IP (tersimpan di sesi). */
function analyticsCountryCode(): string
{
    $cf = strtoupper((string)($_SERVER['HTTP_CF_IPCOUNTRY'] ?? ''));
    if (preg_match('/^[A-Z]{2}$/', $cf) === 1 && $cf !== 'XX') {
        return $cf;
    }
    $geo = function_exists('ddDetectVisitorCurrency') ? ddDetectVisitorCurrency() : null;
    return $geo ? strtoupper((string)($geo['country_code'] ?? '')) : '';
}

/**
 * Catat satu kejadian. event: 'pageview' | 'product_view' | 'add_to_cart'.
 * Gagal diam-diam: analytics tidak boleh merusak halaman.
 */
function analyticsTrack(PDO $pdo, string $event, string $label = '', ?int $productId = null): void
{
    if (session_status() !== PHP_SESSION_ACTIVE || analyticsIsBot() || !ensureAnalyticsTable($pdo)) {
        return;
    }
    try {
        $pdo->prepare(
            'INSERT INTO analytics_events (visitor_id, event, label, product_id, country_code)
             VALUES (:v, :e, :l, :p, :c)'
        )->execute([
            'v' => analyticsVisitorId(),
            'e' => $event,
            'l' => mb_substr($label, 0, 150),
            'p' => $productId,
            'c' => analyticsCountryCode(),
        ]);
    } catch (Throwable $e) {
        error_log('[analytics] track failed: ' . $e->getMessage());
    }
}

/** Nama negara bahasa Inggris dari kode ISO-2 ('' → "Unknown"). */
function analyticsCountryName(string $code): string
{
    if ($code === '') {
        return 'Unknown';
    }
    $name = class_exists('Locale') ? Locale::getDisplayRegion('-' . $code, 'en') : '';
    return $name !== '' && $name !== $code ? $name : $code;
}

/** Emoji bendera dari kode ISO-2. */
function analyticsFlag(string $code): string
{
    if (preg_match('/^[A-Z]{2}$/', $code) !== 1) {
        return '';
    }
    return mb_chr(0x1F1E6 + ord($code[0]) - 65) . mb_chr(0x1F1E6 + ord($code[1]) - 65);
}

/**
 * Semua angka dashboard untuk $days terakhir (0 = sejak awal).
 * @return array{visitors:int,pageviews:int,funnel:array,popular:array,countries:array}
 */
function analyticsReport(PDO $pdo, int $days): array
{
    $report = ['visitors' => 0, 'pageviews' => 0, 'funnel' => [], 'popular' => [], 'countries' => []];
    if (!ensureAnalyticsTable($pdo)) {
        return $report;
    }
    $since = $days > 0 ? 'created_at >= (NOW() - INTERVAL ' . (int)$days . ' DAY)' : '1=1';
    $one   = static fn(string $sql) => (int)$pdo->query($sql)->fetchColumn();

    $visitors  = $one("SELECT COUNT(DISTINCT visitor_id) FROM analytics_events WHERE $since");
    $viewed    = $one("SELECT COUNT(DISTINCT visitor_id) FROM analytics_events WHERE event = 'product_view' AND $since");
    $inCart    = $one("SELECT COUNT(DISTINCT visitor_id) FROM analytics_events WHERE event = 'add_to_cart' AND $since");
    $purchased = $one("SELECT COUNT(*) FROM orders WHERE payment_status = 'paid' AND " . ($days > 0 ? 'created_at >= (NOW() - INTERVAL ' . (int)$days . ' DAY)' : '1=1'));

    $report['visitors']  = $visitors;
    $report['pageviews'] = $one("SELECT COUNT(*) FROM analytics_events WHERE event IN ('pageview','product_view') AND $since");
    $report['funnel']    = [
        ['label' => 'Visits',    'count' => $visitors],
        ['label' => 'Viewed',    'count' => $viewed],
        ['label' => 'In Cart',   'count' => $inCart],
        ['label' => 'Purchased', 'count' => $purchased],
    ];
    $report['popular'] = $pdo->query(
        "SELECT label, COUNT(*) AS views FROM analytics_events
         WHERE event IN ('pageview','product_view') AND label <> '' AND $since
         GROUP BY label ORDER BY views DESC, label ASC LIMIT 10"
    )->fetchAll(PDO::FETCH_ASSOC);
    $report['countries'] = $pdo->query(
        "SELECT country_code AS code, COUNT(DISTINCT visitor_id) AS visitors FROM analytics_events
         WHERE $since GROUP BY country_code ORDER BY visitors DESC, code ASC LIMIT 15"
    )->fetchAll(PDO::FETCH_ASSOC);
    return $report;
}
