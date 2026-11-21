<?php
// Penanda produk baru (products.is_new).
return static function (PDO $pdo): void {
    if ($pdo->query("SHOW COLUMNS FROM products LIKE 'is_new'")->fetch() === false) {
        $pdo->exec('ALTER TABLE products ADD COLUMN is_new TINYINT(1) NOT NULL DEFAULT 0');
    }
};
