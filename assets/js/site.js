/**
 * Perilaku bersama semua halaman storefront Simple Days.
 * Mengekspos window.Site agar skrip halaman tidak perlu menduplikasi:
 *
 *   Site.cart            keranjang mini (DDMiniCart): items, save(), render()
 *   Site.showToast(msg)  toast kecil
 *   Site.open(id) / Site.close(id) / Site.closeAll()   panel & modal (menuPanel, cartPanel, accountPopup, faqPopup)
 *   Site.openCart()
 *   Site.syncCart()      sinkronkan keranjang ke sesi server (otomatis saat tombol Checkout)
 *   Site.reducedMotion   true bila pengguna minta reduced-motion
 */
(function () {
  'use strict';

  var $ = function (id) { return document.getElementById(id); };
  function on(id, event, handler) { var el = $(id); if (el) el.addEventListener(event, handler); return el; }
  var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ── toast + keranjang ──────────────────────────────── */
  function showToast(msg) {
    var container = $('toastContainer');
    if (!container) return;
    var t = document.createElement('div');
    t.className = 'toast';
    t.textContent = msg;
    container.appendChild(t);
    requestAnimationFrame(function () { requestAnimationFrame(function () { t.classList.add('show'); }); });
    setTimeout(function () { t.classList.remove('show'); setTimeout(function () { t.remove(); }, 300); }, 2600);
  }
  var cart = window.DDMiniCart ? DDMiniCart.create({ showToast: showToast }) : null;

  /* ── layer: menu, cart, akun, terms, faq ────────────── */
  var layers = ['menuPanel', 'cartPanel', 'accountPopup', 'faqPopup'].map($).filter(Boolean);

  function syncOverlay() {
    var anyOpen = layers.some(function (l) { return l.classList.contains('active'); });
    var overlay = $('overlay');
    if (overlay) overlay.classList.toggle('active', anyOpen);
    document.body.classList.toggle('lock-scroll', anyOpen);
  }
  function closeAll() { layers.forEach(function (l) { l.classList.remove('active'); }); syncOverlay(); }
  function open(id) {
    var el = $(id);
    if (!el) return;
    layers.forEach(function (l) { l.classList.remove('active'); });
    el.classList.add('active');
    syncOverlay();
  }
  function close(id) { var el = $(id); if (el) el.classList.remove('active'); syncOverlay(); }

  on('menuToggle', 'click', function () { open('menuPanel'); });
  on('closeMenu', 'click', function () { close('menuPanel'); });
  on('openCartBtn', 'click', function () { open('cartPanel'); });
  on('closeCart', 'click', function () { close('cartPanel'); });
  on('openAccountBtn', 'click', function () { open('accountPopup'); });
  on('openAccountBtnMobile', 'click', function () { open('accountPopup'); });
  on('closeAccountBtn', 'click', function () { close('accountPopup'); });
  on('accountBackdrop', 'click', function () { close('accountPopup'); });
  on('overlay', 'click', closeAll);
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeAll(); });

  document.querySelectorAll('[data-close]').forEach(function (el) {
    el.addEventListener('click', function () { close(el.dataset.close); });
  });
  /* ── tema terang / gelap ─────────────────────────────── */
  var themeBtns = document.querySelectorAll('[data-theme-set]');
  function syncTheme() {
    var cur = document.documentElement.getAttribute('data-theme') || 'light';
    themeBtns.forEach(function (b) { b.setAttribute('aria-pressed', String(b.dataset.themeSet === cur)); });
  }
  themeBtns.forEach(function (b) {
    b.addEventListener('click', function () {
      document.documentElement.setAttribute('data-theme', b.dataset.themeSet);
      try { localStorage.setItem('sdTheme', b.dataset.themeSet); } catch (e) {}
      syncTheme();
    });
  });
  syncTheme();
  document.querySelectorAll('.dropdown-toggle').forEach(function (toggle) {
    toggle.addEventListener('click', function () {
      var isOpen = toggle.parentElement.classList.toggle('open');
      toggle.setAttribute('aria-expanded', String(isOpen));
    });
  });

  /** Kirim isi keranjang ke sesi server (checkout.php membaca $_SESSION['guest_cart']). */
  function syncCart() {
    if (!cart) return Promise.resolve();
    var data = new FormData();
    data.append('cart_data', JSON.stringify(cart.items));
    return fetch('sync-cart.php', { method: 'POST', body: data }).catch(function () {});
  }
  on('checkoutBtn', 'click', function () {
    syncCart().then(function () { window.location.href = 'checkout.php'; });
  });
  if (cart) cart.render();

  /* ── akun: masuk / daftar ───────────────────────────── */
  document.querySelectorAll('.account-tab').forEach(function (tab) {
    tab.addEventListener('click', function () {
      document.querySelectorAll('.account-tab').forEach(function (t) {
        var active = t === tab;
        t.classList.toggle('active', active);
        t.setAttribute('aria-selected', String(active));
      });
      $('tabLogin').style.display    = tab.dataset.tab === 'login' ? '' : 'none';
      $('tabRegister').style.display = tab.dataset.tab === 'register' ? '' : 'none';
    });
  });

  function setAccountMsg(targetId, kind, text) {
    var el = $(targetId);
    el.className = 'account-msg ' + kind;
    el.textContent = text;
    el.style.display = 'block';
  }
  function setAccountBusy(busy) {
    var login = $('loginBtn'), register = $('registerBtn');
    if (!login || !register) return;
    login.disabled = register.disabled = busy;
    login.textContent    = busy ? 'Signing in…' : 'Sign in';
    register.textContent = busy ? 'Creating account…' : 'Create account';
  }
  function value(id) { return $(id).value; }

  /** Pesan error, atau '' bila form valid. */
  function validateAccount(isLogin) {
    var email = value(isLogin ? 'loginEmail' : 'regEmail').trim();
    var pass  = value(isLogin ? 'loginPassword' : 'regPassword');
    if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return 'Please enter a valid email address.';
    if (isLogin) return (email && pass) ? '' : 'Please fill in all fields.';
    if (!value('regName').trim() || !email || !pass || !value('regPasswordConfirm')) return 'Please complete all required fields.';
    if (pass.length < 6) return 'Password must be at least 6 characters.';
    if (pass !== value('regPasswordConfirm')) return 'Password confirmation does not match.';
    return '';
  }

  function submitAccount(action) {
    var isLogin = action === 'login';
    var msgId = isLogin ? 'loginMsg' : 'registerMsg';
    var error = validateAccount(isLogin);
    if (error) return setAccountMsg(msgId, 'error', error);

    var params = new URLSearchParams();
    params.set('action', action);
    params.set('email', value(isLogin ? 'loginEmail' : 'regEmail').trim());
    params.set('password', value(isLogin ? 'loginPassword' : 'regPassword'));
    if (!isLogin) {
      params.set('full_name', value('regName').trim());
      params.set('confirm_password', value('regPasswordConfirm'));
    }

    setAccountMsg(msgId, 'success', isLogin ? 'Signing in…' : 'Creating account…');
    setAccountBusy(true);

    fetch('auth_api.php', {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: params.toString()
    })
      .then(function (r) { return r.json().catch(function () { return { ok: false, message: 'Unexpected server response.' }; }); })
      .then(function (data) {
        if (!data || data.ok !== true) {
          setAccountMsg(msgId, 'error', (data && data.message) || 'Something went wrong.');
          setAccountBusy(false);
          return;
        }
        setAccountMsg(msgId, 'success', isLogin ? 'Signed in. Refreshing…' : 'Account created. Refreshing…');
        var redirect = typeof data.redirect === 'string' && data.redirect ? data.redirect : 'index.php';
        setTimeout(function () { window.location.href = redirect; }, 450);
      })
      .catch(function (err) {
        console.error('[auth]', err);
        setAccountMsg(msgId, 'error', 'Network error. Please try again.');
        setAccountBusy(false);
      });
  }
  on('loginBtn', 'click', function () { submitAccount('login'); });
  on('registerBtn', 'click', function () { submitAccount('register'); });

  /* ── navbar: di atas hero transparan, lalu solid ────── */
  (function () {
    var nav = document.querySelector('.navbar--overlay');
    if (!nav) return;
    var hero = document.querySelector('.hero');
    var ticking = false;
    function update() {
      var threshold = hero ? Math.max(hero.offsetHeight - nav.offsetHeight, 8) : 8;
      nav.classList.toggle('is-solid', window.scrollY > threshold);
      ticking = false;
    }
    window.addEventListener('scroll', function () { if (!ticking) { ticking = true; requestAnimationFrame(update); } }, { passive: true });
    window.addEventListener('resize', update);
    update();
  })();

  /* ── keranjang: geser ke kanan untuk menutup ────────── */
  /* 1:1 mengikuti jari, rubber-band di tepi, lalu "dilempar" menurut kecepatan lepas (momentum projection). */
  (function () {
    var panel = $('cartPanel'), closeBtn = $('closeCart');
    if (!panel || !closeBtn) return;

    var pid = null, startX = 0, startY = 0, lastX = 0, lastT = 0, vx = 0, offset = 0, width = 0;
    var decided = false, dragging = false, suppressClick = false, HYST = 10;

    function project(v) { return (v / 1000) * 0.998 / (1 - 0.998); }
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
        if (Math.abs(dy) >= Math.abs(dx) || dx < 0) { pid = null; return; }
        dragging = true;
        try { panel.setPointerCapture(pid); } catch (err) {}
        panel.style.transition = 'none';
        panel.style.userSelect = 'none';
      }
      if (!dragging) return;
      var raw = dx - HYST;
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
      panel.style.transition = '';
      panel.style.userSelect = '';
      if (projected > width * 0.5) closeBtn.click();
      panel.style.transform = '';
    }
    panel.addEventListener('pointerup', release);
    panel.addEventListener('pointercancel', release);
    panel.addEventListener('click', function (e) {
      if (suppressClick) { e.stopPropagation(); e.preventDefault(); }
    }, true);
  })();

  /* ── motion: reveal saat masuk viewport + tekan kartu ── */
  window.addEventListener('DOMContentLoaded', function () {
    var root = document.documentElement;
    if (!root.classList.contains('js')) return;
    if (!window.Motion) { root.classList.remove('js'); return; }

    var M = window.Motion, smooth = [0.32, 0.72, 0, 1];
    var spring = { type: 'spring', stiffness: 380, damping: 30 };

    var batch = 0;   // bertahap kalau banyak yang muncul bersamaan
    M.inView(document.querySelectorAll('.reveal'), function (el) {
      if (el.dataset.revealed) return;
      el.dataset.revealed = '1';
      var delay = Math.min(batch++, 5) * 0.06;
      requestAnimationFrame(function () { batch = 0; });
      M.animate(el, { opacity: 1, y: 0 }, { duration: 0.8, delay: delay, ease: smooth });
    }, { margin: '0px 0px -8% 0px' });

    M.press('.product-tile', function (el) {   // umpan balik instan, lepas dengan pegas
      M.animate(el, { scale: 0.985 }, { duration: 0.12 });
      return function () { M.animate(el, { scale: 1 }, spring); };
    });
  });

  /* ── kursor: tiga titik logo menggantikan panah (hanya mouse/trackpad; layar sentuh tidak terpengaruh) ── */
  (function () {
    if (!window.matchMedia || !window.matchMedia('(hover:hover) and (pointer:fine)').matches || reducedMotion) return;
    var dots = document.createElement('img');
    dots.className = 'dd-cursor';
    dots.src = 'assets/images/simple-days-dots.png';
    dots.alt = '';
    dots.setAttribute('aria-hidden', 'true');
    document.body.appendChild(dots);
    document.documentElement.classList.add('has-dot-cursor');

    document.addEventListener('mousemove', function (e) {
      dots.style.transform = 'translate3d(' + e.clientX + 'px,' + e.clientY + 'px,0) translate(-50%,-50%)';
      dots.classList.add('is-on');
      dots.classList.toggle('is-link', !!e.target.closest('a,button,summary,label,[role=button]'));
    }, { passive: true });
    document.addEventListener('mouseleave', function () { dots.classList.remove('is-on'); });
  })();

  window.Site = {
    cart: cart,
    showToast: showToast,
    open: open,
    close: close,
    closeAll: closeAll,
    openCart: function () { open('cartPanel'); },
    syncCart: syncCart,
    reducedMotion: reducedMotion
  };
})();
