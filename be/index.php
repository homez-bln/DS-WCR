<?php
/**
 * index.php — Dashboard v8
 * Vollflächiges Layout · Hero-Zeile · Produkt-Cards groß · Schnellzugriff prominent
 */
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/db.php';
require_login();

$stats = [];
try {
    foreach (['drinks', 'food', 'cable', 'camping', 'ice', 'extra'] as $tbl) {
        $row = $pdo->query("SELECT COUNT(*) as total, SUM(stock) as active FROM `{$tbl}`")->fetch();
        $stats[$tbl] = $row;
    }
    $todayRow = $pdo->prepare("SELECT start_time, end_time, is_closed FROM opening_hours WHERE datum = ?");
    $todayRow->execute([date('Y-m-d')]);
    $todayOh = $todayRow->fetch();
    if (wcr_can('manage_users')) {
        $userCount = $pdo->query("SELECT COUNT(*) FROM be_users WHERE active = 1")->fetchColumn();
    }
} catch (Exception $e) {}

$totalItems  = array_sum(array_column($stats, 'total'));
$activeItems = array_sum(array_column($stats, 'active'));

$isOpen = false; $statusText = 'Kein Eintrag'; $statusColor = 'var(--text-muted)'; $statusDot = '#86868b';
if (!empty($todayOh)) {
    if ($todayOh['is_closed'])    { $statusText = 'Geschlossen'; $statusColor = 'var(--danger)'; $statusDot = 'var(--danger)'; }
    elseif ($todayOh['start_time']) {
        $isOpen = true;
        $statusText  = ($todayOh['start_time'] ?? '?') . ' – ' . ($todayOh['end_time'] ?: 'Sonnenuntergang');
        $statusColor = 'var(--success)'; $statusDot = 'var(--success)';
    }
}

$icons  = ['drinks'=>'🍺','food'=>'🍔','cable'=>'🏄','camping'=>'⛺','ice'=>'🍦','extra'=>'🛍️'];
$labels = ['drinks'=>'Getränke','food'=>'Essen','cable'=>'Cable','camping'=>'Camping','ice'=>'Eis','extra'=>'Extra'];
$hrefs  = ['drinks'=>'/be/ctrl/drinks.php','food'=>'/be/ctrl/food.php','cable'=>'/be/ctrl/list.php?t=cable',
           'camping'=>'/be/ctrl/list.php?t=camping','ice'=>'/be/ctrl/list.php?t=ice','extra'=>'/be/ctrl/list.php?t=extra'];
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>WCR Backend – Dashboard</title>
</head>
<body>
<?php include __DIR__ . '/inc/menu.php'; ?>

