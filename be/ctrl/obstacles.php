<?php
/**
 * ctrl/obstacles.php — Obstacles-Verwaltung + Karten-Einstellungen
 * lat/lon statt pos_x/y – einmal eingeben, alle Modi korrekt
 * SECURITY v9: Erfordert edit_content Permission + CSRF-Token
 */

$PAGE_TITLE = 'Obstacles';

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';

// ── SECURITY: Login + Permission erforderlich ──
wcr_require('edit_content');

$db = $pdo;

// ── Tabelle erstellen ──
$db->exec("CREATE TABLE IF NOT EXISTS obstacles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(50)  NOT NULL,
    icon_url VARCHAR(500) NULL,
    pos_x   DECIMAL(6,3) NOT NULL DEFAULT 0,
    pos_y   DECIMAL(6,3) NOT NULL DEFAULT 0,
    rotation DECIMAL(6,2) DEFAULT 0,
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// ── Spalten-Migration ──
$dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
$cols   = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = " . $pdo->quote($dbName) . "
    AND TABLE_NAME = 'obstacles'")->fetchAll(PDO::FETCH_COLUMN);

foreach ([
    'lat'     => 'DOUBLE NULL DEFAULT NULL',
    'lon'     => 'DOUBLE NULL DEFAULT NULL',
    'pos_x_l' => 'DECIMAL(6,3) NULL DEFAULT NULL',
    'pos_y_l' => 'DECIMAL(6,3) NULL DEFAULT NULL',
    'pos_x_p' => 'DECIMAL(6,3) NULL DEFAULT NULL',
    'pos_y_p' => 'DECIMAL(6,3) NULL DEFAULT NULL',
] as $col => $def) {
    if (!in_array($col, $cols)) {
        $pdo->exec("ALTER TABLE obstacles ADD COLUMN `$col` $def");
    }
}

define('OBS_WP_API',  'https://wcr-webpage.de/wp-json/wakecamp/v1/obstacles/map-config');
define('OBS_SECRET',  'WCR_DS_2026');

function obs_curl(string $url, ?array $postData = null): array {
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
    ];
    if ($postData !== null) {
        $opts[CURLOPT_POST]       = true;
        $opts[CURLOPT_POSTFIELDS] = json_encode($postData);
    }
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    return [
        'ok'   => ($code === 200 && !$err),
        'code' => $code,
        'json' => json_decode($body ?: '', true),
        'err'  => $err ?: ($code !== 200 ? "HTTP $code" : ''),
        'body' => $body,
    ];
}

$STYLE_OPTIONS = [
    'voyager-nolabels'  => ['label' => '🗺️ OSM (clean)',    'dot' => '#e8dcc8'],
    'satellite'         => ['label' => '🛰️ Satellite',      'dot' => '#4a6741'],
    'dark'              => ['label' => '🌑 Dark',            'dot' => '#1a1a2e'],
    'light'             => ['label' => '☀️ Light',           'dot' => '#f0f0f0'],
    'satellite-labels'  => ['label' => '🛰️ Sat + Labels',   'dot' => '#3d6b50'],
];

// ── Mode ──
$mode = $_POST['mode'] ?? ($_GET['mode'] ?? 'landscape');
$mode = strtolower(trim((string)$mode));
if (!in_array($mode, ['landscape', 'portrait'], true)) $mode = 'landscape';
$modeLabel = ($mode === 'portrait') ? 'Portrait (1080×1920)' : 'Landscape (1920×1080)';

// ── Aktuelle Map-Config laden ──
$mapCfg = ['lat' => 52.821428, 'lon' => 13.577100, 'zoom' => 17.9, 'rot' => 0, 'style' => 'voyager-nolabels'];
$r = obs_curl(OBS_WP_API . '?mode=' . rawurlencode($mode));
if ($r['ok'] && is_array($r['json']) && isset($r['json']['lat'])) {
    $mapCfg = array_merge($mapCfg, $r['json']);
}
$currentStyle = $mapCfg['style'] ?? 'voyager-nolabels';
if (!isset($STYLE_OPTIONS[$currentStyle])) $currentStyle = 'voyager-nolabels';

