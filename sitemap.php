<?php
// /sitemap.xml (lewat .htaccess): halaman statis + semua produk aktif.
require_once __DIR__ . '/config/database.php';

require_once __DIR__ . '/includes/brand.php';

header('Content-Type: application/xml; charset=utf-8');
$origin = BRAND_URL;

$urls = [];
foreach (['' => '1.0', 'shop' => '0.9', 'lookbook' => '0.7', 'about' => '0.6', 'contact' => '0.5', 'faq' => '0.4', 'shipping' => '0.4', 'privacy' => '0.2', 'terms' => '0.2'] as $path => $priority) {
    $urls[] = [$origin . '/' . $path, null, $priority];
}
try {
    $rows = $pdo->query('SELECT slug, updated_at FROM products WHERE is_active = 1 ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $rows = [];
}
foreach ($rows as $row) {
    $urls[] = [$origin . '/product?slug=' . rawurlencode((string)$row['slug']), $row['updated_at'] ?? null, '0.8'];
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as [$loc, $modified, $priority]) {
    echo '  <url><loc>' . htmlspecialchars($loc, ENT_XML1) . '</loc>';
    if ($modified) { echo '<lastmod>' . date('c', strtotime((string)$modified)) . '</lastmod>'; }
    echo '<priority>' . $priority . "</priority></url>\n";
}
echo "</urlset>\n";
