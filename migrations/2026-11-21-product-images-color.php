<?php
// Foto per warna: product_images.color_name ('' = foto umum, dipakai semua warna yang tidak punya foto sendiri).
return static function (PDO $pdo): void {
    if ($pdo->query("SHOW COLUMNS FROM product_images LIKE 'color_name'")->fetch() === false) {
        $pdo->exec("ALTER TABLE product_images ADD COLUMN color_name VARCHAR(60) NOT NULL DEFAULT '' AFTER image_path");
    }
};
