<?php
session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/db_schema.php';
require_once __DIR__ . '/includes/currency_helper.php';
require_once __DIR__ . '/includes/layout.php';

/* ── schema detection ────────────────────────────────── */
$tableReady = tableExists($pdo, 'lookbook_photos');

/* ── query photos grouped by year ────────────────────── */
$yearGroups = [];
if ($tableReady) {
    try {
        // Get distinct years + counts (descending)
        $yearStmt = $pdo->query(
            "SELECT year, COUNT(*) AS photo_count
             FROM lookbook_photos
             WHERE is_active = 1
             GROUP BY year
             ORDER BY year DESC"
        );
        $yearList = $yearStmt->fetchAll(PDO::FETCH_ASSOC);

        // For each year, fetch photos
        $photoStmt = $pdo->prepare(
            "SELECT id, year, photo_path, caption, sort_order
             FROM lookbook_photos
             WHERE year = :year AND is_active = 1
             ORDER BY sort_order ASC, id ASC"
        );

        // Year titles (one title per year, shown below the year heading)
        $yearTitles = [];
        if (tableExists($pdo, 'lookbook_year_titles')) {
            $titleRows = $pdo->query('SELECT year, title FROM lookbook_year_titles')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($titleRows as $tr) {
                $yearTitles[(int) $tr['year']] = $tr['title'];
            }
        }

        foreach ($yearList as $y) {
            $yearVal = (int) $y['year'];
            $photoStmt->execute(['year' => $yearVal]);
            $photos = $photoStmt->fetchAll(PDO::FETCH_ASSOC);
            $yearGroups[] = [
                'year'  => $yearVal,
                'count' => (int) $y['photo_count'],
                'title' => $yearTitles[$yearVal] ?? null,
                'photos' => $photos,
            ];
        }
    } catch (Throwable $e) {
        $yearGroups = [];
    }
}

