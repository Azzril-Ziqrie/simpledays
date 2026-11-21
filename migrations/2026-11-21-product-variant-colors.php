<?php
// Warna per varian: product_sizes.color_name / color_hex + unique (product_id, size_id, color_name).
require_once __DIR__ . '/../includes/variants.php';

return static function (PDO $pdo): void {
    ddAddVariantColumns($pdo);
};
