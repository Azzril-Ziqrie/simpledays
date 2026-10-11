/**
 * Keranjang mini (panel samping) yang dipakai semua halaman toko.
 * Item disimpan di localStorage 'ppf_cart'; harga item dalam USD, ditampilkan lewat
 * window.ddFormatDisplay (lihat ddDisplayJs() di includes/currency_helper.php).
 *
 * Pemakaian:
 *   const cart = DDMiniCart.create({ showToast });   // showToast opsional
 *   cart.items.push({...}); cart.save(); cart.render();
 * `cart.items` adalah array yang sama sepanjang hidup halaman (diubah di tempat).
 */
window.DDMiniCart = (function () {
  'use strict';

  var STORAGE_KEY = 'ppf_cart';
  var BASE_CURRENCY = 'USD';

  function loadItems() {
    try {
      var saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
      // Item lama bisa berisi harga mata uang lain (toko sekarang USD saja) — buang.
      return saved.filter(function (i) { return i && i.currency === BASE_CURRENCY; });
    } catch (e) {
      return [];
    }
  }

  function setText(id, text) {
    var el = document.getElementById(id);
    if (el) el.textContent = text;
  }

  function itemHtml(item, idx, isNew) {
    return '<div class="cart-item' + (isNew ? ' is-new' : '') + '">' +
      '<img class="cart-item-img" src="' + item.thumb + '" alt="' + item.name + '">' +
      '<div>' +
        '<div class="cart-item-name">' + item.name + '</div>' +
        '<div class="cart-item-price">' + window.ddFormatDisplay(item.price) + '</div>' +
        '<div class="cart-item-qty">' +
          '<button aria-label="Decrease" data-action="dec" data-idx="' + idx + '">−</button>' +
          '<span>' + item.qty + '</span>' +
          '<button aria-label="Increase" data-action="inc" data-idx="' + idx + '">+</button>' +
        '</div>' +
      '</div>' +
      '<button class="cart-item-remove" aria-label="Remove" data-action="remove" data-idx="' + idx + '">×</button>' +
    '</div>';
  }

  /** Toast sederhana di #toastContainer; dipakai kalau halaman tidak memberi showToast sendiri. */
  function defaultToast(msg) {
    var container = document.getElementById('toastContainer');
    if (!container) return;
    var t = document.createElement('div');
    t.className = 'toast';
    t.textContent = msg;
    container.appendChild(t);
    requestAnimationFrame(function () { requestAnimationFrame(function () { t.classList.add('show'); }); });
    setTimeout(function () { t.classList.remove('show'); setTimeout(function () { t.remove(); }, 250); }, 2600);
  }

  function create(options) {
    var showToast = (options && options.showToast) || defaultToast;
    var cart = { items: loadItems(), lastAddedName: null };

    cart.save = function () {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(cart.items));
    };

    function changeQuantity(idx, action) {
      var item = cart.items[idx];
      if (!item) return;
      if (action === 'inc') {
        var stockLimit = parseInt(item.stock || '0', 10) || 0;
        if (stockLimit > 0 && item.qty >= stockLimit) { showToast('Out Of Stock'); return; }
        item.qty++;
      } else if (action === 'dec') {
        item.qty--;
        if (item.qty <= 0) cart.items.splice(idx, 1);
      } else if (action === 'remove') {
        cart.items.splice(idx, 1);
      }
      cart.save();
      cart.render();
    }

    cart.render = function () {
      var body  = document.getElementById('cartBody');
      var foot  = document.getElementById('cartFoot');
      var count = cart.items.reduce(function (s, i) { return s + i.qty; }, 0);
      var total = cart.items.reduce(function (s, i) { return s + (parseFloat(i.price) || 0) * i.qty; }, 0);

      setText('cartCount', count);
      setText('navCartCount', count);
      setText('menuCartCount', count);

      if (!body || !foot) return;

      if (cart.items.length === 0) {
        body.innerHTML = '<div class="cart-empty"><span>Your cart is empty</span>' +
          '<a class="cart-empty-link" href="shop.php">Continue shopping</a></div>';
        foot.style.display = 'none';
        return;
      }

      foot.style.display = '';
      setText('cartTotal', window.ddFormatDisplay(total));

      body.innerHTML = cart.items.map(function (item, idx) {
        return itemHtml(item, idx, item.name === cart.lastAddedName);
      }).join('');
      cart.lastAddedName = null; // animasi masuk hanya sekali

      body.querySelectorAll('[data-action]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          changeQuantity(parseInt(btn.dataset.idx, 10), btn.dataset.action);
        });
      });
    };

    return cart;
  }

  return { create: create };
})();
