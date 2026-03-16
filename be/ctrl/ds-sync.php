<?php
/**
 * ctrl/ds-sync.php — WP-Seiten automatisch generieren
 * v2.2: Fix — Slug nach Erstellen via PATCH erzwingen (WP ignoriert slug bei POST)
 */
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';

wcr_require('view_ds');

$PAGE_TITLE     = 'DS-Seiten Sync';
$SITE_URL       = 'https://wcr-webpage.de';
$WP_API_BASE    = $SITE_URL . '/wp-json/wp/v2';
$PAGES_FILE     = __DIR__ . '/../inc/ds-pages.json';
$CONFIG_FILE    = __DIR__ . '/../inc/ds-sync-config.json';
$ALLOWED_TABLES = ['food','drinks','ice','cable','camping','extra'];

function wcr_sync_load_config(string $file): array {
    if (!file_exists($file)) return ['wp_user'=>'','wp_app_pass'=>''];
    $r = json_decode(file_get_contents($file), true);
    return is_array($r) ? $r : ['wp_user'=>'','wp_app_pass'=>''];
}
function wcr_sync_save_config(string $file, array $cfg): void {
    file_put_contents($file, json_encode($cfg, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE), LOCK_EX);
}
$cfg = wcr_sync_load_config($CONFIG_FILE);

function wcr_sync_load_pages(string $file): array {
    if (!file_exists($file)) return [];
    $r = json_decode(file_get_contents($file), true);
    return is_array($r) ? $r : [];
}
$pages_def = wcr_sync_load_pages($PAGES_FILE);

function wcr_sync_get_typen(PDO $pdo, array $tables): array {
    $result = [];
    foreach ($tables as $tbl) {
        try {
            $rows = $pdo->query("SELECT DISTINCT typ FROM `{$tbl}` WHERE typ IS NOT NULL AND typ != '' ORDER BY typ")
                        ->fetchAll(PDO::FETCH_COLUMN);
            foreach ($rows as $t) {
                $t = trim($t);
                if ($t !== '') $result[] = ['table' => $tbl, 'typ' => $t];
            }
        } catch (Exception $e) {}
    }
    return $result;
}

// Slug: ds-{name}-{suffix} — nur a-z, 0-9, Bindestriche
function wcr_sync_make_slug(string $name, string $suffix): string {
    // Umlaute ersetzen
    $map = ['\u00e4'=>'ae','\u00f6'=>'oe','\u00fc'=>'ue','\u00df'=>'ss',
            '\u00c4'=>'ae','\u00d6'=>'oe','\u00dc'=>'ue',
            '&'=>'und',' '=>'-'];
    $name = mb_strtolower(trim($name), 'UTF-8');
    foreach ($map as $k => $v) $name = str_replace(json_decode('"'.$k.'"'), $v, $name);
    $name = preg_replace('/[^a-z0-9\-]+/', '-', $name);
    $name = trim(preg_replace('/-+/', '-', $name), '-');
    return 'ds-' . $name . '-' . $suffix;
}

function wcr_http_code(array $headers): int {
    foreach ($headers as $h) {
        if (preg_match('/HTTP\/\S+\s+(\d+)/', $h, $m)) return (int)$m[1];
    }
    return 0;
}

function wcr_sync_test_auth(string $api_base, string $auth): array {
    $ctx = stream_context_create(['http'=>[
        'timeout'=>6,'ignore_errors'=>true,
        'header'=>"Accept: application/json\r\nAuthorization: Basic {$auth}\r\n",
    ]]);
    $raw  = @file_get_contents($api_base.'/users/me?_fields=id,name', false, $ctx);
    $code = wcr_http_code($http_response_header ?? []);
    if ($raw === false) return ['ok'=>false,'msg'=>'HTTP fehlgeschlagen'];
    if ($code === 401)  return ['ok'=>false,'msg'=>'401 Unauthorized — falsches Passwort oder User'];
    if ($code === 403)  return ['ok'=>false,'msg'=>'403 Forbidden'];
    $res = json_decode($raw, true);
    if (!empty($res['id'])) return ['ok'=>true,'msg'=>'Eingeloggt als: '.($res['name']??'?')];
    return ['ok'=>false,'msg'=>'Auth fehlgeschlagen: '.substr($raw,0,120)];
}

