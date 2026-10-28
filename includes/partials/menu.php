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
      <a href="research.php">Research</a>
      <a href="about.php">About</a>
      <a href="index.php?choose_currency=1">Currency (<?= esc(ddDisplayCurrency()) ?>)</a>
      <button id="openAccountBtnMobile">Account</button>
    </nav>
  </div>
  <div class="menu-foot">
    <div class="theme-switch" role="group" aria-label="Appearance">
      <button type="button" class="theme-opt" data-theme-set="light" aria-pressed="true" aria-label="Light mode" title="Light"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2.5v2M12 19.5v2M2.5 12h2M19.5 12h2M5.3 5.3l1.4 1.4M17.3 17.3l1.4 1.4M5.3 18.7l1.4-1.4M17.3 6.7l1.4-1.4"/></svg></button>
      <button type="button" class="theme-opt" data-theme-set="dark" aria-pressed="false" aria-label="Dark mode" title="Dark"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z"/></svg></button>
    </div>
  </div>
</aside>