// ── Map-Config speichern ──
$cfgMsg  = '';
$cfgType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_map_config'])) {
    // ── CSRF-Schutz ──
    wcr_verify_csrf();
    
    $lat   = (float)str_replace(',', '.', $_POST['map_lat']   ?? '');
    $lon   = (float)str_replace(',', '.', $_POST['map_lon']   ?? '');
    $zoom  = (float)str_replace(',', '.', $_POST['map_zoom']  ?? '');
    $rot   = (float)str_replace(',', '.', $_POST['map_rot']   ?? '0');
    $style = $_POST['map_style'] ?? 'voyager-nolabels';
    if (!isset($STYLE_OPTIONS[$style])) $style = 'voyager-nolabels';
    $r2 = obs_curl(OBS_WP_API, [
        'mode' => $mode, 'lat' => $lat, 'lon' => $lon,
        'zoom' => $zoom, 'rot' => $rot, 'style' => $style,
        'wcr_secret' => OBS_SECRET,
    ]);
    if ($r2['ok'] && !empty($r2['json']['ok'])) {
        $cfgMsg  = '✓ Gespeichert — ' . $mode . ' · zoom ' . number_format($zoom,1) . ' · rot ' . number_format($rot,1) . '° · Style: ' . htmlspecialchars($STYLE_OPTIONS[$style]['label']);
        $cfgType = 'ok';
        $mapCfg  = ['lat'=>$lat,'lon'=>$lon,'zoom'=>$zoom,'rot'=>$rot,'style'=>$style];
        $currentStyle = $style;
    } else {
        $debug   = $r2['body'] ? ' — ' . htmlspecialchars(substr($r2['body'],0,120)) : '';
        $cfgMsg  = '✗ Fehler: ' . ($r2['err'] ?: 'Unbekannt') . $debug;
        $cfgType = 'error';
    }
}

// ── Obstacles speichern (lat/lon) ──
$saveMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_obstacles'])) {
    // ── CSRF-Schutz ──
    wcr_verify_csrf();
    
    $ids     = $_POST['id']       ?? [];
    $names   = $_POST['name']     ?? [];
    $types   = $_POST['type']     ?? [];
    $icons   = $_POST['icon_url'] ?? [];
    $lats    = $_POST['lat']      ?? [];
    $lons    = $_POST['lon']      ?? [];
    $rots    = $_POST['rotation'] ?? [];
    $actives = $_POST['active']   ?? [];

    $stmt = $db->prepare("INSERT INTO obstacles
        (id, name, type, icon_url, lat, lon, rotation, active)
        VALUES (:id,:name,:type,:icon_url,:lat,:lon,:rotation,:active)
        ON DUPLICATE KEY UPDATE
            name=VALUES(name), type=VALUES(type), icon_url=VALUES(icon_url),
            lat=VALUES(lat), lon=VALUES(lon),
            rotation=VALUES(rotation), active=VALUES(active)");

    $count = 0;
    foreach ($names as $idx => $n) {
        $n = trim((string)$n);
        $t = trim((string)($types[$idx] ?? ''));
        if ($n === '' && $t === '') continue;
        $lat_v = trim((string)($lats[$idx] ?? ''));
        $lon_v = trim((string)($lons[$idx] ?? ''));
        $stmt->execute([
            ':id'       => ((int)($ids[$idx]??0)) ?: null,
            ':name'     => $n,
            ':type'     => $t ?: 'default',
            ':icon_url' => trim((string)($icons[$idx]??'')),
            ':lat'      => $lat_v !== '' ? (float)str_replace(',','.',$lat_v) : null,
            ':lon'      => $lon_v !== '' ? (float)str_replace(',','.',$lon_v) : null,
            ':rotation' => (float)str_replace(',','.',(string)($rots[$idx]??0)),
            ':active'   => isset($actives[$idx]) ? 1 : 0,
        ]);
        $count++;
    }
    header('Location: ' . strtok($_SERVER['REQUEST_URI'],'?') . '?saved='.$count.'&mode='.rawurlencode($mode));
    exit;
}

