<?php
/**
 * ctrl/ds-settings.php — DS Zentraler Controller v8
 * Schreiben + Lesen komplett über WP REST API (update_option / get_option).
 * PDO-User hat kein Schreibrecht auf wp_options — WP-Brücke löst das.
 * 
 * v7: + CSRF-Schutz für alle POST-Aktionen
 * v8: + wcr_secret für alle GET-Requests zu /ds-settings (dsc_api_load, ig_get)
 */
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
wcr_require('view_ds');

define('DSC_WP_API_BASE', 'https://wcr-webpage.de/wp-json/wakecamp/v1');
define('DSC_WP_SECRET',   'WCR_DS_2026');

$THEMES = [
    'glass'  => ['icon'=>'🪟', 'name'=>'Glass',  'sub'=>'Glassmorphism',   'desc'=>'Blur-Effekte, frosted Cards, subtile Tiefe durch Transparenz'],
    'flat'   => ['icon'=>'▪',  'name'=>'Flat',   'sub'=>'Modern Flat',     'desc'=>'Kein Blur, solide Flächen, scharfe Kanten — klares Minimaldesign'],
    'aurora' => ['icon'=>'🌌', 'name'=>'Aurora', 'sub'=>'Aurora Gradient', 'desc'=>'Animierter Gradient-Mesh Hintergrund, Farb-Borders pro Karte'],
];

$DEFAULTS = [
    'clr_green'    => '#679467',
    'clr_blue'     => '#019ee3',
    'clr_white'    => '#ffffff',
    'clr_text'     => '#eeeeee',
    'clr_muted'    => '#7a8a8a',
    'clr_bg'       => '#080808',
    'clr_bg_dark'  => '#0d0d0d',
    'clr_bg_glass' => 'rgba(10,14,24,0.65)',
    'font_family'  => 'Segoe UI',
    'viewport_w'   => '1920',
    'viewport_h'   => '1080',
];

$FONTS  = ['Segoe UI','Inter','Roboto','Montserrat','Poppins','Oswald','Raleway','Open Sans','Lato','Ubuntu'];
$COLORS = [
    'clr_green'   => ['Primärfarbe',       'Akzente, Punkte, Kategorien'],
    'clr_blue'    => ['Sekundärfarbe',      'Windmap, Timeline, Badges'],
    'clr_white'   => ['Weiß',              'Preise, Zahlen'],
    'clr_text'    => ['Textfarbe',          'Produktnamen, Fließtext'],
    'clr_muted'   => ['Grau',              'Labels, Nebeninfos'],
    'clr_bg'      => ['Hintergrund',        'Body-Hintergrund'],
    'clr_bg_dark' => ['Hintergrund Dunkel', 'Karten-Hintergrund'],
];

// ── WP REST API Hilfsfunktionen ──────────────────────────────

function dsc_curl(string $url, ?array $postData = null): array {
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
    ];
    if ($postData !== null) {
        $opts[CURLOPT_POST]       = true;
        $opts[CURLOPT_POSTFIELDS] = json_encode($postData, JSON_UNESCAPED_UNICODE);
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
    ];
}

/**
 * Lädt DS-Settings aus WP REST API mit wcr_secret Authentifizierung
 */
function dsc_api_load(array $defaults): array {
    // GET mit wcr_secret Parameter (konsistent mit dsc_get_option)
    $r = dsc_curl(DSC_WP_API_BASE . '/ds-settings?wcr_secret=' . urlencode(DSC_WP_SECRET));
    if (!$r['ok'] || !isset($r['json']['options'])) {
        return ['opts' => $defaults, 'theme' => 'glass'];
    }
    $wpOpts = $r['json']['options'];
    $opts   = (is_array($wpOpts) && !empty($wpOpts))
                ? array_merge($defaults, array_intersect_key($wpOpts, $defaults))
                : $defaults;
    $theme = $r['json']['theme'] ?? 'glass';
    if (!in_array($theme, ['glass','flat','aurora'], true)) $theme = 'glass';
    return ['opts' => $opts, 'theme' => $theme];
}

function dsc_api_save(array $payload): array {
    $r = dsc_curl(
        DSC_WP_API_BASE . '/ds-settings',
        array_merge($payload, ['wcr_secret' => DSC_WP_SECRET])
    );
    return [
        'ok'    => ($r['ok'] && isset($r['json']['ok']) && $r['json']['ok'] === true),
        'error' => $r['err'] ?: '',
    ];
}

/**
 * Liest eine wp_option direkt via REST
 */
function dsc_get_option(string $key, $default = '') {
    $r = dsc_curl(DSC_WP_API_BASE . '/options/' . urlencode($key) . '?wcr_secret=' . DSC_WP_SECRET);
    if ($r['ok'] && isset($r['json']['value'])) return $r['json']['value'];
    return $default;
}

/**
 * Schreibt eine einzelne wp_option via REST
 */
function dsc_set_option(string $key, $value): bool {
    $r = dsc_curl(DSC_WP_API_BASE . '/options', [
        'wcr_secret' => DSC_WP_SECRET,
        'key'        => $key,
        'value'      => $value,
    ]);
    return $r['ok'] && !empty($r['json']['ok']);
}

if (!function_exists('ov')) {
    function ov(array $o, string $k): string { return htmlspecialchars($o[$k] ?? ''); }
}

