<?php
// Campaign hadiah gratis: progres "belanja $X lagi" di keranjang (diisi ulang oleh mini-cart.js)
require_once dirname(__DIR__) . '/campaign.php';
$cartCampaign = (isset($pdo) && $pdo instanceof PDO) ? campaignCurrent($pdo) : null;
?>
<aside class="panel cart-panel" id="cartPanel" aria-label="Shopping cart">
  <div class="panel-head">
    <span class="panel-label">Cart (<span id="cartCount">0</span>)</span>
    <button class="panel-close" id="closeCart" aria-label="Close cart">×</button>
  </div>
  <?php if ($cartCampaign): ?>
    <div class="cart-gift" id="cartGift" hidden
         data-min="<?= esc(number_format((float)$cartCampaign['min_subtotal'], 2, '.', '')) ?>"
         data-gift="<?= esc(implode(' or ', array_unique(array_column($cartCampaign['gifts'], 'product_name')))) ?>">
      <p class="cart-gift__text" id="cartGiftText"></p>
      <div class="cart-gift__bar" aria-hidden="true"><span id="cartGiftBar"></span></div>
    </div>
    <?php endif; ?>
  <div class="cart-body" id="cartBody">
    <div class="cart-empty"><span>Your cart is empty</span><a class="cart-empty-link link-u" href="shop.php">Continue shopping</a></div>
  </div>
  <div class="cart-foot" id="cartFoot" style="display:none">
    <div class="cart-total"><span>Total</span><strong id="cartTotal">$0.00</strong></div>
    <p class="cart-note">Shipping calculated at checkout.</p>
    <button class="cart-checkout-btn" id="checkoutBtn">Checkout</button>
  </div>
</aside>
