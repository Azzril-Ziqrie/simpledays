
<style>
  /* ── Loading screen ──────────────────────────────────────
     Logo "bernapas" pelan + garis progres tipis. Saat selesai, layar
     tidak sekadar hilang: isinya membesar sedikit & memudar (materialize
     terbalik) sehingga halaman terasa "terbuka". */
  #global-loader {
    position: fixed;
    inset: 0;
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #fafafa;
    opacity: 1;
    visibility: visible;
    transition: opacity .3s ease .12s, visibility 0s .12s; /* tampil saat pindah halaman: tunda 120ms agar navigasi cepat tidak berkedip */
  }
  #global-loader.is-initial { transition: none; }         /* load pertama: langsung menutup, tanpa kilatan konten */
  #global-loader.hidden {
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transition: opacity .6s cubic-bezier(.32, .72, 0, 1), visibility 0s .6s;
  }

  .loader-stack {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 30px;
    animation: ldIn .7s cubic-bezier(.32, .72, 0, 1) both;
    transition: transform .6s cubic-bezier(.32, .72, 0, 1), opacity .45s ease, filter .6s ease;
  }
  #global-loader.hidden .loader-stack {
    transform: scale(1.1);
    opacity: 0;
    filter: blur(8px);
  }

  .loader-logo {
    width: 76px;
    height: 76px;
    border-radius: 50%;
    object-fit: cover;
    animation: ldBreath 2.6s ease-in-out .7s infinite;
    will-change: transform, opacity;
  }

  .loader-track {
    width: 96px;
    height: 2px;
    border-radius: 2px;
    background: rgba(0, 0, 0, .1);
    overflow: hidden;
  }
  .loader-bar {
    width: 100%;
    height: 100%;
    background: #111;
    border-radius: 2px;
    transform: scaleX(0);
    transform-origin: left center;
    will-change: transform;
  }
  .loader-bar.is-done { transition: transform .3s cubic-bezier(.32, .72, 0, 1); }

  @keyframes ldIn {
    from { opacity: 0; transform: scale(.94); }
    to   { opacity: 1; transform: none; }
  }
  @keyframes ldBreath {
    0%, 100% { transform: scale(1);    opacity: .92; }
    50%      { transform: scale(1.05); opacity: 1; }
  }

  @media (prefers-reduced-motion: reduce) {
    .loader-stack { animation: none; }
    .loader-logo  { animation: none; }
    #global-loader.hidden .loader-stack { transform: none; filter: none; }
    #global-loader.hidden { transition: opacity .2s ease, visibility 0s .2s; }
  }
</style>

<div id="global-loader" class="is-initial" role="status" aria-live="polite" aria-label="Loading">
  <div class="loader-stack">
    <img src="assets/images/1212.png" alt="" class="loader-logo">
    <div class="loader-track" aria-hidden="true"><div class="loader-bar" id="loaderBar"></div></div>
  </div>
</div>

<script>
(function () {
  'use strict';
  var loader = document.getElementById('global-loader');
  var bar    = document.getElementById('loaderBar');
  if (!loader || !bar) return;

  var raf = null, startT = 0, failsafe = null, finishTimer = null;

  /* Progres simulasi: cepat di awal, melambat mendekati 90%, tuntas saat halaman benar-benar siap. */
  function run() {
    cancelAnimationFrame(raf);
    clearTimeout(finishTimer);
    bar.classList.remove('is-done');
    bar.style.transform = 'scaleX(0)';
    startT = performance.now();
    (function tick(now) {
      var p = 0.9 * (1 - Math.exp(-(now - startT) / 900));
      bar.style.transform = 'scaleX(' + p.toFixed(4) + ')';
      raf = requestAnimationFrame(tick);
    })(startT);
  }

  function show() {
    loader.classList.remove('is-initial', 'hidden');
    run();
    clearTimeout(failsafe);
    failsafe = setTimeout(hide, 10000); // jaring pengaman: jangan pernah mengunci layar
  }

  function hide() {
    clearTimeout(failsafe);
    cancelAnimationFrame(raf);
    bar.classList.add('is-done');
    bar.style.transform = 'scaleX(1)';
    clearTimeout(finishTimer);
    finishTimer = setTimeout(function () { loader.classList.add('hidden'); }, 260); // beri waktu garis menutup penuh
  }

  run();
  failsafe = setTimeout(hide, 8000);

  // Halaman selesai dimuat
  if (document.readyState === 'complete') hide();
  else window.addEventListener('load', hide);

  // Kembali dengan tombol Back (bfcache): jangan tertinggal di layar loading
  window.addEventListener('pageshow', function (e) { if (e.persisted) hide(); });

  /* Pindah halaman: pakai event delegation — loader ini di-include sebelum <a>/<form> lain
     diparse, jadi querySelectorAll di sini dulu tidak pernah menemukan apa pun. */
  document.addEventListener('click', function (e) {
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    var a = e.target.closest && e.target.closest('a[href]');
    if (!a) return;
    var href = a.getAttribute('href');
    if (!href || href.charAt(0) === '#' || /^(javascript:|mailto:|tel:)/i.test(href)) return;
    if ((a.target && a.target !== '_self') || a.hasAttribute('download')) return;
    show();
  });

  document.addEventListener('submit', function (e) {
    if (e.defaultPrevented) return;
    var f = e.target;
    if (f && f.target && f.target !== '_self') return;
    show();
  });
})();
</script>
