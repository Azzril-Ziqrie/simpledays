<?php
// Rahasia produksi. SENGAJA di-commit (repo private) supaya ikut ter-deploy ke Hostinger.
// Override khusus komputer lokal ada di secrets.local.php (tidak di-commit).
return [
    'SITE_LOCK'            => '1', // kunci beranda dengan password (kosongkan untuk membuka ke publik)
    'DB_HOST'              => 'localhost',
    'DB_NAME'              => 'u584945443_newsimple',
    'DB_USER'              => 'u584945443_azril',
    'DB_PASS'              => 'Agacil12345',
    'PAYPAL_CLIENT_ID'     => 'AQH-nEddDRWnjcbKpcESDiCWakOnXWAM79ydOhvAvX_3SIaSQ8YTEK6LapTyI4gdtj-3HsAyCLeoTRAa',
    'PAYPAL_CLIENT_SECRET' => 'EL_ZM_PDNgyRatBwJ82ht1yy-Wq49414EwQukdTljB78sOObMI1CnYB8QX_mAvy-_5saLUZ8sUjmqz7y',
    'KASERA_API_KEY'        => 'kp_live_Q2mv_F0UuJqGJS8djxpH60Rj0WcG2T2fTb2eg2gzSxs',
    'KASERA_WEBHOOK_SECRET' => 'whsec_-Wz93onueE6BuRscCafKcFakTCGx3Wa991H1bhEQgBQ',
    'RAJAONGKIR_API_KEY'    => 'yauCjOlH5098102ba59beadf7VlDzBA3',
    'EXCHANGERATE_API_KEY'  => '3d5e70d4fbc11043ad7cbd51',
    'SMTP_HOST'             => 'smtp.hostinger.com',
    'SMTP_PORT'             => '465', // SSL
    'SMTP_USERNAME'         => 'info@thesimpledays.co',
    'SMTP_FROM_EMAIL'       => 'info@thesimpledays.co',
    'SMTP_PASSWORD'         => 'Agacil12345!',
    'ADMIN_SMTP_PASSWORD'   => 'Agacil12345!',
    'ADMIN_PASSWORD_HASH'   => '$2y$10$eY.IASFCF3zfKMaMMAi0DuHje4cuy/JUYhXQMvixQr6i72zqrfGM2',
];
