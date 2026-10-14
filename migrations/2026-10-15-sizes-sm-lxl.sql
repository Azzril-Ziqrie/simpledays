-- Hanya dua ukuran di semua produk: S/M dan L/XL.
-- Aman dijalankan berulang. Jalankan di phpMyAdmin (database toko) setelah import simple.sql.
--   X-Small / Small / Medium  -> S/M
--   Large / X-Large / XX-Large -> L/XL
--   One Size & lainnya        -> dibagi dua ke S/M dan L/XL
-- Riwayat pesanan tetap utuh: order_items menyimpan nama ukuran sebagai teks (size_name).

START TRANSACTION;

-- 1) pastikan dua ukuran target ada dan urutannya S/M dulu
INSERT INTO sizes (name, sort_order)
SELECT 'S/M', 1 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM sizes WHERE name = 'S/M');
INSERT INTO sizes (name, sort_order)
SELECT 'L/XL', 2 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM sizes WHERE name = 'L/XL');
UPDATE sizes SET sort_order = 1 WHERE name = 'S/M';
UPDATE sizes SET sort_order = 2 WHERE name = 'L/XL';

-- 2) hitung stok per produk
DROP TEMPORARY TABLE IF EXISTS tmp_size_pack;
CREATE TEMPORARY TABLE tmp_size_pack AS
SELECT ps.product_id,
       SUM(CASE WHEN s.name IN ('X-Small','Small','Medium','XS','S','M','S/M') THEN ps.stock ELSE 0 END) AS sm_stock,
       SUM(CASE WHEN s.name IN ('Large','X-Large','XX-Large','L','XL','XXL','L/XL') THEN ps.stock ELSE 0 END) AS lxl_stock,
       SUM(CASE WHEN s.name NOT IN ('X-Small','Small','Medium','XS','S','M','S/M',
                                    'Large','X-Large','XX-Large','L','XL','XXL','L/XL') THEN ps.stock ELSE 0 END) AS other_stock,
       MAX(ps.is_active) AS is_active
FROM product_sizes ps
JOIN sizes s ON s.id = ps.size_id
GROUP BY ps.product_id;

-- 3) tulis ulang ke dua ukuran target
INSERT INTO product_sizes (product_id, size_id, stock, is_active)
SELECT t.product_id, (SELECT id FROM sizes WHERE name = 'S/M'),
       t.sm_stock + CEIL(t.other_stock / 2), t.is_active
FROM tmp_size_pack t
ON DUPLICATE KEY UPDATE stock = VALUES(stock), is_active = VALUES(is_active);

INSERT INTO product_sizes (product_id, size_id, stock, is_active)
SELECT t.product_id, (SELECT id FROM sizes WHERE name = 'L/XL'),
       t.lxl_stock + FLOOR(t.other_stock / 2), t.is_active
FROM tmp_size_pack t
ON DUPLICATE KEY UPDATE stock = VALUES(stock), is_active = VALUES(is_active);

-- 4) buang ukuran lama (order_items.product_size_id otomatis NULL, cart_items ikut terhapus)
DELETE ps FROM product_sizes ps
JOIN sizes s ON s.id = ps.size_id
WHERE s.name NOT IN ('S/M', 'L/XL');

DELETE FROM sizes WHERE name NOT IN ('S/M', 'L/XL');

-- 5) semua kategori menampilkan pilihan ukuran
UPDATE categories SET has_size = 1;

COMMIT;
DROP TEMPORARY TABLE IF EXISTS tmp_size_pack;
