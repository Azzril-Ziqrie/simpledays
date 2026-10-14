/* Shop: toggle tampilan grid (A/B) dan dropdown Category / Sort. */
(function () {
  'use strict';

  var STORAGE_KEY = 'sd-shop-view';
  var grid = document.getElementById('shopGrid');
  var buttons = document.querySelectorAll('.shop-view__btn');

  function readView() {
    try { return localStorage.getItem(STORAGE_KEY); } catch (e) { return null; }
  }
  function saveView(view) {
    try { localStorage.setItem(STORAGE_KEY, view); } catch (e) { /* mode privat: abaikan */ }
  }

  function paint(view) {
    if (grid) { grid.dataset.view = view; }
    buttons.forEach(function (btn) {
      btn.setAttribute('aria-pressed', btn.dataset.view === view ? 'true' : 'false');
    });
  }

  function setView(view) {
    if (!grid || grid.dataset.view === view) { return; }
    saveView(view);
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (document.startViewTransition && !reduce) {
      document.startViewTransition(function () { paint(view); });
    } else {
      paint(view);
    }
  }

  var saved = readView();
  if (saved === 'a' || saved === 'b') { paint(saved); }

  buttons.forEach(function (btn) {
    btn.addEventListener('click', function () { setView(btn.dataset.view); });
  });

  /* Dropdown (Category, Sort): satu terbuka pada satu waktu; tutup via klik luar / Escape */
  var menus = Array.prototype.slice.call(document.querySelectorAll('.shop-menu'));

  menus.forEach(function (menu) {
    menu.addEventListener('toggle', function () {
      if (!menu.open) { return; }
      menus.forEach(function (other) { if (other !== menu) { other.open = false; } });
    });
  });

  document.addEventListener('pointerdown', function (e) {
    menus.forEach(function (menu) {
      if (menu.open && !menu.contains(e.target)) { menu.open = false; }
    });
  });

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') { return; }
    menus.forEach(function (menu) {
      if (!menu.open) { return; }
      menu.open = false;
      var summary = menu.querySelector('summary');
      if (summary) { summary.focus(); }
    });
  });
})();
