-- Simple Days: homepage media + new hero banner.
-- Run once on the live database (phpMyAdmin > Import / SQL). Replaces ALL existing hero slides.

CREATE TABLE IF NOT EXISTS home_media (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  media VARCHAR(500) NOT NULL,
  placement VARCHAR(10) NOT NULL DEFAULT 'top',
  title VARCHAR(150) DEFAULT NULL,
  subtitle VARCHAR(255) DEFAULT NULL,
  link VARCHAR(500) DEFAULT NULL,
  width SMALLINT UNSIGNED DEFAULT NULL,
  height SMALLINT UNSIGNED DEFAULT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_placement_sort (placement, is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELETE FROM hero_slides;
INSERT INTO hero_slides (id, title, subtitle, button_text, button_link, image, image_mobile, sort_order, is_active) VALUES
(19, NULL, NULL, NULL, NULL, 'assets/images/hero/sd-hero-1.webp', NULL, 0, 1),
(20, NULL, NULL, NULL, NULL, 'assets/images/hero/sd-hero-2.webp', NULL, 1, 1),
(21, NULL, NULL, NULL, NULL, 'assets/images/hero/sd-hero-3.webp', NULL, 2, 1),
(22, NULL, NULL, NULL, NULL, 'assets/images/hero/sd-hero-4.webp', NULL, 3, 1);

DELETE FROM home_media;
INSERT INTO home_media (id, media, placement, title, subtitle, link, width, height, sort_order, is_active) VALUES
(1, 'assets/images/home/sd-01.webp', 'top', NULL, NULL, NULL, 900, 1599, 0, 1),
(2, 'assets/images/home/sd-clip-1.gif', 'top', NULL, NULL, NULL, 440, 248, 1, 1),
(3, 'assets/images/home/sd-03.webp', 'top', NULL, NULL, NULL, 900, 1599, 2, 1),
(4, 'assets/images/home/sd-clip-2.gif', 'top', NULL, NULL, NULL, 440, 248, 3, 1),
(5, 'assets/images/home/sd-05.webp', 'top', NULL, NULL, NULL, 900, 1597, 4, 1),
(6, 'assets/images/home/sd-09.webp', 'bottom', NULL, NULL, NULL, 900, 1597, 0, 1),
(7, 'assets/images/home/sd-clip-3.gif', 'bottom', NULL, NULL, NULL, 440, 248, 1, 1),
(8, 'assets/images/home/sd-10.webp', 'bottom', NULL, NULL, NULL, 900, 1597, 2, 1),
(9, 'assets/images/home/sd-06.webp', 'bottom', NULL, NULL, NULL, 900, 1597, 3, 1);