// ── POST-Handler ────────────────────────────────────────────────
$msg        = '';
$msgType    = '';
$savedOpts  = null;
$savedTheme = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ── CSRF-Schutz: ALLE POST-Aktionen validieren ──
    if (!wcr_verify_csrf(false)) {
        $msg = 'Sicherheitsprüfung fehlgeschlagen. Bitte Seite neu laden und erneut versuchen.';
        $msgType = 'error';
    } else {
        $action = trim($_POST['action'] ?? '');

        // ── Theme ────────────────────────────────────────────────
        if ($action === 'theme') {
            $t = trim($_POST['theme'] ?? '');
            if (isset($THEMES[$t])) {
                $r = dsc_api_save(['action' => 'theme', 'theme' => $t]);
                if ($r['ok']) {
                    $msg = 'Theme „' . $THEMES[$t]['name'] . '“ aktiviert — alle DS-Screens zeigen sofort den neuen Stil.';
                    $msgType    = 'ok';
                    $savedTheme = $t;
                } else {
                    $msg = 'Fehler beim Speichern des Themes: ' . $r['error'];
                    $msgType = 'error';
                }
            }
        }

        // ── Farben / Font speichern ────────────────────────────────────────────
        if ($action === 'save') {
            $new = [];
            foreach (array_keys($COLORS) as $k) {
                $v = trim($_POST[$k] ?? '');
                $new[$k] = (preg_match('/^#[0-9a-fA-F]{3,8}$/', $v) || preg_match('/^rgba?\([\d,.\s]+\)$/', $v))
                    ? $v : $DEFAULTS[$k];
            }
            $glass = trim($_POST['clr_bg_glass'] ?? '');
            $new['clr_bg_glass'] = preg_match('/^rgba?\([\d,.\s]+\)$/', $glass) ? $glass : $DEFAULTS['clr_bg_glass'];
            $new['font_family']  = in_array($_POST['font_family'] ?? '', $FONTS, true)
                ? $_POST['font_family'] : $DEFAULTS['font_family'];
            $current = dsc_api_load($DEFAULTS);
            $new['viewport_w'] = $current['opts']['viewport_w'] ?? $DEFAULTS['viewport_w'];
            $new['viewport_h'] = $current['opts']['viewport_h'] ?? $DEFAULTS['viewport_h'];
            $toSave = array_merge($DEFAULTS, $new);
            $r = dsc_api_save(['action' => 'save', 'options' => $toSave]);
            if ($r['ok']) {
                $msg       = 'Gespeichert — Änderungen sind sofort auf allen DS-Seiten aktiv.';
                $msgType   = 'ok';
                $savedOpts = $toSave;
            } else {
                $msg = 'Fehler beim Speichern: ' . $r['error'];
                $msgType = 'error';
            }
        }

        // ── Reset ────────────────────────────────────────────────
        if ($action === 'reset') {
            $r = dsc_api_save(['action' => 'reset']);
            if ($r['ok']) {
                $msg       = 'Einstellungen auf Standard zurückgesetzt.';
                $msgType   = 'ok';
                $savedOpts = $DEFAULTS;
            } else {
                $msg = 'Fehler beim Zurücksetzen: ' . $r['error'];
                $msgType = 'error';
            }
        }

        // ── Instagram Settings speichern ────────────────────────────────────────────
        if ($action === 'ig_save') {
            $ig_fields = [
                'wcr_instagram_token'          => 'strval',
                'wcr_instagram_user_id'        => 'strval',
                'wcr_instagram_hashtags'       => 'strval',
                'wcr_instagram_excluded'       => 'strval',
                'wcr_instagram_location_label' => 'strval',
                'wcr_instagram_cta_text'       => 'strval',
                'wcr_instagram_qr_url'         => 'strval',
                'wcr_instagram_max_age_value'  => 'intval',
                'wcr_instagram_max_age_unit'   => 'strval',
                'wcr_instagram_max_posts'      => 'intval',
                'wcr_instagram_refresh'        => 'intval',
                'wcr_instagram_new_hours'      => 'intval',
                'wcr_instagram_video_pool'     => 'intval',
                'wcr_instagram_video_count'    => 'intval',
                'wcr_instagram_min_likes'      => 'intval',
            ];
            $ig_toggles = [
                'wcr_instagram_use_tagged','wcr_instagram_use_hashtag','wcr_instagram_show_user',
                'wcr_instagram_cta_active','wcr_instagram_qr_active','wcr_instagram_weekly_best',
            ];
            $payload = ['wcr_secret' => DSC_WP_SECRET, 'action' => 'ig_save', 'options' => []];
            foreach ($ig_fields as $key => $fn) {
                if (isset($_POST[$key])) $payload['options'][$key] = $fn($_POST[$key]);
            }
            foreach ($ig_toggles as $t) {
                $payload['options'][$t] = isset($_POST[$t]) ? 1 : 0;
            }
            // Cache leeren mitschicken
            $payload['options']['wcr_instagram_flush_cache'] = 1;

            $r = dsc_curl(DSC_WP_API_BASE . '/ds-settings', $payload);
            if ($r['ok'] && !empty($r['json']['ok'])) {
                $msg = '📸 Instagram-Einstellungen gespeichert & Cache geleert.';
                $msgType = 'ok';
            } else {
                $msg = 'Fehler beim Speichern der Instagram-Einstellungen: ' . ($r['err'] ?: 'Unbekannt');
                $msgType = 'error';
            }
        }
    }
}

// ── Laden ──────────────────────────────────────────────────────────────────
$loaded      = dsc_api_load($DEFAULTS);
$opts        = $savedOpts  ?? $loaded['opts'];
$activeTheme = $savedTheme ?? $loaded['theme'];

// ── Instagram-Options aus WP lesen ────────────────────────────────────────────
/**
 * Lädt Instagram-Einstellungen aus WP REST API mit wcr_secret Authentifizierung
 */
function ig_get(string $key, $default = '') {
    static $cache = null;
    if ($cache === null) {
        // GET mit wcr_secret Parameter (konsistent mit dsc_api_load)
        $r = dsc_curl(DSC_WP_API_BASE . '/ds-settings?wcr_secret=' . urlencode(DSC_WP_SECRET));
        $cache = ($r['ok'] && isset($r['json']['instagram'])) ? $r['json']['instagram'] : [];
    }
    return $cache[$key] ?? $default;
}

