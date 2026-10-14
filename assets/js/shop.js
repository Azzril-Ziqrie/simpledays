/* Shop: dropdown Category / Sort — satu terbuka pada satu waktu; tutup via klik luar atau Escape. */
(function () {
  'use strict';

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
