<?php
/**
 * ctrl/instagram.php — Instagram Feed Einstellungen
 * v1.2: Verbindung + Debug in Settings-Popup
 */
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';
wcr_require('view_ds');

if (!defined('DSC_WP_API_BASE')) define('DSC_WP_API_BASE', 'https://wcr-webpage.de/wp-json/wakecamp/v1');
if (!defined('DSC_WP_SECRET'))   define('DSC_WP_SECRET',   'WCR_DS_2026');

function ig_curl(string $url, ?array $postData = null): array {
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
    return ['ok'=>($code===200&&!$err),'code'=>$code,'json'=>json_decode($body?:'',true),'err'=>$err?:($code!==200?"HTTP $code":'')];
}

function ig_opt(string $key, $default = '') {
    static $cache = null;
    if ($cache === null) {
        $r = ig_curl(DSC_WP_API_BASE . '/ds-settings?wcr_secret=' . urlencode(DSC_WP_SECRET));
        $cache = ($r['ok'] && isset($r['json']['instagram'])) ? $r['json']['instagram'] : [];
    }
    return $cache[$key] ?? $default;
}
function ig_val2(string $key, $default = ''): string { return htmlspecialchars(ig_opt($key, $default)); }
function ig_chk2(string $key, $default = 1): string  { return ig_opt($key, $default) ? 'checked' : ''; }

// ── POST-Handler
$msg = ''; $msgType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!wcr_verify_csrf(false)) {
        $msg = 'Sicherheitsprüfung fehlgeschlagen. Bitte Seite neu laden.';
        $msgType = 'error';
    } else {
        $action = trim($_POST['action'] ?? '');

        if ($action === 'ig_save_connection') {
            $token = trim((string)($_POST['wcr_instagram_token'] ?? ''));
            $user  = trim((string)($_POST['wcr_instagram_user_id'] ?? ''));
            $payload = ['wcr_secret'=>DSC_WP_SECRET,'action'=>'ig_save','options'=>[
                'wcr_instagram_user_id'     => $user,
                'wcr_instagram_flush_cache' => 1,
            ]];
            if ($token !== '') $payload['options']['wcr_instagram_token'] = $token;
            $r = ig_curl(DSC_WP_API_BASE . '/ds-settings', $payload);
            $msg = ($r['ok'] && !empty($r['json']['ok']))
                ? '🔐 Instagram-Verbindung gespeichert.'
                : 'Fehler: ' . ($r['err'] ?: 'Unbekannt');
            $msgType = ($r['ok'] && !empty($r['json']['ok'])) ? 'ok' : 'error';
        }

        if ($action === 'ig_save_settings') {
            $ig_fields = [
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
            $payload = ['wcr_secret'=>DSC_WP_SECRET,'action'=>'ig_save','options'=>[]];
            foreach ($ig_fields as $key => $fn)  { if (isset($_POST[$key])) $payload['options'][$key] = $fn($_POST[$key]); }
            foreach ($ig_toggles as $t)          { $payload['options'][$t] = isset($_POST[$t]) ? 1 : 0; }
            $payload['options']['wcr_instagram_flush_cache'] = 1;
            $r = ig_curl(DSC_WP_API_BASE . '/ds-settings', $payload);
            $msg = ($r['ok'] && !empty($r['json']['ok']))
                ? '📸 Instagram-Einstellungen gespeichert & Cache geleert.'
                : 'Fehler: ' . ($r['err'] ?: 'Unbekannt');
            $msgType = ($r['ok'] && !empty($r['json']['ok'])) ? 'ok' : 'error';
        }
    }
}

// ── Token-Status
$ig_token   = ig_opt('wcr_instagram_token', '');
$ig_user_id = ig_opt('wcr_instagram_user_id', '');
$token_status = '⚪ Kein Token hinterlegt';
$token_class  = 'muted';
if ($ig_token && $ig_user_id) {
    $chk = ig_curl("https://graph.instagram.com/me?fields=id,username&access_token={$ig_token}");
    if ($chk['ok'] && !empty($chk['json']['id'])) {
        $token_status = '✅ Verbunden als @' . ($chk['json']['username'] ?? $chk['json']['id']);
        $token_class  = 'ok';
    } else {
        $token_status = '❌ Token ungültig oder abgelaufen';
        $token_class  = 'error';
    }
}

