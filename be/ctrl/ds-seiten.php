<?php
/**
 * ctrl/ds-seiten.php — DS-Seiten Vorschau + Aktivierungssteuerung
 * v4: WP REST API statt direktem DB-Zugriff
 */
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';

wcr_require('view_ds');

$PAGE_TITLE  = 'DS-Seiten – Vorschau & Steuerung';
$DS_SLUG_PRE = 'ds-';
$SITE_URL    = 'https://wcr-webpage.de';
$WP_API_BASE = $SITE_URL . '/wp-json/wp/v2';

$ALLOWED_TABLES = ['food','drinks','cable','camping','extra','ice'];
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

// ── WP-Seiten via REST API laden ──
function wcr_ds_load_wp_pages_api(string $api_base, string $slug_pre, string $site_url): array
{
    $url = $api_base . '/pages?per_page=100&status=publish&orderby=menu_order&order=asc&_fields=id,slug,title,meta,acf';

    $ctx = stream_context_create([
        'http' => [
            'timeout'       => 5,
            'ignore_errors' => true,
            'header'        => "Accept: application/json\r\n",
        ]
    ]);

    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) return ['pages' => [], 'error' => 'HTTP-Anfrage fehlgeschlagen (file_get_contents)'];

    // HTTP-Statuscode prüfen
    $http_code = 0;
    if (!empty($http_response_header)) {
        preg_match('/HTTP\/\S+\s+(\d+)/', $http_response_header[0], $m);
        $http_code = (int)($m[1] ?? 0);
    }
    if ($http_code !== 200) {
        return ['pages' => [], 'error' => "REST API HTTP {$http_code}", 'raw' => substr($raw, 0, 300)];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) return ['pages' => [], 'error' => 'JSON-Decode fehlgeschlagen'];

    $result = [];
    foreach ($data as $p) {
        $slug = $p['slug'] ?? '';
        if (!str_starts_with($slug, $slug_pre)) continue;

        $short = substr($slug, strlen($slug_pre));
        $title = $p['title']['rendered'] ?? $slug;
        $title = strip_tags(html_entity_decode($title, ENT_QUOTES, 'UTF-8'));

        // Custom Fields: zuerst ACF, dann meta
        $acf    = $p['acf']  ?? [];
        $meta   = $p['meta'] ?? [];
        $gruppe = $acf['ds_gruppe']   ?? $meta['ds_gruppe']   ?? 'landscape';
        $icon   = $acf['ds_icon']     ?? $meta['ds_icon']     ?? '📱';
        $port   = ($acf['ds_portrait'] ?? $meta['ds_portrait'] ?? '') === '1';

        $result[] = [
            'title'    => $title,
            'slug'     => $short,
            'url'      => rtrim($site_url, '/') . '/' . $slug . '/',
            'icon'     => $icon ?: '📱',
            'gruppe'   => $gruppe ?: 'landscape',
            'portrait' => $port,
            'wp_id'    => $p['id'] ?? 0,
        ];
    }
    return ['pages' => $result, 'total_wp' => count($data)];
}

$api_result = wcr_ds_load_wp_pages_api($WP_API_BASE, $DS_SLUG_PRE, $SITE_URL);
$wp_seiten  = $api_result['pages'] ?? [];
$api_error  = $api_result['error'] ?? null;
$api_total  = $api_result['total_wp'] ?? null;