function wcr_sync_page_exists(string $api_base, string $slug, string $auth): array {
    $url = $api_base.'/pages?slug='.urlencode($slug).'&status=any&per_page=1&_fields=id,slug,status';
    $ctx = stream_context_create(['http'=>[
        'timeout'=>8,'ignore_errors'=>true,
        'header'=>"Accept: application/json\r\nAuthorization: Basic {$auth}\r\n",
    ]]);
    $raw  = @file_get_contents($url, false, $ctx);
    $code = wcr_http_code($http_response_header ?? []);
    if ($raw === false) return ['error'=>'HTTP fehlgeschlagen'];
    if ($code === 401)  return ['error'=>'401 Unauthorized'];
    if ($code === 403)  return ['error'=>'403 Forbidden'];
    if ($code !== 200)  return ['error'=>'HTTP '.$code];
    $data = json_decode($raw, true);
    if (!is_array($data)) return ['error'=>'JSON-Fehler: '.substr($raw,0,80)];
    if (!empty($data) && (int)($data[0]['id']??0) > 0)
        return ['exists'=>true,'id'=>(int)$data[0]['id'],'status'=>$data[0]['status']??'?'];
    return ['exists'=>false];
}

function wcr_sync_elementor_data(string $shortcode): string {
    $uid = substr(md5($shortcode.uniqid('',true)),0,7);
    $data = [[
        'id'       => $uid,
        'elType'   => 'container',
        'settings' => ['padding'=>['unit'=>'px','top'=>'0','right'=>'0','bottom'=>'0','left'=>'0','isLinked'=>true]],
        'elements' => [[
            'id'         => substr(md5($uid.'w'),0,7),
            'elType'     => 'widget',
            'widgetType' => 'shortcode',
            'settings'   => ['shortcode' => $shortcode],
            'elements'   => [],
        ]],
    ]];
    return json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}

/**
 * Seite erstellen + Slug danach via PATCH erzwingen
 * WP ignoriert manchmal den slug beim POST und generiert ihn aus dem Titel.
 * Daher: POST zum Erstellen, dann PATCH /pages/{id} mit korrektem Slug.
 */
function wcr_sync_create_page(
    string $api_base, string $auth,
    string $slug, string $title, string $shortcode,
    int $menu_order = 9999
): array {
    // Titel sauber (keine Sonderzeichen die WP verwirren)
    $safe_title = $title;

    $body = json_encode([
        'slug'       => $slug,
        'title'      => $safe_title,
        'status'     => 'publish',
        'menu_order' => $menu_order,
        'content'    => '<!-- wp:shortcode -->'.$shortcode.'<!-- /wp:shortcode -->',
        'meta'       => [
            '_elementor_edit_mode'     => 'builder',
            '_elementor_template_type' => 'page',
            '_elementor_data'          => wcr_sync_elementor_data($shortcode),
        ],
    ], JSON_UNESCAPED_UNICODE);

    $headers = implode("\r\n",[
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Basic '.$auth,
    ])."\r\n";

    // POST — Seite erstellen
    $ctx = stream_context_create(['http'=>[
        'method'=>'POST','timeout'=>12,'ignore_errors'=>true,
        'header'=>$headers,'content'=>$body,
    ]]);
    $raw  = @file_get_contents($api_base.'/pages', false, $ctx);
    $code = wcr_http_code($http_response_header ?? []);

    if ($raw === false) return ['ok'=>false,'error'=>'POST HTTP fehlgeschlagen'];
    if ($code === 401)  return ['ok'=>false,'error'=>'401 Unauthorized'];
    if ($code === 403)  return ['ok'=>false,'error'=>'403 Forbidden'];

    $res = json_decode($raw, true);
    if (!is_array($res) || empty($res['id']) || (int)$res['id'] < 1)
        return ['ok'=>false,'error'=>($res['message']??substr($raw,0,200))];

    $page_id  = (int)$res['id'];
    $real_slug = $res['slug'] ?? '';

    // PATCH — Slug erzwingen falls WP ihn umbenannt hat
    if ($real_slug !== $slug) {
        $patch_body = json_encode(['slug' => $slug], JSON_UNESCAPED_UNICODE);
        $pctx = stream_context_create(['http'=>[
            'method'=>'POST','timeout'=>8,'ignore_errors'=>true,
            'header'=>$headers.'X-HTTP-Method-Override: PATCH'."\r\n",
            'content'=>$patch_body,
        ]]);
        @file_get_contents($api_base.'/pages/'.$page_id, false, $pctx);
    }

    return ['ok'=>true,'id'=>$page_id,'url'=>$res['link']??'','slug_fixed'=>($real_slug !== $slug)];
}

