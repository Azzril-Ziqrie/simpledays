<?php
session_start();
require __DIR__ . '/includes/site_gate.php';

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/db_schema.php';
require_once __DIR__ . '/includes/currency_helper.php';
require_once __DIR__ . '/includes/layout.php';

$article = null;
$articles = [];
$gallery = [];

if (tableExists($pdo, 'blogs')) {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare('SELECT * FROM blogs WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $article = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($article && tableExists($pdo, 'blog_gallery')) {
            $g = $pdo->prepare('SELECT image_url FROM blog_gallery WHERE blog_id = :id ORDER BY id ASC');
            $g->execute(['id' => $id]);
            $gallery = $g->fetchAll(PDO::FETCH_COLUMN);
        }
    } else {
        $articles = $pdo->query('SELECT id, title, subtitle, author, created_at FROM blogs ORDER BY created_at DESC, id DESC')->fetchAll(PDO::FETCH_ASSOC);
    }
}

/** Teks artikel: paragraf dipisah baris kosong. */
function researchParagraphs(?string $text): void
{
    foreach (preg_split('/\R{2,}/', trim((string)$text)) as $para) {
        if (trim($para) !== '') {
            echo '<p class="doc-text reveal">' . nl2br(esc(trim($para))) . '</p>';
        }
    }
}

siteHeadOpen([
    'title'       => $article ? (string)$article['title'] : 'Research',
    'description' => 'Notes and research from ' . BRAND_NAME . '.',
    'css'         => ['assets/css/pages.css'],
]);
siteBodyOpen(['bodyClass' => 'page-research', 'searchAction' => 'shop.php']);
?>
  <main class="page-main">
    <article class="doc-body">
      <?php if ($article): ?>
        <header class="doc-head reveal">
          <p class="doc-eyebrow"><a href="research.php">Research</a></p>
          <h1 class="doc-title"><?= esc($article['title']) ?></h1>
          <?php if (!empty($article['subtitle'])): ?><p class="doc-eyebrow"><?= esc($article['subtitle']) ?></p><?php endif; ?>
        </header>
        <?php if (!empty($article['main_image'])): ?>
          <p class="doc-text reveal"><img src="<?= esc($article['main_image']) ?>" alt="<?= esc($article['title']) ?>" style="width:100%;height:auto;display:block"></p>
        <?php endif; ?>
        <?php researchParagraphs($article['content']); ?>
        <?php researchParagraphs($article['extra_text']); ?>
        <?php foreach ($gallery as $img): ?>
          <p class="doc-text reveal"><img src="<?= esc($img) ?>" alt="" loading="lazy" style="width:100%;height:auto;display:block"></p>
        <?php endforeach; ?>
        <?php if (!empty($article['author'])): ?><p class="doc-text reveal"><?= esc($article['author']) ?></p><?php endif; ?>
      <?php else: ?>
        <header class="doc-head reveal">
          <p class="doc-eyebrow">Research</p>
          <h1 class="doc-title">Notes &amp; Research</h1>
        </header>
        <?php foreach ($articles as $a): ?>
          <p class="doc-text reveal">
            <a href="research.php?id=<?= (int)$a['id'] ?>"><?= esc($a['title']) ?></a>
            <?php if (!empty($a['subtitle'])): ?><br><?= esc($a['subtitle']) ?><?php endif; ?>
          </p>
        <?php endforeach; ?>
        <?php if (!$articles): ?><p class="doc-text reveal">Nothing here yet.</p><?php endif; ?>
      <?php endif; ?>
    </article>
  </main>
<?php
siteBodyClose();
siteDocClose();
