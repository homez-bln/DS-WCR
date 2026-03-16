<?php
/**
 * inc/ds-sync-partial.php — Sync-Logik als einbettbares Partial
 * Kein <html>, kein <body>, kein Menü — wird direkt in ds-seiten.php included.
 * Nutzt Session + CSRF aus dem aufrufenden Context.
 * v1.0
 */
if (!defined('WCR_DS_PARTIAL')) {
    die('Direktaufruf nicht erlaubt.');
}

$SYNC_SITE_URL    = 'https://wcr-webpage.de';
$SYNC_WP_API_BASE = $SYNC_SITE_URL . '/wp-json/wp/v2';
$SYNC_PAGES_FILE  = __DIR__ . '/ds-pages.json';
$SYNC_CONFIG_FILE = __DIR__ . '/ds-sync-config.json';
$SYNC_TABLES      = ['food','drinks','ice','cable','camping','extra'];

// ── Helpers ──────────────────────────────────────────────────────────────────
function _sync_load_config(string $f): array {
    if (!file_exists($f)) return ['wp_user'=>'','wp_app_pass'=>''];
    $r = json_decode(file_get_contents($f), true);
    return is_array($r) ? $r : ['wp_user'=>'','wp_app_pass'=>''];
}
function _sync_save_config(string $f, array $c): void {
    file_put_contents($f, json_encode($c, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE), LOCK_EX);
}
function _sync_load_pages(string $f): array {
    if (!file_exists($f)) return [];
    $r = json_decode(file_get_contents($f), true);
    return is_array($r) ? $r : [];
}
function _sync_http_code(array $h): int {
    foreach ($h as $l) { if (preg_match('/HTTP\/\S+\s+(\d+)/', $l, $m)) return (int)$m[1]; }
    return 0;
}
function _sync_make_slug(string $name, string $suffix): string {
    $map = ["\xc3\xa4"=>'ae',"\xc3\xb6"=>'oe',"\xc3\xbc"=>'ue',"\xc3\x9f"=>'ss',
            "\xc3\x84"=>'ae',"\xc3\x96"=>'oe',"\xc3\x9c"=>'ue','&'=>'und',' '=>'-'];
    $name = mb_strtolower(trim($name), 'UTF-8');
    foreach ($map as $k => $v) $name = str_replace($k, $v, $name);
    $name = preg_replace('/[^a-z0-9\-]+/', '-', $name);
    $name = trim(preg_replace('/-+/', '-', $name), '-');
    return 'ds-' . $name . '-' . $suffix;
}
function _sync_make_title(string $slug): string {
    return implode(' ', array_map(fn($p) => strtolower($p)==='ds'?'DS':ucfirst($p), explode('-', $slug)));
}
function _sync_test_auth(string $api, string $auth): array {
    $ctx = stream_context_create(['http'=>['timeout'=>6,'ignore_errors'=>true,
        'header'=>"Accept: application/json\r\nAuthorization: Basic {$auth}\r\n"]]);
    $raw = @file_get_contents($api.'/users/me?_fields=id,name', false, $ctx);
    $code = _sync_http_code($http_response_header ?? []);
    if ($raw===false) return ['ok'=>false,'msg'=>'HTTP fehlgeschlagen'];
    if ($code===401)  return ['ok'=>false,'msg'=>'401 Unauthorized'];
    if ($code===403)  return ['ok'=>false,'msg'=>'403 Forbidden'];
    $res = json_decode($raw, true);
    if (!empty($res['id'])) return ['ok'=>true,'msg'=>'Eingeloggt als: '.($res['name']??'?')];
    return ['ok'=>false,'msg'=>'Auth fehlgeschlagen'];
}
function _sync_page_exists(string $api, string $slug, string $auth): array {
    $url = $api.'/pages?slug='.urlencode($slug).'&status=any&per_page=1&_fields=id,slug,status';
    $ctx = stream_context_create(['http'=>['timeout'=>8,'ignore_errors'=>true,
        'header'=>"Accept: application/json\r\nAuthorization: Basic {$auth}\r\n"]]);
    $raw = @file_get_contents($url, false, $ctx);
    $code = _sync_http_code($http_response_header ?? []);
    if ($raw===false)   return ['error'=>'HTTP fehlgeschlagen'];
    if ($code===401)    return ['error'=>'401 Unauthorized'];
    if ($code===403)    return ['error'=>'403 Forbidden'];
    if ($code!==200)    return ['error'=>'HTTP '.$code];
    $data = json_decode($raw, true);
    if (!is_array($data)) return ['error'=>'JSON-Fehler'];
    if (!empty($data) && (int)($data[0]['id']??0) > 0)
        return ['exists'=>true,'id'=>(int)$data[0]['id'],'status'=>$data[0]['status']??'?'];
    return ['exists'=>false];
}
function _sync_elementor_data(string $sc): string {
    $uid = substr(md5($sc.uniqid('',true)),0,7);
    return json_encode([[
        'id'=>$uid,'elType'=>'container',
        'settings'=>['padding'=>['unit'=>'px','top'=>'0','right'=>'0','bottom'=>'0','left'=>'0','isLinked'=>true]],
        'elements'=>[['id'=>substr(md5($uid.'w'),0,7),'elType'=>'widget','widgetType'=>'shortcode',
            'settings'=>['shortcode'=>$sc],'elements'=>[]]],
    ]], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}
function _sync_create_page(string $api, string $auth, string $slug, string $sc, int $mo=9999): array {
    $title = _sync_make_title($slug);
    $body  = json_encode(['slug'=>$slug,'title'=>$title,'status'=>'publish','menu_order'=>$mo,
        'content'=>'<!-- wp:shortcode -->'.$sc.'<!-- /wp:shortcode -->',
        'meta'=>['_elementor_edit_mode'=>'builder','_elementor_template_type'=>'page',
                  '_elementor_data'=>_sync_elementor_data($sc)]
    ], JSON_UNESCAPED_UNICODE);
    $hdr = "Content-Type: application/json\r\nAccept: application/json\r\nAuthorization: Basic {$auth}\r\n";
    $ctx = stream_context_create(['http'=>['method'=>'POST','timeout'=>12,'ignore_errors'=>true,'header'=>$hdr,'content'=>$body]]);
    $raw = @file_get_contents($api.'/pages', false, $ctx);
    $code = _sync_http_code($http_response_header ?? []);
    if ($raw===false) return ['ok'=>false,'error'=>'POST fehlgeschlagen'];
    if ($code===401)  return ['ok'=>false,'error'=>'401 Unauthorized'];
    if ($code===403)  return ['ok'=>false,'error'=>'403 Forbidden'];
    $res = json_decode($raw, true);
    if (!is_array($res)||empty($res['id'])||(int)$res['id']<1)
        return ['ok'=>false,'error'=>($res['message']??substr($raw,0,200))];
    $pid  = (int)$res['id'];
    $real = $res['slug']??'';
    if ($real !== $slug) {
        $pctx = stream_context_create(['http'=>['method'=>'POST','timeout'=>8,'ignore_errors'=>true,
            'header'=>$hdr.'X-HTTP-Method-Override: PATCH'."\r\n",
            'content'=>json_encode(['slug'=>$slug])]]);
        @file_get_contents($api.'/pages/'.$pid, false, $pctx);
    }
    return ['ok'=>true,'id'=>$pid,'url'=>$res['link']??'','slug_fixed'=>($real!==$slug),'title'=>$title];
}
function _sync_get_typen(PDO $pdo, array $tables): array {
    $r=[];
    foreach($tables as $tbl){
        try{
            $rows=$pdo->query("SELECT DISTINCT typ FROM `{$tbl}` WHERE typ IS NOT NULL AND typ!='' ORDER BY typ")->fetchAll(PDO::FETCH_COLUMN);
            foreach($rows as $t){ $t=trim($t); if($t!=='') $r[]=['table'=>$tbl,'typ'=>$t]; }
        }catch(Exception $e){}
    }
    return $r;
}

// ── POST-Handling ─────────────────────────────────────────────────────────────
$_sync_cfg        = _sync_load_config($SYNC_CONFIG_FILE);
$_sync_pages_def  = _sync_load_pages($SYNC_PAGES_FILE);
$_sync_config_msg = '';
$_sync_auth_test  = null;
$_sync_log        = [];
$_sync_done       = false;

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['wcr_save_config'])) {
    wcr_verify_csrf();
    $_sync_cfg['wp_user']     = trim($_POST['wp_user'] ?? '');
    $_sync_cfg['wp_app_pass'] = trim($_POST['wp_app_pass'] ?? '');
    _sync_save_config($SYNC_CONFIG_FILE, $_sync_cfg);
    if ($_sync_cfg['wp_user'] && $_sync_cfg['wp_app_pass'])
        $_sync_auth_test = _sync_test_auth($SYNC_WP_API_BASE, base64_encode($_sync_cfg['wp_user'].':'.$_sync_cfg['wp_app_pass']));
    $_sync_config_msg = '✅ Gespeichert.';
}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['wcr_run_sync'])) {
    wcr_verify_csrf();
    $wu = $_sync_cfg['wp_user']??'';
    $wp = $_sync_cfg['wp_app_pass']??'';
    if (empty($wu)||empty($wp)) {
        $_sync_log[] = ['status'=>'error','slug'=>'–','msg'=>'Kein App-Password konfiguriert.'];
    } else {
        $auth = base64_encode($wu.':'.$wp);
        $ac   = _sync_test_auth($SYNC_WP_API_BASE, $auth);
        if (!$ac['ok']) {
            $_sync_log[] = ['status'=>'error','slug'=>'AUTH','msg'=>'⛔ '.$ac['msg']];
        } else {
            $_sync_log[] = ['status'=>'skip','slug'=>'AUTH','msg'=>'🔑 '.$ac['msg']];
            $to_sync = [];
            foreach (($_sync_pages_def['static']??[]) as $p)
                $to_sync[] = ['slug'=>_sync_make_slug($p['name'],$p['suffix']),'shortcode'=>$p['shortcode'],'menu_order'=>(int)($p['menu_order']??9999)];
            foreach (($_sync_pages_def['tables']??[]) as $t)
                $to_sync[] = ['slug'=>_sync_make_slug($t['name'],$t['suffix']),'shortcode'=>$t['shortcode'],'menu_order'=>(int)($t['menu_order']??9999)];
            $hl_tpl = $_sync_pages_def['highlight_shortcode']??'[wcr_produkte table="{table}" titel="{title}"]';
            $hl_mo  = 200;
            foreach (_sync_get_typen($pdo, $SYNC_TABLES) as $entry) {
                $sc = str_replace(['{table}','{title}'],[$entry['table'],$entry['typ']],$hl_tpl);
                $to_sync[] = ['slug'=>_sync_make_slug($entry['typ'],'highlight'),'shortcode'=>$sc,'menu_order'=>$hl_mo];
                $hl_mo += 10;
            }
            foreach ($to_sync as $page) {
                $check = _sync_page_exists($SYNC_WP_API_BASE, $page['slug'], $auth);
                if (!empty($check['error'])) { $_sync_log[]=['status'=>'error','slug'=>$page['slug'],'msg'=>'❌ '.$check['error']]; continue; }
                if ($check['exists'])         { $_sync_log[]=['status'=>'skip','slug'=>$page['slug'],'msg'=>'⏭ ID '.$check['id'].' ('.$check['status'].')']; continue; }
                $res = _sync_create_page($SYNC_WP_API_BASE, $auth, $page['slug'], $page['shortcode'], $page['menu_order']);
                if ($res['ok']) {
                    $_sync_log[]=['status'=>'created','slug'=>$page['slug'],'msg'=>'✅ '.$res['title'].' — ID '.$res['id'].(!empty($res['slug_fixed'])?' (Slug korrigiert)':''),'url'=>$res['url']??''];
                } else {
                    $_sync_log[]=['status'=>'error','slug'=>$page['slug'],'msg'=>'❌ '.$res['error']];
                }
            }
        }
    }
    $_sync_done = true;
}