// Token-Status prüfen (Live-Check)
$ig_token   = ig_get('wcr_instagram_token', '');
$ig_user_id = ig_get('wcr_instagram_user_id', '');
$token_status = '';
$token_class  = 'muted';
if ($ig_token && $ig_user_id) {
    $chk = dsc_curl("https://graph.instagram.com/me?fields=id,username&access_token={$ig_token}");
    if ($chk['ok'] && !empty($chk['json']['id'])) {
        $token_status = '✅ Verbunden als @' . ($chk['json']['username'] ?? $chk['json']['id']);
        $token_class  = 'ok';
    } else {
        $token_status = '❌ Token ungültig oder abgelaufen';
        $token_class  = 'error';
    }
} else {
    $token_status = '⚪ Kein Token hinterlegt';
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>DS Controller</title>
<link id="gf-link" rel="stylesheet" href="https://fonts.googleapis.com/css2?family=<?= rawurlencode($opts['font_family']==='Segoe UI'?'Inter':$opts['font_family']) ?>:wght@400;600;700;800&display=swap">
<style>
/* ── Instagram Block Styles ────────────────────────────────────── */
.ig-block{background:var(--bg-card);border-radius:var(--radius);box-shadow:var(--shadow);padding:22px 24px;margin-bottom:20px;}
.ig-block-title{font-size:14px;font-weight:700;margin:0 0 3px;display:flex;align-items:center;gap:8px;}
.ig-block-sub{font-size:12px;color:var(--text-muted);margin:0 0 20px;}
.ig-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 32px;}
.ig-row{display:flex;flex-direction:column;gap:4px;margin-bottom:14px;}
.ig-label{font-size:12px;font-weight:600;color:var(--text-main);}
.ig-sublabel{font-size:11px;color:var(--text-muted);margin-top:1px;}
.ig-input{padding:7px 10px;border:1px solid var(--border);border-radius:7px;font-size:13px;background:var(--bg-card);color:var(--text-main);width:100%;box-sizing:border-box;}
.ig-input:focus{outline:none;border-color:var(--primary);}
.ig-textarea{padding:7px 10px;border:1px solid var(--border);border-radius:7px;font-size:12px;font-family:monospace;background:var(--bg-card);color:var(--text-main);width:100%;box-sizing:border-box;height:76px;resize:vertical;}
.ig-select{padding:7px 10px;border:1px solid var(--border);border-radius:7px;font-size:13px;background:var(--bg-card);color:var(--text-main);}
.ig-num{padding:7px 10px;border:1px solid var(--border);border-radius:7px;font-size:13px;background:var(--bg-card);color:var(--text-main);width:80px;}
.ig-toggle-row{display:flex;align-items:center;gap:10px;padding:8px 12px;background:var(--bg-subtle);border-radius:8px;cursor:pointer;}
.ig-toggle-row:hover{background:var(--border-light);}
.ig-toggle-label{font-size:13px;font-weight:600;color:var(--text-main);flex:1;}
.ig-toggle-sub{font-size:11px;color:var(--text-muted);}
.ig-toggles{display:flex;flex-direction:column;gap:6px;margin-bottom:14px;}
.ig-section-head{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:var(--text-muted);margin:18px 0 10px;padding-bottom:5px;border-bottom:1px solid var(--border-light);}
.ig-status{font-size:12px;padding:6px 12px;border-radius:6px;display:inline-block;}
.ig-status.ok{background:rgba(52,199,89,.10);color:#1a7a30;border:1px solid rgba(52,199,89,.25);}
.ig-status.error{background:rgba(255,59,48,.08);color:#c0392b;border:1px solid rgba(255,59,48,.2);}
.ig-status.muted{background:var(--bg-subtle);color:var(--text-muted);border:1px solid var(--border-light);}
.ig-footer{display:flex;align-items:center;gap:14px;flex-wrap:wrap;padding-top:18px;margin-top:4px;border-top:1px solid var(--border-light);}
.ig-inline-pair{display:flex;gap:8px;align-items:center;}
</style>
</head>
<body class="bo">
<?php include __DIR__ . '/../inc/menu.php'; ?>

<div class="header-controls">
  <h1>🖥️ DS Controller</h1>
  <div class="header-right">
    <a href="/be/ctrl/ds-seiten.php" class="btn-secondary">← DS-Seiten</a>
  </div>
</div>

<?php if ($msg): ?>
<div class="status-banner <?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<!-- BLOCK 1 — THEME -->
<div class="dsc-block">
  <div class="dsc-block-title">🎨 Theme wählen</div>
  <div class="dsc-block-sub">Bestimmt das visuelle Erscheinungsbild aller DS-Seiten</div>
  <div class="theme-grid">
    <?php foreach ($THEMES as $key => $t):
      $isActive = ($key === $activeTheme);
      $bg   = $key==='aurora' ? '#06080e' : ($key==='flat' ? '#0a0a0a' : '#080808');
      $cbg  = $key==='glass'  ? 'rgba(255,255,255,0.07)' : ($key==='aurora' ? 'rgba(11,14,22,0.92)' : '#141414');
      $blur = $key==='glass'  ? 'blur(8px)' : 'none';
    ?>
    <form method="POST">
      <?= wcr_csrf_field() ?>
      <input type="hidden" name="action" value="theme">
      <input type="hidden" name="theme"  value="<?= $key ?>">
      <button type="submit" class="theme-card <?= $isActive ? 'theme-card--on' : '' ?>">
        <div class="tc-preview" style="background:<?= $bg ?>; font-family:'Segoe UI',system-ui">
          <?php if ($key==='aurora'): ?>
          <div style="position:absolute;inset:0;background:radial-gradient(ellipse 65% 55% at 8% 15%,rgba(1,158,227,.28) 0%,transparent 58%),radial-gradient(ellipse 50% 50% at 92% 85%,rgba(103,148,103,.22) 0%,transparent 54%);pointer-events:none"></div>
          <?php elseif ($key==='glass'): ?>
          <div style="position:absolute;inset:0;background:radial-gradient(ellipse 65% 65% at 15% 50%,rgba(103,148,103,.10) 0%,transparent 68%);pointer-events:none"></div>
          <?php endif; ?>
          <div style="position:relative;z-index:1;height:17%;display:flex;align-items:center;gap:5px;padding:0 7%;border-bottom:1px solid rgba(255,255,255,.05)">
            <div style="flex:1;height:1px;background:rgba(103,148,103,.4)"></div>
            <span style="font-size:5px;font-weight:700;letter-spacing:3px;white-space:nowrap;color:<?= $key==='aurora'?'#019ee3':'#679467' ?>● WCR ●</span>
            <div style="flex:1;height:1px;background:rgba(103,148,103,.4)"></div>
          </div>
          <div style="position:relative;z-index:1;display:grid;grid-template-columns:repeat(3,1fr);gap:3px;padding:4px 4%">
            <?php foreach(['Espresso','Latte','Cappuccino'] as $ci => $cn):
              $bar = $key==='aurora' ? ($ci%2===0 ? 'linear-gradient(90deg,#679467,#019ee3)' : 'linear-gradient(90deg,#019ee3,#679467)') : '#679467';
            ?>
            <div style="background:<?= $cbg ?>;border:1px solid rgba(255,255,255,.09);border-radius:5px;padding:5px 3px;display:flex;flex-direction:column;align-items:center;gap:2px;position:relative;overflow:hidden;<?= $blur!=='none'?'backdrop-filter:'.$blur.';-webkit-backdrop-filter:'.$blur.';':'' ?>">
              <div style="position:absolute;top:0;left:0;right:0;height:2px;background:<?= $bar ?>"></div>
              <div style="width:18px;height:18px;border-radius:50%;background:rgba(103,148,103,.12);border:1px solid rgba(103,148,103,.28);display:flex;align-items:center;justify-content:center;font-size:9px;margin-top:2px">☕</div>
              <div style="font-size:4.5px;color:rgba(238,238,238,.85)"><?= $cn ?></div>
              <div style="font-size:7px;font-weight:800;color:#fff"><?= ['2,80','3,20','3,00'][$ci] ?> €</div>
            </div>
            <?php endforeach; ?>
          </div>
          <div style="position:relative;z-index:1;padding:3px 7%">
            <?php foreach(['Erdinger · 4,50 €','Corona · 4,00 €','Augustiner · 4,20 €'] as $li): ?>
            <div style="display:flex;justify-content:space-between;font-size:5px;color:rgba(238,238,238,.65);padding:1.5px 0"><?= $li ?></div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="tc-info">
          <span class="tc-icon"><?= $t['icon'] ?></span>
          <div class="tc-text">
            <div class="tc-name"><?= $t['name'] ?></div>
            <div class="tc-sub"><?= $t['sub'] ?></div>
          </div>
          <?php if ($isActive): ?><span class="tc-badge">✓ Aktiv</span><?php endif; ?>
        </div>
        <div class="tc-desc"><?= $t['desc'] ?></div>
      </button>
    </form>
    <?php endforeach; ?>
  </div>
</div>

<!-- BLOCK 2 — EINSTELLUNGEN + VORSCHAU -->
<form method="POST" id="dsc-form">
<?= wcr_csrf_field() ?>
<input type="hidden" name="action" value="save">
<div class="dsc-2col">

  <div class="dsc-left">
    <div class="dsc-block">
      <div class="dsc-block-title">🎨 Farben</div>
      <div class="dsc-block-sub">Gelten für alle drei Themes</div>
      <div class="clr-grid">
        <?php foreach ($COLORS as $k => [$label, $desc]): ?>
        <div class="clr-row">
          <div class="clr-sw-wrap">
            <input type="color" id="cp_<?= $k ?>" value="<?= ov($opts,$k) ?>"
                   onchange="syncClr('<?= $k ?>',this.value)">
            <div class="clr-sw" id="sw_<?= $k ?>"
                 style="background:<?= ov($opts,$k) ?>"
                 onclick="document.getElementById('cp_<?= $k ?>').click()"></div>
          </div>
          <div class="clr-info">
            <div class="clr-label"><?= htmlspecialchars($label) ?></div>
            <div class="clr-desc"><?= htmlspecialchars($desc) ?></div>
          </div>
          <input type="text" class="clr-hex" id="ct_<?= $k ?>" name="<?= $k ?>"
                 value="<?= ov($opts,$k) ?>" placeholder="<?= $DEFAULTS[$k] ?>"
                 oninput="syncClrTxt('<?= $k ?>',this.value)">
        </div>
        <?php endforeach; ?>
        <div class="clr-row">
          <div class="clr-sw-wrap">
            <div class="clr-sw" style="position:relative;overflow:hidden;background:#111">
              <div style="position:absolute;inset:0;background:repeating-conic-gradient(#555 0% 25%,#333 0% 50%) 0 0/8px 8px;opacity:.5"></div>
            </div>
          </div>
          <div class="clr-info">
            <div class="clr-label">Glas-Hintergrund</div>
            <div class="clr-desc">rgba() — für Glass-Theme</div>
          </div>
          <input type="text" class="clr-hex" style="width:190px" name="clr_bg_glass"
                 value="<?= ov($opts,'clr_bg_glass') ?>" placeholder="rgba(10,14,24,0.65)">
        </div>
      </div>
    </div>

    <div class="dsc-block">
      <div class="dsc-block-title">🔤 Schriftart</div>
      <div class="font-row">
        <select name="font_family" id="font-sel" onchange="updFont(this.value)">
          <?php foreach ($FONTS as $f): ?>
          <option value="<?= htmlspecialchars($f) ?>" <?= $opts['font_family']===$f?'selected':'' ?>><?= htmlspecialchars($f) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="font-prev" id="font-prev" style="font-family:'<?= ov($opts,'font_family') ?>',system-ui">
          Wake &amp; Camp — 12,50 €
        </div>
      </div>
    </div>

    <div class="dsc-actions">
      <button type="submit" class="btn-save">💾 Einstellungen speichern</button>
      <button type="button" class="btn-reset"
              onclick="if(confirm('Alle Farben auf Standard zurücksetzen?'))document.getElementById('rst-form').submit()">
        ↩ Zurücksetzen
      </button>
    </div>
  </div>

  <div class="dsc-right">
    <div class="dsc-preview-wrap">
      <div class="prev-lbl">Landscape · 1920×1080</div>
      <div class="pv-screen" id="pv-ls">
        <div class="pv-bg" id="pv-bg"></div>
        <div class="pv-hdr">
          <div class="pv-line" id="pv-l1"></div>
          <div class="pv-hi" id="pv-hi"><div class="pv-dot" id="pv-dot"></div><span>WCR · WAKE &amp; CAMP</span></div>
          <div class="pv-line" id="pv-l2"></div>
        </div>
        <div class="pv-cards">
          <?php foreach(['Espresso','Latte Macchiato','Cappuccino'] as $i=>$n): ?>
          <div class="pv-card" id="pc-<?= $i ?>">
            <div class="pv-cbar" id="pb-<?= $i ?>"></div>
            <div class="pv-circ" id="pcirc-<?= $i ?>">☕</div>
            <div class="pv-cname" id="pcn-<?= $i ?>"><?= $n ?></div>
            <div class="pv-cprice" id="pcp-<?= $i ?>"><?= ['2,80','3,20','3,00'][$i] ?> <small>€</small></div>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="pv-list">
          <?php foreach(['Erdinger Weißbier','Corona Extra','Augustiner'] as $j=>$n): ?>
          <div class="pv-item">
            <span class="pv-pn" id="ppn-<?= $j ?>"><?= $n ?></span>
            <span class="pv-pp" id="ppp-<?= $j ?>"><?= ['4,50','4,00','4,20'][$j] ?> €</span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="prev-lbl" style="margin-top:12px">Portrait · Öffnungszeiten</div>
      <div class="pv-screen pv-portrait">
        <div class="pv-bg" id="pv-bg-p"></div>
        <div class="pv-glass" id="pv-glass">
          <?php foreach(['Mo:','Di:','Mi:','Do:','Fr:'] as $day): ?>
          <div class="pv-oh-row">
            <span class="pv-oh-day"><?= $day ?></span>
            <span class="pv-oh-time">14 – 20</span>
            <span class="pv-oh-unit">UHR</span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="prev-lbl" style="margin-top:12px">CSS (aktive Variablen)</div>
      <pre class="pv-css" id="pv-css"></pre>
    </div>
  </div>

</div>
</form>
<form method="POST" id="rst-form" style="display:none">
<?= wcr_csrf_field() ?>
<input type="hidden" name="action" value="reset">
</form>

<!-- ═══════════════════════════════════════════════════════════════
     BLOCK 3 — INSTAGRAM FEED EINSTELLUNGEN
═══════════════════════════════════════════════════════════════ -->
<form method="POST" id="ig-form">
<?= wcr_csrf_field() ?>
<input type="hidden" name="action" value="ig_save">
<div class="ig-block">
  <div class="ig-block-title">📸 Instagram Feed</div>
  <div class="ig-block-sub">Token, Quellen, Filter und Darstellungsoptionen für /instagram/ und /instagram-video/</div>

  <?php
  // Hilfsfunktion: checked
  function ig_chk($key, $default=1) { return ig_get($key, $default) ? 'checked' : ''; }
  function ig_val($key, $default='') { return htmlspecialchars(ig_get($key, $default)); }
  ?>

  <!-- Verbindung -->
  <div class="ig-section-head">🔗 Verbindung</div>
  <div class="ig-grid">
    <div class="ig-row">
      <label class="ig-label">Access Token</label>
      <span class="ig-sublabel">Meta Graph API — Long-lived token</span>
      <input type="password" name="wcr_instagram_token" class="ig-input"
             value="<?= ig_val('wcr_instagram_token') ?>" placeholder="Einfügen..." autocomplete="off">
    </div>
    <div class="ig-row">
      <label class="ig-label">Instagram User ID</label>
      <span class="ig-sublabel">Numerische ID des Business-Accounts</span>
      <input type="text" name="wcr_instagram_user_id" class="ig-input"
             value="<?= ig_val('wcr_instagram_user_id') ?>" placeholder="z.B. 17841400000000">
    </div>
  </div>
  <div style="margin-bottom:18px;">
    <span class="ig-status <?= $token_class ?>"><?= htmlspecialchars($token_status) ?></span>
  </div>

  <!-- Quellen -->
  <div class="ig-section-head">📡 Quellen &amp; Filter</div>
  <div class="ig-toggles">
    <label class="ig-toggle-row">
      <input type="checkbox" name="wcr_instagram_use_tagged" <?= ig_chk('wcr_instagram_use_tagged') ?>>
      <div>
        <div class="ig-toggle-label">Tagged (@mention)</div>
        <div class="ig-toggle-sub">Posts in denen der Account getaggt wurde</div>
      </div>
    </label>
    <label class="ig-toggle-row">
      <input type="checkbox" name="wcr_instagram_use_hashtag" <?= ig_chk('wcr_instagram_use_hashtag') ?>>
      <div>
        <div class="ig-toggle-label">Hashtag-Feed</div>
        <div class="ig-toggle-sub">Posts mit den unten definierten Hashtags</div>
      </div>
    </label>
  </div>
  <div class="ig-grid">
    <div class="ig-row">
      <label class="ig-label">Hashtags</label>
      <span class="ig-sublabel">Ohne #, ein Hashtag pro Zeile</span>
      <textarea name="wcr_instagram_hashtags" class="ig-textarea"
                placeholder="wakecampruhlsdorf"><?= ig_val('wcr_instagram_hashtags', 'wakecampruhlsdorf') ?></textarea>
    </div>
    <div class="ig-row">
      <label class="ig-label">Ausgeschlossene Accounts</label>
      <span class="ig-sublabel">Ein Username pro Zeile, ohne @</span>
      <textarea name="wcr_instagram_excluded" class="ig-textarea"
                placeholder="spamaccount"><?= ig_val('wcr_instagram_excluded') ?></textarea>
    </div>
    <div class="ig-row">
      <label class="ig-label">Max. Post-Alter</label>
      <span class="ig-sublabel">Ältere Posts werden ignoriert</span>
      <div class="ig-inline-pair">
        <input type="number" name="wcr_instagram_max_age_value" class="ig-num"
               value="<?= ig_val('wcr_instagram_max_age_value', 30) ?>" min="0">
        <select name="wcr_instagram_max_age_unit" class="ig-select">
          <?php foreach(['days'=>'Tage','weeks'=>'Wochen','months'=>'Monate'] as $v=>$l): ?>
          <option value="<?= $v ?>" <?= ig_get('wcr_instagram_max_age_unit','days')===$v?'selected':'' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="ig-row">
      <label class="ig-label">Mindest-Likes</label>
      <span class="ig-sublabel">0 = alle Posts anzeigen</span>
      <input type="number" name="wcr_instagram_min_likes" class="ig-num"
             value="<?= ig_val('wcr_instagram_min_likes', 0) ?>" min="0">
    </div>
  </div>

  <!-- Grid-Einstellungen -->
  <div class="ig-section-head">🖼️ Grid-Darstellung</div>
  <div class="ig-grid">
    <div class="ig-row">
      <label class="ig-label">Max. Posts im Grid</label>
      <span class="ig-sublabel">Anzahl sichtbarer Posts (2×2 / 2×3 / 2×4)</span>
      <select name="wcr_instagram_max_posts" class="ig-select">
        <?php foreach([4,6,8] as $v): ?>
        <option value="<?= $v ?>" <?= ig_get('wcr_instagram_max_posts',8)==$v?'selected':'' ?>><?= $v ?> Posts</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="ig-row">
      <label class="ig-label">Auto-Refresh</label>
      <span class="ig-sublabel">Grid neu laden alle X Minuten</span>
      <select name="wcr_instagram_refresh" class="ig-select">
        <?php foreach([5,10,15,30] as $v): ?>
        <option value="<?= $v ?>" <?= ig_get('wcr_instagram_refresh',10)==$v?'selected':'' ?>><?= $v ?> Min</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="ig-row">
      <label class="ig-label">NEU-Badge (Stunden)</label>
      <span class="ig-sublabel">Posts neuer als X Stunden erhalten Badge</span>
      <input type="number" name="wcr_instagram_new_hours" class="ig-num"
             value="<?= ig_val('wcr_instagram_new_hours', 2) ?>" min="1" max="72">
    </div>
    <div class="ig-row">
      <label class="ig-label">Username-Overlay</label>
      <span class="ig-sublabel">@Username + Zeitstempel auf jedem Post</span>
      <label class="ig-toggle-row" style="margin-top:4px;">
        <input type="checkbox" name="wcr_instagram_show_user" <?= ig_chk('wcr_instagram_show_user') ?>>
        <div><div class="ig-toggle-label">@Username anzeigen</div></div>
      </label>
    </div>
  </div>

  <!-- Video -->
  <div class="ig-section-head">🎬 Video-Player</div>
  <div class="ig-grid">
    <div class="ig-row">
      <label class="ig-label">Video-Pool</label>
      <span class="ig-sublabel">Aus den X neuesten Videos wird zufällig gewählt</span>
      <select name="wcr_instagram_video_pool" class="ig-select">
        <?php foreach([5,10,15,20] as $v): ?>
        <option value="<?= $v ?>" <?= ig_get('wcr_instagram_video_pool',10)==$v?'selected':'' ?>><?= $v ?> Videos</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="ig-row">
      <label class="ig-label">Clips pro Session</label>
      <span class="ig-sublabel">Wie viele Videos hintereinander abgespielt werden</span>
      <select name="wcr_instagram_video_count" class="ig-select">
        <?php for ($v=1;$v<=6;$v++): ?>
        <option value="<?= $v ?>" <?= ig_get('wcr_instagram_video_count',3)==$v?'selected':'' ?>><?= $v ?> Clips</option>
        <?php endfor; ?>
      </select>
    </div>
  </div>

  <!-- CTA & QR -->
  <div class="ig-section-head">📢 CTA &amp; QR-Code</div>
  <div class="ig-grid">
    <div class="ig-row">
      <label class="ig-label">CTA-Text</label>
      <span class="ig-sublabel">Unten am Bildschirm eingeblendet</span>
      <input type="text" name="wcr_instagram_cta_text" class="ig-input"
             value="<?= ig_val('wcr_instagram_cta_text', 'Markiere uns auf Instagram und erscheine hier! 📸') ?>">
    </div>
    <div class="ig-row">
      <label class="ig-label">QR-Code Ziel-URL</label>
      <span class="ig-sublabel">z.B. https://instagram.com/wakecamp</span>
      <input type="url" name="wcr_instagram_qr_url" class="ig-input"
             value="<?= ig_val('wcr_instagram_qr_url') ?>" placeholder="https://instagram.com/...">
    </div>
    <div class="ig-row">
      <label class="ig-label" style="margin-bottom:6px;">Einblenden</label>
      <label class="ig-toggle-row">
        <input type="checkbox" name="wcr_instagram_cta_active" <?= ig_chk('wcr_instagram_cta_active') ?>>
        <div><div class="ig-toggle-label">CTA-Leiste anzeigen</div></div>
      </label>
      <label class="ig-toggle-row" style="margin-top:5px;">
        <input type="checkbox" name="wcr_instagram_qr_active" <?= ig_chk('wcr_instagram_qr_active', 0) ?>>
        <div><div class="ig-toggle-label">QR-Code anzeigen</div></div>
      </label>
    </div>
    <div class="ig-row">
      <label class="ig-label">Standort-Label</label>
      <span class="ig-sublabel">Wird im Overlay angezeigt</span>
      <input type="text" name="wcr_instagram_location_label" class="ig-input"
             value="<?= ig_val('wcr_instagram_location_label') ?>" placeholder="Wake &amp; Camp Ruhlsdorf">
    </div>
  </div>

  <!-- Extras -->
  <div class="ig-section-head">⭐ Extras</div>
  <div class="ig-toggles">
    <label class="ig-toggle-row">
      <input type="checkbox" name="wcr_instagram_weekly_best" <?= ig_chk('wcr_instagram_weekly_best', 0) ?>>
      <div>
        <div class="ig-toggle-label">Post der Woche</div>
        <div class="ig-toggle-sub">Sonntags automatisch Fullscreen-Highlight des beliebtesten Posts</div>
      </div>
    </label>
  </div>

  <!-- Footer -->
  <div class="ig-footer">
    <button type="submit" class="btn-save" style="flex:unset;padding:11px 28px;">💾 Speichern</button>
    <span class="ig-status <?= $token_class ?>"><?= htmlspecialchars($token_status) ?></span>
  </div>
</div>
</form>

<style>
.dsc-block{background:var(--bg-card);border-radius:var(--radius);box-shadow:var(--shadow);padding:22px 24px;margin-bottom:20px;}
.dsc-block-title{font-size:14px;font-weight:700;margin:0 0 3px;}
.dsc-block-sub{font-size:12px;color:var(--text-muted);margin:0 0 16px;}
.theme-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;}
.theme-card{all:unset;cursor:pointer;display:flex;flex-direction:column;border:2px solid var(--border-light);border-radius:12px;overflow:hidden;background:var(--bg-subtle);transition:border-color .18s,transform .15s;width:100%;}
.theme-card:hover{border-color:var(--border);transform:translateY(-2px);}
.theme-card--on{border-color:var(--primary)!important;box-shadow:0 0 0 3px rgba(0,113,227,.12)!important;}
.tc-preview{aspect-ratio:16/9;overflow:hidden;position:relative;}
.tc-info{display:flex;align-items:center;gap:10px;padding:10px 14px 4px;}
.tc-icon{font-size:18px;flex-shrink:0;}
.tc-name{font-size:14px;font-weight:700;color:var(--text-main);}
.tc-sub{font-size:11px;color:var(--text-muted);}
.tc-badge{margin-left:auto;padding:3px 10px;border-radius:20px;background:rgba(52,199,89,.12);color:#1a7a30;border:1px solid rgba(52,199,89,.3);font-size:10px;font-weight:700;flex-shrink:0;}
.tc-desc{font-size:11px;color:var(--text-muted);padding:0 14px 12px;line-height:1.45;}
.dsc-2col{display:grid;grid-template-columns:1fr 340px;gap:24px;align-items:start;margin-top:20px;}
.dsc-left{display:flex;flex-direction:column;}
.dsc-right{position:sticky;top:76px;}
.clr-grid{display:flex;flex-direction:column;gap:9px;}
.clr-row{display:flex;align-items:center;gap:11px;padding:8px 11px;border-radius:8px;background:var(--bg-subtle);}
.clr-row:hover{background:var(--border-light);}
.clr-sw-wrap{position:relative;flex-shrink:0;}
.clr-sw-wrap input[type=color]{position:absolute;opacity:0;width:0;height:0;}
.clr-sw{width:36px;height:36px;border-radius:7px;cursor:pointer;border:2px solid rgba(0,0,0,.08);box-shadow:0 2px 6px rgba(0,0,0,.14);transition:transform .14s;}
.clr-sw:hover{transform:scale(1.1);}
.clr-info{flex:1;min-width:0;}
.clr-label{font-size:13px;font-weight:600;color:var(--text-main);}
.clr-desc{font-size:11px;color:var(--text-muted);margin-top:1px;}
.clr-hex{width:100px;padding:6px 8px;border:1px solid var(--border);border-radius:6px;font-family:monospace;font-size:12px;background:var(--bg-card);color:var(--text-main);}
.clr-hex:focus{outline:none;border-color:var(--primary);}
.font-row{display:flex;gap:12px;align-items:center;}
.font-row select{padding:8px 11px;border:1px solid var(--border);border-radius:8px;font-size:13px;background:var(--bg-card);color:var(--text-main);}
.font-prev{flex:1;padding:11px 14px;background:#12121e;color:#eee;border-radius:8px;font-size:18px;text-align:center;}
.dsc-actions{display:flex;gap:12px;padding-bottom:4px;}
.btn-save{flex:1;padding:13px;font-size:15px;font-weight:700;background:var(--primary);color:#fff;border:none;border-radius:10px;cursor:pointer;}
.btn-save:hover{opacity:.86;}
.btn-reset{padding:13px 16px;font-size:13px;font-weight:600;background:var(--bg-subtle);color:var(--text-main);border:1px solid var(--border);border-radius:10px;cursor:pointer;}
.btn-reset:hover{background:#fff0f0;color:#c0392b;border-color:#ffd0cc;}
.pv-screen{position:relative;width:100%;aspect-ratio:16/9;border-radius:8px;overflow:hidden;border:2px solid var(--border);}
.pv-portrait{aspect-ratio:9/16;max-height:220px;width:auto;}
.pv-bg{position:absolute;inset:0;z-index:0;}
.pv-hdr{position:relative;z-index:1;height:17%;display:flex;align-items:center;gap:5px;padding:0 7%;border-bottom:1px solid rgba(255,255,255,.06);}
.pv-line{flex:1;height:1px;}
.pv-hi{display:flex;align-items:center;gap:4px;font-size:5.5px;font-weight:700;text-transform:uppercase;letter-spacing:3px;white-space:nowrap;}
.pv-dot{width:5px;height:5px;border-radius:50%;flex-shrink:0;}
.pv-cards{position:relative;z-index:1;display:grid;grid-template-columns:repeat(3,1fr);gap:3px;padding:4px 4%;}
.pv-card{border-radius:5px;padding:5px 3px;display:flex;flex-direction:column;align-items:center;gap:2px;position:relative;overflow:hidden;border:1px solid rgba(255,255,255,.08);}
.pv-cbar{position:absolute;top:0;left:0;right:0;height:2px;border-radius:5px 5px 0 0;}
.pv-circ{width:18px;height:18px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:9px;margin-top:2px;}
.pv-cname{font-size:4.5px;text-align:center;}
.pv-cprice{font-size:7px;font-weight:800;}
.pv-list{position:relative;z-index:1;padding:3px 6%;display:flex;flex-direction:column;gap:2px;}
.pv-item{display:flex;justify-content:space-between;font-size:5.5px;}
.pv-pp{font-weight:700;}
.pv-glass{position:relative;z-index:1;margin:8px;border-radius:6px;padding:8px 10px;}
.pv-oh-row{display:flex;align-items:baseline;gap:5px;padding:4px 0;border-bottom:1px solid rgba(255,255,255,.06);}
.pv-oh-row:last-child{border-bottom:none;}
.pv-oh-day{font-size:8px;font-weight:800;width:20px;text-align:right;opacity:.55;}
.pv-oh-time{font-size:10px;font-weight:600;flex:1;text-align:center;}
.pv-oh-unit{font-size:5px;font-weight:700;text-transform:uppercase;letter-spacing:1px;opacity:.5;}
.pv-css{background:#0d1117;color:#7ee787;font-size:9px;padding:10px 12px;border-radius:7px;overflow-x:auto;white-space:pre;line-height:1.6;max-height:160px;overflow-y:auto;margin:0;}
@media(max-width:1200px){.dsc-2col{grid-template-columns:1fr;}.dsc-right{position:static;}}
@media(max-width:900px){.theme-grid{grid-template-columns:1fr;}}
</style>

<script>
var G={
  green:  "<?= addslashes($opts['clr_green']) ?>",
  blue:   "<?= addslashes($opts['clr_blue']) ?>",
  white:  "<?= addslashes($opts['clr_white']) ?>",
  text:   "<?= addslashes($opts['clr_text']) ?>",
  muted:  "<?= addslashes($opts['clr_muted']) ?>",
  bg:     "<?= addslashes($opts['clr_bg']) ?>",
  bgDark: "<?= addslashes($opts['clr_bg_dark']) ?>",
  bgGlass:"<?= addslashes($opts['clr_bg_glass']) ?>",
  font:   "<?= addslashes($opts['font_family']) ?>"
};
var THEME="<?= $activeTheme ?>";
var KEY_MAP={clr_green:'green',clr_blue:'blue',clr_white:'white',clr_text:'text',clr_muted:'muted',clr_bg:'bg',clr_bg_dark:'bgDark'};
function syncClr(k,v){if(KEY_MAP[k])G[KEY_MAP[k]]=v;var s=document.getElementById('sw_'+k);if(s)s.style.background=v;var t=document.getElementById('ct_'+k);if(t)t.value=v;render();}
function syncClrTxt(k,v){if(KEY_MAP[k])G[KEY_MAP[k]]=v;try{var s=document.getElementById('sw_'+k);if(s)s.style.background=v;}catch(e){}if(/^#[0-9a-fA-F]{6}$/.test(v)){var c=document.getElementById('cp_'+k);if(c)c.value=v;}render();}
function updFont(f){G.font=f;document.getElementById('font-prev').style.fontFamily="'"+f+"',system-ui";if(f!=='Segoe UI'){var l=document.getElementById('gf-link');if(l)l.href='https://fonts.googleapis.com/css2?family='+encodeURIComponent(f)+':wght@400;600;700;800&display=swap';}render();}
function h2r(h,a){if(!h||h[0]!=='#')return'rgba(128,128,128,'+a+')';var r=parseInt(h.slice(1,3),16)||0,g=parseInt(h.slice(3,5),16)||0,b=parseInt(h.slice(5,7),16)||0;return'rgba('+r+','+g+','+b+','+a+')';}
function render(){
  var font="'"+G.font+"',system-ui,sans-serif",glass=THEME==='glass',aurora=THEME==='aurora';
  var bg=document.getElementById('pv-bg');
  if(bg){bg.style.background=G.bg;bg.style.backgroundImage=aurora?'radial-gradient(ellipse 55% 55% at 5% 10%,'+h2r(G.blue,.2)+' 0%,transparent 58%),radial-gradient(ellipse 45% 45% at 92% 88%,'+h2r(G.green,.15)+' 0%,transparent 54%)':(glass?'radial-gradient(ellipse 65% 65% at 15% 50%,'+h2r(G.green,.08)+' 0%,transparent 68%)':'none');}
  var bgp=document.getElementById('pv-bg-p');if(bgp){bgp.style.background=G.bg;bgp.style.backgroundImage='none';}
  var hi=document.getElementById('pv-hi');if(hi){hi.style.color=aurora?G.blue:G.green;hi.style.fontFamily=font;}
  var dot=document.getElementById('pv-dot');if(dot)dot.style.background=aurora?G.blue:G.green;
  ['pv-l1','pv-l2'].forEach(function(id,i){var el=document.getElementById(id),c=aurora?G.blue:G.green;if(el)el.style.background=i===0?'linear-gradient(90deg,transparent,'+c+'55)':'linear-gradient(90deg,'+c+'55,transparent)';});
  for(var i=0;i<3;i++){
    var card=document.getElementById('pc-'+i),cardBg=glass?'rgba(255,255,255,0.06)':(aurora?h2r(G.bgDark,.92):G.bgDark);
    if(card)card.style.background=cardBg;
    var bar=document.getElementById('pb-'+i);if(bar)bar.style.background=aurora?(i%2===0?'linear-gradient(90deg,'+G.green+','+G.blue+')':'linear-gradient(90deg,'+G.blue+','+G.green+')'):G.green;
    var circ=document.getElementById('pcirc-'+i);if(circ){circ.style.background=h2r(G.green,.1);circ.style.borderColor=h2r(G.green,.25);}
    var cn=document.getElementById('pcn-'+i);if(cn){cn.style.color=G.text;cn.style.fontFamily=font;}
    var cp=document.getElementById('pcp-'+i);if(cp){cp.style.color=G.white;cp.style.fontFamily=font;}
  }
  for(var j=0;j<3;j++){var pn=document.getElementById('ppn-'+j),pp=document.getElementById('ppp-'+j);if(pn){pn.style.color=G.text;pn.style.fontFamily=font;}if(pp){pp.style.color=G.white;pp.style.fontFamily=font;}}
  var gl=document.getElementById('pv-glass');
  if(gl){gl.style.background=glass?'radial-gradient(circle at top left,'+h2r(G.green,.24)+' 0%,'+h2r(G.blue,.14)+' 40%,rgba(0,0,0,.45) 100%)':(aurora?h2r(G.bgDark,.9):G.bgDark);gl.style.border='1px solid '+(aurora?h2r(G.blue,.22):'rgba(255,255,255,0.09)');}
  document.querySelectorAll('.pv-oh-day').forEach(function(el){el.style.color=G.muted;});
  document.querySelectorAll('.pv-oh-time').forEach(function(el){el.style.color=G.white;el.style.fontFamily=font;});
  var out=document.getElementById('pv-css');
  if(out)out.textContent=':root {\n  --clr-green:  '+G.green+';\n  --clr-blue:   '+G.blue+';\n  --clr-text:   '+G.text+';\n  --clr-muted:  '+G.muted+';\n  --clr-bg:     '+G.bg+';\n  --font-main:  '+font+';\n}';
}
document.addEventListener('DOMContentLoaded',function(){render();});
</script>

<?php include __DIR__ . '/../inc/debug.php'; ?>
</body>
</html>
