<?php
/**
 * Harga produk di storefront & checkout (semua dalam USD).
 * Diskon, harga akhir, dan harga coret dihitung di sini supaya halaman produk,
 * daftar toko, dan checkout selalu memberi angka yang sama.
 */

require_once __DIR__ . '/currency_helper.php';

/** Diskon aktif: flag menyala, persen > 0, dan sekarang berada di dalam jendela waktunya. */
function isDiscountActive(array $product): bool
{
    if ((int)($product['is_discount_active'] ?? 0) !== 1) return false;
    if ((float)($product['discount_percent'] ?? 0) <= 0)  return false;

    $now   = time();
    $start = !empty($product['discount_start_at']) ? strtotime((string)$product['discount_start_at']) : false;
    $end   = !empty($product['discount_end_at'])   ? strtotime((string)$product['discount_end_at'])   : false;
    if ($start !== false && $now < $start) return false;
    if ($end   !== false && $now > $end)   return false;
    return true;
}

/** Harga yang dibayar pembeli: harga dasar dikurangi diskon bila aktif. */
function productFinalPrice(array $product): float
{
    $base = ddProductBasePrice($product);
    if (!isDiscountActive($product)) return $base;
    return max($base - ($base * ((float)$product['discount_percent'] / 100)), 0);
}

/** Harga coret (compare price) dalam USD; 0 kalau tidak ada. */
function productComparePrice(array $product): float
{
    if ((float)($product['compare_price_usd'] ?? 0) > 0) return (float)$product['compare_price_usd'];

    $compareIdr = (float)($product['compare_price_idr'] ?? 0);
    $priceIdr   = (float)($product['price_idr'] ?? 0);
    if ($compareIdr <= 0 || $priceIdr <= 0) return 0.0;

    // Tanpa compare price USD khusus: pertahankan rasio compare/harga IDR terhadap harga USD.
    return round(ddProductBasePrice($product) * ($compareIdr / $priceIdr), 2);
}