// Vorschau
$_sync_preview = [];
foreach (($_sync_pages_def['static']??[]) as $p)
    $_sync_preview[] = ['slug'=>_sync_make_slug($p['name'],$p['suffix']),'sc'=>$p['shortcode'],'group'=>'Statisch'];
foreach (($_sync_pages_def['tables']??[]) as $t)
    $_sync_preview[] = ['slug'=>_sync_make_slug($t['name'],$t['suffix']),'sc'=>$t['shortcode'],'group'=>'Tabellen-Liste'];
$_hl_tpl = $_sync_pages_def['highlight_shortcode']??'[wcr_produkte table="{table}" titel="{title}"]';
foreach (_sync_get_typen($pdo, $SYNC_TABLES) as $entry) {
    $sc = str_replace(['{table}','{title}'],[$entry['table'],$entry['typ']],$_hl_tpl);
    $_sync_preview[] = ['slug'=>_sync_make_slug($entry['typ'],'highlight'),'sc'=>$sc,'group'=>'Highlight ('.$entry['table'].')'];
}
$_sync_has_auth = !empty($_sync_cfg['wp_user']) && !empty($_sync_cfg['wp_app_pass']);
?>

<!-- ── Sync Partial HTML ────────────────────────────────────────────────────── -->
<div class="sync-partial-wrap">

  <!-- Config Card -->
  <div class="sync-card">
    <h2>🔑 WP Application Password</h2>
    <?php if ($_sync_config_msg): ?>
      <div class="sp-notice ok"><?= htmlspecialchars($_sync_config_msg,ENT_QUOTES,'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($_sync_auth_test !== null): ?>
      <div class="sp-notice <?= $_sync_auth_test['ok']?'ok':'err' ?>"><?= htmlspecialchars($_sync_auth_test['msg'],ENT_QUOTES,'UTF-8') ?></div>
    <?php endif; ?>
    <?php if (!$_sync_has_auth): ?>
      <div class="sp-notice warn">⚠️ Noch kein App-Password. WP Admin → Benutzer → Profil → Anwendungspasswörter.</div>
    <?php endif; ?>
    <form method="post">
      <?= wcr_csrf_field() ?>
      <input type="hidden" name="wcr_save_config" value="1">
      <div class="sp-form-row">
        <label>WP Benutzername<input type="text" name="wp_user" value="<?= htmlspecialchars($_sync_cfg['wp_user']??'',ENT_QUOTES,'UTF-8') ?>" autocomplete="off"></label>
        <label>Application Password<input type="password" name="wp_app_pass" value="<?= htmlspecialchars($_sync_cfg['wp_app_pass']??'',ENT_QUOTES,'UTF-8') ?>" autocomplete="off"></label>
        <button type="submit" class="sp-btn-primary" style="align-self:flex-end;">Speichern &amp; testen</button>
      </div>
    </form>
  </div>

  <!-- Sync Card -->
  <div class="sync-card">
    <h2>🚀 Sync ausführen</h2>
    <p style="font-size:.8rem;color:#6b7280;margin:0 0 12px;">Titel = Slug lesbar (DS Burger Highlight). Nur fehlende Seiten werden erstellt.</p>
    <?php if (!empty($_sync_log)):
      $nc=count(array_filter($_sync_log,fn($l)=>$l['status']==='created'));
      $ns=count(array_filter($_sync_log,fn($l)=>$l['status']==='skip'));
      $ne=count(array_filter($_sync_log,fn($l)=>$l['status']==='error')); ?>
      <div class="sp-summary">
        <span class="sp-chip c">✅ <?= $nc ?> erstellt</span>
        <span class="sp-chip s">⏭ <?= $ns ?> vorhanden</span>
        <?php if($ne): ?><span class="sp-chip e">❌ <?= $ne ?> Fehler</span><?php endif; ?>
      </div>
      <?php foreach($_sync_log as $l): ?>
      <div class="sp-log-item <?= htmlspecialchars($l['status'],ENT_QUOTES,'UTF-8') ?>">
        <span class="sp-slug"><?= htmlspecialchars($l['slug'],ENT_QUOTES,'UTF-8') ?></span>
        <span class="sp-msg"><?= htmlspecialchars($l['msg'],ENT_QUOTES,'UTF-8') ?>
          <?php if(!empty($l['url'])): ?><a href="<?= htmlspecialchars($l['url'],ENT_QUOTES,'UTF-8') ?>" target="_blank" rel="noopener" style="color:#0071e3;">↗</a><?php endif; ?>
        </span>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
    <form method="post" style="margin-top:12px;">
      <?= wcr_csrf_field() ?>
      <input type="hidden" name="wcr_run_sync" value="1">
      <button type="submit" class="sp-btn-sync" <?= !$_sync_has_auth?'disabled':'' ?>>🔄 Sync starten — <?= count($_sync_preview) ?> Seiten prüfen</button>
    </form>
  </div>

  <!-- Preview Card -->
  <div class="sync-card">
    <h2>📋 Geplante Seiten <small style="font-weight:400;color:#9ca3af;">(<?= count($_sync_preview) ?> total)</small></h2>
    <table class="sp-preview-table">
      <thead><tr><th>Slug</th><th>Titel (WP)</th><th>Typ</th><th>Shortcode</th></tr></thead>
      <tbody>
      <?php foreach($_sync_preview as $p):
        $bc = str_contains($p['group'],'Statisch')?'static':(str_contains($p['group'],'Liste')?'list':'highlight');
      ?>
      <tr>
        <td><code style="font-size:.73rem;"><?= htmlspecialchars($p['slug'],ENT_QUOTES,'UTF-8') ?></code></td>
        <td style="font-size:.78rem;font-weight:600;"><?= htmlspecialchars(_sync_make_title($p['slug']),ENT_QUOTES,'UTF-8') ?></td>
        <td><span class="sp-badge <?= $bc ?>"><?= htmlspecialchars($p['group'],ENT_QUOTES,'UTF-8') ?></span></td>
        <td><code style="font-size:.7rem;color:#6b7280;"><?= htmlspecialchars($p['sc'],ENT_QUOTES,'UTF-8') ?></code></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

</div><!-- /sync-partial-wrap -->
