/** Halaman depan: pergantian hero, parallax hero, pop-up pembayaran, pop-up mata uang. Butuh site.js. */
(function () {
  'use strict';

  var $ = function (id) { return document.getElementById(id); };
  function on(id, event, handler) { var el = $(id); if (el) el.addEventListener(event, handler); return el; }

  /* ── hero: foto baru memudar masuk di atas foto lama ── */
  (function () {
    var slides = document.querySelectorAll('#heroSlider .hero-slide');
    if (slides.length < 2) return;
    var current = 0, HOLD_MS = 7500, FADE_MS = 2300;
    setInterval(function () {
      var previous = slides[current];
      previous.classList.remove('active');
      previous.classList.add('leaving');          // tetap tampil di bawah selama foto baru memudar masuk
      current = (current + 1) % slides.length;
      slides[current].classList.remove('leaving');
      slides[current].classList.add('active');
      setTimeout(function () { previous.classList.remove('leaving'); }, FADE_MS);
    }, HOLD_MS);
  })();

  /* ── hero: foto membesar pelan & logo naik/pudar saat scroll ── */
  window.addEventListener('DOMContentLoaded', function () {
    var hero = document.querySelector('.hero');
    if (!hero || !window.Motion || !document.documentElement.classList.contains('js')) return;
    var M = window.Motion;
    M.scroll(M.animate('.hero-media', { scale: [1, 1.1] }, { ease: 'linear' }),
      { target: hero, offset: ['start start', 'end start'] });
    M.scroll(M.animate('.hero-mark', { y: [0, -60], opacity: [1, 0] }, { ease: 'linear' }),
      { target: hero, offset: ['start start', '70% start'] });
  });

  /* ── pop-up pembayaran berhasil ─────────────────────── */
  (function () {
    var popup = $('paymentPopup');
    if (!popup) return;

    function clearUrlParams() {
      var url = new URL(window.location.href);
      ['payment_uploaded', 'order', 'invoice_email'].forEach(function (p) { url.searchParams.delete(p); });
      var query = url.searchParams.toString();
      history.replaceState({}, document.title, url.pathname + (query ? '?' + query : ''));
    }
    function closePopup() {
      popup.classList.remove('modal--open');
      popup.classList.add('modal--closing');
      setTimeout(function () { popup.remove(); clearUrlParams(); }, 250);
    }
    on('closePaymentPopup', 'click', closePopup);
    on('closePaymentPopupSecondary', 'click', closePopup);
    popup.querySelector('.modal-backdrop').addEventListener('click', closePopup);
  })();

  /* ── pop-up mata uang ───────────────────────────────── */
  (function () {
    var KEY = 'ddCurrencyPicked', root = document.documentElement, gate = $('currencyGate');
    try {
      if (location.search.indexOf('choose_currency') !== -1) {
        sessionStorage.removeItem(KEY);
        // Buang parameter supaya refresh / kembali tidak membuka pop-up lagi.
        var clean = location.search.replace(/([?&])choose_currency(=[^&]*)?(&|$)/, '$1').replace(/[?&]$/, '');
        history.replaceState(null, '', location.pathname + clean + location.hash);
      }
      if (sessionStorage.getItem(KEY) === '1') root.classList.add('cg-skip');
    } catch (e) {}
    if (!gate || root.classList.contains('cg-skip')) return;

    function picked() { try { sessionStorage.setItem(KEY, '1'); } catch (e) {} }
    document.body.classList.add('cg-lock');
    gate.querySelectorAll('a.cg-opt').forEach(function (a) { a.addEventListener('click', picked); });

    var other = $('cgOther');
    if (other) other.addEventListener('change', function () {
      if (!other.value) return;
      picked();
      location.href = 'set-currency.php?to=' + encodeURIComponent(other.value);
    });

    var focusTarget = gate.querySelector('a.cg-rec') || $('cgDialog');
    if (focusTarget) focusTarget.focus({ preventScroll: true });
  })();
})();
