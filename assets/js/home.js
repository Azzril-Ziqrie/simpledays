/** Halaman depan: pergantian hero, parallax hero, pop-up pembayaran, pop-up mata uang. Butuh site.js. */
(function () {
  'use strict';

  var $ = function (id) { return document.getElementById(id); };
  function on(id, event, handler) { var el = $(id); if (el) el.addEventListener(event, handler); return el; }

  /* ── hero: foto memudar bergantian; pindah hanya setelah foto sebelumnya tampil penuh ──
     Urutan: memudar masuk → tampil penuh → tahan HOLD_MS → pastikan foto berikutnya sudah ter-decode → memudar lagi.
     (setTimeout berantai, bukan setInterval, supaya jeda dihitung dari saat foto benar-benar tampil penuh.) */
  (function () {
    var slides = document.querySelectorAll('#heroSlider .hero-slide');
    if (slides.length < 2) return;
    var FADE_MS = 1400, HOLD_MS = 5000, current = 0, timer = null;

    /* Foto siap = sudah terunduh & ter-decode. Dibatasi waktu: kalau browser tak kunjung selesai, tetap lanjut
       (daripada slider macet selamanya). */
    function ready(slide) {
      var img = slide.querySelector('img');
      if (!img) return Promise.resolve();
      var loaded = img.complete ? Promise.resolve() : new Promise(function (r) { img.onload = img.onerror = r; });
      var decoded = loaded.then(function () { return img.decode ? img.decode().catch(function () {}) : null; });
      var giveUp = new Promise(function (r) { setTimeout(r, 2500); });
      return Promise.race([decoded, giveUp]);
    }

    /* step: +1 maju, -1 mundur. Dipakai timer otomatis dan tombol panah. */
    function go(step) {
      var previous = slides[current], nextIndex = (current + step + slides.length) % slides.length, incoming = slides[nextIndex];
      ready(incoming).then(function () {
        if (document.hidden) { schedule(HOLD_MS); return; }   // tab di belakang: jangan jalan, coba lagi nanti
        previous.classList.remove('active');
        previous.classList.add('leaving');
        incoming.classList.add('active');
        current = nextIndex;
        setTimeout(function () { previous.classList.remove('leaving'); }, FADE_MS + 50);
        schedule(FADE_MS + HOLD_MS);                          // jeda dimulai setelah foto baru tampil penuh
      });
    }

    function next() { go(1); }
    function schedule(ms) { clearTimeout(timer); timer = setTimeout(next, ms); }

    on('heroPrev', 'click', function () { go(-1); });
    on('heroNext', 'click', function () { go(1); });

    ready(slides[0]).then(function () { schedule(HOLD_MS); });
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
