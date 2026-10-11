<?php
/**
 * Uji logika murni kode promo (tanpa database). Jalankan: php tests/promo_test.php
 */
require_once __DIR__ . '/../includes/currency_helper.php';
require_once __DIR__ . '/../includes/promo.php';

$failures = 0;
function check(string $name, $actual, $expected): void
{
    global $failures;
    $ok = is_float($expected) ? abs($actual - $expected) < 0.005 : $actual == $expected;
    if (!$ok) { $failures++; echo "FAIL  $name\n      expected: " . var_export($expected, true) . "\n      actual:   " . var_export($actual, true) . "\n"; }
    else      { echo "ok    $name\n"; }
}
function promo(array $o = []): array
{
    return array_merge([
        'id' => 1, 'code' => 'T', 'type' => 'percent', 'value' => 10, 'max_discount' => null,
        'min_subtotal' => 0, 'starts_at' => null, 'ends_at' => null,
        'usage_limit' => null, 'used_count' => 0, 'is_active' => 1,
    ], $o);
}
$now = strtotime('2026-06-15 12:00:00');

// ── promoCheck
check('aktif & tanpa syarat → boleh', promoCheck(promo(), 50.0, $now), null);
check('nonaktif ditolak', promoCheck(promo(['is_active' => 0]), 50.0, $now), 'This promo code is not active.');
check('belum mulai ditolak', promoCheck(promo(['starts_at' => '2026-07-01 00:00:00']), 50.0, $now), 'This promo code is not valid yet.');
check('kedaluwarsa ditolak', promoCheck(promo(['ends_at' => '2026-06-01 00:00:00']), 50.0, $now), 'This promo code has expired.');
check('di dalam rentang tanggal boleh', promoCheck(promo(['starts_at' => '2026-06-01 00:00:00', 'ends_at' => '2026-06-30 23:59:59']), 50.0, $now), null);
check('kuota habis ditolak', promoCheck(promo(['usage_limit' => 5, 'used_count' => 5]), 50.0, $now), 'This promo code has reached its usage limit.');
check('kuota tersisa boleh', promoCheck(promo(['usage_limit' => 5, 'used_count' => 4]), 50.0, $now), null);
check('di bawah minimal belanja ditolak', promoCheck(promo(['min_subtotal' => 100]), 99.99, $now), 'Spend at least $100.00 to use this promo code.');
check('tepat minimal belanja boleh', promoCheck(promo(['min_subtotal' => 100]), 100.0, $now), null);

// ── promoDiscounts
check('percent 10% dari 80', promoDiscounts(promo(), 80.0, 10.0)['item'], 8.0);
check('percent tidak menyentuh ongkir', promoDiscounts(promo(), 80.0, 10.0)['shipping'], 0.0);
check('percent dibatasi max_discount', promoDiscounts(promo(['value' => 50, 'max_discount' => 15]), 100.0, 0.0)['item'], 15.0);
check('fixed $5', promoDiscounts(promo(['type' => 'fixed', 'value' => 5]), 80.0, 10.0)['item'], 5.0);
check('fixed tidak melebihi subtotal', promoDiscounts(promo(['type' => 'fixed', 'value' => 500]), 40.0, 0.0)['item'], 39.99);
check('free shipping = seluruh ongkir', promoDiscounts(promo(['type' => 'free_shipping']), 80.0, 12.5)['shipping'], 12.5);
check('free shipping tanpa potongan barang', promoDiscounts(promo(['type' => 'free_shipping']), 80.0, 12.5)['item'], 0.0);
check('free shipping sebelum ongkir dipilih = 0', promoDiscounts(promo(['type' => 'free_shipping']), 80.0, 0.0)['total'], 0.0);
check('100% off tanpa ongkir menyisakan tagihan minimal', 30.0 - promoDiscounts(promo(['value' => 100]), 30.0, 0.0)['total'], 0.01);
check('100% off hanya memotong barang, ongkir tetap dibayar', 35.0 - promoDiscounts(promo(['value' => 100]), 30.0, 5.0)['total'], 5.0);
check('fixed melebihi subtotal: ongkir tetap dibayar', 35.0 - promoDiscounts(promo(['type' => 'fixed', 'value' => 35]), 30.0, 5.0)['total'], 5.0);
check('total tagihan tidak pernah negatif', 20.0 + 3.0 - promoDiscounts(promo(['type' => 'fixed', 'value' => 999]), 20.0, 3.0)['total'] >= 0.0, true);

// ── format kode
check('normalisasi huruf besar + trim', promoNormalizeCode('  welcome10 '), 'WELCOME10');
check('format kode valid', promoIsValidCodeFormat('WELCOME-10'), true);
check('format kode terlalu pendek', promoIsValidCodeFormat('AB'), false);
check('format kode berisi spasi', promoIsValidCodeFormat('A B C'), false);
check('label percent', promoLabel(promo(['value' => 15])), '15% off');
check('label fixed', promoLabel(promo(['type' => 'fixed', 'value' => 5])), '$5.00 off');
check('label free shipping', promoLabel(promo(['type' => 'free_shipping'])), 'Free shipping');

// ── promoOrderDiscount
check('order tanpa potongan', promoOrderDiscount(['discount_amount' => '0.00', 'promo_code' => null]), null);
check('order dengan kode', promoOrderDiscount(['discount_amount' => '5.50', 'promo_code' => 'WELCOME10']), ['label' => 'Discount (WELCOME10)', 'amount' => 5.5]);
check('order dengan potongan tanpa kode', promoOrderDiscount(['discount_amount' => '2.00']), ['label' => 'Discount', 'amount' => 2.0]);

echo $failures === 0 ? "\nSemua tes lulus.\n" : "\n$failures tes gagal.\n";
exit($failures === 0 ? 0 : 1);