// ── Fallback-Liste ──
$fallback_seiten = [
    ['title'=>'Starter Pack',       'slug'=>'starter-pack',          'url'=>$SITE_URL.'/starter-pack/',          'icon'=>'🏄','gruppe'=>'landscape','portrait'=>false],
    ['title'=>'Wetter',             'slug'=>'wetter',                'url'=>$SITE_URL.'/wetter/',                'icon'=>'🌤','gruppe'=>'landscape','portrait'=>false],
    ['title'=>'Wind Map',           'slug'=>'windmap',               'url'=>$SITE_URL.'/windmap/',               'icon'=>'💨','gruppe'=>'landscape','portrait'=>false],
    ['title'=>'Tickets / Cable',    'slug'=>'tickets',               'url'=>$SITE_URL.'/tickets/',               'icon'=>'🏄','gruppe'=>'landscape','portrait'=>false],
    ['title'=>'Kaffee',             'slug'=>'kaffee',                'url'=>$SITE_URL.'/kaffee/',                'icon'=>'☕','gruppe'=>'landscape','portrait'=>false],
    ['title'=>'Merchandise',        'slug'=>'merchandise',           'url'=>$SITE_URL.'/merchandise/',           'icon'=>'👕','gruppe'=>'landscape','portrait'=>false],
    ['title'=>'Stand Up Paddle',    'slug'=>'sup',                   'url'=>$SITE_URL.'/sup/',                   'icon'=>'🏄','gruppe'=>'landscape','portrait'=>false],
    ['title'=>'Park',               'slug'=>'park',                  'url'=>$SITE_URL.'/park/',                  'icon'=>'🌊','gruppe'=>'landscape','portrait'=>false],
    ['title'=>'Obstacles',          'slug'=>'obstacles',             'url'=>'https://wake-and-camp.de/obst/',    'icon'=>'🤸','gruppe'=>'landscape','portrait'=>false],
    ['title'=>'Kino',               'slug'=>'kino',                  'url'=>$SITE_URL.'/kino/',                  'icon'=>'🎦','gruppe'=>'landscape','portrait'=>false],
    ['title'=>'Cable Preisliste',   'slug'=>'cable-list',            'url'=>$SITE_URL.'/cable-list/',            'icon'=>'🎫','gruppe'=>'liste',   'portrait'=>false],
    ['title'=>'Camping Preise',     'slug'=>'camping-list',          'url'=>$SITE_URL.'/camping-list/',          'icon'=>'⛺','gruppe'=>'liste',   'portrait'=>false],
    ['title'=>'Eiskarte',           'slug'=>'eis',                   'url'=>$SITE_URL.'/eis/',                   'icon'=>'🍦','gruppe'=>'liste',   'portrait'=>false],
    ['title'=>'Getränke',           'slug'=>'getraenke',             'url'=>$SITE_URL.'/getraenke/',             'icon'=>'🍺','gruppe'=>'liste',   'portrait'=>false],
    ['title'=>'Softdrinks',         'slug'=>'soft',                  'url'=>$SITE_URL.'/soft/',                  'icon'=>'🥤','gruppe'=>'liste',   'portrait'=>false],
    ['title'=>'Speisekarte',        'slug'=>'essen',                 'url'=>$SITE_URL.'/essen/',                 'icon'=>'🍔','gruppe'=>'liste',   'portrait'=>false],
    ['title'=>'Burger Table',       'slug'=>'produkt-table',         'url'=>$SITE_URL.'/produkt-table/',         'icon'=>'🍔','gruppe'=>'spotlight','portrait'=>false],
    ['title'=>'Öffnungszeiten Story','slug'=>'oeffnungszeiten-story','url'=>$SITE_URL.'/oeffnungszeiten-story/','icon'=>'🕐','gruppe'=>'portrait','portrait'=>true],
    ['title'=>'Instagram Grid',     'slug'=>'insta',                 'url'=>$SITE_URL.'/insta/',                 'icon'=>'📸','gruppe'=>'portrait','portrait'=>true],
    ['title'=>'Instagram Reels',    'slug'=>'insta-reel',            'url'=>$SITE_URL.'/insta-reel/',            'icon'=>'🎥','gruppe'=>'portrait','portrait'=>true],
    ['title'=>'Cable-Park Portrait','slug'=>'park-portrait',         'url'=>$SITE_URL.'/park-portrait/',         'icon'=>'🌊','gruppe'=>'portrait','portrait'=>true],
];

$raw_seiten = !empty($wp_seiten) ? $wp_seiten : $fallback_seiten;
$using_wp   = !empty($wp_seiten);

$gruppen_def = [
    'landscape' => ['label' => '🖼️ 16:9 — Landscape', 'portrait' => false],
    'liste'     => ['label' => '📱 Listen',              'portrait' => false],
    'spotlight' => ['label' => '📱 Produkt-Spotlight',   'portrait' => false],
    'portrait'  => ['label' => '📱 9:16 — Portrait',    'portrait' => true],
];

