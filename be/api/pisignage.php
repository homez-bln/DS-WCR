<?php
/**
 * api/pisignage.php — piSignage REST-Proxy v3
 * Tabs: Szenen | Assets | Playlists | Verbindung
 */
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/pisignage-config.php';

require_login();

if (!wcr_is_cernal()) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Kein Zugriff']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

wcr_verify_csrf();

$action = $_POST['action'] ?? '';
$config = wcr_pisignage_load_config();

// ── Helpers ────────────────────────────────────────────────────────

function wcr_pi_respond(array $payload, int $status = 200): void {
    $payload['csrf_token'] = wcr_csrf_token();
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function wcr_pi_request(string $method, string $endpoint, ?array $body = null, ?string $token = null, string $baseOverride = ''): array {
    $config  = wcr_pisignage_load_config();
    $baseUrl = $baseOverride !== '' ? $baseOverride : rtrim($config['base_url'] ?? '', '/');
    if ($baseUrl === '') return ['success' => false, 'error' => 'piSignage Base URL fehlt'];
    $url     = $baseUrl . $endpoint;
    $headers = ['Content-Type: application/json'];
    if (!empty($token)) $headers[] = 'x-access-token: ' . $token;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_SLASHES));
    $raw = curl_exec($ch); $err = curl_error($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $err) return ['success' => false, 'error' => 'cURL: ' . $err];
    return ['success' => ($code >= 200 && $code < 300), 'status' => $code, 'data' => json_decode($raw, true), 'raw' => $raw];
}

function wcr_pi_token(): string { return wcr_pisignage_load_config()['api_token'] ?? ''; }

function wcr_pi_login(?string $otp = null): array {
    $c = wcr_pisignage_load_config();
    if (empty($c['email']) || empty($c['password'])) return ['success' => false, 'error' => 'E-Mail oder Passwort fehlt'];
    $p = ['email' => $c['email'], 'password' => $c['password'], 'getToken' => true];
    if ($otp) $p['code'] = $otp;
    return wcr_pi_request('POST', '/api/session', $p, null, 'https://piathome.com');
}

function wcr_pi_extract_token(array $r): ?string {
    $d = $r['data'] ?? null;
    if (!is_array($d)) return null;
    foreach ([$d['token']??null,$d['accessToken']??null,$d['data']['token']??null,$d['data']['accessToken']??null] as $c)
        if (is_string($c) && trim($c)!=='') return trim($c);
    return null;
}

// Szenen in wp_options
function wcr_pi_scenes_load(): array {
    if (!defined('DSC_WP_API_BASE')) return [];
    $ch = curl_init(DSC_WP_API_BASE . '/options/wcr_pisignage_scenes?wcr_secret=' . urlencode(DSC_WP_SECRET));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>5,CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_HTTPHEADER=>['Accept: application/json']]);
    $body = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($code===200 && $body) { $j = json_decode($body,true); if (isset($j['value']) && is_array($j['value'])) return $j['value']; }
    return [];
}
function wcr_pi_scenes_save(array $scenes): bool {
    if (!defined('DSC_WP_API_BASE')) return false;
    $ch = curl_init(DSC_WP_API_BASE . '/options');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_TIMEOUT=>10,CURLOPT_SSL_VERIFYPEER=>false,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
        CURLOPT_POSTFIELDS=>json_encode(['wcr_secret'=>DSC_WP_SECRET,'key'=>'wcr_pisignage_scenes','value'=>$scenes],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch); return $code===200;
    $body = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return $code === 200;
}

// ── Router ───────────────────────────────────────────────────────────

