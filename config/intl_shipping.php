<?php
/*
 * Ongkir internasional: satu tarif FLAT (INTL_FLAT_SHIPPING_USD) untuk semua negara tujuan.
 * Daftar negara statis (config/intl_countries.php); tidak memakai API RajaOngkir.
 */

require_once __DIR__ . '/../includes/currency_helper.php';

/** Ongkir internasional flat, dalam USD. Ubah angka ini untuk mengganti tarif. */
const INTL_FLAT_SHIPPING_USD = 25.00;

/** Label layanan yang tampil di checkout dan tersimpan di order. */
const INTL_SHIPPING_SERVICE = 'Shipping';

/** Kurir internasional (kode => label). Hanya satu, jadi dipilih otomatis di checkout. */
function intlCouriers(): array
{
    return ['pos' => 'POS Indonesia'];
}

/** Kode ISO => nama negara. */
function intlCountries(): array
{
    static $countries = null;
    if ($countries === null) $countries = require __DIR__ . '/intl_countries.php';
    return $countries;
}

/** Cari negara berdasarkan nama; hasil berbentuk [{country_id (kode ISO), country_name}]. */
function intlSearchCountries(string $search, int $limit = 10): array
{
    $search = mb_strtolower(trim($search));
    if (mb_strlen($search) < 2) return [];
    $limit = max(1, min($limit, 50));

    $rows = [];
    foreach (intlCountries() as $code => $name) {
        if (mb_strpos(mb_strtolower($name), $search) !== false) {
            $rows[] = ['country_id' => $code, 'country_name' => $name];
        }
    }
    // Nama yang diawali kata kunci tampil lebih dulu.
    usort($rows, static function (array $a, array $b) use ($search): int {
        $aStarts = mb_strpos(mb_strtolower($a['country_name']), $search) === 0 ? 0 : 1;
        $bStarts = mb_strpos(mb_strtolower($b['country_name']), $search) === 0 ? 0 : 1;
        return $aStarts <=> $bStarts ?: strcmp($a['country_name'], $b['country_name']);
    });
    return array_slice($rows, 0, $limit);
}

/** Negara berdasarkan kode ISO → ['code' => kode, 'name' => nama]; null kalau tidak dikenal. */
function intlFindCountry(string $code): ?array
{
    $code = strtoupper(trim($code));
    $name = intlCountries()[$code] ?? null;
    return $name !== null ? ['code' => $code, 'name' => $name] : null;
}

/**
 * Layanan pengiriman internasional ke satu negara: satu tarif flat.
 * Return null kalau negara atau kurir tidak valid.
 *
 * Alur ongkir toko menghitung dalam IDR lalu mengonversi ke USD, jadi tarif flat USD dikonversi ke
 * IDR dengan kurs yang sama — hasilnya kembali tepat INTL_FLAT_SHIPPING_USD di sisi pembeli.
 */
function intlShippingServices(string $countryCode, string $courier): ?array
{
    $courier = strtolower(trim($courier));
    if (!isset(intlCouriers()[$courier]) || !intlFindCountry($countryCode)) return null;

    return [[
        'service'     => INTL_SHIPPING_SERVICE,
        'code'        => $courier,
        'description' => '',
        'cost'        => ddConvertToIdr(INTL_FLAT_SHIPPING_USD),
        'currency'    => 'IDR',
        'etd'         => null,
    ]];
}
