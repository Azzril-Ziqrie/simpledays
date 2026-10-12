<?php
/**
 * Query katalog untuk halaman depan: hero, kategori, dan produk (tahan beda skema DB).
 * Membutuhkan tableExists()/tableColumns() dari includes/db_schema.php.
 */

const SEARCH_MAX_LENGTH = 100;

function fetchHeroSlides(PDO $pdo, string $fallbackImage): array
{
    $slides = [];
    if (tableExists($pdo, 'hero_slides')) {
        try {
            $slides = $pdo->query(
                'SELECT id, title, subtitle, button_text, button_link, image
                   FROM hero_slides
                  WHERE is_active = 1
                  ORDER BY sort_order ASC, id ASC'
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('[catalog] Hero slides query failed: ' . $e->getMessage());
        }
    }

    return $slides ?: [[
        'id' => 0, 'title' => null, 'subtitle' => null,
        'button_text' => null, 'button_link' => null,
        'image' => $fallbackImage,
    ]];
}

/** Deteksi bentuk skema: relasi kategori (tabel/teks) dan sumber stok. */
function catalogSchema(PDO $pdo): array
{
    $columns   = tableColumns($pdo, 'products');
    $hasTable  = tableExists($pdo, 'categories') && in_array('category_id', $columns, true);
    $hasText   = in_array('category', $columns, true);
    $hasSizes  = tableExists($pdo, 'product_sizes');
    $hasStock  = in_array('stock', $columns, true);
    $sizeStock = '(SELECT SUM(stock) FROM product_sizes WHERE product_id = p.id AND is_active = 1)';

    if ($hasSizes && $hasStock) {
        $stockSelect = "(p.stock > 0 OR IFNULL($sizeStock, 0) > 0) AS is_in_stock";
    } elseif ($hasSizes) {
        $stockSelect = "(IFNULL($sizeStock, 0) > 0) AS is_in_stock";
    } elseif ($hasStock) {
        $stockSelect = '(p.stock > 0) AS is_in_stock';
    } else {
        $stockSelect = '1 AS is_in_stock';
    }

    // Foto kedua (hover): gambar galeri pertama yang berbeda dari thumbnail.
    $hoverSelect = tableExists($pdo, 'product_images')
        ? '(SELECT pi.image_path FROM product_images pi
             WHERE pi.product_id = p.id AND pi.image_path <> p.thumbnail
             ORDER BY pi.sort_order ASC, pi.id ASC LIMIT 1) AS hover_image'
        : 'NULL AS hover_image';

    return [
        'joinCategory'  => $hasTable,
        'categoryText'  => $hasText,
        'hasSortOrder'  => in_array('sort_order', $columns, true),
        'hasIsNew'      => in_array('is_new', $columns, true),
        'stockSelect'   => $stockSelect,
        'hoverSelect'   => $hoverSelect,
        'newSelect'     => in_array('is_new', $columns, true) ? 'p.is_new' : '0 AS is_new',
    ];
}

function fetchCategories(PDO $pdo, array $schema): array
{
    try {
        if ($schema['joinCategory']) {
            return $pdo->query('SELECT name, slug FROM categories ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
        }
        if ($schema['categoryText']) {
            return $pdo->query(
                "SELECT DISTINCT category AS name, LOWER(REPLACE(category, ' ', '-')) AS slug
                   FROM products WHERE category IS NOT NULL AND category <> '' ORDER BY category ASC"
            )->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $e) {
        error_log('[catalog] Categories query failed: ' . $e->getMessage());
    }
    return [];
}

/**
 * Produk aktif, difilter pencarian dan/atau slug kategori.
 * $hideSoldOut=false menyertakan produk stok 0 (kolom is_in_stock tetap diisi) — dipakai halaman Shop.
 */
function fetchInStockProducts(PDO $pdo, array $schema, string $search, string $category, bool $hideSoldOut = true): array
{
    $categorySelect = 'NULL AS category_name, NULL AS category_slug';
    if ($schema['joinCategory']) {
        $categorySelect = 'c.name AS category_name, c.slug AS category_slug';
    } elseif ($schema['categoryText']) {
        $categorySelect = "p.category AS category_name, LOWER(REPLACE(p.category, ' ', '-')) AS category_slug";
    }

    $sql = "SELECT p.id, p.name, p.slug, p.price_idr, p.price_usd,
                   p.discount_percent, p.discount_start_at, p.discount_end_at, p.is_discount_active,
                   p.thumbnail, {$schema['hoverSelect']}, {$schema['newSelect']},
                   {$categorySelect}, {$schema['stockSelect']}
              FROM products p";
    if ($schema['joinCategory']) {
        $sql .= ' LEFT JOIN categories c ON p.category_id = c.id';
    }
    $sql .= ' WHERE p.is_active = 1';

    $params = [];
    if ($search !== '') {
        $searchColumns = ['p.name', 'p.slug'];
        if ($schema['joinCategory'])     { array_push($searchColumns, 'c.name', 'c.slug'); }
        elseif ($schema['categoryText']) { $searchColumns[] = 'p.category'; }

        $clauses = [];
        foreach ($searchColumns as $i => $column) {
            $clauses[] = "$column LIKE :search$i";
            $params[":search$i"] = '%' . $search . '%';
        }
        $sql .= ' AND (' . implode(' OR ', $clauses) . ')';
    }

    if ($category !== '') {
        if ($schema['joinCategory']) {
            // Kategori "new" juga memuat produk bertanda baru (is_new) di kategori lain.
            $includeNew = strtolower($category) === 'new' && $schema['hasIsNew'];
            $sql .= $includeNew ? ' AND (c.slug = :category OR p.is_new = 1)' : ' AND c.slug = :category';
            $params[':category'] = $category;
        } elseif ($schema['categoryText']) {
            $sql .= " AND LOWER(REPLACE(p.category, ' ', '-')) = :category";
            $params[':category'] = strtolower($category);
        }
    }

    $sql .= $schema['hasSortOrder'] ? ' ORDER BY p.sort_order ASC, p.id DESC' : ' ORDER BY p.id DESC';

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('[catalog] Product query failed: ' . $e->getMessage());
        return [];
    }

    if (!$hideSoldOut) {
        return $rows;
    }
    return array_values(array_filter($rows, fn(array $p) => (int)($p['is_in_stock'] ?? 1) === 1));
}
