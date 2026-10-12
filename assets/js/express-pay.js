/**
 * Apple Pay & Google Pay lewat PayPal (checkout.php).
 *
 * Order dibuat dan di-capture oleh endpoint yang sama dengan tombol PayPal
 * (paypal-create-order.php / paypal-capture-order.php); nominal selalu dari database.
 * Barisnya baru ditampilkan setelah perangkat dan akun PayPal terbukti mendukung metodenya.
 */
(function () {
  'use strict';

  var cfg = window.EXPRESS_PAY;
  if (!cfg) { return; }

  var statusEl = document.getElementById('express-status');

  function fail(message) {
    if (statusEl) { statusEl.textContent = message; }
  }

  function reveal(method) {
    var row = document.querySelector('[data-express="' + method + '"]');
    if (row) { row.hidden = false; }
  }

  function postJson(url, body) {
    return fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: body ? JSON.stringify(body) : undefined
    }).then(function (res) { return res.json(); });
  }

  function createOrder() {
    return postJson('paypal-create-order.php?order=' + encodeURIComponent(cfg.orderNumber)).then(function (data) {
      if (!data.id) { throw new Error(data.error || 'Could not start the payment.'); }
      return data.id;
    });
  }

  function captureOrder(orderId) {
    return postJson('paypal-capture-order.php', { order_number: cfg.orderNumber, paypal_order_id: orderId }).then(function (data) {
      if (data.status !== 'COMPLETED') { throw new Error(data.error || 'Payment could not be completed.'); }
      window.location.href = 'index.php?payment_uploaded=1&invoice_email=' +
        encodeURIComponent(data.invoiceMail || 'failed') + '&order=' + encodeURIComponent(cfg.orderNumber);
    });
  }

  /* ── Apple Pay ─────────────────────────────────────── */
  function initApplePay() {
    if (!window.ApplePaySession || !window.paypal || !paypal.Applepay) { return; }
    if (!ApplePaySession.canMakePayments()) { return; }

    var applepay = paypal.Applepay();
    applepay.config().then(function (config) {
      if (!config.isEligible) { return; }

      var holder = document.getElementById('apple-pay-button');
      var button = document.createElement('apple-pay-button');
      button.setAttribute('buttonstyle', 'black');
      button.setAttribute('type', 'buy');
      button.setAttribute('locale', 'en');
      button.addEventListener('click', function () { startApplePay(applepay, config); });
      holder.appendChild(button);
      reveal('apple');
    }).catch(function () { /* akun belum disetujui untuk Apple Pay: baris tetap tersembunyi */ });
  }

  function startApplePay(applepay, config) {
    var session = new ApplePaySession(4, {
      countryCode: config.countryCode,
      currencyCode: cfg.currency,
      merchantCapabilities: config.merchantCapabilities,
      supportedNetworks: config.supportedNetworks,
      requiredBillingContactFields: ['name', 'postalAddress'],
      total: { label: cfg.shop, amount: cfg.amount, type: 'final' }
    });

    session.onvalidatemerchant = function (event) {
      applepay.validateMerchant({ validationUrl: event.validationURL, displayName: cfg.shop })
        .then(function (result) { session.completeMerchantValidation(result.merchantSession); })
        .catch(function () { session.abort(); fail('Apple Pay is not available right now.'); });
    };

    session.onpaymentauthorized = function (event) {
      var orderId;
      createOrder()
        .then(function (id) {
          orderId = id;
          return applepay.confirmOrder({
            orderId: orderId,
            token: event.payment.token,
            billingContact: event.payment.billingContact
          });
        })
        .then(function () {
          session.completePayment({ status: ApplePaySession.STATUS_SUCCESS });
          return captureOrder(orderId);
        })
        .catch(function (error) {
          session.completePayment({ status: ApplePaySession.STATUS_FAILURE });
          fail(error.message || 'Apple Pay payment failed.');
        });
    };

    session.begin();
  }

  /* ── Google Pay ────────────────────────────────────── */
  function initGooglePay() {
    if (!window.google || !google.payments || !window.paypal || !paypal.Googlepay) { return; }

    var googlepay = paypal.Googlepay();
    var client = new google.payments.api.PaymentsClient({ environment: cfg.sandbox ? 'TEST' : 'PRODUCTION' });

    googlepay.config().then(function (config) {
      return client.isReadyToPay({
        apiVersion: config.apiVersion,
        apiVersionMinor: config.apiVersionMinor,
        allowedPaymentMethods: config.allowedPaymentMethods
      }).then(function (ready) {
        if (!ready.result) { return; }
        var button = client.createButton({
          buttonType: 'pay',
          buttonSizeMode: 'fill',
          allowedPaymentMethods: config.allowedPaymentMethods,
          onClick: function () { startGooglePay(googlepay, client, config); }
        });
        document.getElementById('google-pay-button').appendChild(button);
        reveal('google');
      });
    }).catch(function () { /* akun belum disetujui untuk Google Pay: baris tetap tersembunyi */ });
  }

  function startGooglePay(googlepay, client, config) {
    var orderId;
    client.loadPaymentData({
      apiVersion: config.apiVersion,
      apiVersionMinor: config.apiVersionMinor,
      allowedPaymentMethods: config.allowedPaymentMethods,
      merchantInfo: config.merchantInfo,
      transactionInfo: {
        countryCode: config.countryCode,
        currencyCode: cfg.currency,
        totalPriceStatus: 'FINAL',
        totalPrice: cfg.amount
      }
    })
      .then(function (paymentData) {
        return createOrder().then(function (id) {
          orderId = id;
          return googlepay.confirmOrder({ orderId: orderId, paymentMethodData: paymentData.paymentMethodData });
        });
      })
      .then(function (confirmation) {
        if (confirmation.status === 'PAYER_ACTION_REQUIRED') {
          return googlepay.initiatePayerAction({ orderId: orderId });
        }
        if (confirmation.status !== 'APPROVED') { throw new Error('Google Pay payment was not approved.'); }
      })
      .then(function () { return captureOrder(orderId); })
      .catch(function (error) {
        if (error && error.statusCode === 'CANCELED') { return; } // pembeli menutup jendela Google Pay
        fail(error && error.message ? error.message : 'Google Pay payment failed.');
      });
  }

  // Skrip Apple / Google dimuat async: tunggu semuanya siap sebelum mengecek dukungan.
  window.addEventListener('load', function () {
    initApplePay();
    initGooglePay();
  });
})();
