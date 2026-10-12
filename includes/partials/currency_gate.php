<?php
/** Pop-up pilih mata uang (tampilan saja; tagihan tetap USD). Variabel: $gate = currencyGateModel(). */
$suggest = $gate['suggest'];
?>
<div id="currencyGate" role="dialog" aria-modal="true" aria-labelledby="cgTitle">
  <div class="cg-dialog" tabindex="-1" id="cgDialog">
    <div class="modal-eyebrow"><img src="<?= esc(BRAND_LOGO) ?>" alt="<?= esc(BRAND_NAME) ?>"></div>
    <h2 class="modal-title" id="cgTitle">Choose your currency</h2>
    <p class="modal-text">
      <?php if ($suggest): ?>
        <img class="cg-flag" src="https://flagcdn.com/w80/<?= esc(strtolower($suggest['country_code'])) ?>.png" alt="" width="40" height="30" onerror="this.style.display='none'"><br>
        Are you from <strong><?= esc($suggest['country']) ?></strong>? The currency there is <strong><?= esc($suggest['currency']) ?></strong>.
        Switch to <?= esc($suggest['currency']) ?>, or stay on USD?
      <?php else: ?>
        Pick the currency you'd like to see prices in.
      <?php endif; ?>
    </p>
    <div class="cg-list">
      <?php if ($suggest): ?>
        <a class="cg-opt cg-rec" href="set-currency.php?to=<?= urlencode($suggest['currency']) ?>">
          <span class="cg-badge">SUGGESTED</span>
          <span class="cg-code">Switch to <?= esc($suggest['currency']) ?></span>
          <span class="cg-name">1 USD ≈ <?= esc($suggest['currency']) ?> <?= number_format($suggest['rate'], $suggest['rate'] >= 100 ? 0 : 2, '.', ',') ?></span>
        </a>
      <?php endif; ?>
      <a class="cg-opt" href="set-currency.php?to=USD">
        <span class="cg-code"><?= $suggest ? 'Stay on USD' : 'USD' ?></span>
        <span class="cg-name">US Dollar</span>
      </a>
    </div>
    <?php if ($gate['options']): ?>
      <select id="cgOther" aria-label="Other currency">
        <option value="">Other currency…</option>
        <?php foreach ($gate['options'] as $code): ?>
          <option value="<?= esc($code) ?>"><?= esc($code) ?></option>
        <?php endforeach; ?>
      </select>
    <?php endif; ?>
    <p class="cg-note">Prices in other currencies are estimates. You are charged in USD at checkout.</p>
  </div>
</div>
