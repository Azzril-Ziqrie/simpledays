<?php
session_start();

require __DIR__ . '/includes/site_gate.php';

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/kasera_client.php';
require_once __DIR__ . '/includes/db_schema.php';
require_once __DIR__ . '/includes/currency_helper.php';
require_once __DIR__ . '/includes/pricing.php';
require_once __DIR__ . '/includes/email_helpers.php';
require_once __DIR__ . '/includes/catalog.php';
require_once __DIR__ . '/includes/home_media.php';
require_once __DIR__ . '/includes/announcement.php';
require_once __DIR__ . '/includes/kasera_return.php';
require_once __DIR__ . '/includes/layout.php';

$composerAutoload = __DIR__ . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
}

// Link "Currency" di menu membuka lagi pop-up pilih currency.
if (isset($_GET['choose_currency'])) {
    unset($_SESSION['currency_prompt_done']);
}

handleKaseraReturn($pdo);

/* ── view helpers ────────────────────────────────────── */
function slugToLabel(string $slug): string
{
    return strtoupper(str_replace('-', ' ', $slug));
}

/** Data untuk pop-up mata uang: saran berdasarkan IP + daftar mata uang lain. */
function currencyGateModel(): array
{
    $suggest = null;
    $options = [];
    $rates   = ddExchangeRates();
    $geo     = ddDetectVisitorCurrency();

    if ($geo && $geo['currency'] !== 'USD' && $rates && !empty($rates[$geo['currency']])) {
        $suggest = $geo + ['rate' => (float)$rates[$geo['currency']]];
    }
    if ($rates) {
        $codes = array_unique(array_values(ddCountryCurrencyMap()));
        sort($codes);
        foreach ($codes as $code) {
            if ($code !== 'USD' && !empty($rates[$code]) && (!$suggest || $code !== $suggest['currency'])) {
                $options[] = $code;
            }
        }
    }
    return ['suggest' => $suggest, 'options' => $options];
}

/* ── request ─────────────────────────────────────────── */
$search     = mb_substr(trim((string)($_GET['search'] ?? '')), 0, SEARCH_MAX_LENGTH);
$category   = trim((string)($_GET['category'] ?? ''));
$isFiltered = $search !== '' || $category !== '';

$paymentUploaded = ($_GET['payment_uploaded'] ?? '') === '1';
$orderUploaded   = trim((string)($_GET['order'] ?? ''));

/* ── data ────────────────────────────────────────────── */
$schema     = catalogSchema($pdo);
$heroSlides = fetchHeroSlides($pdo, 'assets/images/hero/sd-hero-1.webp');
$categories = fetchCategories($pdo, $schema);
$products   = fetchInStockProducts($pdo, $schema, $search, $category);
$gate       = currencyGateModel();
$announce   = announcementForDisplay($pdo);
$rowBottom  = $isFiltered ? [] : fetchHomeMedia($pdo, 'bottom');
$captionId  = firstVideoId([], $rowBottom);
$mediaBottom = renderHomeMedia($rowBottom, BRAND_NAME, 'low', $captionId);

$origin  = BRAND_URL;
$jsonLd  = [
    '@context'   => 'https://schema.org',
    '@type'      => 'Store',
    'name'       => BRAND_NAME,
    'url'        => $origin,
    'image'      => $origin . '/' . ltrim((string)$heroSlides[0]['image'], '/'),
    'priceRange' => '$$',
];

/* ── render ──────────────────────────────────────────── */
siteHeadOpen([
    'description'  => BRAND_NAME . ' — ' . BRAND_TAGLINE,
    'image'        => (string)$heroSlides[0]['image'],
    'css'          => ['assets/css/home.css'],
    'currencyGate' => true,
]);
?>
  <script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
