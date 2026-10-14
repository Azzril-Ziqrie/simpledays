<footer class="site-footer" aria-label="Site footer">
  <div class="footer-inner">
    <nav class="footer-links-row" aria-label="Information">
      <div class="footer-group">
        <a href="shipping.php">Shipping &amp; Returns</a>
        <a href="faq.php">FAQ</a>
        <a href="privacy.php">Privacy</a>
        <a href="terms.php">Terms of Service</a>
      </div>
      <div class="footer-group">
        <a href="about.php">About</a>
        <a href="contact.php">Contact Us</a>
        <?php if (BRAND_INSTAGRAM !== ''): ?>
        <a href="<?= esc(BRAND_INSTAGRAM) ?>" target="_blank" rel="noopener">Instagram</a>
        <?php endif; ?>
      </div>
    </nav>

    <?php if (BRAND_NEWSLETTER): ?>
    <form class="footer-news" id="footerNewsletter" action="newsletter.php" method="post" novalidate>
      <label class="footer-news__title" for="footerNewsEmail">Subscribe to the newsletter</label>
      <div class="footer-news__row">
        <input class="footer-news__input" id="footerNewsEmail" name="email" type="email" inputmode="email"
               autocomplete="email" placeholder="Email address" maxlength="190" required>
        <button class="footer-news__btn" type="submit">Subscribe</button>
      </div>
      <input type="text" name="company" tabindex="-1" autocomplete="off" aria-hidden="true" class="footer-news__hp">
      <p class="footer-news__msg" id="footerNewsMsg" role="status" aria-live="polite"></p>
    </form>
    <?php endif; ?>

    <div class="footer"><span class="footer-copy">© <?= date('Y') ?> <?= esc(BRAND_NAME) ?>. All rights reserved. Development by Cantum Studio.</span></div>
  </div>
</footer>
<?php if (BRAND_NEWSLETTER): ?>
<script>
(function () {
  'use strict';
  var form = document.getElementById('footerNewsletter');
  if (!form) { return; }
  var input = document.getElementById('footerNewsEmail');
  var msg   = document.getElementById('footerNewsMsg');
  var btn   = form.querySelector('.footer-news__btn');

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (!input.value.trim() || !input.checkValidity()) {
      msg.textContent = 'Please enter a valid email address.';
      msg.className = 'footer-news__msg is-error';
      input.focus();
      return;
    }
    btn.disabled = true;
    fetch(form.action, { method: 'POST', body: new FormData(form), headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        msg.textContent = d.message || '';
        msg.className = 'footer-news__msg ' + (d.success ? 'is-ok' : 'is-error');
        if (d.success) { form.reset(); }
      })
      .catch(function () {
        msg.textContent = 'Something went wrong. Please try again.';
        msg.className = 'footer-news__msg is-error';
      })
      .then(function () { btn.disabled = false; });
  });
})();
</script>
<?php endif; ?>
