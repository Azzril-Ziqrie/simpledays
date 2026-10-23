<?php
/**
 * Panel analytics (popular content, purchase funnel, visitors by country).
 * Dipakai dashboard.php dan dashboard-analytics.php. Variabel: $report dari analyticsReport().
 */
$anFunnel = $report['funnel'];
$anTop    = max(1, (int)($anFunnel[0]['count'] ?? 0));
$anEsc    = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<div class="an-grid">
  <div class="an-card an-card--wide">
    <h3>Purchase funnel</h3>
    <div class="an-funnel">
      <?php foreach ($anFunnel as $i => $step):
          $pct  = $i > 0 && $anFunnel[$i - 1]['count'] > 0 ? round($step['count'] / $anFunnel[$i - 1]['count'] * 100) : 0;
          $fill = min(100, $step['count'] / $anTop * 100); ?>
        <div class="an-col">
          <div class="an-num"><?= number_format($step['count']) ?></div>
          <div class="an-track"><i style="height:<?= round($fill, 1) ?>%"></i></div>
          <?php if ($i > 0): ?><span class="an-pct"><?= $pct ?>%</span><?php endif; ?>
          <div class="an-name"><?= $anEsc($step['label']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="an-note">Percentages are from the previous step. Purchased counts paid orders.</p>
  </div>

  <div class="an-card">
    <h3>Popular content</h3>
    <?php if (!$report['popular']): ?>
      <p class="an-empty">No visits recorded yet.</p>
    <?php else: $max = max(1, (int)$report['popular'][0]['views']); ?>
      <div class="an-bars">
        <?php foreach ($report['popular'] as $row): ?>
          <div>
            <div class="an-label"><span><?= $anEsc($row['label']) ?></span><span><?= number_format((int)$row['views']) ?></span></div>
            <div class="an-bar"><i style="width:<?= round($row['views'] / $max * 100, 1) ?>%"></i></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="an-card">
    <h3>Visitors by country</h3>
    <?php if (!$report['countries']): ?>
      <p class="an-empty">No visits recorded yet.</p>
    <?php else: $max = max(1, (int)$report['countries'][0]['visitors']); ?>
      <div class="an-bars">
        <?php foreach ($report['countries'] as $row): ?>
          <div>
            <div class="an-label"><span><?= $anEsc(trim(analyticsFlag((string)$row['code']) . ' ' . analyticsCountryName((string)$row['code']))) ?></span><span><?= number_format((int)$row['visitors']) ?></span></div>
            <div class="an-bar"><i style="width:<?= round($row['visitors'] / $max * 100, 1) ?>%"></i></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
