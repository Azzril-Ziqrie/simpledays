/* Shop: toggle tampilan grid (A/B) dan popover Sort. */
(function () {
  'use strict';

  var STORAGE_KEY = 'sd-shop-view';
  var grid = document.getElementById('shopGrid');
  var buttons = document.querySelectorAll('.shop-view__btn');
  var sort = document.querySelector('.shop-sort');

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

  /* Sort: tutup saat klik di luar atau tekan Escape */
  if (sort) {
    document.addEventListener('pointerdown', function (e) {
      if (sort.open && !sort.contains(e.target)) { sort.open = false; }
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && sort.open) {
        sort.open = false;
        var summary = sort.querySelector('summary');
        if (summary) { summary.focus(); }
      }
    });
  }
})();