<div class="db8-wrap">

  <!-- ── HERO ─────────────────────────────────────────────────────── -->
  <div class="db8-hero">
    <div class="db8-hero-left">
      <div class="db8-greeting">Willkommen zurück 👋</div>
      <div class="db8-sub">
        Eingeloggt als <?= wcr_role_badge() ?>
        &nbsp;·&nbsp; <?= date('l, d. F Y', strtotime('today')) ?>
      </div>
    </div>
    <div class="db8-clock" id="dash-clock"></div>
  </div>

  <!-- ── KPI-ZEILE ─────────────────────────────────────────────────── -->
  <div class="db8-kpi-row">

    <div class="db8-kpi">
      <div class="db8-kpi-val"><?= $activeItems ?><span class="db8-kpi-of"> / <?= $totalItems ?></span></div>
      <div class="db8-kpi-lbl">Aktive Produkte</div>
      <div class="db8-kpi-bar-bg"><div class="db8-kpi-bar-fill" style="width:<?= $totalItems > 0 ? round($activeItems/$totalItems*100) : 0 ?>%"></div></div>
    </div>

    <div class="db8-kpi db8-kpi--status">
      <div class="db8-status-dot" style="background:<?= $statusDot ?>"></div>
      <div>
        <div class="db8-kpi-val" style="color:<?= $statusColor ?>; font-size:16px; line-height:1.3"><?= htmlspecialchars($statusText) ?></div>
        <div class="db8-kpi-lbl">Heute geöffnet</div>
      </div>
    </div>

    <?php if (isset($userCount)): ?>
    <div class="db8-kpi">
      <div class="db8-kpi-val"><?= $userCount ?></div>
      <div class="db8-kpi-lbl">Aktive Benutzer</div>
    </div>
    <?php endif; ?>

    <div class="db8-kpi db8-kpi--date">
      <div class="db8-kpi-val" style="font-size:22px"><?= date('d') ?></div>
      <div class="db8-kpi-month"><?= date('M Y') ?></div>
      <div class="db8-kpi-lbl">Heute</div>
    </div>

  </div>

  <!-- ── HAUPT-CONTENT-GRID ────────────────────────────────────────── -->
  <div class="db8-main">

    <!-- LINKE SPALTE: Produkt-Karten -->
    <?php if (wcr_can('edit_products')): ?>
    <section class="db8-section">
      <h2 class="db8-sh">Produkte</h2>
      <div class="db8-prod-grid">
        <?php foreach ($stats as $tbl => $row):
            $pct   = $row['total'] > 0 ? round($row['active'] / $row['total'] * 100) : 0;
            $color = $pct >= 80 ? 'var(--success)' : ($pct >= 40 ? 'var(--warning)' : 'var(--danger)');
            $colorRaw = $pct >= 80 ? 'var(--success-rgb)' : ($pct >= 40 ? 'var(--warning-rgb)' : 'var(--danger-rgb)');
        ?>
        <a href="<?= $hrefs[$tbl] ?>" class="db8-pc" style="--pc-color:<?= $color ?>; --pc-rgb:<?= $colorRaw ?>">
          <div class="db8-pc-icon"><?= $icons[$tbl] ?></div>
          <div class="db8-pc-body">
            <div class="db8-pc-name"><?= $labels[$tbl] ?></div>
            <div class="db8-pc-count"><?= (int)$row['active'] ?> / <?= (int)$row['total'] ?></div>
          </div>
          <div class="db8-pc-right">
            <div class="db8-pc-pct"><?= $pct ?>%</div>
            <div class="db8-pc-bar-bg"><div class="db8-pc-bar-fill" style="width:<?= $pct ?>%"></div></div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <!-- RECHTE SPALTE: Schnellzugriff -->
    <section class="db8-section">
      <h2 class="db8-sh">Schnellzugriff</h2>
      <div class="db8-qa-grid">

        <?php if (wcr_can('view_times')): ?>
        <a href="/be/ctrl/times.php" class="db8-qa" style="--qa-bg:#e8f4ff;--qa-fg:#0a5899">
          <span class="db8-qa-icon">🕐</span>
          <span class="db8-qa-label">Öffnungszeiten</span>
          <span class="db8-qa-sub">Zeiten &amp; Kurse</span>
        </a>
        <?php endif; ?>

        <?php if (wcr_can('view_media')): ?>
        <a href="/be/ctrl/media.php" class="db8-qa" style="--qa-bg:#fff0e8;--qa-fg:#b56010">
          <span class="db8-qa-icon">🖼️</span>
          <span class="db8-qa-label">Media</span>
          <span class="db8-qa-sub">Bilder verwalten</span>
        </a>
        <?php endif; ?>

        <?php if (wcr_can('view_ds')): ?>
        <a href="/be/ctrl/ds-seiten.php" class="db8-qa" style="--qa-bg:#ede0ff;--qa-fg:#6b2fb5">
          <span class="db8-qa-icon">🖥️</span>
          <span class="db8-qa-label">DS-Seiten</span>
          <span class="db8-qa-sub">Screen-Vorschau</span>
        </a>
        <a href="/be/ctrl/ds-settings.php" class="db8-qa" style="--qa-bg:#ffd6f5;--qa-fg:#a0238a">
          <span class="db8-qa-icon">⚙️</span>
          <span class="db8-qa-label">DS Controller</span>
          <span class="db8-qa-sub">Theme · Farben</span>
        </a>
        <?php endif; ?>

        <?php if (wcr_can('manage_users')): ?>
        <a href="/be/ctrl/users.php" class="db8-qa" style="--qa-bg:#d6f4ff;--qa-fg:#0a7a99">
          <span class="db8-qa-icon">👥</span>
          <span class="db8-qa-label">Benutzer</span>
          <span class="db8-qa-sub">Anlegen &amp; verwalten</span>
        </a>
        <?php endif; ?>

        <?php if (wcr_can('view_ds')): ?>
        <a href="/be/ctrl/instagram.php" class="db8-qa" style="--qa-bg:#ffd6d6;--qa-fg:#c0241c">
          <span class="db8-qa-icon">📸</span>
          <span class="db8-qa-label">Instagram</span>
          <span class="db8-qa-sub">Feed-Einstellungen</span>
        </a>
        <a href="/be/ctrl/obstacles.php" class="db8-qa" style="--qa-bg:#d4f5d4;--qa-fg:#1a7a1a">
          <span class="db8-qa-icon">🗺️</span>
          <span class="db8-qa-label">Obstacles</span>
          <span class="db8-qa-sub">Karte verwalten</span>
        </a>
        <?php endif; ?>

      </div>
    </section>

  </div><!-- /.db8-main -->
</div><!-- /.db8-wrap -->

<style>
/* ── Dashboard v8 ────────────────────────────────────────────────── */
.db8-wrap {
    display: flex;
    flex-direction: column;
    gap: 20px;
    min-height: calc(100vh - 100px);
}

/* Hero */
.db8-hero {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--bg-card);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 24px 32px;
    border: 1px solid var(--border-xlight);
}
.db8-greeting {
    font-size: 24px;
    font-weight: 700;
    letter-spacing: -.4px;
    color: var(--text-main);
    margin-bottom: 6px;
}
.db8-sub {
    font-size: 13px;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}
.db8-clock {
    font-size: 44px;
    font-weight: 700;
    color: var(--primary);
    font-variant-numeric: tabular-nums;
    letter-spacing: -1px;
    line-height: 1;
}