switch ($action) {

    // ─ Verbindung ──────────────────────────────────────────────────
    case 'save_settings':
        $n = ['base_url'=>rtrim(trim($_POST['base_url']??''),'/'), 'api_token'=>trim($_POST['api_token']??''), 'email'=>trim($_POST['email']??''), 'password'=>trim($_POST['password']??'')];
        if ($n['base_url']!=='' && !preg_match('~^https?://~',$n['base_url'])) wcr_pi_respond(['success'=>false,'error'=>'Base URL muss mit https:// beginnen'],422);
        if ($n['password']==='') $n['password'] = $config['password']??'';
        if (!wcr_pisignage_save_config(array_merge($config,$n))) wcr_pi_respond(['success'=>false,'error'=>'Speichern fehlgeschlagen'],500);
        wcr_pi_respond(['success'=>true,'message'=>'✅ Gespeichert']);
        break;

    case 'request_token':
        $login = wcr_pi_login(trim($_POST['otp']??'')?:null);
        if (!$login['success']) wcr_pi_respond(['success'=>false,'error'=>'Login fehlgeschlagen','details'=>$login],401);
        $token = wcr_pi_extract_token($login);
        if (!$token) wcr_pi_respond(['success'=>false,'error'=>'Kein Token in Antwort','details'=>$login],500);
        $config['api_token']=$token;
        if (!wcr_pisignage_save_config($config)) wcr_pi_respond(['success'=>false,'error'=>'Speichern fehlgeschlagen'],500);
        wcr_pi_respond(['success'=>true,'message'=>'✅ Token gespeichert','token_masked'=>wcr_pisignage_mask_token($token)]);
        break;

    case 'test_connection':
        $t = wcr_pi_token(); if (!$t) wcr_pi_respond(['success'=>false,'error'=>'Kein Token'],422);
        $r = wcr_pi_request('GET','/api/groups',null,$t);
        wcr_pi_respond(array_merge($r,['message'=>$r['success']?'✅ Verbindung OK':'❌ Fehlgeschlagen']),$r['success']?200:500);
        break;

    // ─ Data-Fetch (für UI) ──────────────────────────────────────────
    case 'get_groups':
        $t=wcr_pi_token(); if(!$t) wcr_pi_respond(['success'=>false,'error'=>'Kein Token'],422);
        wcr_pi_respond(wcr_pi_request('GET','/api/groups',null,$t));
        break;

    case 'get_playlists':
        $t=wcr_pi_token(); if(!$t) wcr_pi_respond(['success'=>false,'error'=>'Kein Token'],422);
        wcr_pi_respond(wcr_pi_request('GET','/api/playlists',null,$t));
        break;

    case 'get_assets':
        $t=wcr_pi_token(); if(!$t) wcr_pi_respond(['success'=>false,'error'=>'Kein Token'],422);
        wcr_pi_respond(wcr_pi_request('GET','/api/assets',null,$t));
        break;

    // ─ Szenen ─────────────────────────────────────────────────────
    case 'load_scenes':
        wcr_pi_respond(['success'=>true,'scenes'=>wcr_pi_scenes_load()]);
        break;

    case 'save_scenes':
        $raw = json_decode($_POST['scenes']??'',true);
        if (!is_array($raw)) wcr_pi_respond(['success'=>false,'error'=>'Ungültiges Format'],422);
        $clean=[];
        foreach($raw as $s) {
            if (empty($s['id'])||empty($s['label'])) continue;
            $slots=[];
            foreach((array)($s['slots']??[]) as $slot) {
                $slots[]=['group_id'=>(string)($slot['group_id']??''),'group_label'=>(string)($slot['group_label']??''),'playlist_id'=>(string)($slot['playlist_id']??''),'playlist_label'=>(string)($slot['playlist_label']??'')];
            }
            $clean[]=['id'=>preg_replace('/[^a-z0-9_-]/','',strtolower((string)$s['id'])),'label'=>substr((string)$s['label'],0,60),'icon'=>substr((string)($s['icon']??'🎬'),0,8),'color'=>preg_match('/^#[0-9a-fA-F]{3,6}$/',$s['color']??'')?$s['color']:'#019ee3','slots'=>$slots];
        }
        if (!wcr_pi_scenes_save($clean)) wcr_pi_respond(['success'=>false,'error'=>'Speichern fehlgeschlagen'],500);
        wcr_pi_respond(['success'=>true,'message'=>'✅ '.count($clean).' Szenen gespeichert','count'=>count($clean)]);
        break;

    case 'trigger_scene':
        $t=wcr_pi_token(); if(!$t) wcr_pi_respond(['success'=>false,'error'=>'Kein Token'],422);
        $sid=trim($_POST['scene_id']??''); if(!$sid) wcr_pi_respond(['success'=>false,'error'=>'scene_id fehlt'],422);
        $scenes=wcr_pi_scenes_load(); $scene=null;
        foreach($scenes as $s){ if($s['id']===$sid){$scene=$s;break;} }
        if(!$scene) wcr_pi_respond(['success'=>false,'error'=>'Szene nicht gefunden'],404);
        $results=[]; $allOk=true;
        foreach($scene['slots'] as $slot){
            if(empty($slot['group_id'])||empty($slot['playlist_id'])) continue;
            $r=wcr_pi_request('PUT','/api/groups/'.rawurlencode($slot['group_id']),['currentPlaylist'=>$slot['playlist_id']],$t);
            $results[]=['group'=>$slot['group_label']??$slot['group_id'],'playlist'=>$slot['playlist_label']??$slot['playlist_id'],'ok'=>$r['success'],'status'=>$r['status']??null];
            if(!$r['success']) $allOk=false;
        }
        wcr_pi_respond(['success'=>$allOk,'message'=>$allOk?'✅ Alle '.count($results).' Screens geschaltet':'⚠️ Teilweise fehlgeschlagen','results'=>$results],$allOk?200:207);
        break;

    // ─ Manueller Einzel-Trigger ─────────────────────────────────────
    case 'set_playlist':
        $t=wcr_pi_token(); if(!$t) wcr_pi_respond(['success'=>false,'error'=>'Kein Token'],422);
        $gid=trim($_POST['group_id']??''); $pid=trim($_POST['playlist_id']??'');
        if(!$gid||!$pid) wcr_pi_respond(['success'=>false,'error'=>'Gruppe und Playlist benötigt'],422);
        $r=wcr_pi_request('PUT','/api/groups/'.rawurlencode($gid),['currentPlaylist'=>$pid],$t);
        wcr_pi_respond(array_merge($r,['message'=>$r['success']?'✅ Getriggert':'❌ Fehlgeschlagen']),$r['success']?200:500);
        break;

    // ─ Asset Upload ──────────────────────────────────────────────
    case 'upload_asset':
        $t=wcr_pi_token(); if(!$t) wcr_pi_respond(['success'=>false,'error'=>'Kein Token'],422);
        if (empty($_FILES['file'])||$_FILES['file']['error']!==0)
            wcr_pi_respond(['success'=>false,'error'=>'Keine Datei oder Upload-Fehler: '.($_FILES['file']['error']??'?')],422);
        $file     = $_FILES['file'];
        $allowed  = ['image/jpeg','image/png','image/gif','image/webp','video/mp4','video/webm','video/quicktime','application/pdf'];
        if (!in_array($file['type'], $allowed, true))
            wcr_pi_respond(['success'=>false,'error'=>'Dateityp nicht erlaubt: '.$file['type']],422);
        if ($file['size'] > 500 * 1024 * 1024)
            wcr_pi_respond(['success'=>false,'error'=>'Datei zu groß (max 500 MB)'],422);
        $config2 = wcr_pisignage_load_config();
        $baseUrl = rtrim($config2['base_url']??'','/');
        if (!$baseUrl) wcr_pi_respond(['success'=>false,'error'=>'Base URL fehlt'],422);
        // Multipart-Upload direkt an piSignage
        $ch = curl_init($baseUrl . '/api/assets');
        $cf = new CURLFile($file['tmp_name'], $file['type'], $file['name']);
        curl_setopt_array($ch,[
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['x-access-token: '.$t],
            CURLOPT_POSTFIELDS     => ['file' => $cf, 'filename' => $file['name']],
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $raw=curl_exec($ch); $err=curl_error($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
        if ($raw===false||$err) wcr_pi_respond(['success'=>false,'error'=>'cURL: '.$err],500);
        $decoded=json_decode($raw,true);
        wcr_pi_respond(['success'=>($code>=200&&$code<300),'message'=>($code>=200&&$code<300)?'✅ '.$file['name'].' hochgeladen':'❌ Upload fehlgeschlagen (HTTP '.$code.')','data'=>$decoded],$code>=200&&$code<300?200:500);
        break;

    // ─ Playlist erstellen ───────────────────────────────────────────
    case 'create_playlist':
        $t=wcr_pi_token(); if(!$t) wcr_pi_respond(['success'=>false,'error'=>'Kein Token'],422);
        $name   = trim($_POST['name']  ?? '');
        $assets = json_decode($_POST['assets'] ?? '[]', true);
        if (!$name) wcr_pi_respond(['success'=>false,'error'=>'Name fehlt'],422);
        if (!is_array($assets)) $assets=[];
        // piSignage Playlist-Format: assets = Array von {filename, duration}
        $cleanAssets=[];
        foreach($assets as $a) {
            if (!empty($a['filename'])) {
                $cleanAssets[]=['filename'=>(string)$a['filename'],'duration'=>(int)($a['duration']??10),'selected'=>true,'dragSelected'=>false];
            }
        }
        $body=['name'=>$name,'assets'=>$cleanAssets,'settings'=>['timeToShowEachAsset'=>10,'playlistType'=>'normal']];
        $r=wcr_pi_request('POST','/api/playlists',$body,$t);
        wcr_pi_respond(array_merge($r,['message'=>$r['success']?'✅ Playlist "'.$name.'" erstellt':'❌ Fehler']),$r['success']?200:500);
        break;

    // ─ Playlist löschen ─────────────────────────────────────────────
    case 'delete_playlist':
        $t=wcr_pi_token(); if(!$t) wcr_pi_respond(['success'=>false,'error'=>'Kein Token'],422);
        $pid=trim($_POST['playlist_id']??''); if(!$pid) wcr_pi_respond(['success'=>false,'error'=>'playlist_id fehlt'],422);
        $r=wcr_pi_request('DELETE','/api/playlists/'.rawurlencode($pid),null,$t);
        wcr_pi_respond(array_merge($r,['message'=>$r['success']?'✅ Playlist gelöscht':'❌ Fehlgeschlagen']),$r['success']?200:500);
        break;

    // ─ Asset löschen ───────────────────────────────────────────────
    case 'delete_asset':
        $t=wcr_pi_token(); if(!$t) wcr_pi_respond(['success'=>false,'error'=>'Kein Token'],422);
        $fn=trim($_POST['filename']??''); if(!$fn) wcr_pi_respond(['success'=>false,'error'=>'filename fehlt'],422);
        $r=wcr_pi_request('DELETE','/api/assets/'.rawurlencode($fn),null,$t);
        wcr_pi_respond(array_merge($r,['message'=>$r['success']?'✅ Asset gelöscht':'❌ Fehlgeschlagen']),$r['success']?200:500);
        break;

    default:
        wcr_pi_respond(['success'=>false,'error'=>'Unbekannte action: '.htmlspecialchars($action)],400);
}