// ── POST-Handling ───────────────────────────────────────────────────────────────────
$config_msg = '';
$auth_test  = null;
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['wcr_save_config'])) {
    wcr_verify_csrf();
    $cfg['wp_user']     = trim($_POST['wp_user'] ?? '');
    $cfg['wp_app_pass'] = trim($_POST['wp_app_pass'] ?? '');
    wcr_sync_save_config($CONFIG_FILE, $cfg);
    if ($cfg['wp_user'] && $cfg['wp_app_pass'])
        $auth_test = wcr_sync_test_auth($WP_API_BASE, base64_encode($cfg['wp_user'].':'.$cfg['wp_app_pass']));
    $config_msg = '✅ Gespeichert.';
}

$sync_log = [];
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['wcr_run_sync'])) {
    wcr_verify_csrf();
    $wp_user = $cfg['wp_user'] ?? '';
    $wp_pass = $cfg['wp_app_pass'] ?? '';

    if (empty($wp_user) || empty($wp_pass)) {
        $sync_log[] = ['status'=>'error','slug'=>'–','msg'=>'Kein App-Password konfiguriert.'];
    } else {
        $auth = base64_encode($wp_user.':'.$wp_pass);
        $auth_check = wcr_sync_test_auth($WP_API_BASE, $auth);
        if (!$auth_check['ok']) {
            $sync_log[] = ['status'=>'error','slug'=>'AUTH','msg'=>'⛔ '.$auth_check['msg']];
        } else {
            $sync_log[] = ['status'=>'skip','slug'=>'AUTH','msg'=>'🔑 '.$auth_check['msg']];
            $to_sync = [];
            foreach (($pages_def['static'] ?? []) as $p)
                $to_sync[] = ['slug'=>wcr_sync_make_slug($p['name'],$p['suffix']),'title'=>$p['title'],'shortcode'=>$p['shortcode'],'menu_order'=>(int)($p['menu_order']??9999)];
            foreach (($pages_def['tables'] ?? []) as $t)
                $to_sync[] = ['slug'=>wcr_sync_make_slug($t['name'],$t['suffix']),'title'=>$t['title'],'shortcode'=>$t['shortcode'],'menu_order'=>(int)($t['menu_order']??9999)];
            $hl_tpl = $pages_def['highlight_shortcode'] ?? '[wcr_produkte table="{table}" titel="{title}"]';
            $hl_order = 200;
            foreach (wcr_sync_get_typen($pdo, $ALLOWED_TABLES) as $entry) {
                $sc = str_replace(['{table}','{title}'],[$entry['table'],$entry['typ']],$hl_tpl);
                $to_sync[] = ['slug'=>wcr_sync_make_slug($entry['typ'],'highlight'),'title'=>$entry['typ'].' Highlight','shortcode'=>$sc,'menu_order'=>$hl_order];
                $hl_order += 10;
            }
            foreach ($to_sync as $page) {
                $check = wcr_sync_page_exists($WP_API_BASE, $page['slug'], $auth);
                if (!empty($check['error'])) {
                    $sync_log[] = ['status'=>'error','slug'=>$page['slug'],'msg'=>'❌ '.$check['error']];
                    continue;
                }
                if ($check['exists']) {
                    $sync_log[] = ['status'=>'skip','slug'=>$page['slug'],'msg'=>'⏭ ID '.$check['id'].' ('.$check['status'].')'];
                    continue;
                }
                $res = wcr_sync_create_page($WP_API_BASE,$auth,$page['slug'],$page['title'],$page['shortcode'],$page['menu_order']);
                if ($res['ok']) {
                    $extra = !empty($res['slug_fixed']) ? ' (Slug korrigiert)' : '';
                    $sync_log[] = ['status'=>'created','slug'=>$page['slug'],'msg'=>'✅ Erstellt — ID '.$res['id'].$extra,'url'=>$res['url']??''];
                } else {
                    $sync_log[] = ['status'=>'error','slug'=>$page['slug'],'msg'=>'❌ '.$res['error']];
                }
            }
        }
    }
}

// ── Vorschau ───────────────────────────────────────────────────────────────────
$preview = [];
foreach (($pages_def['static'] ?? []) as $p)
    $preview[] = ['slug'=>wcr_sync_make_slug($p['name'],$p['suffix']),'sc'=>$p['shortcode'],'group'=>'Statisch'];
