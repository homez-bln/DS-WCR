<?php
/**
 * ctrl/ds-seiten.php — Digital Signage Screen-Vorschau
 * Zwei Gruppen: 16:9 Landscape | 9:16 Portrait
 */
require_once __DIR__ . '/../inc/auth.php';
wcr_require('view_ds');

$PAGE_TITLE = 'DS-Seiten – Vorschau';

$gruppen = [
    [
        'label'   => '🖼️ 16:9 — Landscape',
        'seiten'  => [
            ['title' => 'Starter Pack',      'url' => 'https://wcr-webpage.de/starter-pack',   'icon' => '🏄', 'badge' => 'Aktiv', 'badge_color' => '#00c853'],
            ['title' => 'Wetter',            'url' => 'https://wcr-webpage.de/wetter',          'icon' => '🌤', 'badge' => 'Aktiv', 'badge_color' => '#00c853'],
            ['title' => 'Wind Map',          'url' => 'https://wcr-webpage.de/windmap',         'icon' => '💨', 'badge' => 'Aktiv', 'badge_color' => '#00c853'],
            ['title' => 'Tickets / Cable',   'url' => 'https://wcr-webpage.de/tickets',         'icon' => '🏄', 'badge' => 'Aktiv', 'badge_color' => '#00c853'],
            ['title' => 'Kaffee',            'url' => 'https://wcr-webpage.de/kaffee',          'icon' => '☕',       'badge' => 'Aktiv', 'badge_color' => '#00c853'],
            ['title' => 'Merchandise',       'url' => 'https://wcr-webpage.de/merchandise',    'icon' => '👕', 'badge' => 'Aktiv', 'badge_color' => '#00c853'],
            ['title' => 'Stand Up Paddle',   'url' => 'https://wcr-webpage.de/sup',             'icon' => '🏄', 'badge' => 'Aktiv', 'badge_color' => '#00c853'],
            ['title' => 'Park',              'url' => 'https://wcr-webpage.de/park',            'icon' => '🌊', 'badge' => 'Aktiv', 'badge_color' => '#00c853'],
            ['title' => 'Obstacles',         'url' => 'https://wake-and-camp.de/obst/',         'icon' => '🤸', 'badge' => '500',   'badge_color' => '#ff3b30'],
            ['title' => 'Kino',              'url' => 'https://wcr-webpage.de/kino',            'icon' => '🎦', 'badge' => '404',   'badge_color' => '#ff9500'],
        ],
        'portrait' => false,
    ],
    [
        'label'   => '📱 Listen',
        'seiten'  => [
            ['title' => 'Cable Preisliste',  'url' => 'https://wcr-webpage.de/cable-list',      'icon' => '🎫', 'badge' => 'Aktiv', 'badge_color' => '#00c853'],
            ['title' => 'Camping Preise',    'url' => 'https://wcr-webpage.de/camping-list',    'icon' => '⛺',       'badge' => 'Aktiv', 'badge_color' => '#00c853'],
            ['title' => 'Eiskarte',          'url' => 'https://wcr-webpage.de/eis',             'icon' => '🧈', 'badge' => 'Aktiv', 'badge_color' => '#00c853'],
            ['title' => 'Getränke',          'url' => 'https://wcr-webpage.de/getraenke',     'icon' => '🍺', 'badge' => 'Aktiv', 'badge_color' => '#00c853'],
            ['title' => 'Softdrinks',        'url' => 'https://wcr-webpage.de/soft',            'icon' => '🥤', 'badge' => 'Aktiv', 'badge_color' => '#00c853'],
            ['title' => 'Speisekarte',       'url' => 'https://wcr-webpage.de/essen',           'icon' => '🍔', 'badge' => 'Aktiv', 'badge_color' => '#00c853'],
        ],
        'portrait' => false,
    ],
    [
        'label'   => '📱 Produkt-Table',
        'seiten'  => [
            ['title' => 'Burger Table',  'url' => 'https://wcr-webpage.de/produkt-table',      'icon' => '🎫', 'badge' => 'Aktiv', 'badge_color' => '#00c853'],
            
        ],
        'portrait' => false,
    ],





    [
        'label'   => '📱 9:16 — Portrait',
        'seiten'  => [
            ['title' => 'Öffnungszeiten Story', 'url' => 'https://wcr-webpage.de/oeffnungszeiten-story', 'icon' => '🕐', 'badge' => 'Aktiv', 'badge_color' => '#00c853'],
            ['title' => 'Instagram Grid',       'url' => 'https://wcr-webpage.de/insta',                  'icon' => '📸', 'badge' => 'Aktiv', 'badge_color' => '#00c853'],
            ['title' => 'Instagram Reels',      'url' => 'https://wcr-webpage.de/insta-reel',             'icon' => '🎥', 'badge' => 'Aktiv', 'badge_color' => '#00c853'],
            ['title' => 'Cable-Park',                 'url' => 'https://wcr-webpage.de/park-portrait',                   'icon' => '🌊', 'badge' => 'Aktiv', 'badge_color' => '#00c853'],
        ],
        'portrait' => true,
    ],
];