$gruppen = [];
foreach ($gruppen_def as $gkey => $gdef) {
    $gruppen[$gkey] = ['label' => $gdef['label'], 'portrait' => $gdef['portrait'], 'seiten' => []];
}
foreach ($raw_seiten as $s) {
    $gkey = $s['gruppe'] ?? 'landscape';
    if (!isset($gruppen[$gkey])) $gkey = 'landscape';
    $gruppen[$gkey]['seiten'][] = $s;
}
$gruppen = array_filter($gruppen, fn($g) => !empty($g['seiten']));

function wcr_ds_check_status(array $rule, PDO $pdo, array $allowed_tables): array {
    $tables  = array_values(array_intersect((array)($rule['tables'] ?? []), $allowed_tables));
    $typ     = trim($rule['typ'] ?? '');
    $ids_raw = trim($rule['ids'] ?? '');
    $mode    = ($rule['mode'] ?? 'any') === 'all' ? 'all' : 'any';
    if (empty($tables) && $typ === '' && $ids_raw === '')
        return ['active' => true, 'reason' => 'no_rule', 'db_ok' => true];
    $check_tables = !empty($tables) ? $tables : $allowed_tables;
    try {
        if ($ids_raw !== '') {
            $ids = array_filter(array_map('intval', explode(',', $ids_raw)));
            if (empty($ids)) return ['active' => true, 'reason' => 'ids_empty', 'db_ok' => true];
            $found = 0; $total = count($ids);
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
        if ($typ !== '') {
            foreach ($check_tables as $tbl) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$tbl}` WHERE typ = ? AND stock > 0");
                $stmt->execute([$typ]);
                if ((int)$stmt->fetchColumn() > 0)
                    return ['active' => true, 'reason' => 'typ_active:'.$tbl.':'.$typ, 'db_ok' => true];
            }
            return ['active' => false, 'reason' => 'typ_none_active:'.$typ, 'db_ok' => true];
        }
        foreach ($check_tables as $tbl) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM `{$tbl}` WHERE stock > 0");
            if ((int)$stmt->fetchColumn() > 0)
                return ['active' => true, 'reason' => 'table_any:'.$tbl, 'db_ok' => true];
        }
        return ['active' => false, 'reason' => 'table_none_active', 'db_ok' => true];
    } catch (Exception $e) {
        return ['active' => true, 'reason' => 'db_error_fail_open', 'db_ok' => false];
    }
}

$alle_seiten = [];
foreach ($gruppen as &$g) {
    foreach ($g['seiten'] as &$s) {
        $s['_idx'] = count($alle_seiten);
        $s['portrait'] = $g['portrait'];
        $rule = $rules[$s['slug']] ?? ['override'=>'auto','tables'=>[],'typ'=>'','ids'=>'','mode'=>'any'];
        $s['rule']   = $rule;
        $s['status'] = wcr_ds_check_status($rule, $pdo, $ALLOWED_TABLES);
        $alle_seiten[] = &$s;
    }
}
unset($g, $s);

$save_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wcr_ds_save'])) {
    wcr_require('view_ds');
    wcr_verify_csrf();
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
        $save_msg = '✅ Gespeichert für: ' . htmlspecialchars($slug, ENT_QUOTES, 'UTF-8');
    }
}

$typen_all = [];
foreach ($ALLOWED_TABLES as $tbl) {
    try {
        $rows = $pdo->query("SELECT DISTINCT typ FROM `{$tbl}` WHERE typ IS NOT NULL AND typ != '' ORDER BY typ")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($rows as $t) $typen_all[] = trim($t);
    } catch (Exception $e) {}
}
$typen_all = array_unique($typen_all);
sort($typen_all);
$ds_count = count($alle_seiten);
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>Verwaltung: <?= htmlspecialchars($PAGE_TITLE, ENT_QUOTES, 'UTF-8') ?></title>
  <style>
    .ds-source-badge{display:inline-flex;align-items:center;gap:5px;font-size:.72rem;font-weight:600;padding:3px 10px;border-radius:20px;margin-left:8px;}
    .ds-source-badge.wp{background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7;}
    .ds-source-badge.fb{background:#fff3e0;color:#e65100;border:1px solid #ffcc80;}
    .ds-group{margin-bottom:40px;}
    .ds-group-label{font-size:.8rem;font-weight:800;text-transform:uppercase;letter-spacing:1.5px;color:#6b7280;padding:0 0 10px;border-bottom:2px solid #e5e7eb;margin-bottom:16px;display:flex;align-items:center;gap:8px;}
    .ds-group-label span.cnt{font-size:.7rem;font-weight:600;background:#f3f4f6;color:#9ca3af;border:1px solid #e5e7eb;border-radius:20px;padding:1px 8px;letter-spacing:0;text-transform:none;}
    .ds-gallery-landscape{display:grid;grid-template-columns:repeat(auto-fill,minmax(420px,1fr));gap:20px;align-items:start;}
    .ds-gallery-portrait{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,280px));gap:20px;align-items:start;}
    .ds-card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;overflow:hidden;transition:transform .2s,box-shadow .2s;}
    .ds-card:hover{transform:translateY(-3px);box-shadow:0 10px 32px rgba(0,0,0,.1);}
    .ds-card.ds-inactive{opacity:.7;border-color:#fca5a5;}
    .ds-card-header{display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border-bottom:1px solid #e5e7eb;background:#f9fafb;gap:8px;flex-wrap:wrap;}
    .ds-card-title{display:flex;align-items:center;gap:7px;font-size:.88rem;font-weight:700;color:#111;min-width:0;}
    .ds-card-actions{display:flex;align-items:center;gap:5px;flex-shrink:0;}
    .ds-badge{display:inline-flex;align-items:center;gap:4px;font-size:.65rem;font-weight:600;padding:2px 8px;border-radius:20px;border:1px solid transparent;text-transform:uppercase;letter-spacing:.5px;white-space:nowrap;}
    .ds-dot{width:5px;height:5px;border-radius:50%;flex-shrink:0;animation:ds-blink 2s infinite;}
    @keyframes ds-blink{0%,100%{opacity:1}50%{opacity:.3}}
    .ds-btn{display:inline-flex;align-items:center;gap:4px;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:7px;color:#555;font-size:.75rem;padding:3px 8px;cursor:pointer;text-decoration:none;transition:background .15s;white-space:nowrap;}
    .ds-btn:hover{background:#e5e7eb;color:#111;}
    .ds-btn.primary{background:#e8f5ff;border-color:#bdd9f5;color:#1a6fb5;}
    .ds-btn.primary:hover{background:#d0eaff;}
    .ds-btn.edit-btn{background:#fff8e1;border-color:#f0c040;color:#b45309;}
    .ds-btn.edit-btn:hover{background:#fff3c4;}
    .ds-frame-wrap{position:relative;width:100%;background:#111;overflow:hidden;min-height:40px;}
    .ds-frame-wrap iframe{display:block;position:absolute;top:0;left:0;border:none;opacity:0;transition:opacity .5s;pointer-events:none;transform-origin:top left;}
    .ds-frame-wrap iframe.loaded{opacity:1;}
    .ds-spin-wrap{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;font-size:.75rem;color:#555;z-index:2;background:#1a1a2e;transition:opacity .3s;}
    .ds-spin-wrap.hidden{opacity:0;pointer-events:none;}
    .ds-spinner{width:24px;height:24px;border:2px solid rgba(255,255,255,.1);border-top-color:#3b82f6;border-radius:50%;animation:ds-spin .75s linear infinite;}
    @keyframes ds-spin{to{transform:rotate(360deg)}}
    .ds-card-footer{display:flex;align-items:center;justify-content:space-between;padding:6px 14px;border-top:1px solid #e5e7eb;background:#f9fafb;}
    .ds-url{font-size:.62rem;color:#9ca3af;font-family:monospace;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:65%;}
    .ds-time{font-size:.62rem;color:#9ca3af;white-space:nowrap;}
    .rule-panel{padding:12px 14px;border-top:2px dashed #e5e7eb;background:#fafafa;display:none;}
    .rule-panel.open{display:block;}
    .rule-panel form{display:flex;flex-direction:column;gap:8px;}
    .rule-row{display:flex;align-items:center;gap:8px;flex-wrap:wrap;font-size:.8rem;}
    .rule-row label{font-weight:600;min-width:70px;color:#374151;}
    .rule-row input[type=text],.rule-row select{border:1px solid #d1d5db;border-radius:6px;padding:4px 8px;font-size:.8rem;flex:1;min-width:120px;}
    .rule-cb-group{display:flex;flex-wrap:wrap;gap:6px;}
    .rule-cb-group label{font-weight:400;min-width:auto;display:flex;align-items:center;gap:4px;}
    .rule-status{font-size:.75rem;padding:4px 8px;border-radius:6px;font-weight:600;}
    .rule-status.active{background:#d1fae5;color:#065f46;}
    .rule-status.inactive{background:#fee2e2;color:#991b1b;}
    .rule-status.dberr{background:#fef3c7;color:#92400e;}
    .rule-save-btn{align-self:flex-end;background:#0071e3;color:#fff;border:none;border-radius:8px;padding:5px 16px;font-size:.8rem;font-weight:600;cursor:pointer;}
    .rule-save-btn:hover{background:#005bb5;}
    .save-notice{padding:10px 20px;margin-bottom:16px;background:#d1fae5;border:1px solid #6ee7b7;border-radius:8px;color:#065f46;font-weight:600;}
    .ds-info-box{padding:12px 16px;margin-bottom:16px;border-radius:8px;font-size:.8rem;line-height:1.7;}
    .ds-info-box.warn{background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af;}
    .ds-info-box.err{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;}
    .ds-info-box code{background:rgba(0,0,0,.07);padding:1px 5px;border-radius:4px;font-size:.78rem;}
  </style>
</head>
<body class="bo" data-csrf="<?= wcr_csrf_attr() ?>">
<?php include __DIR__ . '/../inc/menu.php'; ?>

<div class="header-controls">
  <h1>🖥 <?= htmlspecialchars($PAGE_TITLE, ENT_QUOTES, 'UTF-8') ?>
    <span class="ds-source-badge <?= $using_wp ? 'wp' : 'fb' ?>">
      <?= $using_wp
        ? '🐙 WordPress (' . count($alle_seiten) . ' Seiten)'
        : '⚠️ Fallback-Liste' ?>
    </span>
  </h1>
  <button class="btn-upload" onclick="dsReloadAll()">↺ Alle neu laden</button>
</div>

<?php if (!$using_wp): ?>
<div class="ds-info-box <?= $api_error ? 'err' : 'warn' ?>">
  <?php if ($api_error): ?>
    🔴 <strong>REST API Fehler:</strong> <code><?= htmlspecialchars($api_error, ENT_QUOTES, 'UTF-8') ?></code><br>
    <small>Geprüfte URL: <code><?= htmlspecialchars($WP_API_BASE . '/pages?...', ENT_QUOTES, 'UTF-8') ?></code></small>
  <?php else: ?>
    ⚠️ <strong>REST API erreichbar</strong> (<?= (int)$api_total ?> Seiten total)
    — aber keine Seite hat Slug-Prefix <code>ds-</code>.<br>
    Bitte in WP Admin: Seite bearbeiten → Permalink → Slug auf <code>ds-seitenname</code> setzen.
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($save_msg): ?>
<div class="save-notice"><?= htmlspecialchars($save_msg, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<?php foreach ($gruppen as $g):
  $portrait  = $g['portrait'];
  $gridClass = $portrait ? 'ds-gallery-portrait' : 'ds-gallery-landscape';
  $nW = $portrait ? 1080 : 1920;
  $nH = $portrait ? 1920 : 1080;
?>
<div class="ds-group">
  <div class="ds-group-label">
    <?= htmlspecialchars($g['label'], ENT_QUOTES, 'UTF-8') ?>
    <span class="cnt"><?= count($g['seiten']) ?></span>
  </div>
  <div class="<?= $gridClass ?>">
    <?php foreach ($g['seiten'] as $s):
      $i        = $s['_idx'];
      $status   = $s['status'];
      $rule     = $s['rule'];
      $ov       = $rule['override'] ?? 'auto';
      $effektiv = ($ov === 'force_on') ? true : (($ov === 'force_off') ? false : $status['active']);
      $bc       = $effektiv ? '#00c853' : '#ff3b30';
      $bt       = $effektiv ? 'Aktiv' : 'Inaktiv';
    ?>
    <div class="ds-card <?= !$effektiv ? 'ds-inactive' : '' ?>" id="ds-card-<?= $i ?>">
      <div class="ds-card-header">
        <div class="ds-card-title">
          <span><?= htmlspecialchars($s['icon'], ENT_QUOTES, 'UTF-8') ?></span>
          <span><?= htmlspecialchars($s['title'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="ds-card-actions">
          <span class="ds-badge" style="background:<?= $bc ?>22;color:<?= $bc ?>;border-color:<?= $bc ?>55;">
            <span class="ds-dot" style="background:<?= $bc ?>"></span>
            <?= htmlspecialchars($bt, ENT_QUOTES, 'UTF-8') ?>
          </span>
          <?php if (!$status['db_ok']): ?><span title="DB-Fehler">⚠️</span><?php endif; ?>
          <button class="ds-btn edit-btn" onclick="toggleRule(<?= $i ?>)">✎ Regel</button>
          <button class="ds-btn" onclick="dsReload(<?= $i ?>)">↺</button>
          <a class="ds-btn primary" href="<?= htmlspecialchars($s['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">↗ Öffnen</a>
        </div>
      </div>

      <div class="rule-panel" id="rule-panel-<?= $i ?>">
        <form method="post">
          <?= wcr_csrf_field() ?>
          <input type="hidden" name="wcr_ds_save" value="1">
          <input type="hidden" name="page_slug" value="<?= htmlspecialchars($s['slug'], ENT_QUOTES, 'UTF-8') ?>">
          <div class="rule-row"><label>Override</label>
            <select name="override">
              <option value="auto"      <?= $ov==='auto'     ?'selected':'' ?>>Auto (DB-Check)</option>
              <option value="force_on"  <?= $ov==='force_on' ?'selected':'' ?>>Force ON</option>
              <option value="force_off" <?= $ov==='force_off'?'selected':'' ?>>Force OFF</option>
            </select></div>
          <div class="rule-row"><label>Tabellen</label>
            <div class="rule-cb-group">
              <?php foreach ($ALLOWED_TABLES as $tbl): ?>
              <label><input type="checkbox" name="tables[]" value="<?= htmlspecialchars($tbl,ENT_QUOTES,'UTF-8') ?>"
                <?= in_array($tbl,(array)($rule['tables']??[]),true)?'checked':'' ?>>
                <?= htmlspecialchars($tbl,ENT_QUOTES,'UTF-8') ?></label>
              <?php endforeach; ?></div></div>
          <div class="rule-row"><label>Typ</label>
            <input type="text" name="typ" value="<?= htmlspecialchars($rule['typ']??'',ENT_QUOTES,'UTF-8') ?>" list="typen-list" placeholder="z.B. Burger …">
            <datalist id="typen-list"><?php foreach($typen_all as $tv):?><option value="<?= htmlspecialchars($tv,ENT_QUOTES,'UTF-8') ?>"><?php endforeach;?></datalist></div>
          <div class="rule-row"><label>IDs</label>
            <input type="text" name="ids" value="<?= htmlspecialchars($rule['ids']??'',ENT_QUOTES,'UTF-8') ?>" placeholder="3010,3089"></div>
          <div class="rule-row"><label>Mode</label>
            <select name="mode">
              <option value="any" <?= ($rule['mode']??'any')==='any'?'selected':'' ?>>any – mind. 1 aktiv</option>
              <option value="all" <?= ($rule['mode']??'any')==='all'?'selected':'' ?>>all – alle aktiv</option>
            </select></div>
          <div class="rule-row">
            <span class="rule-status <?= !$status['db_ok']?'dberr':($status['active']?'active':'inactive') ?>">
              <?= $status['active']?'✅':'⛔' ?> <?= htmlspecialchars($status['reason'],ENT_QUOTES,'UTF-8') ?>
            </span></div>
          <button type="submit" class="rule-save-btn">Speichern</button>
        </form>
      </div>

      <div class="ds-frame-wrap" id="ds-wrap-<?= $i ?>">
        <div class="ds-spin-wrap" id="ds-spin-<?= $i ?>">
          <div class="ds-spinner"></div><span>Lädt…</span>
        </div>
        <iframe id="ds-frame-<?= $i ?>"
          data-src="<?= htmlspecialchars($s['url'],ENT_QUOTES,'UTF-8') ?>"
          data-nw="<?= $nW ?>" data-nh="<?= $nH ?>"
          style="width:<?= $nW ?>px;height:<?= $nH ?>px;"
          scrolling="no"></iframe>
      </div>
      <div class="ds-card-footer">
        <span class="ds-url"><?= htmlspecialchars($s['url'],ENT_QUOTES,'UTF-8') ?></span>
        <span class="ds-time" id="ds-time-<?= $i ?>">-</span>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endforeach; ?>

<script>
var DS_COUNT = <?= json_encode($ds_count) ?>;
var dsStartTimes = {};
function dsScaleWrap(w){
  var f=w.querySelector('iframe'); if(!f)return;
  var nW=parseInt(f.dataset.nw,10)||1920, nH=parseInt(f.dataset.nh,10)||1080;
  var s=w.offsetWidth/nW;
  f.style.transform='scale('+s+')';
  w.style.height=Math.round(nH*s)+'px';
}
var ro=new ResizeObserver(function(e){e.forEach(function(x){dsScaleWrap(x.target);});});
function dsLoaded(i){
  var f=document.getElementById('ds-frame-'+i);
  var sp=document.getElementById('ds-spin-'+i);
  var ti=document.getElementById('ds-time-'+i);
  if(!f||!sp)return;
  f.classList.add('loaded'); sp.classList.add('hidden');
  if(ti&&dsStartTimes[i]){
    var ms=Date.now()-dsStartTimes[i];
    ti.textContent='✓ '+(ms/1000).toFixed(1)+'s';
    ti.style.color=ms<2000?'#16a34a':ms<5000?'#d97706':'#dc2626';
  }
  var w=document.getElementById('ds-wrap-'+i); if(w)dsScaleWrap(w);
}
function dsReload(i){
  var f=document.getElementById('ds-frame-'+i);
  var sp=document.getElementById('ds-spin-'+i);
  var ti=document.getElementById('ds-time-'+i);
  if(!f)return;
  f.classList.remove('loaded');
  if(sp){sp.classList.remove('hidden');sp.innerHTML='<div class="ds-spinner"></div><span>Lädt…</span>';}
  if(ti){ti.textContent='-';ti.style.color='';}
  dsStartTimes[i]=Date.now();
  f.onload=function(){dsLoaded(i);};
  f.src=f.dataset.src+'?t='+Date.now();
}
function dsReloadAll(){for(var i=0;i<DS_COUNT;i++)dsReload(i);}
function toggleRule(i){
  var p=document.getElementById('rule-panel-'+i);
  if(p)p.classList.toggle('open');
}
document.addEventListener('DOMContentLoaded',function(){
  document.querySelectorAll('.ds-frame-wrap').forEach(function(w){ro.observe(w);dsScaleWrap(w);});
  setTimeout(function(){
    document.querySelectorAll('.ds-frame-wrap iframe').forEach(function(f){
      var i=parseInt(f.id.replace('ds-frame-',''),10);
      dsStartTimes[i]=Date.now();
      f.onload=function(){dsLoaded(i);};
      f.src=f.dataset.src;
    });
  },200);
});
</script>

<?php include __DIR__ . '/../inc/debug.php'; ?>
</body>
</html>