// ── REST-Feed Status
$ig_rest = ig_curl(DSC_WP_API_BASE . '/instagram');
$ig_rest_posts = 0; $ig_rest_info = 'REST-Feed aktuell nicht erreichbar'; $ig_rest_preview = '[]';
if ($ig_rest['ok'] && is_array($ig_rest['json'])) {
    $ig_rest_posts   = count($ig_rest['json']);
    $ig_rest_info    = 'REST-Feed liefert aktuell ' . $ig_rest_posts . ' Beitrag/Beiträge';
    $ig_rest_preview = json_encode(array_slice($ig_rest['json'], 0, 2), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} elseif ($ig_rest['err']) {
    $ig_rest_info = $ig_rest['err'];
}

$csrf = wcr_csrf_token();
?>
<!DOCTYPE html>
<html lang="de">
<head><meta charset="UTF-8"><title>Instagram Feed</title></head>
<body class="bo">
<?php include __DIR__ . '/../inc/menu.php'; ?>

<!-- ── Settings-Popup ──────────────────────────────────────────────────── -->
<div id="ig-settings-overlay" onclick="if(event.target===this)closeIgSettings()">
  <div id="ig-settings-popup">
    <div id="ig-settings-header">
      <h2>⚙️ Verbindung &amp; Debug</h2>
      <button id="ig-settings-close" onclick="closeIgSettings()" title="Schließen">✕</button>
    </div>
    <div id="ig-settings-body">

      <!-- Verbindung -->
      <div class="ig-block">
        <div class="ig-block-title">🔗 Verbindung</div>
        <div class="ig-block-sub">Meta Graph API — Long-lived Access Token + Instagram Business Account ID</div>
        <form method="POST">
          <?= wcr_csrf_field() ?>
          <input type="hidden" name="action" value="ig_save_connection">
          <div class="ig-grid">
            <div class="ig-row">
              <label class="ig-label">Access Token</label>
              <span class="ig-sublabel">Long-lived token. Leer lassen = bestehenden Token behalten.</span>
              <input type="password" name="wcr_instagram_token" class="ig-input"
                     value="" placeholder="<?= $ig_token ? 'Vorhandener Token bleibt gespeichert' : 'Neuen Token einfügen...' ?>" autocomplete="off">
            </div>
            <div class="ig-row">
              <label class="ig-label">Instagram User ID</label>
              <span class="ig-sublabel">Numerische ID des Business-Accounts</span>
              <input type="text" name="wcr_instagram_user_id" class="ig-input"
                     value="<?= ig_val2('wcr_instagram_user_id') ?>" placeholder="z.B. 17841400000000">
            </div>
          </div>
          <div class="ig-footer" style="margin-top:0;padding-top:0;border-top:none;margin-bottom:16px;">
            <button type="submit" class="btn-upload">🔐 Verbindung speichern</button>
            <span class="ig-status <?= $token_class ?>"><?= htmlspecialchars($token_status) ?></span>
          </div>
          <div class="ig-debug">
            <div class="ig-debug-card">
              <div class="ig-debug-title">Verbindungsstatus</div>
              <p class="ig-debug-text"><?= htmlspecialchars($token_status) ?></p>
            </div>
            <div class="ig-debug-card">
              <div class="ig-debug-title">REST-Feed-Status</div>
              <p class="ig-debug-text"><?= htmlspecialchars($ig_rest_info) ?></p>
            </div>
          </div>
        </form>
      </div>

      <!-- Debug -->
      <div class="ig-block">
        <div class="ig-block-title">🧪 Debug</div>
        <div class="ig-block-sub">Aktuelle Feed-Daten und Hinweise</div>
        <div class="ig-debug">
          <div class="ig-debug-card">
            <div class="ig-debug-title">Feed-Vorschau</div>
            <p class="ig-debug-text">Erste 2 Einträge aus <code>/wp-json/wakecamp/v1/instagram</code></p>
            <pre class="ig-debug-code"><?= htmlspecialchars((string)$ig_rest_preview) ?></pre>
          </div>
          <div class="ig-debug-card">
            <div class="ig-debug-title">Hinweise</div>
            <p class="ig-debug-text">Der Token bleibt gespeichert bis du ihn oben bewusst neu setzt.</p>
            <p class="ig-debug-text" style="margin-top:8px;">Wenn der Feed hier Daten zeigt, aber <code>/insta/</code> leer bleibt, liegt der Fehler im Frontend.</p>
          </div>
        </div>
      </div>

    </div><!-- /ig-settings-body -->
  </div>
</div>

<!-- ── Header ──────────────────────────────────────────────────────────── -->
<div class="header-controls">
  <h1>📸 Instagram Feed</h1>
  <div style="display:flex;align-items:center;gap:8px;">
    <a href="/be/ctrl/ds-settings.php" class="btn-secondary">← DS Controller</a>
    <button class="settings-btn" onclick="openIgSettings()">⚙️ Verbindung &amp; Debug</button>
  </div>
</div>

<?php if ($msg): ?>
<div class="status-banner <?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<!-- ── Feed-Einstellungen ──────────────────────────────────────────────── -->
<form method="POST">
  <?= wcr_csrf_field() ?>
  <input type="hidden" name="action" value="ig_save_settings">

  <!-- Quellen & Filter -->
  <div class="ig-block">
    <div class="ig-block-title">📡 Quellen &amp; Filter</div>
    <div class="ig-block-sub">Welche Posts in den Feed aufgenommen werden</div>
    <div class="ig-toggles">
      <label class="ig-toggle-row">
        <input type="checkbox" name="wcr_instagram_use_tagged" <?= ig_chk2('wcr_instagram_use_tagged') ?>>
        <div><div class="ig-toggle-label">Tagged (@mention)</div><div class="ig-toggle-sub">Posts in denen der Account getaggt wurde</div></div>
      </label>
      <label class="ig-toggle-row">
        <input type="checkbox" name="wcr_instagram_use_hashtag" <?= ig_chk2('wcr_instagram_use_hashtag') ?>>
        <div><div class="ig-toggle-label">Hashtag-Feed</div><div class="ig-toggle-sub">Posts mit den unten definierten Hashtags</div></div>
      </label>
    </div>
    <div class="ig-grid">
      <div class="ig-row">
        <label class="ig-label">Hashtags</label>
        <span class="ig-sublabel">Ohne #, ein Hashtag pro Zeile</span>
        <textarea name="wcr_instagram_hashtags" class="ig-textarea" placeholder="wakecampruhlsdorf"><?= ig_val2('wcr_instagram_hashtags','wakecampruhlsdorf') ?></textarea>
      </div>
      <div class="ig-row">
        <label class="ig-label">Ausgeschlossene Accounts</label>
        <span class="ig-sublabel">Ein Username pro Zeile, ohne @</span>
        <textarea name="wcr_instagram_excluded" class="ig-textarea" placeholder="spamaccount"><?= ig_val2('wcr_instagram_excluded') ?></textarea>
      </div>
      <div class="ig-row">
        <label class="ig-label">Max. Post-Alter</label>
        <span class="ig-sublabel">Ältere Posts werden ignoriert</span>
        <div class="ig-inline-pair">
          <input type="number" name="wcr_instagram_max_age_value" class="ig-num" value="<?= ig_val2('wcr_instagram_max_age_value',30) ?>" min="0">
          <select name="wcr_instagram_max_age_unit" class="ig-select">
            <?php foreach(['days'=>'Tage','weeks'=>'Wochen','months'=>'Monate'] as $v=>$l): ?>
            <option value="<?= $v ?>" <?= ig_opt('wcr_instagram_max_age_unit','days')===$v?'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="ig-row">
        <label class="ig-label">Mindest-Likes</label>
        <span class="ig-sublabel">0 = alle Posts anzeigen</span>
        <input type="number" name="wcr_instagram_min_likes" class="ig-num" value="<?= ig_val2('wcr_instagram_min_likes',0) ?>" min="0">
      </div>
    </div>
  </div>

  <!-- Grid -->
  <div class="ig-block">
    <div class="ig-block-title">🖼️ Grid-Darstellung</div>
    <div class="ig-block-sub">Anzahl, Refresh und Badges</div>
    <div class="ig-grid">
      <div class="ig-row">
        <label class="ig-label">Max. Posts im Grid</label>
        <span class="ig-sublabel">2×2 / 2×3 / 2×4</span>
        <select name="wcr_instagram_max_posts" class="ig-select">
          <?php foreach([4,6,8] as $v): ?>
          <option value="<?= $v ?>" <?= ig_opt('wcr_instagram_max_posts',8)==$v?'selected':'' ?>><?= $v ?> Posts</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="ig-row">
        <label class="ig-label">Auto-Refresh</label>
        <span class="ig-sublabel">Grid neu laden alle X Minuten</span>
        <select name="wcr_instagram_refresh" class="ig-select">
          <?php foreach([5,10,15,30] as $v): ?>
          <option value="<?= $v ?>" <?= ig_opt('wcr_instagram_refresh',10)==$v?'selected':'' ?>><?= $v ?> Min</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="ig-row">
        <label class="ig-label">NEU-Badge (Stunden)</label>
        <span class="ig-sublabel">Posts neuer als X Stunden erhalten Badge</span>
        <input type="number" name="wcr_instagram_new_hours" class="ig-num" value="<?= ig_val2('wcr_instagram_new_hours',2) ?>" min="1" max="72">
      </div>
      <div class="ig-row">
        <label class="ig-label">@Username-Overlay</label>
        <span class="ig-sublabel">Username + Zeitstempel auf jedem Post</span>
        <label class="ig-toggle-row" style="margin-top:4px;">
          <input type="checkbox" name="wcr_instagram_show_user" <?= ig_chk2('wcr_instagram_show_user') ?>>
          <div><div class="ig-toggle-label">@Username anzeigen</div></div>
        </label>
      </div>
    </div>
  </div>

  <!-- Video -->
  <div class="ig-block">
    <div class="ig-block-title">🎬 Video-Player</div>
    <div class="ig-block-sub">Einstellungen für /instagram-video/</div>
    <div class="ig-grid">
      <div class="ig-row">
        <label class="ig-label">Video-Pool</label>
        <span class="ig-sublabel">Aus den X neuesten Videos wird zufällig gewählt</span>
        <select name="wcr_instagram_video_pool" class="ig-select">
          <?php foreach([5,10,15,20] as $v): ?>
          <option value="<?= $v ?>" <?= ig_opt('wcr_instagram_video_pool',10)==$v?'selected':'' ?>><?= $v ?> Videos</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="ig-row">
        <label class="ig-label">Clips pro Session</label>
        <span class="ig-sublabel">Wie viele Videos hintereinander abgespielt werden</span>
        <select name="wcr_instagram_video_count" class="ig-select">
          <?php for($v=1;$v<=6;$v++): ?>
          <option value="<?= $v ?>" <?= ig_opt('wcr_instagram_video_count',3)==$v?'selected':'' ?>><?= $v ?> Clips</option>
          <?php endfor; ?>
        </select>
      </div>
    </div>
  </div>

  <!-- CTA & QR -->
  <div class="ig-block">
    <div class="ig-block-title">📢 CTA &amp; QR-Code</div>
    <div class="ig-block-sub">Call-to-Action Leiste und QR-Code Overlay</div>
    <div class="ig-grid">
      <div class="ig-row">
        <label class="ig-label">CTA-Text</label>
        <span class="ig-sublabel">Unten am Bildschirm eingeblendet</span>
        <input type="text" name="wcr_instagram_cta_text" class="ig-input"
               value="<?= ig_val2('wcr_instagram_cta_text','Markiere uns auf Instagram und erscheine hier! 📸') ?>">
      </div>
      <div class="ig-row">
        <label class="ig-label">QR-Code Ziel-URL</label>
        <span class="ig-sublabel">z.B. https://instagram.com/wakecamp</span>
        <input type="url" name="wcr_instagram_qr_url" class="ig-input"
               value="<?= ig_val2('wcr_instagram_qr_url') ?>" placeholder="https://instagram.com/...">
      </div>
      <div class="ig-row">
        <label class="ig-label" style="margin-bottom:6px;">Einblenden</label>
        <label class="ig-toggle-row">
          <input type="checkbox" name="wcr_instagram_cta_active" <?= ig_chk2('wcr_instagram_cta_active') ?>>
          <div><div class="ig-toggle-label">CTA-Leiste anzeigen</div></div>
        </label>
        <label class="ig-toggle-row" style="margin-top:5px;">
          <input type="checkbox" name="wcr_instagram_qr_active" <?= ig_chk2('wcr_instagram_qr_active',0) ?>>
          <div><div class="ig-toggle-label">QR-Code anzeigen</div></div>
        </label>
      </div>
      <div class="ig-row">
        <label class="ig-label">Standort-Label</label>
        <span class="ig-sublabel">Wird im Overlay angezeigt</span>
        <input type="text" name="wcr_instagram_location_label" class="ig-input"
               value="<?= ig_val2('wcr_instagram_location_label') ?>" placeholder="Wake &amp; Camp Ruhlsdorf">
      </div>
    </div>
  </div>

  <!-- Extras -->
  <div class="ig-block">
    <div class="ig-block-title">⭐ Extras</div>
    <div class="ig-block-sub">Optionale Features</div>
    <div class="ig-toggles">
      <label class="ig-toggle-row">
        <input type="checkbox" name="wcr_instagram_weekly_best" <?= ig_chk2('wcr_instagram_weekly_best',0) ?>>
        <div>
          <div class="ig-toggle-label">Post der Woche</div>
          <div class="ig-toggle-sub">Sonntags automatisch Fullscreen-Highlight des beliebtesten Posts</div>
        </div>
      </label>
    </div>
  </div>

  <div style="display:flex;justify-content:flex-end;margin-bottom:30px;">
    <button type="submit" class="btn-upload" style="min-width:220px;padding:13px;font-size:15px;font-weight:700;">
      💾 Feed-Einstellungen speichern
    </button>
  </div>
</form>

<style>
/* ── Settings-Popup ─────────────────────────────────────────────────────── */
#ig-settings-overlay{
  display:none;position:fixed;inset:0;z-index:9999;
  background:rgba(0,0,0,.55);backdrop-filter:blur(3px);
  align-items:center;justify-content:center;
}
#ig-settings-overlay.open{display:flex;}
#ig-settings-popup{
  background:#fff;border-radius:16px;overflow:hidden;
  box-shadow:0 24px 80px rgba(0,0,0,.28);
  width:min(880px,95vw);height:min(86vh,820px);
  display:flex;flex-direction:column;
}
#ig-settings-header{
  display:flex;align-items:center;justify-content:space-between;
  padding:12px 18px;border-bottom:1px solid #e5e7eb;background:#f9fafb;flex-shrink:0;
}
#ig-settings-header h2{margin:0;font-size:.92rem;font-weight:700;display:flex;align-items:center;gap:7px;}
#ig-settings-close{background:none;border:none;font-size:1.25rem;cursor:pointer;color:#6b7280;padding:4px 8px;border-radius:6px;line-height:1;}
#ig-settings-close:hover{background:#f3f4f6;color:#111;}
#ig-settings-body{flex:1;overflow-y:auto;padding:16px 20px 24px;}
/* ── Settings-Button (gleicher Style wie ds-seiten.php) ─────────────────── */
.settings-btn{display:inline-flex;align-items:center;gap:6px;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:8px;color:#374151;font-size:.85rem;font-weight:600;padding:6px 14px;cursor:pointer;transition:background .15s;}
.settings-btn:hover{background:#e5e7eb;}
/* ── ig-Blocks ──────────────────────────────────────────────────────────── */
.ig-block{background:var(--bg-card);border-radius:var(--radius);box-shadow:var(--shadow);padding:22px 24px;margin-bottom:20px;}
.ig-block-title{font-size:15px;font-weight:700;margin:0 0 3px;display:flex;align-items:center;gap:8px;}
.ig-block-sub{font-size:12px;color:var(--text-muted);margin:0 0 18px;}
.ig-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 32px;}
.ig-row{display:flex;flex-direction:column;gap:4px;margin-bottom:14px;}
.ig-label{font-size:12px;font-weight:600;color:var(--text-main);}
.ig-sublabel{font-size:11px;color:var(--text-muted);margin-top:1px;}
.ig-input{padding:8px 11px;border:1px solid var(--border);border-radius:8px;font-size:13px;background:var(--bg-card);color:var(--text-main);width:100%;box-sizing:border-box;transition:border-color .15s;}
.ig-input:focus{outline:none;border-color:var(--primary);}
.ig-textarea{padding:8px 11px;border:1px solid var(--border);border-radius:8px;font-size:12px;font-family:monospace;background:var(--bg-card);color:var(--text-main);width:100%;box-sizing:border-box;height:80px;resize:vertical;}
.ig-select,.ig-num{padding:8px 11px;border:1px solid var(--border);border-radius:8px;font-size:13px;background:var(--bg-card);color:var(--text-main);}
.ig-num{width:80px;}
.ig-toggles{display:flex;flex-direction:column;gap:6px;margin-bottom:14px;}
.ig-toggle-row{display:flex;align-items:center;gap:10px;padding:9px 13px;background:var(--bg-subtle);border-radius:9px;cursor:pointer;transition:background .13s;}
.ig-toggle-row:hover{background:var(--border-light);}
.ig-toggle-label{font-size:13px;font-weight:600;color:var(--text-main);flex:1;}
.ig-toggle-sub{font-size:11px;color:var(--text-muted);}
.ig-inline-pair{display:flex;gap:8px;align-items:center;}
.ig-footer{display:flex;align-items:center;gap:14px;flex-wrap:wrap;padding-top:16px;margin-top:4px;border-top:1px solid var(--border-light);}
.ig-status{font-size:12px;padding:6px 12px;border-radius:6px;display:inline-block;}
.ig-status.ok{background:rgba(52,199,89,.10);color:#1a7a30;border:1px solid rgba(52,199,89,.25);}
.ig-status.error{background:rgba(255,59,48,.08);color:#c0392b;border:1px solid rgba(255,59,48,.2);}
.ig-status.muted{background:var(--bg-subtle);color:var(--text-muted);border:1px solid var(--border-light);}
.ig-debug{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin-top:12px;}
.ig-debug-card{padding:12px 14px;border:1px solid var(--border-light);border-radius:10px;background:var(--bg-subtle);}
.ig-debug-title{font-size:12px;font-weight:700;margin:0 0 4px;color:var(--text-main);}
.ig-debug-text{font-size:12px;color:var(--text-muted);margin:0;}
.ig-debug-code{margin:10px 0 0;padding:10px;border-radius:8px;background:#0d1117;color:#c9d1d9;font-size:11px;line-height:1.45;overflow:auto;max-height:180px;white-space:pre-wrap;word-break:break-word;}
/* inside popup: kein box-shadow nötig */
#ig-settings-body .ig-block{box-shadow:none;border:1px solid #e5e7eb;}
@media(max-width:700px){.ig-grid{grid-template-columns:1fr;}.ig-debug{grid-template-columns:1fr;}}
</style>

<script>
function openIgSettings(){
  var ov=document.getElementById('ig-settings-overlay');
  if(ov){ov.classList.add('open');document.body.style.overflow='hidden';}
}
function closeIgSettings(){
  var ov=document.getElementById('ig-settings-overlay');
  if(ov){ov.classList.remove('open');document.body.style.overflow='';}
}
document.addEventListener('keydown',function(e){if(e.key==='Escape')closeIgSettings();});
</script>

<?php include __DIR__ . '/../inc/debug.php'; ?>
</body></html>
