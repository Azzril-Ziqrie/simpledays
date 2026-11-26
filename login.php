<?php
// Tidak ada halaman login terpisah: login lewat modal akun di beranda.
// Link lama (logout, cart, register) diarahkan ke sini → buka modal itu.
header('Location: ./?login=1', true, 302);
exit;
