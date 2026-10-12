<?php
session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/db_schema.php';
require_once __DIR__ . '/includes/currency_helper.php';
require_once __DIR__ . '/includes/layout.php';

siteHeadOpen([
    'title'       => 'Shipping & Returns',
    'description' => 'Shipping times, duties and the return and exchange policy of ' . BRAND_NAME . '.',
]);
?>
  <style>
    .policy-wrap { max-width: 760px; }
    .policy-sec { padding: 24px 0; border-top: 1px solid var(--line); }
    .policy-sec h2 { font-size: 14px; font-weight: 500; letter-spacing: -.01em; margin-bottom: 12px; }
    .policy-sec p { font-size: 13px; line-height: 1.8; color: var(--ink-2); margin-bottom: 12px; }
    .policy-sec ul { margin: 0 0 12px; padding-left: 18px; font-size: 13px; line-height: 1.8; color: var(--ink-2); }
    .policy-sec strong { font-weight: 500; color: var(--ink); }
    .policy-sec a { text-decoration: underline; text-underline-offset: 3px; }
    .policy-version { font-size: 12px; color: var(--mute); padding-top: 24px; border-top: 1px solid var(--line); }
  </style>
<?php
siteBodyOpen(['bodyClass' => 'page-shipping', 'searchAction' => 'shop.php']);
?>
  <main class="page-main">
    <div class="page-wrap policy-wrap">
      <h1 class="page-title">Shipping &amp; Returns</h1>