// Flache Liste mit globalen Indizes für JS
$alle_seiten = [];
foreach ($gruppen as &$g) {
    foreach ($g['seiten'] as &$s) {
        $s['_idx']     = count($alle_seiten);
        $s['portrait'] = $g['portrait'];
        $alle_seiten[] = &$s;
    }
}
unset($g, $s);
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>Verwaltung: <?= htmlspecialchars($PAGE_TITLE) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    .ds-group { margin-bottom: 40px; }
    .ds-group-label {
      font-size: .8rem; font-weight: 800; text-transform: uppercase;
      letter-spacing: 1.5px; color: #6b7280;
      padding: 0 0 10px; border-bottom: 2px solid #e5e7eb; margin-bottom: 16px;
      display: flex; align-items: center; gap: 8px;
    }
    .ds-group-label span.cnt {
      font-size: .7rem; font-weight: 600;
      background: #f3f4f6; color: #9ca3af;
      border: 1px solid #e5e7eb; border-radius: 20px;
      padding: 1px 8px; letter-spacing: 0; text-transform: none;
    }

    /* Landscape-Grid: breitere Karten */
    .ds-gallery-landscape {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(420px, 1fr));
      gap: 20px;
      align-items: start;
    }
    /* Portrait-Grid: schmälere Karten */
    .ds-gallery-portrait {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(240px, 280px));
      gap: 20px;
      align-items: start;
    }

    .ds-card { background:#fff; border:1px solid #e5e7eb; border-radius:14px; overflow:hidden; transition:transform .2s,box-shadow .2s; }
    .ds-card:hover { transform:translateY(-3px); box-shadow:0 10px 32px rgba(0,0,0,.1); }
    .ds-card-header { display:flex; align-items:center; justify-content:space-between; padding:10px 14px; border-bottom:1px solid #e5e7eb; background:#f9fafb; gap:8px; flex-wrap:wrap; }
    .ds-card-title  { display:flex; align-items:center; gap:7px; font-size:.88rem; font-weight:700; color:#111; min-width:0; }
    .ds-card-actions { display:flex; align-items:center; gap:5px; flex-shrink:0; }
    .ds-badge { display:inline-flex; align-items:center; gap:4px; font-size:.65rem; font-weight:600; padding:2px 8px; border-radius:20px; border:1px solid transparent; text-transform:uppercase; letter-spacing:.5px; white-space:nowrap; }
    .ds-dot { width:5px; height:5px; border-radius:50%; flex-shrink:0; animation:ds-blink 2s infinite; }
    @keyframes ds-blink { 0%,100%{opacity:1} 50%{opacity:.3} }
    .ds-btn { display:inline-flex; align-items:center; gap:4px; background:#f3f4f6; border:1px solid #e5e7eb; border-radius:7px; color:#555; font-size:.75rem; padding:3px 8px; cursor:pointer; text-decoration:none; transition:background .15s; white-space:nowrap; }
    .ds-btn:hover { background:#e5e7eb; color:#111; }
    .ds-btn.primary { background:#e8f5ff; border-color:#bdd9f5; color:#1a6fb5; }
    .ds-btn.primary:hover { background:#d0eaff; }

    .ds-frame-wrap { position:relative; width:100%; background:#111; overflow:hidden; min-height:40px; }
    .ds-frame-wrap iframe { display:block; position:absolute; top:0; left:0; border:none; opacity:0; transition:opacity .5s; pointer-events:none; transform-origin:top left; }
    .ds-frame-wrap iframe.loaded { opacity:1; }
    .ds-spin-wrap { position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:8px; font-size:.75rem; color:#555; z-index:2; background:#1a1a2e; transition:opacity .3s; }
    .ds-spin-wrap.hidden { opacity:0; pointer-events:none; }
    .ds-spinner { width:24px; height:24px; border:2px solid rgba(255,255,255,.1); border-top-color:#3b82f6; border-radius:50%; animation:ds-spin .75s linear infinite; }
    @keyframes ds-spin { to{transform:rotate(360deg)} }

    .ds-card-footer { display:flex; align-items:center; justify-content:space-between; padding:6px 14px; border-top:1px solid #e5e7eb; background:#f9fafb; }
    .ds-url  { font-size:.62rem; color:#9ca3af; font-family:monospace; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:65%; }
    .ds-time { font-size:.62rem; color:#9ca3af; white-space:nowrap; }
  </style>
</head>
<body class="bo">
<?php include __DIR__ . '/../inc/menu.php'; ?>

<div class="header-controls">
  <h1>🖥 <?= htmlspecialchars($PAGE_TITLE) ?></h1>
  <button class="btn-upload" onclick="dsReloadAll()">&#x21BA; Alle neu laden</button>
</div>

<?php foreach ($gruppen as $g):
  $portrait  = $g['portrait'];
  $gridClass = $portrait ? 'ds-gallery-portrait' : 'ds-gallery-landscape';
  $nW        = $portrait ? 1080 : 1920;
  $nH        = $portrait ? 1920 : 1080;
?>
<div class="ds-group">
  <div class="ds-group-label">
    <?= htmlspecialchars($g['label']) ?>
    <span class="cnt"><?= count($g['seiten']) ?></span>
  </div>
  <div class="<?= $gridClass ?>">
    <?php foreach ($g['seiten'] as $s):
      $i = $s['_idx'];
    ?>
    <div class="ds-card" id="ds-card-<?= $i ?>">
      <div class="ds-card-header">
        <div class="ds-card-title">
          <span><?= htmlspecialchars($s['icon']) ?></span>
          <span><?= htmlspecialchars($s['title']) ?></span>
        </div>
        <div class="ds-card-actions">
          <span class="ds-badge" style="background:<?= htmlspecialchars($s['badge_color']) ?>22;color:<?= htmlspecialchars($s['badge_color']) ?>;border-color:<?= htmlspecialchars($s['badge_color']) ?>55;">
            <span class="ds-dot" style="background:<?= htmlspecialchars($s['badge_color']) ?>"></span>
            <?= htmlspecialchars($s['badge']) ?>
          </span>
          <button class="ds-btn" onclick="dsReload(<?= $i ?>)">&#x21BA;</button>
          <a class="ds-btn primary" href="<?= htmlspecialchars($s['url']) ?>" target="_blank">↗ Öffnen</a>
        </div>
      </div>

      <div class="ds-frame-wrap" id="ds-wrap-<?= $i ?>">
        <div class="ds-spin-wrap" id="ds-spin-<?= $i ?>">
          <div class="ds-spinner"></div>
          <span>Lädt…</span>
        </div>
        <iframe
          id="ds-frame-<?= $i ?>"
          data-src="<?= htmlspecialchars($s['url']) ?>"
          data-nw="<?= $nW ?>"
          data-nh="<?= $nH ?>"
          style="width:<?= $nW ?>px;height:<?= $nH ?>px;"
          scrolling="no"
        ></iframe>
      </div>

      <div class="ds-card-footer">
        <span class="ds-url"><?= htmlspecialchars($s['url']) ?></span>
        <span class="ds-time" id="ds-time-<?= $i ?>">-</span>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endforeach; ?>

<script>
var dsStartTimes = {};

function dsScaleWrap(wrap) {
    var iframe = wrap.querySelector('iframe');
    if (!iframe) return;
    var nW    = parseInt(iframe.dataset.nw, 10) || 1920;
    var nH    = parseInt(iframe.dataset.nh, 10) || 1080;
    var scale = wrap.offsetWidth / nW;
    iframe.style.transform = 'scale(' + scale + ')';
    wrap.style.height      = Math.round(nH * scale) + 'px';
}

var ro = new ResizeObserver(function(entries) {
    entries.forEach(function(e) { dsScaleWrap(e.target); });
});

function dsLoaded(idx) {
    var frame = document.getElementById('ds-frame-' + idx);
    var spin  = document.getElementById('ds-spin-'  + idx);
    var time  = document.getElementById('ds-time-'  + idx);
    if (!frame || !spin) return;
    frame.classList.add('loaded');
    spin.classList.add('hidden');
    if (time && dsStartTimes[idx]) {
        var ms = Date.now() - dsStartTimes[idx];
        time.textContent = '\u2713 ' + (ms / 1000).toFixed(1) + 's';
        time.style.color = ms < 2000 ? '#16a34a' : ms < 5000 ? '#d97706' : '#dc2626';
    }
    var wrap = document.getElementById('ds-wrap-' + idx);
    if (wrap) dsScaleWrap(wrap);
}

function dsReload(idx) {
    var frame = document.getElementById('ds-frame-' + idx);
    var spin  = document.getElementById('ds-spin-'  + idx);
    var time  = document.getElementById('ds-time-'  + idx);
    if (!frame) return;
    frame.classList.remove('loaded');
    if (spin) { spin.classList.remove('hidden'); spin.innerHTML = '<div class="ds-spinner"></div><span>Lädt…</span>'; }
    if (time) { time.textContent = '-'; time.style.color = ''; }
    dsStartTimes[idx] = Date.now();
    frame.onload = function() { dsLoaded(idx); };
    frame.src = frame.dataset.src + '?t=' + Date.now();
}

function dsReloadAll() {
    for (var i = 0; i < <?= count($alle_seiten) ?>; i++) dsReload(i);
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.ds-frame-wrap').forEach(function(wrap) {
        ro.observe(wrap);
        dsScaleWrap(wrap);
    });
    setTimeout(function() {
        document.querySelectorAll('.ds-frame-wrap iframe').forEach(function(frame) {
            var idx = parseInt(frame.id.replace('ds-frame-', ''), 10);
            dsStartTimes[idx] = Date.now();
            frame.onload = function() { dsLoaded(idx); };
            frame.src = frame.dataset.src;
        });
    }, 200);
});
</script>

<?php include __DIR__ . '/../inc/debug.php'; ?>
</body>
</html>
