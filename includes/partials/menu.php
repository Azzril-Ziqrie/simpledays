<?php
/** Panel menu kiri. Variabel dari siteBodyOpen(): $ctx (search, category, searchAction, categoryBase, categories). */
?>
<aside class="panel menu-panel" id="menuPanel" aria-label="Main menu">
  <div class="menu-body">
    <form class="menu-search" action="<?= esc($ctx['searchAction']) ?>" method="GET" role="search">
      <?php if ($ctx['category'] !== ''): ?><input type="hidden" name="category" value="<?= esc($ctx['category']) ?>"><?php endif; ?>
      <input type="text" name="search" placeholder="Search" value="<?= esc($ctx['search']) ?>" aria-label="Search products">
      <button type="submit" aria-label="Search">&#8594;</button>
    </form>

    <nav class="menu-nav">
      <div class="dropdown">
        <button class="dropdown-toggle" aria-expanded="false">Shop</button>
        <div class="dropdown-menu">
          <a href="shop.php">All</a>
          <?php foreach ($ctx['categories'] as $cat): ?>
            <a href="<?= esc($ctx['categoryBase']) ?>?category=<?= urlencode((string)$cat['slug']) ?>"><?= esc(sentenceCase((string)$cat['name'])) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
      <a href="lookbook.php">Lookbook</a>
      <a href="about.php">About</a>
      <a href="index.php?choose_currency=1">Currency (<?= esc(ddDisplayCurrency()) ?>)</a>
      <button id="openAccountBtnMobile">Account</button>
    </nav>
  </div>
</aside>