/* ── render ──────────────────────────────────────────── */
siteHeadOpen([
    'title'       => 'Lookbook',
    'description' => BRAND_NAME . ' lookbook — ' . BRAND_TAGLINE,
]);
?>
<style>
  .lb-jump{display:flex;gap:20px;flex-wrap:wrap;padding:clamp(24px,4vw,48px) var(--gutter) 0}
  .lb-jump a{color:var(--mute);transition:color .2s}
  .lb-jump a:hover{color:var(--ink)}

  .lb-year{padding:clamp(40px,7vw,96px) 0 clamp(8px,2vw,24px)}
  .lb-year + .lb-year{border-top:1px solid var(--line)}
  .lb-head{padding:0 var(--gutter);margin-bottom:clamp(20px,3vw,36px)}
  .lb-head h2{font-size:clamp(28px,4vw,48px);font-weight:500;letter-spacing:-.02em;line-height:1.1}
  .lb-head p{margin-top:10px;color:var(--mute)}

  .lb-wrap{position:relative}
  .lb-strip{display:flex;align-items:flex-start;gap:14px;overflow-x:auto;padding:0 var(--gutter) 24px;scroll-snap-type:x mandatory;scroll-padding-left:var(--gutter);scroll-behavior:smooth;-webkit-overflow-scrolling:touch;scrollbar-width:none}
  .lb-strip::-webkit-scrollbar{display:none}
  .lb-tile{flex:0 0 auto;height:min(70vh,560px);max-width:86vw;overflow:hidden;background:var(--tile);scroll-snap-align:start;display:flex;align-items:center;justify-content:center}
  .lb-tile img{width:auto;height:100%;max-width:100%;object-fit:contain;transition:transform .7s var(--ease)}
  @media(hover:hover){.lb-tile:hover img{transform:scale(1.02)}}

  .lb-arrow{position:absolute;top:50%;margin-top:-34px;width:44px;height:44px;border:1px solid var(--line);background:rgba(255,255,255,.8);-webkit-backdrop-filter:blur(12px);backdrop-filter:blur(12px);color:var(--ink);font-size:16px;display:flex;align-items:center;justify-content:center;z-index:2;transition:background-color .2s,color .2s,opacity .2s}
  .lb-arrow:hover{background:var(--ink);color:#fff}
  .lb-arrow--left{left:var(--gutter)}
  .lb-arrow--right{right:var(--gutter)}
  .lb-arrow[disabled]{opacity:0;pointer-events:none}
  @media(max-width:600px){.lb-arrow{display:none}.lb-tile{height:62vh;max-width:90vw}}

  .lb-empty{padding:clamp(80px,14vw,180px) var(--gutter);text-align:center;color:var(--mute)}
  @media(prefers-reduced-motion:reduce){.lb-strip{scroll-behavior:auto}.lb-tile img{transition:none}}
</style>
<?php
siteBodyOpen([
    'bodyClass'    => 'page-lookbook',
    'searchAction' => 'shop.php',
]);
?>
<main class="page-main">
  <?php if (count($yearGroups) >= 2): ?>
    <nav class="lb-jump" aria-label="Jump to year">
      <?php foreach ($yearGroups as $group): ?>
        <a href="#year-<?= (int)$group['year'] ?>"><?= (int)$group['year'] ?></a>
      <?php endforeach; ?>
    </nav>
  <?php endif; ?>

  <?php if (!$tableReady): ?>
    <p class="lb-empty">Lookbook coming soon.</p>
  <?php elseif (!$yearGroups): ?>
    <p class="lb-empty">No lookbook photos yet.</p>
  <?php else: ?>
    <?php foreach ($yearGroups as $group): ?>
      <section class="lb-year" id="year-<?= (int)$group['year'] ?>" aria-label="Lookbook <?= (int)$group['year'] ?>">
        <div class="lb-head">
          <h2><?= (int)$group['year'] ?></h2>
          <?php if (!empty($group['title'])): ?>
            <p><?= esc($group['title']) ?></p>
          <?php endif; ?>
        </div>

        <div class="lb-wrap">
          <button type="button" class="lb-arrow lb-arrow--left" aria-label="Previous photos">←</button>
          <div class="lb-strip">
            <?php foreach ($group['photos'] as $photo): ?>
              <a class="lb-tile" href="<?= esc((string)$photo['photo_path']) ?>" target="_blank" rel="noopener">
                <img src="<?= esc((string)$photo['photo_path']) ?>" alt="Lookbook <?= (int)$group['year'] ?>" loading="lazy">
              </a>
            <?php endforeach; ?>
          </div>
          <button type="button" class="lb-arrow lb-arrow--right" aria-label="Next photos">→</button>
        </div>
      </section>
    <?php endforeach; ?>
  <?php endif; ?>
</main>
<?php
siteBodyClose();
?>
<script>
(function () {
  'use strict';

  // Panah geser untuk strip foto per tahun
  document.querySelectorAll('.lb-year').forEach(section => {
    const strip = section.querySelector('.lb-strip');
    const left  = section.querySelector('.lb-arrow--left');
    const right = section.querySelector('.lb-arrow--right');
    if (!strip || !left || !right) return;

    function tileWidth() {
      const tile = strip.querySelector('.lb-tile');
      if (!tile) return 320;
      const styles = window.getComputedStyle(strip);
      const gap = parseFloat(styles.columnGap || styles.gap || '0') || 0;
      return tile.getBoundingClientRect().width + gap;
    }

    function updateArrows() {
      left.disabled  = strip.scrollLeft <= 1;
      right.disabled = strip.scrollLeft + strip.clientWidth >= strip.scrollWidth - 1;
    }

    left.addEventListener('click', () => { strip.scrollBy({ left: -tileWidth(), behavior: 'smooth' }); });
    right.addEventListener('click', () => { strip.scrollBy({ left:  tileWidth(), behavior: 'smooth' }); });
    strip.addEventListener('scroll', updateArrows);
    window.addEventListener('resize', updateArrows);
    requestAnimationFrame(updateArrows);
  });
})();
</script>
<?php siteDocClose();