<?php
siteBodyOpen([
    'bodyClass'    => 'home',
    'overlay'      => true,
    'searchAction' => 'index.php',
    'categoryBase' => 'index.php',
    'search'       => $search,
    'category'     => $category,
    'currencyGate' => $gate,
    'beforeNavbar' => function () use ($paymentUploaded, $orderUploaded) {
        if (!$paymentUploaded) { return; }
        ?>
  <div id="paymentPopup" class="modal modal--open" role="dialog" aria-modal="true" aria-labelledby="paymentPopupTitle">
    <div class="modal-backdrop"></div>
    <div class="modal-dialog">
      <button type="button" class="modal-close" id="closePaymentPopup" aria-label="Close">×</button>
      <div class="modal-eyebrow"><img src="<?= esc(BRAND_LOGO) ?>" alt="<?= esc(BRAND_NAME) ?>"></div>
      <h2 class="modal-title" id="paymentPopupTitle">Invoice sent to your email</h2>
      <p class="modal-text">Your payment was received. The invoice is on its way to your inbox and your order is now being processed.</p>
      <?php if ($orderUploaded !== ''): ?>
        <div class="modal-meta"><span>Order number</span><strong><?= esc($orderUploaded) ?></strong></div>
      <?php endif; ?>
      <div class="modal-actions">
        <button type="button" id="closePaymentPopupSecondary" class="button">Continue shopping</button>
      </div>
    </div>
  </div>
<?php
    },
]);
?>
  <main>
    <section class="hero" aria-label="Featured">
      <div class="hero-slider" id="heroSlider">
        <?php foreach ($heroSlides as $i => $slide): ?>
          <div class="hero-slide<?= $i === 0 ? ' active' : '' ?>">
            <div class="hero-media">
              <img src="<?= esc($slide['image']) ?>" alt="<?= esc($slide['title'] ?: BRAND_NAME) ?>" fetchpriority="<?= $i === 0 ? 'high' : 'low' ?>" decoding="async">
            </div>
            <?php if (!empty($slide['title']) || !empty($slide['subtitle']) || !empty($slide['button_text'])): ?>
              <div class="hero-copy">
                <?php if (!empty($slide['subtitle'])): ?><div class="hero-sub"><?= esc($slide['subtitle']) ?></div><?php endif; ?>
                <?php if (!empty($slide['title'])): ?><h1 class="hero-title"><?= esc($slide['title']) ?></h1><?php endif; ?>
                <?php if (!empty($slide['button_text'])): ?>
                  <a href="<?= esc($slide['button_link'] ?: '#') ?>" class="hero-cta link-u"><?= esc($slide['button_text']) ?></a>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>

        <?php if (count($heroSlides) > 1): ?>
        <button type="button" class="hero-arrow hero-arrow--prev" id="heroPrev" aria-label="Previous photo">&#8592;</button>
        <button type="button" class="hero-arrow hero-arrow--next" id="heroNext" aria-label="Next photo">&#8594;</button>
        <?php endif; ?>

        <img class="hero-mark" src="<?= esc(BRAND_LOGO) ?>" alt="<?= esc(BRAND_NAME) ?>">
        <?php if (!$isFiltered): ?>
          <a href="shop.php" class="hero-shop link-u">SHOP NOW</a>
        <?php endif; ?>
      </div>
    </section>

    <?php if ($announce) { echo renderAnnouncement($announce); } ?>

    <?php if (!$isFiltered): ?>
    <section class="intro" aria-label="About">
      <div class="intro-copy">
        <?php foreach (brandAboutCopy() as $line): ?>
        <p><?= esc($line) ?></p>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <div class="category-bar">
      <nav class="category-nav" aria-label="Categories">
        <a href="index.php#products" class="<?= !$isFiltered ? 'active' : '' ?>">All</a>
        <?php foreach ($categories as $cat): ?>
          <a href="index.php?category=<?= urlencode((string)$cat['slug']) ?>#products" class="<?= $category === (string)$cat['slug'] ? 'active' : '' ?>"><?= esc(sentenceCase((string)$cat['name'])) ?></a>
        <?php endforeach; ?>
      </nav>
    </div>

    <section class="shelf" id="products" aria-label="Products">
      <div class="shelf-head">
        <?php if ($isFiltered): ?>
          <div class="shelf-title">
            <?php if ($category !== ''): ?>Category: <?= esc(slugToLabel($category)) ?><?php endif; ?>
            <?php if ($search !== ''): ?><?= $category !== '' ? ' · ' : '' ?>Search: <?= esc(strtoupper($search)) ?><?php endif; ?>
            — <a class="link-u" href="index.php">Clear</a>
          </div>
        <?php else: ?>
          <div class="shelf-title">Products (<?= count($products) ?>)</div>
        <?php endif; ?>
      </div>

      <?php if (!$products): ?>
        <p class="empty-text">
          <?php if ($isFiltered): ?>
            No products found<?= $search !== '' ? ' matching “' . esc($search) . '”' : '' ?><?= $category !== '' ? ' in ' . esc(slugToLabel($category)) : '' ?>.
          <?php else: ?>
            No products yet.
          <?php endif; ?>
        </p>
      <?php else: ?>
        <div class="product-grid">
          <?php foreach ($products as $product): ?>
            <?= renderProductCard($product) ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <?php if ($mediaBottom): ?>
    <section class="media media--bottom" aria-label="More"><?= $mediaBottom ?></section>
    <?php endif; ?>
  </main>

<?php
siteBodyClose(['scripts' => ['assets/js/home.js']]);
if (isset($_GET['notfound'])): ?>
  <script>
    // Halaman yang diketik tidak ada: beri tahu sekali, lalu bersihkan ?notfound dari URL supaya refresh tidak mengulang.
    Site.showToast('That page doesn’t exist, so we brought you home.');
    history.replaceState(null, '', location.pathname + location.search.replace(/[?&]notfound=1/, '').replace(/^&/, '?') + location.hash);
  </script>
<?php endif;
siteDocClose();
