<?php
/**
 * index.php — Dashboard v7
 * Zeigt Rollen-abhängige Kacheln, User-Mgmt Button, System-Status
 */
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/db.php';
require_login();

// Quick Stats aus DB laden
$stats = [];
try {
    foreach (['drinks', 'food', 'cable', 'camping', 'ice', 'extra'] as $tbl) {
        $row = $pdo->query("SELECT COUNT(*) as total, SUM(stock) as active FROM `{$tbl}`")->fetch();
        $stats[$tbl] = $row;
    }
    // Öffnungszeiten heute
    $todayRow = $pdo->prepare("SELECT start_time, end_time, is_closed FROM opening_hours WHERE datum = ?");
    $todayRow->execute([date('Y-m-d')]);
    $todayOh = $todayRow->fetch();

    // User-Count (nur für admin/cernal)
    if (wcr_can('manage_users')) {
        $userCount = $pdo->query("SELECT COUNT(*) FROM be_users WHERE active = 1")->fetchColumn();
    }
} catch (Exception $e) {
    // Graceful: Stats sind optional
}

$totalItems  = array_sum(array_column($stats, 'total'));
$activeItems = array_sum(array_column($stats, 'active'));
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>WCR Backend – Dashboard</title>
</head>
<body class="bo">
<?php include __DIR__ . '/inc/menu.php'; ?>

<!-- Willkommen-Banner -->
<div class="dash-welcome">
  <div>
    <h1 style="margin:0; font-size:22px;">Willkommen im Backoffice 👋</h1>
    <p style="color:#86868b; margin:4px 0 0; font-size:14px;">
      Du bist eingeloggt als <?= wcr_role_badge() ?>
    </p>
  </div>
  <div class="dash-time" id="dash-clock"></div>
</div>

<!-- Quick-Stats -->
<div class="dash-stats-row">
  <div class="dash-stat">
    <div class="ds-num"><?= $activeItems ?> / <?= $totalItems ?></div>
    <div class="ds-lbl">Aktive Produkte</div>
  </div>
  <div class="dash-stat">
    <?php
    $isOpen = false; $statusText = 'Kein Eintrag'; $statusColor = '#86868b';
    if ($todayOh) {
        if ($todayOh['is_closed']) { $statusText = 'Geschlossen'; $statusColor = '#ff3b30'; }
        elseif ($todayOh['start_time']) {
            $isOpen = true;
            $statusText = ($todayOh['start_time'] ?? '?') . ' – ' . ($todayOh['end_time'] ?: 'Sonnenuntergang');
            $statusColor = '#34c759';
        }
    }
    ?>
    <div class="ds-num" style="font-size:14px; color:<?= $statusColor ?>;"><?= htmlspecialchars($statusText) ?></div>
    <div class="ds-lbl">Heute geöffnet</div>
  </div>
  <?php if (isset($userCount)): ?>
  <div class="dash-stat">
    <div class="ds-num"><?= $userCount ?></div>
    <div class="ds-lbl">Aktive Benutzer</div>
  </div>
  <?php endif; ?>
  <div class="dash-stat">
    <div class="ds-num" style="font-size:14px;"><?= date('d.m.Y') ?></div>
    <div class="ds-lbl">Datum</div>
  </div>
</div>

<!-- Gruppen-Schnellstatus -->
<div class="dash-section-title">Produkt-Übersicht</div>
<div class="dash-product-grid">
  <?php
  $icons = ['drinks'=>'🍺','food'=>'🍔','cable'=>'🏄','camping'=>'⛺','ice'=>'🍦','extra'=>'🛍️'];
  $labels= ['drinks'=>'Getränke','food'=>'Essen','cable'=>'Cable','camping'=>'Camping','ice'=>'Eis','extra'=>'Extra'];
  foreach ($stats as $tbl => $row):
      $pct = $row['total'] > 0 ? round($row['active'] / $row['total'] * 100) : 0;
      $color = $pct >= 80 ? '#34c759' : ($pct >= 40 ? '#f0ad4e' : '#ff3b30');
  ?>
  <div class="dash-product-card">
    <div class="dpc-top">
      <span class="dpc-icon"><?= $icons[$tbl] ?></span>
      <span class="dpc-label"><?= $labels[$tbl] ?></span>
      <span class="dpc-count" style="color:<?= $color ?>"><?= (int)$row['active'] ?>/<?= (int)$row['total'] ?></span>
    </div>
    <div class="dpc-bar-bg">
      <div class="dpc-bar-fill" style="width:<?= $pct ?>%; background:<?= $color ?>"></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Navigations-Kacheln -->
<div class="dash-section-title">Schnellzugriff</div>
<div class="dashboard-grid">

  <?php if (wcr_can('view_times')): ?>
  <a href="/be/ctrl/times.php" class="dash-card">
    <span class="icon">🕐</span><strong>Öffnungszeiten</strong>
    <span class="lbl">Zeiten & Kurse</span>
  </a>
  <?php endif; ?>

  <a href="/be/ctrl/drinks.php" class="dash-card">
    <span class="icon">🍺</span><strong>Getränke</strong>
    <span class="lbl">Preise & Verfügbarkeit</span>
  </a>
  <a href="/be/ctrl/food.php" class="dash-card">
    <span class="icon">🍔</span><strong>Essen</strong>
    <span class="lbl">Speisekarte & Gruppen</span>
  </a>
  <a href="/be/ctrl/list.php?t=cable" class="dash-card">
    <span class="icon">🏄</span><strong>Cable</strong>
    <span class="lbl">Tickets & Preise</span>
  </a>
  <a href="/be/ctrl/list.php?t=camping" class="dash-card">
    <span class="icon">⛺</span><strong>Camping</strong>
    <span class="lbl">Stellplatz-Preise</span>
  </a>
  <a href="/be/ctrl/list.php?t=ice" class="dash-card">
    <span class="icon">🍦</span><strong>Eis</strong>
    <span class="lbl">Eiskarte</span>
  </a>
  <a href="/be/ctrl/list.php?t=extra" class="dash-card">
    <span class="icon">🛍️</span><strong>Extra</strong>
    <span class="lbl">Zusatzprodukte</span>
  </a>

  <?php if (wcr_can('view_media')): ?>
  <a href="/be/ctrl/media.php" class="dash-card">
    <span class="icon">🖼️</span><strong>Media</strong>
    <span class="lbl">Bilder verwalten</span>
  </a>
  <?php endif; ?>

  <?php if (wcr_can('view_ds')): ?>
  <a href="/be/ctrl/ds-seiten.php" class="dash-card">
    <span class="icon">🖥️</span><strong>DS-Seiten</strong>
    <span class="lbl">Screen-Vorschau</span>
  </a>
  <a href="/be/ctrl/ds-settings.php" class="dash-card">
    <span class="icon">🎨</span><strong>DS Controller</strong>
    <span class="lbl">Theme · Farben · Layout</span>
  </a>
  <?php endif; ?>

  <?php if (wcr_can('manage_users')): ?>
  <a href="/be/ctrl/users.php" class="dash-card dash-card-highlight">
    <span class="icon">👥</span><strong>Benutzer</strong>
    <span class="lbl">Anlegen & verwalten</span>
  </a>
  <?php endif; ?>

</div>

<script>
(function tick() {
  const el = document.getElementById('dash-clock');
  if (el) {
    const n = new Date();
    el.textContent = n.toLocaleTimeString('de-DE', {hour:'2-digit',minute:'2-digit',second:'2-digit'});
  }
  setTimeout(tick, 1000);
})();
</script>

<?php include __DIR__ . '/inc/debug.php'; ?>
</body>
</html>
