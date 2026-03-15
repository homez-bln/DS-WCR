<?php
/**
 * ctrl/ds-seiten.php — DS-Seiten Vorschau + Aktivierungssteuerung
 * SECURITY: wcr_require('view_ds') + wcr_verify_csrf() bei POST
 * v2: Live-DB-Status pro Seite, an/ausschalten per Regel (Typ ODER Nummern)
 */
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';

wcr_require('view_ds');

$PAGE_TITLE = 'DS-Seiten – Vorschau & Steuerung';

// ── Whitelist ──
$ALLOWED_TABLES = ['food','drinks','cable','camping','extra','ice'];

// ── Regeln laden (gespeichert in JSON-Datei neben auth.php) ──
$RULES_FILE = __DIR__ . '/../inc/ds-rules.json';
function wcr_ds_load_rules(string $file): array {
    if (!file_exists($file)) return [];
    $raw = json_decode(file_get_contents($file), true);
    return is_array($raw) ? $raw : [];
}
function wcr_ds_save_rules(string $file, array $rules): void {
    file_put_contents($file, json_encode($rules, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

$rules = wcr_ds_load_rules($RULES_FILE);

// ── Hilfsfunktion: Live DB-Status einer Regel prüfen ──
function wcr_ds_check_status(array $rule, PDO $pdo, array $allowed_tables): array {
    $tables  = array_values(array_intersect((array)($rule['tables'] ?? []), $allowed_tables));
    $typ     = trim($rule['typ'] ?? '');
    $ids_raw = trim($rule['ids'] ?? '');
    $mode    = ($rule['mode'] ?? 'any') === 'all' ? 'all' : 'any';

    // Keine Regel → immer aktiv
    if (empty($tables) && $typ === '' && $ids_raw === '') {
        return ['active' => true, 'reason' => 'no_rule', 'db_ok' => true];
    }

    $check_tables = !empty($tables) ? $tables : $allowed_tables;

    try {
        // ── IDs-Modus ──
        if ($ids_raw !== '') {
            $ids = array_filter(array_map('intval', explode(',', $ids_raw)));
            if (empty($ids)) return ['active' => true, 'reason' => 'ids_empty', 'db_ok' => true];
            $found = 0;
            $total = count($ids);
            foreach ($ids as $id) {
                foreach ($check_tables as $tbl) {
                    $stmt = $pdo->prepare("SELECT stock FROM `{$tbl}` WHERE nummer = ? LIMIT 1");
                    $stmt->execute([$id]);
                    $val = $stmt->fetchColumn();
                    if ($val !== false && (int)$val > 0) { $found++; break; }
                }
            }
            $active = ($mode === 'all') ? ($found === $total) : ($found > 0);
            return ['active' => $active, 'reason' => 'ids_'.$mode.':'.$found.'/'.$total, 'db_ok' => true];
        }

        // ── Typ-Modus ──
        if ($typ !== '') {
            foreach ($check_tables as $tbl) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$tbl}` WHERE typ = ? AND stock > 0");
                $stmt->execute([$typ]);
                if ((int)$stmt->fetchColumn() > 0) {
                    return ['active' => true, 'reason' => 'typ_active:'.$tbl.':'.$typ, 'db_ok' => true];
                }
            }
            return ['active' => false, 'reason' => 'typ_none_active:'.$typ, 'db_ok' => true];
        }

        // ── Nur Tabellen-Modus ──
        foreach ($check_tables as $tbl) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM `{$tbl}` WHERE stock > 0");
            if ((int)$stmt->fetchColumn() > 0) {
                return ['active' => true, 'reason' => 'table_any:'.$tbl, 'db_ok' => true];
            }
        }
        return ['active' => false, 'reason' => 'table_none_active', 'db_ok' => true];

    } catch (Exception $e) {
        return ['active' => true, 'reason' => 'db_error_fail_open', 'db_ok' => false];
    }
}

// ── POST: Regel speichern ──
$save_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wcr_ds_save'])) {
    wcr_require('view_ds'); // nochmal explizit
    wcr_verify_csrf();      // 403 + exit bei Fehler

    $slug = preg_replace('/[^a-z0-9_\-]/', '', strtolower($_POST['page_slug'] ?? ''));
    if ($slug !== '') {
        $rules[$slug] = [
            'override' => in_array($_POST['override'] ?? 'auto', ['auto','force_on','force_off']) ? $_POST['override'] : 'auto',
            'tables'   => array_values(array_intersect((array)($_POST['tables'] ?? []), $ALLOWED_TABLES)),
            'typ'      => trim(strip_tags($_POST['typ'] ?? '')),
            'ids'      => trim(preg_replace('/[^0-9,]/', '', $_POST['ids'] ?? '')),
            'mode'     => in_array($_POST['mode'] ?? 'any', ['any','all']) ? $_POST['mode'] : 'any',
        ];
        wcr_ds_save_rules($RULES_FILE, $rules);
        $save_msg = '\u2705 Gespeichert f\u00fcr Seite: ' . htmlspecialchars($slug);
    }
}

// ── Seiten-Konfiguration (statisch + dynamisch aus Regeln) ──
$gruppen = [
    [
        'label'   => '\ud83d\uddbc\ufe0f 16:9 \u2014 Landscape',
        'portrait' => false,
        'seiten'  => [
            ['title' => 'Starter Pack',         'slug' => 'starter-pack',          'url' => 'https://wcr-webpage.de/starter-pack',          'icon' => '\ud83c\udfc4'],
            ['title' => 'Wetter',               'slug' => 'wetter',                'url' => 'https://wcr-webpage.de/wetter',                'icon' => '\ud83c\udf24'],
            ['title' => 'Wind Map',             'slug' => 'windmap',               'url' => 'https://wcr-webpage.de/windmap',               'icon' => '\ud83d\udca8'],
            ['title' => 'Tickets / Cable',      'slug' => 'tickets',               'url' => 'https://wcr-webpage.de/tickets',               'icon' => '\ud83c\udfc4'],
            ['title' => 'Kaffee',               'slug' => 'kaffee',                'url' => 'https://wcr-webpage.de/kaffee',                'icon' => '\u2615'],
            ['title' => 'Merchandise',          'slug' => 'merchandise',           'url' => 'https://wcr-webpage.de/merchandise',           'icon' => '\ud83d\udc55'],
            ['title' => 'Stand Up Paddle',      'slug' => 'sup',                   'url' => 'https://wcr-webpage.de/sup',                   'icon' => '\ud83c\udfc4'],
            ['title' => 'Park',                 'slug' => 'park',                  'url' => 'https://wcr-webpage.de/park',                  'icon' => '\ud83c\udf0a'],
            ['title' => 'Obstacles',            'slug' => 'obstacles',             'url' => 'https://wake-and-camp.de/obst/',               'icon' => '\ud83e\udd38'],
            ['title' => 'Kino',                 'slug' => 'kino',                  'url' => 'https://wcr-webpage.de/kino',                  'icon' => '\ud83c\udfa6'],
        ],
    ],
    [
        'label'   => '\ud83d\udcf1 Listen',
        'portrait' => false,
        'seiten'  => [
            ['title' => 'Cable Preisliste',     'slug' => 'cable-list',            'url' => 'https://wcr-webpage.de/cable-list',            'icon' => '\ud83c\udfab'],
            ['title' => 'Camping Preise',       'slug' => 'camping-list',          'url' => 'https://wcr-webpage.de/camping-list',          'icon' => '\u26fa'],
            ['title' => 'Eiskarte',             'slug' => 'eis',                   'url' => 'https://wcr-webpage.de/eis',                   'icon' => '\ud83c\udf66'],
            ['title' => 'Getr\u00e4nke',        'slug' => 'getraenke',             'url' => 'https://wcr-webpage.de/getraenke',             'icon' => '\ud83c\udf7a'],
            ['title' => 'Softdrinks',           'slug' => 'soft',                  'url' => 'https://wcr-webpage.de/soft',                  'icon' => '\ud83e\udd64'],
            ['title' => 'Speisekarte',          'slug' => 'essen',                 'url' => 'https://wcr-webpage.de/essen',                 'icon' => '\ud83c\udf54'],
        ],
    ],
    [
        'label'   => '\ud83d\udcf1 Produkt-Spotlight',
        'portrait' => false,
        'seiten'  => [
            ['title' => 'Burger Table',         'slug' => 'produkt-table',         'url' => 'https://wcr-webpage.de/produkt-table',         'icon' => '\ud83c\udf54'],
        ],
    ],
    [
        'label'   => '\ud83d\udcf1 9:16 \u2014 Portrait',
        'portrait' => true,
        'seiten'  => [
            ['title' => '\u00d6ffnungszeiten Story', 'slug' => 'oeffnungszeiten-story', 'url' => 'https://wcr-webpage.de/oeffnungszeiten-story', 'icon' => '\ud83d\udd50'],
            ['title' => 'Instagram Grid',        'slug' => 'insta',                 'url' => 'https://wcr-webpage.de/insta',                 'icon' => '\ud83d\udcf8'],
            ['title' => 'Instagram Reels',       'slug' => 'insta-reel',            'url' => 'https://wcr-webpage.de/insta-reel',            'icon' => '\ud83c\udfa5'],
            ['title' => 'Cable-Park Portrait',   'slug' => 'park-portrait',         'url' => 'https://wcr-webpage.de/park-portrait',         'icon' => '\ud83c\udf0a'],
        ],
    ],
];

// Globale Index-Liste
$alle_seiten = [];
foreach ($gruppen as &$g) {
    foreach ($g['seiten'] as &$s) {
        $s['_idx']     = count($alle_seiten);
        $s['portrait'] = $g['portrait'];
        // Regel + Live-Status anhängen
        $slug = $s['slug'] ?? '';
        $rule = $rules[$slug] ?? ['override'=>'auto','tables'=>[],'typ'=>'','ids'=>'','mode'=>'any'];
        $s['rule']   = $rule;
        $s['status'] = wcr_ds_check_status($rule, $pdo, $ALLOWED_TABLES);
        $alle_seiten[] = &$s;
    }
}
unset($g, $s);

// Typ-Vorschläge für Datalist (alle eindeutigen Typen aus DB)
$typen_all = [];
foreach ($ALLOWED_TABLES as $tbl) {
    try {
        $rows = $pdo->query("SELECT DISTINCT typ FROM `{$tbl}` WHERE typ IS NOT NULL AND typ != '' ORDER BY typ")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($rows as $t) $typen_all[] = trim($t);
    } catch (Exception $e) {}
}
$typen_all = array_unique($typen_all);
sort($typen_all);
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>Verwaltung: <?= htmlspecialchars($PAGE_TITLE) ?></title>
  <style>
    .ds-group { margin-bottom: 40px; }
    .ds-group-label { font-size:.8rem;font-weight:800;text-transform:uppercase;letter-spacing:1.5px;color:#6b7280;padding:0 0 10px;border-bottom:2px solid #e5e7eb;margin-bottom:16px;display:flex;align-items:center;gap:8px; }
    .ds-group-label span.cnt { font-size:.7rem;font-weight:600;background:#f3f4f6;color:#9ca3af;border:1px solid #e5e7eb;border-radius:20px;padding:1px 8px;letter-spacing:0;text-transform:none; }
    .ds-gallery-landscape { display:grid;grid-template-columns:repeat(auto-fill,minmax(420px,1fr));gap:20px;align-items:start; }
    .ds-gallery-portrait  { display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,280px));gap:20px;align-items:start; }
    .ds-card { background:#fff;border:1px solid #e5e7eb;border-radius:14px;overflow:hidden;transition:transform .2s,box-shadow .2s; }
    .ds-card:hover { transform:translateY(-3px);box-shadow:0 10px 32px rgba(0,0,0,.1); }
    .ds-card.ds-inactive { opacity:.7;border-color:#fca5a5; }
    .ds-card-header { display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border-bottom:1px solid #e5e7eb;background:#f9fafb;gap:8px;flex-wrap:wrap; }
    .ds-card-title  { display:flex;align-items:center;gap:7px;font-size:.88rem;font-weight:700;color:#111;min-width:0; }
    .ds-card-actions { display:flex;align-items:center;gap:5px;flex-shrink:0; }
    .ds-badge { display:inline-flex;align-items:center;gap:4px;font-size:.65rem;font-weight:600;padding:2px 8px;border-radius:20px;border:1px solid transparent;text-transform:uppercase;letter-spacing:.5px;white-space:nowrap; }
    .ds-dot { width:5px;height:5px;border-radius:50%;flex-shrink:0;animation:ds-blink 2s infinite; }
    @keyframes ds-blink{0%,100%{opacity:1}50%{opacity:.3}}
    .ds-btn { display:inline-flex;align-items:center;gap:4px;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:7px;color:#555;font-size:.75rem;padding:3px 8px;cursor:pointer;text-decoration:none;transition:background .15s;white-space:nowrap; }
    .ds-btn:hover { background:#e5e7eb;color:#111; }
    .ds-btn.primary { background:#e8f5ff;border-color:#bdd9f5;color:#1a6fb5; }
    .ds-btn.primary:hover { background:#d0eaff; }
    .ds-btn.edit-btn { background:#fff8e1;border-color:#f0c040;color:#b45309; }
    .ds-btn.edit-btn:hover { background:#fff3c4; }
    .ds-frame-wrap { position:relative;width:100%;background:#111;overflow:hidden;min-height:40px; }
    .ds-frame-wrap iframe { display:block;position:absolute;top:0;left:0;border:none;opacity:0;transition:opacity .5s;pointer-events:none;transform-origin:top left; }
    .ds-frame-wrap iframe.loaded { opacity:1; }
    .ds-spin-wrap { position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;font-size:.75rem;color:#555;z-index:2;background:#1a1a2e;transition:opacity .3s; }
    .ds-spin-wrap.hidden { opacity:0;pointer-events:none; }
    .ds-spinner { width:24px;height:24px;border:2px solid rgba(255,255,255,.1);border-top-color:#3b82f6;border-radius:50%;animation:ds-spin .75s linear infinite; }
    @keyframes ds-spin{to{transform:rotate(360deg)}}
    .ds-card-footer { display:flex;align-items:center;justify-content:space-between;padding:6px 14px;border-top:1px solid #e5e7eb;background:#f9fafb; }
    .ds-url  { font-size:.62rem;color:#9ca3af;font-family:monospace;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:65%; }
    .ds-time { font-size:.62rem;color:#9ca3af;white-space:nowrap; }
    /* Regel-Panel */
    .rule-panel { padding:12px 14px;border-top:2px dashed #e5e7eb;background:#fafafa;display:none; }
    .rule-panel.open { display:block; }
    .rule-panel form { display:flex;flex-direction:column;gap:8px; }
    .rule-row { display:flex;align-items:center;gap:8px;flex-wrap:wrap;font-size:.8rem; }
    .rule-row label { font-weight:600;min-width:70px;color:#374151; }
    .rule-row input[type=text],.rule-row select { border:1px solid #d1d5db;border-radius:6px;padding:4px 8px;font-size:.8rem;flex:1;min-width:120px; }
    .rule-cb-group { display:flex;flex-wrap:wrap;gap:6px; }
    .rule-cb-group label { font-weight:400;min-width:auto;display:flex;align-items:center;gap:4px; }
    .rule-status { font-size:.75rem;padding:4px 8px;border-radius:6px;font-weight:600; }
    .rule-status.active   { background:#d1fae5;color:#065f46; }
    .rule-status.inactive { background:#fee2e2;color:#991b1b; }
    .rule-status.dberr    { background:#fef3c7;color:#92400e; }
    .rule-save-btn { align-self:flex-end;background:#0071e3;color:#fff;border:none;border-radius:8px;padding:5px 16px;font-size:.8rem;font-weight:600;cursor:pointer; }
    .rule-save-btn:hover { background:#005bb5; }
    .save-notice { padding:10px 20px;margin-bottom:16px;background:#d1fae5;border:1px solid #6ee7b7;border-radius:8px;color:#065f46;font-weight:600; }
  </style>
</head>
<body class="bo" data-csrf="<?= wcr_csrf_attr() ?>">
<?php include __DIR__ . '/../inc/menu.php'; ?>

<div class="header-controls">
  <h1>\ud83d\udda5 <?= htmlspecialchars($PAGE_TITLE) ?></h1>
  <button class="btn-upload" onclick="dsReloadAll()">&#x21BA; Alle neu laden</button>
</div>

<?php if ($save_msg): ?>
<div class="save-notice"><?= $save_msg ?></div>
<?php endif; ?>

<?php foreach ($gruppen as $g):
  $portrait  = $g['portrait'];
  $gridClass = $portrait ? 'ds-gallery-portrait' : 'ds-gallery-landscape';
  $nW = $portrait ? 1080 : 1920;
  $nH = $portrait ? 1920 : 1080;
?>
<div class="ds-group">
  <div class="ds-group-label">
    <?= htmlspecialchars($g['label']) ?>
    <span class="cnt"><?= count($g['seiten']) ?></span>
  </div>
  <div class="<?= $gridClass ?>">
    <?php foreach ($g['seiten'] as $s):
      $i      = $s['_idx'];
      $status = $s['status'];
      $rule   = $s['rule'];
      $ov     = $rule['override'] ?? 'auto';
      $effektiv = ($ov === 'force_on') ? true : (($ov === 'force_off') ? false : $status['active']);
      $badgeColor = $effektiv ? '#00c853' : '#ff3b30';
      $badgeText  = $effektiv ? 'Aktiv' : 'Inaktiv';
    ?>
    <div class="ds-card <?= !$effektiv ? 'ds-inactive' : '' ?>" id="ds-card-<?= $i ?>">
      <div class="ds-card-header">
        <div class="ds-card-title">
          <span><?= htmlspecialchars($s['icon']) ?></span>
          <span><?= htmlspecialchars($s['title']) ?></span>
        </div>
        <div class="ds-card-actions">
          <span class="ds-badge" style="background:<?= $badgeColor ?>22;color:<?= $badgeColor ?>;border-color:<?= $badgeColor ?>55;">
            <span class="ds-dot" style="background:<?= $badgeColor ?>"></span>
            <?= htmlspecialchars($badgeText) ?>
          </span>
          <?php if (!$status['db_ok']): ?>
          <span title="DB-Fehler" style="font-size:.8rem;">⚠️</span>
          <?php endif; ?>
          <button class="ds-btn edit-btn" onclick="toggleRule(<?= $i ?>)">&#9998; Regel</button>
          <button class="ds-btn" onclick="dsReload(<?= $i ?>)">&#x21BA;</button>
          <a class="ds-btn primary" href="<?= htmlspecialchars($s['url']) ?>" target="_blank">↗ Öffnen</a>
        </div>
      </div>

      <!-- Regel-Panel -->
      <div class="rule-panel" id="rule-panel-<?= $i ?>">
        <form method="post">
          <?= wcr_csrf_field() ?>
          <input type="hidden" name="wcr_ds_save" value="1">
          <input type="hidden" name="page_slug" value="<?= htmlspecialchars($s['slug']) ?>">

          <div class="rule-row">
            <label>Override</label>
            <select name="override">
              <option value="auto"      <?= ($ov==='auto')      ? 'selected':'' ?>>Auto (DB-Check)</option>
              <option value="force_on"  <?= ($ov==='force_on')  ? 'selected':'' ?>>Force ON</option>
              <option value="force_off" <?= ($ov==='force_off') ? 'selected':'' ?>>Force OFF</option>
            </select>
          </div>

          <div class="rule-row">
            <label>Tabellen</label>
            <div class="rule-cb-group">
              <?php foreach ($ALLOWED_TABLES as $tbl): ?>
              <label>
                <input type="checkbox" name="tables[]" value="<?= $tbl ?>"
                  <?= in_array($tbl,(array)($rule['tables']??[]),true)?'checked':'' ?>>
                <?= $tbl ?>
              </label>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="rule-row">
            <label>Typ</label>
            <input type="text" name="typ" value="<?= htmlspecialchars($rule['typ']??'') ?>"
                   list="typen-list" placeholder="z.B. Burger, Softdrink …">
            <datalist id="typen-list">
              <?php foreach($typen_all as $tv): ?>
              <option value="<?= htmlspecialchars($tv) ?>">
              <?php endforeach; ?>
            </datalist>
          </div>

          <div class="rule-row">
            <label>IDs</label>
            <input type="text" name="ids" value="<?= htmlspecialchars($rule['ids']??'') ?>"
                   placeholder="3010,3089,3162">
          </div>

          <div class="rule-row">
            <label>Mode</label>
            <select name="mode">
              <option value="any" <?= (($rule['mode']??'any')==='any')?'selected':'' ?>>any – mind. 1 aktiv</option>
              <option value="all" <?= (($rule['mode']??'any')==='all')?'selected':'' ?>>all – alle müssen aktiv sein</option>
            </select>
          </div>

          <div class="rule-row">
            <span class="rule-status <?= !$status['db_ok']?'dberr':($status['active']?'active':'inactive') ?>">
              <?= $status['active']?'✅':'⛔' ?> <?= htmlspecialchars($status['reason']) ?>
            </span>
          </div>

          <button type="submit" class="rule-save-btn">Speichern</button>
        </form>
      </div>

      <div class="ds-frame-wrap" id="ds-wrap-<?= $i ?>">
        <div class="ds-spin-wrap" id="ds-spin-<?= $i ?>">
          <div class="ds-spinner"></div>
          <span>Lädt…</span>
        </div>
        <iframe id="ds-frame-<?= $i ?>"
          data-src="<?= htmlspecialchars($s['url']) ?>"
          data-nw="<?= $nW ?>" data-nh="<?= $nH ?>"
          style="width:<?= $nW ?>px;height:<?= $nH ?>px;"
          scrolling="no"></iframe>
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
function dsScaleWrap(wrap){
  var iframe=wrap.querySelector('iframe');if(!iframe)return;
  var nW=parseInt(iframe.dataset.nw,10)||1920,nH=parseInt(iframe.dataset.nh,10)||1080;
  var scale=wrap.offsetWidth/nW;
  iframe.style.transform='scale('+scale+')';
  wrap.style.height=Math.round(nH*scale)+'px';
}
var ro=new ResizeObserver(function(e){e.forEach(function(e){dsScaleWrap(e.target);});});
function dsLoaded(idx){
  var f=document.getElementById('ds-frame-'+idx),sp=document.getElementById('ds-spin-'+idx),ti=document.getElementById('ds-time-'+idx);
  if(!f||!sp)return;
  f.classList.add('loaded');sp.classList.add('hidden');
  if(ti&&dsStartTimes[idx]){var ms=Date.now()-dsStartTimes[idx];ti.textContent='✓ '+(ms/1000).toFixed(1)+'s';ti.style.color=ms<2000?'#16a34a':ms<5000?'#d97706':'#dc2626';}
  var w=document.getElementById('ds-wrap-'+idx);if(w)dsScaleWrap(w);
}
function dsReload(idx){
  var f=document.getElementById('ds-frame-'+idx),sp=document.getElementById('ds-spin-'+idx),ti=document.getElementById('ds-time-'+idx);
  if(!f)return;
  f.classList.remove('loaded');
  if(sp){sp.classList.remove('hidden');sp.innerHTML='<div class="ds-spinner"></div><span>Lädt…</span>';}
  if(ti){ti.textContent='-';ti.style.color='';}
  dsStartTimes[idx]=Date.now();
  f.onload=function(){dsLoaded(idx);};
  f.src=f.dataset.src+'?t='+Date.now();
}
function dsReloadAll(){for(var i=0;i<<?= count($alle_seiten) ?>;i++)dsReload(i);}
function toggleRule(idx){
  var p=document.getElementById('rule-panel-'+idx);
  if(p)p.classList.toggle('open');
}
document.addEventListener('DOMContentLoaded',function(){
  document.querySelectorAll('.ds-frame-wrap').forEach(function(w){ro.observe(w);dsScaleWrap(w);});
  setTimeout(function(){
    document.querySelectorAll('.ds-frame-wrap iframe').forEach(function(f){
      var idx=parseInt(f.id.replace('ds-frame-',''),10);
      dsStartTimes[idx]=Date.now();
      f.onload=function(){dsLoaded(idx);};
      f.src=f.dataset.src;
    });
  },200);
});
</script>

<?php include __DIR__ . '/../inc/debug.php'; ?>
</body>
</html>