foreach (($pages_def['tables'] ?? []) as $t)
    $preview[] = ['slug'=>wcr_sync_make_slug($t['name'],$t['suffix']),'sc'=>$t['shortcode'],'group'=>'Tabellen-Liste'];
$hl_tpl = $pages_def['highlight_shortcode'] ?? '[wcr_produkte table="{table}" titel="{title}"]';
foreach (wcr_sync_get_typen($pdo, $ALLOWED_TABLES) as $entry) {
    $sc = str_replace(['{table}','{title}'],[$entry['table'],$entry['typ']],$hl_tpl);
    $preview[] = ['slug'=>wcr_sync_make_slug($entry['typ'],'highlight'),'sc'=>$sc,'group'=>'Highlight ('.$entry['table'].')'];
}
$has_auth = !empty($cfg['wp_user']) && !empty($cfg['wp_app_pass']);
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($PAGE_TITLE,ENT_QUOTES,'UTF-8') ?></title>
<style>
.sync-wrap{max-width:960px;margin:0 auto;padding:0 20px 60px;}
.sync-card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:20px 24px;margin-bottom:24px;}
.sync-card h2{font-size:.95rem;font-weight:700;margin:0 0 14px;display:flex;align-items:center;gap:8px;}
.form-row{display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-bottom:8px;}
.form-row label{font-size:.8rem;font-weight:600;color:#374151;display:flex;flex-direction:column;gap:3px;}
.form-row input{border:1px solid #d1d5db;border-radius:6px;padding:6px 10px;font-size:.85rem;min-width:200px;}
.btn-primary{background:#0071e3;color:#fff;border:none;border-radius:8px;padding:7px 20px;font-size:.85rem;font-weight:600;cursor:pointer;}
.btn-primary:hover{background:#005bb5;}
.btn-sync{background:#059669;color:#fff;border:none;border-radius:10px;padding:10px 28px;font-size:.95rem;font-weight:700;cursor:pointer;width:100%;margin-top:6px;}
.btn-sync:hover{background:#047857;}
.btn-sync:disabled{background:#9ca3af;cursor:not-allowed;}
.preview-table{width:100%;border-collapse:collapse;font-size:.78rem;}
.preview-table th{text-align:left;padding:6px 10px;background:#f3f4f6;font-weight:700;color:#6b7280;border-bottom:2px solid #e5e7eb;}
.preview-table td{padding:6px 10px;border-bottom:1px solid #f3f4f6;vertical-align:top;}
.badge{display:inline-block;font-size:.65rem;font-weight:700;padding:2px 7px;border-radius:10px;white-space:nowrap;}
.badge.static{background:#dbeafe;color:#1e40af;}
.badge.list{background:#d1fae5;color:#065f46;}
.badge.highlight{background:#fef3c7;color:#92400e;}
.log-item{display:flex;gap:10px;padding:5px 0;border-bottom:1px solid #f3f4f6;font-size:.78rem;align-items:flex-start;}
.log-item .log-slug{font-family:monospace;min-width:230px;color:#374151;}
.log-item .log-msg{color:#6b7280;}
.log-item.created .log-msg{color:#059669;font-weight:600;}
.log-item.error   .log-msg{color:#dc2626;font-weight:600;}
.log-item.skip    .log-msg{color:#9ca3af;}
.notice{padding:10px 16px;border-radius:8px;font-size:.82rem;margin-bottom:12px;}
.notice.ok{background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;font-weight:600;}
.notice.err{background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;font-weight:600;}
.notice.warn{background:#fef3c7;color:#92400e;border:1px solid #fcd34d;}
.summary{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:12px;}
.sum-chip{border-radius:8px;padding:6px 14px;font-size:.8rem;font-weight:700;}
.sum-chip.c{background:#d1fae5;color:#065f46;}
.sum-chip.s{background:#f0f9ff;color:#0369a1;}
.sum-chip.e{background:#fee2e2;color:#991b1b;}
</style>
</head>
<body class="bo">
<?php include __DIR__ . '/../inc/menu.php'; ?>
<div class="sync-wrap">
<div class="header-controls" style="margin-bottom:20px;">
  <h1>🔄 <?= htmlspecialchars($PAGE_TITLE,ENT_QUOTES,'UTF-8') ?></h1>
</div>
<div class="sync-card">
  <h2>🔑 WP Application Password</h2>
  <?php if ($config_msg): ?><div class="notice ok"><?= htmlspecialchars($config_msg,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>
  <?php if ($auth_test !== null): ?><div class="notice <?= $auth_test['ok']?'ok':'err' ?>"><?= htmlspecialchars($auth_test['msg'],ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>
  <?php if (!$has_auth): ?><div class="notice warn">⚠️ Noch kein App-Password. WP Admin → Benutzer → Profil → Anwendungspasswörter.</div><?php endif; ?>
  <form method="post">
    <?= wcr_csrf_field() ?>
    <input type="hidden" name="wcr_save_config" value="1">
    <div class="form-row">
      <label>WP Benutzername<input type="text" name="wp_user" value="<?= htmlspecialchars($cfg['wp_user']??'',ENT_QUOTES,'UTF-8') ?>" autocomplete="off"></label>
      <label>Application Password<input type="password" name="wp_app_pass" value="<?= htmlspecialchars($cfg['wp_app_pass']??'',ENT_QUOTES,'UTF-8') ?>" autocomplete="off"></label>
      <button type="submit" class="btn-primary" style="margin-bottom:3px;">Speichern &amp; testen</button>
    </div>
  </form>
</div>
<div class="sync-card">
  <h2>🚀 Sync ausführen</h2>
  <p style="font-size:.82rem;color:#6b7280;margin:0 0 12px;">Prüft jeden Slug — <strong>keine Duplikate</strong>. Slug wird nach dem Erstellen via PATCH erzwungen.</p>
  <?php if (!empty($sync_log)):
    $n_c=count(array_filter($sync_log,fn($l)=>$l['status']==='created'));
    $n_s=count(array_filter($sync_log,fn($l)=>$l['status']==='skip'));
    $n_e=count(array_filter($sync_log,fn($l)=>$l['status']==='error')); ?>
  <div class="summary">
    <div class="sum-chip c">✅ <?= $n_c ?> erstellt</div>
    <div class="sum-chip s">⏭ <?= $n_s ?> vorhanden</div>
    <?php if($n_e): ?><div class="sum-chip e">❌ <?= $n_e ?> Fehler</div><?php endif; ?>
  </div>
  <?php foreach($sync_log as $l): ?>
  <div class="log-item <?= $l['status'] ?>">
    <span class="log-slug"><?= htmlspecialchars($l['slug'],ENT_QUOTES,'UTF-8') ?></span>
    <span class="log-msg"><?= htmlspecialchars($l['msg'],ENT_QUOTES,'UTF-8') ?>
      <?php if(!empty($l['url'])): ?><a href="<?= htmlspecialchars($l['url'],ENT_QUOTES,'UTF-8') ?>" target="_blank" style="color:#0071e3;">↗</a><?php endif; ?>
    </span>
  </div>
  <?php endforeach; ?>
  <div style="height:12px;"></div>
  <?php endif; ?>
  <form method="post">
    <?= wcr_csrf_field() ?>
    <input type="hidden" name="wcr_run_sync" value="1">
    <button type="submit" class="btn-sync" <?= !$has_auth?'disabled':'' ?>>🔄 Sync starten — <?= count($preview) ?> Seiten prüfen</button>
  </form>
</div>
<div class="sync-card">
  <h2>📋 Geplante Seiten <small style="font-weight:400;color:#9ca3af;">(<?= count($preview) ?> total)</small></h2>
  <table class="preview-table">
    <thead><tr><th>Slug</th><th>Typ</th><th>Shortcode</th></tr></thead>
    <tbody>
    <?php foreach($preview as $p):
      $bc=str_contains($p['group'],'Statisch')?'static':(str_contains($p['group'],'Liste')?'list':'highlight'); ?>
    <tr>
      <td><code style="font-size:.74rem;"><?= htmlspecialchars($p['slug'],ENT_QUOTES,'UTF-8') ?></code></td>
      <td><span class="badge <?= $bc ?>"><?= htmlspecialchars($p['group'],ENT_QUOTES,'UTF-8') ?></span></td>
      <td><code style="font-size:.71rem;color:#6b7280;"><?= htmlspecialchars($p['sc'],ENT_QUOTES,'UTF-8') ?></code></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
</div>
<?php include __DIR__ . '/../inc/debug.php'; ?>
</body>
</html>
