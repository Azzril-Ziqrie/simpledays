<?php
/** Identitas brand — satu-satunya tempat nama/logo Simple Days didefinisikan. */

const BRAND_NAME    = 'Simple Days';
const BRAND_URL    = 'https://thesimpledays.co';   // domain utama, dipakai canonical, sitemap, JSON-LD
const BRAND_EST     = '2019';   // tahun berdiri, tampil di halaman About
const BRAND_TAGLINE = 'Everyday essentials, quietly made.';
const BRAND_LOGO    = 'assets/images/simple-days-logo.png';   // hitam, transparan
const BRAND_ICON    = 'assets/images/simple-days-icon.png';
const BRAND_MARK    = 'assets/images/simple-days-dots.png';   // hanya tiga titik, hitam, transparan (navbar)

// Isi dengan URL profil Instagram brand; kosong = ikon Instagram disembunyikan di footer.
const BRAND_INSTAGRAM = 'https://www.instagram.com/thesimpledaysco/';
const BRAND_WHATSAPP  = 'https://wa.me/6282197404484';

// Alamat email kontak (halaman Contact Us). Kosong = kartu email disembunyikan.
const BRAND_EMAIL = 'info@thesimpledays.co';

// Kotak newsletter di footer (kirim ke newsletter.php). false = disembunyikan.
const BRAND_NEWSLETTER = true;

/**
 * Teks "about" brand: dipakai bagian intro di beranda dan halaman About supaya selalu sama.
 * Baris pertama adalah pernyataan brand; sisanya paragraf pendamping.
 *
 * @return string[]
 */
function brandAboutCopy(): array
{
    return [
        'S/DAYS (Simple Days) - Inspired by Breakin’ - Tools for the Creative Mind',
        'S/DAYS draws inspiration from breakin’, where originality and style stand alone; ideals stamped and expressed in the craft.',
        'With breakin’ as the foundation of our approach, we embrace curiosity, play and fun in our creative process and product testing, making sure all our tools are “breakin’ tested”.',
        'Balancing comfort, style and function, we design with the lifestyle of the active creative in mind to equip you for your adventures.',
        'We’ll worry about the gears so you can focus on your craft.',
        'Create. Just Because.',
    ];
}
