<?php
/*
 * TARIF PENGIRIMAN INTERNASIONAL (IDR) — dikelola manual, tanpa API.
 *
 * !! ANGKA DI BAWAH MASIH SEMENTARA !!
 * Dibuat dari 2 titik tarif JNE ke Malaysia yang disebut client:
 *   ±90 gram  → ±Rp90.000
 *   ±1,02 kg  → ±Rp240.000
 * Ganti dengan rate card JNE asli sebelum dipakai di produksi.
 *
 * Rumus tarif per zona:
 *   berat <= base_gram  → base_price
 *   berat >  base_gram  → base_price + ceil((berat - base_gram) / step_gram) * step_price
 *
 * Menambah negara: tambahkan di 'countries' (kunci = kode ISO 2 huruf) dan arahkan ke zona.
 * Menambah zona: tambahkan di 'zones'.
 */
return [
    'courier_code' => 'jne',
    'service_name' => 'JNE International',
    'etd'          => '4-5 day',

    'zones' => [
        'MY' => ['base_gram' => 500, 'base_price' => 90000, 'step_gram' => 500, 'step_price' => 75000],
    ],

    'countries' => [
        'MY' => ['name' => 'Malaysia', 'zone' => 'MY'],
    ],
];
