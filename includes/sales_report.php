<?php
/**
 * Ringkasan penjualan untuk dashboard admin: pendapatan, jumlah order, rata-rata order, item terjual,
 * tren, produk terlaris, dan penjualan per negara. Hanya order berstatus 'paid'; semua nominal dalam USD
 * (order IDR lama dikonversi, sisanya sudah USD — lihat ddOrderCurrency()).
 * Membutuhkan includes/currency_helper.php.
 */

function salesToUsd(array $order): float
{
    $amount = (float) $order['grand_total'];
    return strtoupper((string) $order['currency']) === 'IDR' ? ddConvertFromIdr($amount) : $amount;
}

/** Order lunas di rentang [$from, $to) (string datetime; null = tak terbatas), dengan tanggal bayar. */
function salesFetchPaid(PDO $pdo, ?string $from, ?string $to): array
{
    $sql = "SELECT id, grand_total, currency, country_name,
                   COALESCE(payment_verified_at, created_at) AS paid_at,
                   (SELECT COALESCE(SUM(quantity), 0) FROM order_items oi WHERE oi.order_id = orders.id) AS items
            FROM orders
            WHERE payment_status = 'paid'";
    $params = [];
    if ($from !== null) { $sql .= ' AND COALESCE(payment_verified_at, created_at) >= :from'; $params['from'] = $from; }
    if ($to   !== null) { $sql .= ' AND COALESCE(payment_verified_at, created_at) < :to';    $params['to']   = $to; }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function salesTotals(array $orders): array
{
    $revenue = 0.0;
    $items   = 0;
    foreach ($orders as $o) {
        $revenue += salesToUsd($o);
        $items   += (int) $o['items'];
    }
    $count = count($orders);
    return ['revenue' => $revenue, 'orders' => $count, 'aov' => $count ? $revenue / $count : 0.0, 'items' => $items];
}

/**
 * @param int $days 7 | 30 | 90 | 0 (sejak awal)
 * @return array{totals:array,previous:?array,series:array,monthly:bool,products:array,countries:array}
 */
function salesReport(PDO $pdo, int $days): array
{
    // Jam database (bukan jam PHP): timestamp order disimpan oleh MySQL, zona waktunya bisa berbeda dari PHP.
    $now   = new DateTimeImmutable((string) $pdo->query('SELECT NOW()')->fetchColumn());
    $end   = $now->modify('+1 day')->setTime(0, 0);            // eksklusif: besok 00:00
    $start = $days > 0 ? $end->modify("-{$days} days") : null;
    $fmt   = 'Y-m-d H:i:s';

    $orders   = salesFetchPaid($pdo, $start ? $start->format($fmt) : null, $end->format($fmt));
    $previous = $start ? salesTotals(salesFetchPaid($pdo, $start->modify("-{$days} days")->format($fmt), $start->format($fmt))) : null;

    // Tren: harian untuk 7/30/90 hari; bulanan (12 bulan terakhir) untuk "All time".
    $monthly = $days === 0;
    $series  = [];
    if ($monthly) {
        for ($i = 11; $i >= 0; $i--) {
            $m = $now->modify("first day of -{$i} month");
            $series[$m->format('Y-m')] = ['label' => $m->format('M Y'), 'value' => 0.0];
        }
    } else {
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = $now->modify("-{$i} days");
            $series[$d->format('Y-m-d')] = ['label' => $d->format('j M'), 'value' => 0.0];
        }
    }
    $countries = [];
    foreach ($orders as $o) {
        $usd = salesToUsd($o);
        $key = date($monthly ? 'Y-m' : 'Y-m-d', strtotime((string) $o['paid_at']));
        if (isset($series[$key])) { $series[$key]['value'] += $usd; }
        $country = trim((string) $o['country_name']) ?: 'Unknown';
        $countries[$country] = ($countries[$country] ?? ['revenue' => 0.0, 'orders' => 0]);
        $countries[$country]['revenue'] += $usd;
        $countries[$country]['orders']++;
    }
    uasort($countries, static fn($a, $b) => $b['revenue'] <=> $a['revenue']);

    // Produk terlaris: dari item order lunas di rentang yang sama.
    $sql = "SELECT oi.product_name, oi.quantity, oi.line_total, o.currency
            FROM order_items oi JOIN orders o ON o.id = oi.order_id
            WHERE o.payment_status = 'paid'";
    $params = [];
    if ($start) { $sql .= ' AND COALESCE(o.payment_verified_at, o.created_at) >= :from'; $params['from'] = $start->format($fmt); }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $name = (string) $row['product_name'];
        $products[$name] = ($products[$name] ?? ['units' => 0, 'revenue' => 0.0]);
        $products[$name]['units']   += (int) $row['quantity'];
        $products[$name]['revenue'] += salesToUsd(['grand_total' => $row['line_total'], 'currency' => $row['currency']]);
    }
    uasort($products, static fn($a, $b) => $b['revenue'] <=> $a['revenue']);

    return [
        'totals'    => salesTotals($orders),
        'previous'  => $previous,
        'series'    => array_values($series),
        'monthly'   => $monthly,
        'products'  => array_slice($products, 0, 5, true),
        'countries' => array_slice($countries, 0, 5, true),
    ];
}
