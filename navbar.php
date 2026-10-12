<?php /** Header. Variabel dari siteBodyOpen(): $ctx. */ ?>
<header class="navbar<?= $ctx['overlay'] ? ' navbar--overlay' : ' is-solid' ?>">
  <div class="nav-left">
    <button class="menu-icon-wrap" id="menuToggle" aria-label="Open menu"><span></span></button>
  </div>

  <div class="nav-center">
    <a href="index.php" class="brand-mark" aria-label="<?= esc(BRAND_NAME) ?>"><img src="<?= esc(BRAND_MARK) ?>" alt="" width="22" height="20"></a>
  </div>

  <div class="nav-right">
    <form class="search-form" action="<?= esc($ctx['searchAction']) ?>" method="GET" role="search">
      <?php if ($ctx['category'] !== ''): ?><input type="hidden" name="category" value="<?= esc($ctx['category']) ?>"><?php endif; ?>
      <input type="text" name="search" placeholder="Search" value="<?= esc($ctx['search']) ?>" aria-label="Search products">
      <button type="submit" aria-label="Search">&#8981;</button>
    </form>
    <a class="nav-btn nav-about" href="about.php">About</a>
    <button class="nav-btn" id="openAccountBtn" aria-label="Account">Account</button>
    <button class="nav-btn" id="openCartBtn" aria-label="Cart">Cart <span id="navCartCount">0</span></button>
  </div>
</header>
