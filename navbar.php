<header class="navbar">
    <div class="nav-left">
      <button class="menu-icon-wrap" id="menuToggle" aria-label="Open menu">
        <span></span>
        <span></span>
        <span></span>
      </button>
    </div>

    <div class="nav-center">
      <a href="index.php" class="brand-mark">
        <img src="<?= htmlspecialchars($brandLogo) ?>" alt="<?= htmlspecialchars($brandName) ?>">
      </a>
    </div>

    <div class="nav-right">
      <form class="search-form" action="<?= htmlspecialchars($searchAction ?? 'index.php') ?>" method="GET" role="search">
        <?php if ($category !== ''): ?>
          <input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>">
        <?php endif; ?>
        <input type="text" name="search" placeholder="Search" value="<?= htmlspecialchars($search) ?>" aria-label="Search products">
        <button type="submit" aria-label="Search">&#8981;</button>
      </form>
      <button class="nav-btn" id="openAccountBtn" aria-label="Account">Account</button>
      <button class="nav-btn" id="openCartBtn" aria-label="Cart">Cart <span id="navCartCount">0</span></button>
    </div>
  </header>

<style>
/* ── Shared polish (apple-design): materials, press feedback, spring-like easing ── */
.navbar{transition:background-color .3s ease,-webkit-backdrop-filter .3s ease,backdrop-filter .3s ease}
.navbar.is-scrolled{background:rgba(255,255,255,.72)!important;-webkit-backdrop-filter:blur(20px) saturate(180%);backdrop-filter:blur(20px) saturate(180%);box-shadow:0 1px 0 rgba(0,0,0,.06)}
.navbar.is-scrolled .nav-btn,
.navbar.is-scrolled .brand-mark,
.navbar.is-scrolled .search-form input,
.navbar.is-scrolled .search-form button,
.navbar.is-scrolled .menu-icon-wrap::before{color:#111!important}
.navbar.is-scrolled .search-form input::placeholder{color:rgba(0,0,0,.55)!important}
.navbar.is-on-hero:not(.is-scrolled) .brand-mark img{filter:none}
.navbar.is-scrolled.is-on-hero .brand-mark img{filter:invert(1)}

.menu-panel,.cart-panel{transition:transform .4s cubic-bezier(.32,.72,0,1)}
/* 100vh di mobile = tinggi layar TERMASUK area address bar, jadi bagian bawah panel (tombol checkout) terpotong.
   dvh = tinggi yang benar-benar terlihat; vh jadi fallback untuk browser lama. */
.menu-panel,.cart-panel{height:100vh;height:100dvh}
.cart-body,.menu-body{min-height:0}
.cart-foot{padding-bottom:max(25px,env(safe-area-inset-bottom))}
@media(max-width:600px){.cart-panel{width:100%}}
.overlay{-webkit-backdrop-filter:blur(4px);backdrop-filter:blur(4px)}
.account-popup__dialog,.info-popup__dialog,.payment-popup__dialog{transform:translateY(15px) scale(.98);transition:opacity .25s ease,transform .35s cubic-bezier(.32,.72,0,1)}
.account-popup.active .account-popup__dialog,.info-popup.active .info-popup__dialog,.payment-popup--open .payment-popup__dialog{transform:none}

/* feedback on press, not on release */
.btn-add-cart,.cart-checkout-btn,.account-submit,.button,.size-btn,.scroll-arrow,.nav-btn,.menu-icon-wrap{transition:transform .12s ease-out,background-color .15s,color .15s,opacity .2s,border-color .15s}
.btn-add-cart:active:not(:disabled),.cart-checkout-btn:active:not(:disabled),.account-submit:active,.button:active,.size-btn:active:not(:disabled),.scroll-arrow:active{transform:scale(.97)}
.nav-btn:active,.menu-icon-wrap:active{opacity:.5}
.product-card:active .product-image img{transform:scale(.97)}

:focus-visible{outline:2px solid #111;outline-offset:3px}

/* ── CART PANEL (apple-design polish) ────────────────── */
.cart-body{overscroll-behavior:contain}
.cart-item{align-items:start;gap:16px}
.cart-item-img{object-fit:contain;background:#f7f7f7}
.cart-item-name{line-height:1.45}
.cart-item-price{color:#555}
.cart-item-qty{gap:4px;margin-top:12px}
.cart-item-qty button{width:32px;height:32px;font-size:15px;transition:transform .12s ease-out,background-color .15s,border-color .15s}
.cart-item-qty button:hover{border-color:#111}
.cart-item-qty button:active{transform:scale(.92)}
.cart-item-qty span{min-width:28px}
.cart-item-remove{width:32px;height:32px;margin:-6px -8px 0 0;display:flex;align-items:center;justify-content:center;font-size:20px;color:#999;transition:color .15s,transform .12s ease-out}
.cart-item-remove:active{transform:scale(.88)}
.cart-item.is-new{animation:cartItemIn .45s cubic-bezier(.32,.72,0,1) both}
.cart-empty-link{margin-top:6px;padding:12px 4px;font-size:11px;font-weight:600;letter-spacing:.1em;color:#111;border-bottom:1px solid #111;line-height:1}
.cart-note{margin:-8px 0 16px;font-size:11px;line-height:1.5;color:#888;text-transform:none;letter-spacing:.02em}
.cart-checkout-btn{transition:transform .12s ease-out,background-color .15s}
@keyframes cartItemIn{from{opacity:0;transform:translateX(14px)}to{opacity:1;transform:none}}

@media (prefers-reduced-transparency:reduce){
  .navbar.is-scrolled{background:#fff!important;-webkit-backdrop-filter:none;backdrop-filter:none}
  .overlay{-webkit-backdrop-filter:none;backdrop-filter:none}
}
@media (prefers-reduced-motion:reduce){
  *,*::before,*::after{animation-duration:.01ms!important;animation-delay:0s!important;transition-duration:.15s!important;scroll-behavior:auto!important}
  .menu-panel,.cart-panel{transition:opacity .2s ease,transform 0s}
}
</style>
<script>
(function () {
  var nav = document.querySelector('.navbar');
  if (!nav) return;
  var hero = document.querySelector('.hero');
  if (hero) nav.classList.add('is-on-hero');
  function threshold() { return hero ? Math.max(hero.offsetHeight - nav.offsetHeight, 8) : 8; }
  var ticking = false;
  function update() { nav.classList.toggle('is-scrolled', window.scrollY > threshold()); ticking = false; }
  window.addEventListener('scroll', function () { if (!ticking) { ticking = true; requestAnimationFrame(update); } }, { passive: true });
  window.addEventListener('resize', update);
  update();
})();
</script>

<script>
/* Cart panel: geser ke kanan untuk menutup — 1:1 mengikuti jari, rubber-band di tepi,
   lalu "lempar" berdasarkan kecepatan lepas (momentum projection). */
(function () {
  var panel = document.getElementById('cartPanel');
  var closeBtn = document.getElementById('closeCart');
  if (!panel || !closeBtn) return;

  var pid = null, startX = 0, startY = 0, lastX = 0, lastT = 0, vx = 0, offset = 0, width = 0;
  var decided = false, dragging = false, suppressClick = false;
  var HYST = 10;

  panel.style.touchAction = 'pan-y'; // scroll vertikal tetap native, horizontal diurus JS

  function project(v) { return (v / 1000) * 0.998 / (1 - 0.998); } // px/s -> px
  function rubber(over, dim) { var c = 0.55; return (over * dim * c) / (dim + c * Math.abs(over)); }

  panel.addEventListener('pointerdown', function (e) {
    if (!panel.classList.contains('active')) return;
    if (e.pointerType === 'mouse' && e.button !== 0) return;
    pid = e.pointerId; startX = lastX = e.clientX; startY = e.clientY; lastT = e.timeStamp;
    vx = 0; offset = 0; decided = false; dragging = false; width = panel.offsetWidth;
  });

  panel.addEventListener('pointermove', function (e) {
    if (pid !== e.pointerId) return;
    var dx = e.clientX - startX, dy = e.clientY - startY;
    if (!decided) {
      if (Math.abs(dx) < HYST && Math.abs(dy) < HYST) return;
      decided = true;
      if (Math.abs(dy) >= Math.abs(dx) || dx < 0) { pid = null; return; } // scroll vertikal / arah salah
      dragging = true;
      try { panel.setPointerCapture(pid); } catch (err) {}
      panel.style.transition = 'none';
      panel.style.userSelect = 'none';
    }
    if (!dragging) return;
    var raw = dx - HYST; // tanpa lompatan saat gesture baru terkunci
    offset = raw >= 0 ? raw : rubber(raw, width);
    var inst = (e.clientX - lastX) / Math.max(e.timeStamp - lastT, 1) * 1000;
    vx = 0.6 * inst + 0.4 * vx;
    lastX = e.clientX; lastT = e.timeStamp;
    panel.style.transform = 'translateX(' + offset + 'px)';
  });

  function release() {
    if (!dragging) { pid = null; return; }
    var projected = offset + project(vx);
    dragging = false; pid = null; suppressClick = true;
    setTimeout(function () { suppressClick = false; }, 0);
    panel.style.transition = '';   // kembali ke kurva sheet di CSS
    panel.style.userSelect = '';
    if (projected > width * 0.5) {
      closeBtn.click();            // pakai handler halaman (overlay, scroll-lock)
      panel.style.transform = '';  // lanjut dari posisi sekarang menuju tertutup
    } else {
      panel.style.transform = '';  // pegas kembali ke terbuka
    }
  }
  panel.addEventListener('pointerup', release);
  panel.addEventListener('pointercancel', release);
  panel.addEventListener('click', function (e) {
    if (suppressClick) { e.stopPropagation(); e.preventDefault(); }
  }, true);
})();
</script>