/* KPI-Zeile */
.db8-kpi-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 14px;
}
.db8-kpi {
    background: var(--bg-card);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 20px 22px;
    border: 1px solid var(--border-xlight);
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.db8-kpi--status {
    flex-direction: row;
    align-items: center;
    gap: 14px;
}
.db8-kpi--date {
    align-items: flex-start;
}
.db8-kpi-val {
    font-size: 32px;
    font-weight: 700;
    color: var(--text-main);
    line-height: 1.1;
    letter-spacing: -.5px;
}
.db8-kpi-of {
    font-size: 18px;
    font-weight: 500;
    color: var(--text-muted);
}
.db8-kpi-lbl {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .07em;
    color: var(--text-light);
    margin-top: 2px;
}
.db8-kpi-month {
    font-size: 13px;
    color: var(--text-muted);
    font-weight: 500;
}
.db8-kpi-bar-bg {
    margin-top: 10px;
    height: 4px;
    background: var(--border-light);
    border-radius: 4px;
    overflow: hidden;
}
.db8-kpi-bar-fill {
    height: 100%;
    background: var(--primary);
    border-radius: 4px;
    transition: width .6s ease;
}
.db8-status-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    flex-shrink: 0;
    box-shadow: 0 0 0 3px rgba(0,0,0,.06);
    margin-top: 2px;
}

/* Haupt-Grid */
.db8-main {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    align-items: start;
    flex: 1;
}
.db8-section {}
.db8-sh {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .09em;
    color: var(--text-muted);
    margin: 0 0 12px 2px;
}

/* Produkt-Cards */
.db8-prod-grid {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.db8-pc {
    display: flex;
    align-items: center;
    gap: 16px;
    background: var(--bg-card);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 16px 20px;
    text-decoration: none;
    color: var(--text-main);
    border: 1px solid var(--border-xlight);
    border-left: 4px solid var(--pc-color);
    transition: transform .15s, box-shadow .15s, background .15s;
}
.db8-pc:hover {
    transform: translateX(4px);
    box-shadow: var(--shadow-hover);
    background: rgba(var(--pc-rgb), .04);
}
.db8-pc-icon { font-size: 26px; flex-shrink: 0; }
.db8-pc-body { flex: 1; min-width: 0; }
.db8-pc-name { font-size: 15px; font-weight: 700; }
.db8-pc-count { font-size: 12px; color: var(--text-muted); margin-top: 2px; }
.db8-pc-right { flex-shrink: 0; text-align: right; min-width: 80px; }
.db8-pc-pct {
    font-size: 18px;
    font-weight: 700;
    color: var(--pc-color);
    line-height: 1;
    margin-bottom: 6px;
}
.db8-pc-bar-bg {
    height: 5px;
    background: var(--border-light);
    border-radius: 4px;
    overflow: hidden;
    width: 80px;
}
.db8-pc-bar-fill {
    height: 100%;
    background: var(--pc-color);
    border-radius: 4px;
    transition: width .6s ease;
}

/* Schnellzugriff-Grid */
.db8-qa-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
}
.db8-qa {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
    padding: 18px 16px;
    background: var(--qa-bg, #f0f0f2);
    border-radius: var(--radius);
    text-decoration: none;
    color: var(--qa-fg, #1c1c1e);
    border: 1px solid rgba(0,0,0,.06);
    transition: transform .15s, box-shadow .15s, filter .15s;
    box-shadow: 0 2px 8px rgba(0,0,0,.05);
}
[data-theme="dark"] .db8-qa {
    filter: brightness(.82) saturate(.85);
}
[data-theme="dark"] .db8-qa:hover {
    filter: brightness(1) saturate(1.1);
}
.db8-qa:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 22px rgba(0,0,0,.12);
}
.db8-qa:active { transform: scale(.97); }
.db8-qa-icon  { font-size: 26px; line-height: 1; margin-bottom: 4px; }
.db8-qa-label { font-size: 14px; font-weight: 700; line-height: 1.2; }
.db8-qa-sub   { font-size: 11px; opacity: .7; font-weight: 500; }

/* Responsive */
@media (max-width: 960px) {
    .db8-main { grid-template-columns: 1fr; }
}
@media (max-width: 640px) {
    .db8-hero { flex-direction: column; align-items: flex-start; gap: 12px; padding: 20px; }
    .db8-clock { font-size: 32px; }
    .db8-qa-grid { grid-template-columns: repeat(2, 1fr); }
    .db8-kpi-row { grid-template-columns: 1fr 1fr; }
}
</style>

<script>
(function tick() {
    var el = document.getElementById('dash-clock');
    if (el) {
        var n = new Date();
        el.textContent = n.toLocaleTimeString('de-DE', {hour:'2-digit', minute:'2-digit', second:'2-digit'});
    }
    setTimeout(tick, 1000);
})();
</script>

<?php include __DIR__ . '/inc/debug.php'; ?>
</body>
</html>