<section class="policy-sec">
      <p>All sales are final. We do not offer refunds under any circumstances.</p>
      </section>
      <section class="policy-sec">
      <h2>Domestic Shipping (Indonesia)</h2>
      <p>All domestic orders within Indonesia are shipped via JNT/JNE Express.</p>
      <ul>
      <li>Processing Time: 1–3 business days</li>
      <li>Delivery Time: 1–3 business days after dispatch</li>
      <li>Shipping Fee: Calculated at checkout</li>
      </ul>
      </section>
      <section class="policy-sec">
      <h2>International Shipping (ASIA, USA, EU)</h2>
      <p>For orders placed outside of Indonesia, international checkout is powered by Paypal.</p>
      <ul>
      <li>Courier: DHL Express</li>
      <li>Processing Time: 1–3 business days</li>
      <li>Delivery Time: 3–10 business days, depending on the destination</li>
      <li>Shipping terms: Delivered Duty Paid (DDP)</li>
      <li>Shipping Fee: Calculated at checkout</li>
      <li>Free Shipping: Thresholds vary by region and are shown at checkout</li>
      <li>Customs & Duties: Included at checkout (Delivered Duty Paid)</li>
      </ul>
      <p>Customers from eligible countries will not be charged any additional fees upon delivery unless otherwise stated.</p>
      <p>You will receive a shipping confirmation and tracking number by email once your order has been dispatched.</p>
      <p><strong>Standard International:</strong> USA, Canada, Mexico, Austria, Belgium, France, Germany, Ireland, Luxembourg, Netherlands, Portugal, Spain, Sweden, Andorra, Liechtenstein, Croatia, Cyprus, Czechia, Greece, Hungary, Romania, Denmark, Finland, Italy, Sweden, Åland Islands, Albania, Armenia, Belarus, Bosnia & Herzegovina, Bouvet Island, Bulgaria, Estonia, Faroe Islands, Georgia, Gibraltar, Greenland, Guadeloupe, Guernsey, Iceland, Isle of Man, Jersey, Kosovo, Latvia, Lithuania, Malta, Mayotte, Moldova, Montenegro, North Macedonia, Poland, Réunion, San Marino, Serbia, Slovakia, Slovenia, Svalbard & Jan Mayen, Turkey, Ukraine, Vatican City†, China, Hong Kong SAR, Malaysia, Singapore, South Korea, Japan, Taiwan, Thailand, Vietnam</p>
      <p><strong>Rest of the world:</strong> 10–15 working days | Standard International</p>
      <p>Delivery times provided are estimates to be used as a guideline only. <?= esc(BRAND_NAME) ?> cannot be held responsible for unforeseen delays due to weather conditions, busy holiday periods or customs delays.</p>
      </section>
      <section class="policy-sec">
      <h2>Duties and Import Taxes</h2>
      <p>If your delivery address is outside the Indonesia, you may need to cover customs/duty and import taxes to receive your order. We recommend reaching out to your local customs office for detailed information and cost estimates prior to placing your order. Please be aware that any delays in paying these customs fees could result in a postponed delivery of your order. For orders delivered within the Indonesia order, <?= esc(BRAND_NAME) ?> will manage all VAT charges, ensuring that there are no additional fees upon delivery.</p>
      </section>
      <section class="policy-sec">
      <h2>Return & Exchanges Policy</h2>
      <p>We are happy to offer an exchange or refund on any unsuitable items within 14 days of receipt, provided they are unworn and in resale-able condition, with all original labels and swing tickets attached. Refunds exclude all shipping costs.</p>
      <p>Exchanges are only offered for the same style in a different size or colour. If you wish to exchange for a different style we ask that you request a refund and place a new order for the new item.</p>
      <p>You may request a return or exchange within 7 days of receiving your order, provided the item is:</p>
      <ul>
      <li>Original condition</li>
      <li>Unworn and unused</li>
      <li>With original tags attached</li>
      <li>In original packaging</li>
      </ul>
      <p>Requests made beyond this period, or for items that are used, damaged, or missing tags, will not be accepted.</p>
      <p>To initiate a return or exchange, please <a class="link-u" href="<?= esc(BRAND_WHATSAPP) ?>" target="_blank" rel="noopener">contact us</a> with your order number and reason for return. We will provide further instructions upon approval.</p>
      <p>Please fill in the exchange form below:</p>
      <ul>
      <li>Full Name:</li>
      <li>Address:</li>
      <li>Tracking Number:</li>
      <li>Items to Exchange:</li>
      <li>Reason for Exchange:</li>
      </ul>
      <p>Our team will confirm the return address upon approval. Please send the item within 3 days of confirmation.</p>
      <p>Return shipping fees are deducted from the refund in the following cases:</p>
      <ul>
      <li>Change-of-mind or customer error: Full round-trip shipping cost</li>
      <li>Partial return from multiple items: Return shipping cost</li>
      </ul>
      <p>Please note that we cannot accept returns of sale items in-store. <?= esc(BRAND_NAME) ?> is not liable for any shipping expenses related to product returns. Refunds will be processed as quickly as possible, but no later than 30 business days after the store receives the returned products. Refunds will be credited back to the original credit or debit card used for the purchase. Additionally, <?= esc(BRAND_NAME) ?> will not refund any duties, taxes, or other charges that may arise.</p>
      <p>For exchanges, there will be no extra shipping fees imposed by <?= esc(BRAND_NAME) ?> after returning the items. However, any duties, taxes, or additional costs incurred will not be refunded. During sales or promotional events, the same policy applies, and exchanges will not be accepted.</p>
      <p>Package your return items securely and post the parcel back to us using a recorded courier service. Include a short note with your order number, the RAN number we emailed to you, and your preference for an exchange.</p>
      <p>Please ensure that the goods are clearly marked as “Return” on any customs paperwork and declare the correct value of the items. Failure to mark them correctly or declaring a zero value may result in delays or additional import duties.</p>
      </section>
      <section class="policy-sec">
      <h2>Exchanges</h2>
      <p>You may request to return your order within 14 days of receiving your package. Unfortunately, we do not offer exchanges. If you wish to choose a different size or item, you will need to place a new order. Returned <?= esc(BRAND_NAME) ?> products must be sent back to the <?= esc(BRAND_NAME) ?> online store’s return address in perfect condition. The items should not have been worn, used, or washed. Returns will only be accepted if the products are returned in their original packaging, fully intact, with all original labels attached. Additionally, the return slip included with your shipment must be completed and enclosed with the returned products.</p>
      </section>
      <section class="policy-sec">
      <h2>Cost for Return</h2>
      <p>Please note that you are responsible for covering the return shipping costs. Additionally, customs duties and the original shipping charges (if applicable) are non-refundable. If a return is refused, you will be responsible for the cost of shipping the item back to you. Once we receive your return, we will process the refund within 5–7 working days.</p>
      </section>
      <section class="policy-sec">
      <h2>Currency Rates</h2>
      <p><?= esc(BRAND_NAME) ?> is not responsible for any fluctuations in currency or the exchange rates applied by banks when converting USD into other currencies.</p>
      </section>
      <p class="policy-version">Version - This Shipping & Return Policy was last updated in January, 2026.</p>
    </div>
  </main>
<?php
siteBodyClose();
siteDocClose();
