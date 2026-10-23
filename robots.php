<?php
// /robots.txt (lewat .htaccess). Crawler pencarian & AI boleh membaca etalase; area transaksi/admin ditutup.
require_once __DIR__ . '/includes/brand.php';

header('Content-Type: text/plain; charset=utf-8');
$origin = BRAND_URL;

echo <<<TXT
User-agent: *
Disallow: /admin/
Disallow: /cart
Disallow: /checkout
Disallow: /payment
Disallow: /register
Disallow: /invoices/
Disallow: /add-to-cart
Disallow: /buy-now
Disallow: /sync-cart
Disallow: /set-currency
Disallow: /logout
Disallow: /*?search=
Allow: /

# Crawler AI (jawaban generatif): boleh membaca etalase publik.
User-agent: GPTBot
User-agent: OAI-SearchBot
User-agent: ChatGPT-User
User-agent: ClaudeBot
User-agent: Claude-SearchBot
User-agent: PerplexityBot
User-agent: Google-Extended
User-agent: Applebot-Extended
Disallow: /admin/
Disallow: /cart
Disallow: /checkout
Disallow: /payment
Disallow: /invoices/
Allow: /

Sitemap: {$origin}/sitemap.xml

TXT;
