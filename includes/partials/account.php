<div id="accountPopup" class="modal" role="dialog" aria-modal="true" aria-labelledby="accountPopupTitle">
  <div class="modal-backdrop" id="accountBackdrop"></div>
  <div class="modal-dialog">
    <button type="button" class="modal-close" id="closeAccountBtn" aria-label="Close">×</button>
    <div class="modal-eyebrow"><img src="<?= esc(BRAND_LOGO) ?>" alt="<?= esc(BRAND_NAME) ?>"></div>
    <h2 class="modal-title" id="accountPopupTitle">My account</h2>
    <div class="account-tabs">
      <button class="account-tab active" data-tab="login" aria-selected="true">Sign in</button>
      <button class="account-tab" data-tab="register" aria-selected="false">Register</button>
    </div>
    <div id="tabLogin">
      <div id="loginMsg" class="account-msg" style="display:none"></div>
      <div class="account-form">
        <input type="email" id="loginEmail" placeholder="Email" autocomplete="email">
        <input type="password" id="loginPassword" placeholder="Password" autocomplete="current-password">
        <button class="account-submit" id="loginBtn">Sign in</button>
      </div>
    </div>
    <div id="tabRegister" style="display:none">
      <div id="registerMsg" class="account-msg" style="display:none"></div>
      <div class="account-form">
        <input type="text" id="regName" placeholder="Full name" autocomplete="name">
        <input type="email" id="regEmail" placeholder="Email" autocomplete="email">
        <input type="password" id="regPassword" placeholder="Password (min. 6 characters)" autocomplete="new-password">
        <input type="password" id="regPasswordConfirm" placeholder="Confirm password" autocomplete="new-password">
        <button class="account-submit" id="registerBtn">Create account</button>
      </div>
    </div>
  </div>
</div>