if (isset($_GET['saved'])) $saveMsg = '✓ ' . (int)$_GET['saved'] . ' Obstacle(s) gespeichert';

$rows    = $db->query("SELECT id, name, type, icon_url, lat, lon, rotation, active FROM obstacles ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$maxRows = max(5, count($rows) + 3);
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>Verwaltung: <?= htmlspecialchars($PAGE_TITLE) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../inc/style.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <style>
    .mcp-card { background:#fff; border-radius:14px; box-shadow:0 8px 20px rgba(0,0,0,.05); padding:22px 24px; margin-bottom:28px; max-width:1280px; }
    .mcp-card h2 { margin:0 0 4px; font-size:16px; font-weight:700; }
    .mcp-card .sub { font-size:12px; color:#86868b; margin-bottom:18px; }
    .mcp-grid { display:grid; grid-template-columns:300px 1fr; gap:20px; align-items:start; }
    .mcp-sliders { display:flex; flex-direction:column; gap:14px; }
    .sl-row label { display:flex; justify-content:space-between; font-size:12px; font-weight:600; color:#1d1d1f; margin-bottom:5px; }
    .sl-row label span { font-family:monospace; font-size:12px; background:#f0f4ff; color:#0057d9; padding:1px 8px; border-radius:20px; }
    .sl-row input[type=range] { width:100%; height:5px; -webkit-appearance:none; appearance:none; border-radius:4px; outline:none; cursor:pointer; }
    .sl-row input[type=range]::-webkit-slider-thumb { -webkit-appearance:none; width:16px; height:16px; border-radius:50%; background:#0071e3; border:2px solid #fff; box-shadow:0 1px 4px rgba(0,0,0,.2); cursor:pointer; }
    .sl-hint { font-size:10px; color:#aeaeb2; margin-top:3px; }
    #mcp-map { width:100%; height:360px; border-radius:10px; border:1px solid #e5e5ea; overflow:hidden; background:#f2ede4; }
    .style-switcher { display:flex; flex-wrap:wrap; gap:7px; margin-bottom:14px; }
    .style-btn { display:flex; align-items:center; gap:5px; padding:5px 12px; border-radius:20px; border:2px solid #e5e7eb; background:#f9fafb; font-size:12px; font-weight:600; color:#374151; cursor:pointer; transition:all .15s; }
    .style-btn.active { border-color:#0071e3; background:#e8f5ff; color:#0057d9; }
    .style-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; }
    .mcp-actions { display:flex; gap:10px; margin-top:14px; }
    .mcp-hint-bar { font-size:11px; color:#86868b; margin-top:6px; line-height:1.5; }
    #mcp-coords { font-family:monospace; font-size:11px; color:#0057d9; }
    .cfg-msg { font-size:12px; margin-top:10px; padding:5px 10px; border-radius:6px; }
    .cfg-msg.ok    { background:rgba(52,199,89,.10); color:#1a7a30; border:1px solid rgba(52,199,89,.25); }
    .cfg-msg.error { background:rgba(255,59,48,.08); color:#c0392b; border:1px solid rgba(255,59,48,.2); word-break:break-all; }
    .obs-wrapper { max-width:1280px; }
    .obs-table { width:100%; border-collapse:collapse; font-size:13px; }
    .obs-table th, .obs-table td { padding:5px 6px; border-bottom:1px solid #f0f0f3; text-align:left; vertical-align:middle; }
    .obs-table th { background:#f5f5f7; font-weight:600; font-size:11px; color:#6e6e73; white-space:nowrap; }
    .obs-table tr.obs-row { cursor:pointer; transition:background .1s; }
    .obs-table tr.obs-row:hover { background:#fafafa; }
    .obs-table tr.obs-active-row td { background:#eef6ff !important; }
    .obs-table tr.obs-active-row { box-shadow:inset 3px 0 0 #0071e3; }
    .obs-table input[type="text"], .obs-table input[type="number"] { width:100%; box-sizing:border-box; padding:4px 6px; border-radius:6px; border:1px solid #d2d2d7; font-size:12px; }
    .obs-table tr.obs-active-row input.obs-lat,
    .obs-table tr.obs-active-row input.obs-lon { border-color:#0071e3; background:#dbeafe; font-weight:700; }
    .obs-id { width:36px; color:#9f9fa5; font-size:11px; }
    .obs-active { text-align:center; width:50px; }
    .obs-save-bar { margin-top:14px; display:flex; align-items:center; gap:12px; }
    .btn-primary   { padding:7px 16px; border-radius:999px; border:none; background:#0071e3; color:#fff; font-size:13px; font-weight:600; cursor:pointer; }
    .btn-secondary { padding:7px 16px; border-radius:999px; border:1px solid #d2d2d7; background:#fff; color:#1d1d1f; font-size:13px; cursor:pointer; }
    .btn-secondary.is-active { background:#f0f4ff; border-color:#bfd7ff; color:#0057d9; }
    .obs-msg { font-size:12px; margin-bottom:10px; color:#1a7a30; }
    .obs-hint-box { background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:10px 14px; font-size:12px; color:#1e40af; margin-bottom:14px; line-height:1.6; }
  </style>
</head>
<body class="bo">

<?php include __DIR__ . '/../inc/menu.php'; ?>

<div class="obs-wrapper" style="padding:24px 20px;">

  <div class="header-controls">
    <h1>🏄 <?= htmlspecialchars($PAGE_TITLE) ?></h1>
  </div>

  <!-- SEKTION 1: KARTEN-CONFIG -->
  <div class="mcp-card">
    <h2>🗺️ Karten-Einstellungen</h2>
    <p class="sub">Aktuell: <strong><?= htmlspecialchars($modeLabel) ?></strong> &mdash; Einstellungen gelten nur für diesen Modus</p>

    <div class="mcp-actions" style="margin-top:0;margin-bottom:16px;">
      <?php $self = strtok($_SERVER['REQUEST_URI'], '?'); ?>
      <a class="btn-secondary <?= $mode==='landscape'?'is-active':'' ?>" href="<?= htmlspecialchars($self) ?>?mode=landscape">🖥 Landscape</a>
      <a class="btn-secondary <?= $mode==='portrait' ?'is-active':'' ?>" href="<?= htmlspecialchars($self) ?>?mode=portrait">📱 Portrait</a>
    </div>

    <form method="POST">
      <?= wcr_csrf_field() ?>
      <input type="hidden" name="save_map_config" value="1">
      <input type="hidden" name="mode" value="<?= hv($mode) ?>">
      <input type="hidden" id="map_style" name="map_style" value="<?= hv($currentStyle) ?>">

      <div style="margin-bottom:14px;">
        <div style="font-size:12px;font-weight:700;color:#374151;margin-bottom:7px;">🎨 Kartenstil</div>
        <div class="style-switcher">
          <?php foreach ($STYLE_OPTIONS as $key => $opt): ?>
          <button type="button" class="style-btn <?= $key===$currentStyle?'active':'' ?>" data-style="<?= hv($key) ?>">
            <span class="style-dot" style="background:<?= hv($opt['dot']) ?>"></span>
            <?= htmlspecialchars($opt['label']) ?>
          </button>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="mcp-grid">
        <div class="mcp-sliders">
          <div class="sl-row">
            <label>🔍 Zoom <span id="lbl-zoom"><?= number_format((float)$mapCfg['zoom'],1) ?></span></label>
            <input type="range" id="sl-zoom" min="10" max="21" step="0.1" value="<?= hv($mapCfg['zoom']) ?>">
            <input type="hidden" id="map_zoom" name="map_zoom" value="<?= hv($mapCfg['zoom']) ?>">
            <div class="sl-hint">Empfohlen: 16–19 · Standard: 17.9</div>
          </div>
          <div class="sl-row">
            <label>📍 Latitude <span id="lbl-lat"><?= hv($mapCfg['lat']) ?></span></label>
            <input type="range" id="sl-lat" min="52.75" max="52.90" step="0.0001" value="<?= hv($mapCfg['lat']) ?>">
            <input type="hidden" id="map_lat" name="map_lat" value="<?= hv($mapCfg['lat']) ?>">
            <div class="sl-hint">Nord–Süd</div>
          </div>
          <div class="sl-row">
            <label>📍 Longitude <span id="lbl-lon"><?= hv($mapCfg['lon']) ?></span></label>
            <input type="range" id="sl-lon" min="13.50" max="13.65" step="0.0001" value="<?= hv($mapCfg['lon']) ?>">
            <input type="hidden" id="map_lon" name="map_lon" value="<?= hv($mapCfg['lon']) ?>">
            <div class="sl-hint">West–Ost</div>
          </div>
          <div class="sl-row">
            <label>🧭 Rotation <span id="lbl-rot"><?= number_format((float)($mapCfg['rot']??0),1) ?>°</span></label>
            <input type="range" id="sl-rot" min="-180" max="180" step="0.5" value="<?= hv((float)($mapCfg['rot']??0)) ?>">
            <input type="hidden" id="map_rot" name="map_rot" value="<?= hv((float)($mapCfg['rot']??0)) ?>">
          </div>
          <div class="mcp-actions">
            <button type="button" id="btn-mcp-reset" class="btn-secondary">↩ Standard</button>
            <button type="submit" class="btn-primary" style="flex:1;">💾 Karten-Config speichern</button>
          </div>
        </div>
        <div>
          <div id="mcp-map"></div>
          <div class="mcp-hint-bar">
            <span id="mcp-coords"><?= hv($mapCfg['lat']) ?>, <?= hv($mapCfg['lon']) ?> · zoom <?= hv($mapCfg['zoom']) ?> · rot <?= hv((float)($mapCfg['rot']??0)) ?>°</span><br>
            <span style="color:#0071e3;">▶ Zeile anklicken (wird blau) → Karte klicken = lat/lon setzen · Marker ziehen = Position ändern · ohne Auswahl = Zentrum verschieben</span>
          </div>
        </div>
      </div>
      <?php if ($cfgMsg): ?>
        <div class="cfg-msg <?= $cfgType ?>"><?= $cfgMsg ?></div>
      <?php endif; ?>
    </form>
  </div>

  <!-- SEKTION 2: OBSTACLES-TABELLE -->
  <div class="obs-hint-box">
    <strong>📍 Obstacle platzieren:</strong>
    Zeile anklicken (wird blau) → auf Karte klicken → lat/lon gesetzt. Marker ziehen = Position live ändern.<br>
    Obstacles mit <strong>lat/lon</strong> erscheinen in allen Modi (Landscape + Portrait) korrekt — egal wie die Karte gedreht ist.
  </div>

  <?php if ($saveMsg): ?>
    <div class="obs-msg"><?= htmlspecialchars($saveMsg) ?></div>
  <?php endif; ?>

  <form method="POST" id="obs-form">
    <?= wcr_csrf_field() ?>
    <input type="hidden" name="save_obstacles" value="1">
    <input type="hidden" name="mode" value="<?= hv($mode) ?>">
    <table class="obs-table">
      <thead>
        <tr>
          <th class="obs-id">ID</th>
          <th>Name</th>
          <th style="width:80px;">Typ</th>
          <th style="width:115px;">Lat</th>
          <th style="width:115px;">Lon</th>
          <th style="width:70px;">Rotation</th>
          <th>Icon-URL</th>
          <th class="obs-active">Aktiv</th>
        </tr>
      </thead>
      <tbody id="obs-tbody">
        <?php $i = 0; foreach ($rows as $row): $i++; ?>
        <tr class="obs-row" data-idx="<?= $i-1 ?>">
          <td class="obs-id"><input type="hidden" name="id[]" value="<?= (int)$row['id'] ?>">#<?= (int)$row['id'] ?></td>
          <td><input type="text" name="name[]" value="<?= htmlspecialchars($row['name']) ?>"></td>
          <td><input type="text" name="type[]" value="<?= htmlspecialchars($row['type']) ?>"></td>
          <td><input class="obs-lat" type="number" name="lat[]" step="0.000001" value="<?= htmlspecialchars($row['lat']??'') ?>" placeholder="52.821…"></td>
          <td><input class="obs-lon" type="number" name="lon[]" step="0.000001" value="<?= htmlspecialchars($row['lon']??'') ?>" placeholder="13.577…"></td>
          <td><input type="number" name="rotation[]" value="<?= htmlspecialchars($row['rotation']??0) ?>" step="1"></td>
          <td><input type="text" name="icon_url[]" value="<?= htmlspecialchars($row['icon_url']??'') ?>" style="min-width:160px;"></td>
          <td class="obs-active"><input type="checkbox" name="active[<?= $i-1 ?>]" value="1" <?= !empty($row['active'])?'checked':'' ?>></td>
        </tr>
        <?php endforeach; ?>
        <?php for (; $i < $maxRows; $i++): ?>
        <tr class="obs-row" data-idx="<?= $i ?>">
          <td class="obs-id"><input type="hidden" name="id[]" value="0">#neu</td>
          <td><input type="text" name="name[]" value=""></td>
          <td><input type="text" name="type[]" value=""></td>
          <td><input class="obs-lat" type="number" name="lat[]" step="0.000001" value="" placeholder="52.821…"></td>
          <td><input class="obs-lon" type="number" name="lon[]" step="0.000001" value="" placeholder="13.577…"></td>
          <td><input type="number" name="rotation[]" value="0" step="1"></td>
          <td><input type="text" name="icon_url[]" value=""></td>
          <td class="obs-active"><input type="checkbox" name="active[<?= $i ?>]" value="1" checked></td>
        </tr>
        <?php endfor; ?>
      </tbody>
    </table>
    <div class="obs-save-bar">
      <button type="submit" class="btn-primary">💾 Obstacles speichern</button>
    </div>
  </form>

</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
    var DEF = { lat: 52.821428, lon: 13.577100, zoom: 17.9, rot: 0 };
    var TILE_URLS = {
        'voyager-nolabels': 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager_nolabels/{z}/{x}/{y}{r}.png',
        'satellite':        'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
        'dark':             'https://{s}.basemaps.cartocdn.com/dark_nolabels/{z}/{x}/{y}{r}.png',
        'light':            'https://{s}.basemaps.cartocdn.com/light_nolabels/{z}/{x}/{y}{r}.png',
        'satellite-labels': 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}'
    };
    var TYPE_ICONS = {kicker:'🚀',rail:'🟧',box:'🟦',fun:'⭐',slider:'🟩','default':'🔵'};

    var slZ=document.getElementById('sl-zoom'), slLat=document.getElementById('sl-lat'),
        slLon=document.getElementById('sl-lon'), slRot=document.getElementById('sl-rot');
    var lblZ=document.getElementById('lbl-zoom'), lblLt=document.getElementById('lbl-lat'),
        lblLn=document.getElementById('lbl-lon'), lblR=document.getElementById('lbl-rot');
    var coords=document.getElementById('mcp-coords');
    var hZ=document.getElementById('map_zoom'), hLat=document.getElementById('map_lat'),
        hLon=document.getElementById('map_lon'), hRot=document.getElementById('map_rot'),
        hStyle=document.getElementById('map_style');

    var activeRowIdx = null;
    var obsMarkers   = [];
    var isUpdatingFromSlider = false;

    // ── Leaflet ──
    var map = L.map('mcp-map', { zoomControl:true, scrollWheelZoom:true, zoomSnap:0.1, zoomDelta:0.1 })
        .setView([parseFloat(slLat.value), parseFloat(slLon.value)], parseFloat(slZ.value));

    var tileLayer = L.tileLayer(TILE_URLS[hStyle.value] || TILE_URLS['voyager-nolabels'],
        {attribution:'© OSM © CARTO', maxZoom:21, detectRetina:true}).addTo(map);

    var crossIcon = L.divIcon({
        html:'<svg width="22" height="22" viewBox="0 0 22 22"><line x1="11" y1="0" x2="11" y2="22" stroke="#0071e3" stroke-width="2"/><line x1="0" y1="11" x2="22" y2="11" stroke="#0071e3" stroke-width="2"/><circle cx="11" cy="11" r="3" fill="#0071e3"/></svg>',
        className:'', iconAnchor:[11,11]
    });
    var crossMarker = L.marker([parseFloat(slLat.value), parseFloat(slLon.value)],
        {icon:crossIcon, interactive:false, zIndexOffset:2000}).addTo(map);

    // ── Style-Buttons ──
    document.querySelectorAll('.style-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            var key = btn.dataset.style;
            tileLayer.setUrl(TILE_URLS[key] || TILE_URLS['voyager-nolabels']);
            hStyle.value = key;
            document.querySelectorAll('.style-btn').forEach(function(b){ b.classList.toggle('active', b.dataset.style===key); });
        });
    });

    function grad(sl){
        var p=(sl.value-sl.min)/(sl.max-sl.min)*100;
        sl.style.background='linear-gradient(90deg,#0071e3 '+p+'%,#e5e5ea '+p+'%)';
    }

    // ── Helper: set value + trigger event ──
    function setInputValue(input, val) {
        input.value = val;
        input.dispatchEvent(new Event('input', {bubbles:true}));
        input.dispatchEvent(new Event('change', {bubbles:true}));
    }

    // ── Slider → Map (ohne Loop) ──
    function syncMapFromSliders(){
        isUpdatingFromSlider = true;
        var z=parseFloat(slZ.value), lat=parseFloat(slLat.value), lon=parseFloat(slLon.value);
        lblZ.textContent=z.toFixed(1); lblLt.textContent=lat.toFixed(6); lblLn.textContent=lon.toFixed(6);
        lblR.textContent=parseFloat(slRot.value||'0').toFixed(1)+'°';
        coords.textContent=lat.toFixed(6)+', '+lon.toFixed(6)+' · zoom '+z.toFixed(1);
        hZ.value=z.toFixed(1); hLat.value=lat.toFixed(6); hLon.value=lon.toFixed(6); hRot.value=parseFloat(slRot.value||'0').toFixed(1);
        map.setView([lat,lon],z,{animate:false});
        crossMarker.setLatLng([lat,lon]);
        [slZ,slLat,slLon,slRot].forEach(grad);
        setTimeout(function(){ isUpdatingFromSlider=false; }, 50);
    }

    // ── Map → Slider (nur wenn NICHT durch Slider getriggert) ──
    function syncSlidersFromMap(){
        if (isUpdatingFromSlider) return;
        var c=map.getCenter(), z=map.getZoom();
        slLat.value=(+c.lat).toFixed(6); slLon.value=(+c.lng).toFixed(6); slZ.value=(+z).toFixed(1);
        syncMapFromSliders();
    }

    slZ.addEventListener('input',   syncMapFromSliders);
    slLat.addEventListener('input', syncMapFromSliders);
    slLon.addEventListener('input', syncMapFromSliders);
    slRot.addEventListener('input', syncMapFromSliders);
    map.on('moveend', syncSlidersFromMap);
    map.on('zoomend', syncSlidersFromMap);

    document.getElementById('btn-mcp-reset').addEventListener('click',function(){
        slZ.value=DEF.zoom; slLat.value=DEF.lat; slLon.value=DEF.lon; slRot.value=DEF.rot;
        syncMapFromSliders();
    });

    // ── Obstacle-Marker ──
    function makeObsIcon(name, type, rot) {
        var emoji = TYPE_ICONS[(type||'').toLowerCase()] || TYPE_ICONS['default'];
        var html = '<div style="display:flex;flex-direction:column;align-items:center;transform:rotate('+(rot||0)+'deg)">'
            + '<div style="font-size:20px;filter:drop-shadow(0 2px 4px rgba(0,0,0,.5))">'+emoji+'</div>'
            + '<span style="font-size:10px;font-weight:700;color:#fff;background:rgba(0,0,0,.6);padding:1px 4px;border-radius:3px;white-space:nowrap">'+(name||'')+'</span>'
            + '</div>';
        return L.divIcon({html:html, className:'', iconAnchor:[16,16]});
    }

    function renderObsMarkers(){
        obsMarkers.forEach(function(m){ if(m) map.removeLayer(m); });
        obsMarkers = [];
        document.querySelectorAll('.obs-row').forEach(function(row, idx){
            var lat = parseFloat(row.querySelector('.obs-lat').value);
            var lon = parseFloat(row.querySelector('.obs-lon').value);
            if (!isFinite(lat)||!isFinite(lon)||lat===0||lon===0) { obsMarkers[idx]=null; return; }
            var name = row.querySelector('input[name="name[]"]').value;
            var type = row.querySelector('input[name="type[]"]').value;
            var rot  = parseFloat(row.querySelector('input[name="rotation[]"]').value)||0;
            var mk = L.marker([lat,lon],{ icon:makeObsIcon(name,type,rot), draggable:true }).addTo(map);
            mk.on('dragend', function(e){
                var latInput = row.querySelector('.obs-lat');
                var lonInput = row.querySelector('.obs-lon');
                setInputValue(latInput, e.target.getLatLng().lat.toFixed(6));
                setInputValue(lonInput, e.target.getLatLng().lng.toFixed(6));
            });
            mk.on('click', function(e){ L.DomEvent.stopPropagation(e); setActiveRow(idx); });
            obsMarkers[idx] = mk;
        });
    }

    // ── Aktive Zeile ──
    function setActiveRow(idx){
        document.querySelectorAll('.obs-row').forEach(function(r){ r.classList.remove('obs-active-row'); });
        if (idx === activeRowIdx) { activeRowIdx = null; return; }
        activeRowIdx = idx;
        var row = document.querySelectorAll('.obs-row')[idx];
        if (!row) return;
        row.classList.add('obs-active-row');
        var lat = parseFloat(row.querySelector('.obs-lat').value);
        var lon = parseFloat(row.querySelector('.obs-lon').value);
        if (isFinite(lat) && isFinite(lon) && lat!==0) map.panTo([lat,lon]);
    }

    // ── Zeilen-Click (stopPropagation auf inputs) ──
    document.querySelectorAll('.obs-row').forEach(function(row, idx){
        row.addEventListener('click', function(e){
            if (e.target.tagName==='INPUT' || e.target.tagName==='TD') return;
            setActiveRow(idx);
        });
        row.querySelectorAll('td').forEach(function(td){
            td.addEventListener('click', function(e){
                if (e.target.tagName!=='INPUT') setActiveRow(idx);
            });
        });
        row.querySelector('.obs-lat').addEventListener('change', renderObsMarkers);
        row.querySelector('.obs-lon').addEventListener('change', renderObsMarkers);
    });

    // ── Karten-Klick ──
    map.on('click', function(e){
        if (activeRowIdx !== null) {
            var row = document.querySelectorAll('.obs-row')[activeRowIdx];
            if (!row) return;
            var lat = e.latlng.lat.toFixed(6);
            var lon = e.latlng.lng.toFixed(6);
            var latInput = row.querySelector('.obs-lat');
            var lonInput = row.querySelector('.obs-lon');
            setInputValue(latInput, lat);
            setInputValue(lonInput, lon);
            var name = row.querySelector('input[name="name[]"]').value || 'Obstacle';
            coords.innerHTML = '<strong style="color:#10b981">✅ '+name+'</strong> → '+lat+', '+lon;
            renderObsMarkers();
        }
    });

    // Init
    [slZ,slLat,slLon,slRot].forEach(grad);
    setTimeout(function(){ renderObsMarkers(); map.invalidateSize(); }, 200);

})();
</script>

<?php
function hv($v) { return htmlspecialchars((string)$v, ENT_QUOTES); }
?>
<?php include __DIR__ . '/../inc/debug.php'; ?>
</body>
</html>
