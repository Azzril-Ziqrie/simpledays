<footer class="site-footer" aria-label="Site footer">
  <div class="footer-social">
    <?php if (BRAND_INSTAGRAM !== ''): ?>
    <a href="<?= esc(BRAND_INSTAGRAM) ?>" target="_blank" rel="noopener" aria-label="Instagram">
      <img src="https://cdn-icons-png.flaticon.com/512/174/174855.png" alt="" width="16" height="16">
    </a>
    <?php endif; ?>
    <a href="<?= esc(BRAND_WHATSAPP) ?>" target="_blank" rel="noopener" aria-label="WhatsApp">
      <img src="https://cdn-icons-png.flaticon.com/512/733/733585.png" alt="" width="16" height="16">
    </a>
  </div>

  <nav class="footer-links-row" aria-label="Information">
    <a href="about.php">About</a>
    <a href="faq.php">FAQ</a>
    <a href="shipping.php">Shipping &amp; Returns</a>
    <a href="contact.php">Contact Us</a>
    <a href="privacy.php">Privacy</a>
    <a href="#" id="footerTermsLink">Terms of Service</a>
  </nav>

  <div class="footer"><span class="footer-copy">© <?= date('Y') ?> <?= esc(BRAND_NAME) ?>. All rights reserved. Development by Cantum Studio.</span></div>
</footer>
