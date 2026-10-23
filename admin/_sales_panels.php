<?php
/**
 * Panel Sales overview. Variabel: $sales dari salesReport(); $days (0 = sejak awal).
 */
$saEsc   = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$saMoney = static fn(float $v): string => ddFormatMoney($v, 'USD');
$totals  = $sales['totals'];
$prev    = $sales['previous'];

$saDelta = static function (float $now, ?float $before): string {
    if ($before === null) return '';
    if ($before <= 0) return $now > 0 ? '<span class="sa-delta sa-delta--up">New</span>' : '<span class="sa-delta">—</span>';
    $pct = round(($now - $before) / $before * 100);
    $cls = $pct > 0 ? 'up' : ($pct < 0 ? 'down' : '');
    return '<span class="sa-delta' . ($cls ? " sa-delta--$cls" : '') . '">' . ($pct > 0 ? '+' : '') . $pct . '% vs previous</span>';
};

$kpis = [
    ['Revenue',         $saMoney($totals['revenue']), $saDelta($totals['revenue'], $prev['revenue'] ?? null)],
    ['Paid orders',     number_format($totals['orders']), $saDelta($totals['orders'], isset($prev) ? (float) $prev['orders'] : null)],
    ['Avg. order value', $saMoney($totals['aov']),    $saDelta($totals['aov'], $prev['aov'] ?? null)],
    ['Items sold',      number_format($totals['items']), $saDelta($totals['items'], isset($prev) ? (float) $prev['items'] : null)],
];
$series = $sales['series'];
$maxVal = max(1.0, ...array_column($series, 'value'));
$every  = max(1, (int) ceil(count($series) / 6));
?>
<div class="sa-kpis">
  <?php foreach ($kpis as [$label, $value, $delta]): ?>
    <div class="sa-kpi">
      <div class="sa-kpi__label"><?= $saEsc($label) ?></div>
      <div class="sa-kpi__value"><?= $saEsc($value) ?></div>
      <?= $delta ?>
    </div>
  <?php endforeach; ?>
</div>

<div class="an-grid">
  <div class="an-card an-card--wide">
    <h3>Revenue <?= $sales['monthly'] ? 'by month' : 'by day' ?></h3>
    <?php if ($totals['orders'] === 0): ?>
      <p class="an-empty">No paid orders in this period yet.</p>
    <?php else: ?>
      <div class="sa-axis-max"><?= $saEsc($saMoney($maxVal)) ?></div>
      <div class="sa-chart" role="img" aria-label="Revenue chart">
        <?php foreach ($series as $point): ?>
          <div class="sa-bar" data-tip="<?= $saEsc($point['label'] . ' · ' . $saMoney($point['value'])) ?>"><i style="height:<?= round($point['value'] / $maxVal * 100, 1) ?>%"></i></div>
        <?php endforeach; ?>
      </div>
      <div class="sa-axis">
        <?php foreach ($series as $i => $point): if ($i % $every === 0): ?><span><?= $saEsc($point['label']) ?></span><?php endif; endforeach; ?>
      </div>
    <?php endif; ?>
    <p class="an-note">Revenue counts paid orders only (items + shipping), in USD.</p>
  </div>

  <div class="an-card">
    <h3>Top products</h3>
    <?php if (!$sales['products']): ?>
      <p class="an-empty">No sales yet.</p>
    <?php else: $max = max(1.0, reset($sales['products'])['revenue']); ?>
      <div class="an-bars">
        <?php foreach ($sales['products'] as $name => $row): ?>
          <div>
            <div class="an-label"><span><?= $saEsc($name) ?> <small>× <?= (int) $row['units'] ?></small></span><span><?= $saEsc($saMoney($row['revenue'])) ?></span></div>
            <div class="an-bar"><i style="width:<?= round($row['revenue'] / $max * 100, 1) ?>%"></i></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="an-card">
    <h3>Sales by country</h3>
    <?php if (!$sales['countries']): ?>
      <p class="an-empty">No sales yet.</p>
    <?php else: $max = max(1.0, reset($sales['countries'])['revenue']); ?>
      <div class="an-bars">
        <?php foreach ($sales['countries'] as $name => $row): ?>
          <div>
            <div class="an-label"><span><?= $saEsc($name) ?> <small>· <?= (int) $row['orders'] ?> order<?= $row['orders'] === 1 ? '' : 's' ?></small></span><span><?= $saEsc($saMoney($row['revenue'])) ?></span></div>
            <div class="an-bar"><i style="width:<?= round($row['revenue'] / $max * 100, 1) ?>%"></i></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
