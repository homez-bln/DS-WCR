<?php
/**
 * api/spotify.php — Spotify REST-Proxy v1
 * Nur admin + cernal
 * Actions: save_settings, start_auth, refresh_token,
 *          get_playlists, play_playlist, get_devices,
 *          search_tracks, add_to_queue, get_current
 */
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/pisignage-config.php'; // wp_options helpers

require_login();
if (!wcr_can('manage_users') && !wcr_is_cernal()) {
    http_response_code(403);
    echo json_encode(['success'=>false,'error'=>'Kein Zugriff']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'error'=>'Method not allowed']);
    exit;
}
wcr_verify_csrf();

// ── Config helpers ────────────────────────────────────────────────
function sp_cfg_load(): array {
    if (!defined('DSC_WP_API_BASE')) return [];
    $ch = curl_init(DSC_WP_API_BASE.'/options/wcr_spotify_config?wcr_secret='.urlencode(DSC_WP_SECRET));
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>5,CURLOPT_SSL_VERIFYPEER=>false]);
    $b=curl_exec($ch); $c=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    if($c===200&&$b){$j=json_decode($b,true);if(isset($j['value'])&&is_array($j['value']))return $j['value'];}
    return [];
}
function sp_cfg_save(array $cfg): bool {
    if (!defined('DSC_WP_API_BASE')) return false;
    $ch=curl_init(DSC_WP_API_BASE.'/options');
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_TIMEOUT=>10,CURLOPT_SSL_VERIFYPEER=>false,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
        CURLOPT_POSTFIELDS=>json_encode(['wcr_secret'=>DSC_WP_SECRET,'key'=>'wcr_spotify_config','value'=>$cfg],JSON_UNESCAPED_UNICODE)]);
    $b=curl_exec($ch); $c=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    return $c===200;
}

// ── Token refresh ─────────────────────────────────────────────────
function sp_get_access_token(): array {
    $cfg = sp_cfg_load();
    if (empty($cfg['client_id'])||empty($cfg['client_secret'])||empty($cfg['refresh_token']))
        return ['success'=>false,'error'=>'Spotify nicht konfiguriert — bitte erst OAuth durchführen'];
    // Prüfen ob cached token noch gültig
    if (!empty($cfg['access_token']) && !empty($cfg['token_expires']) && time() < (int)$cfg['token_expires'] - 60)
        return ['success'=>true,'token'=>$cfg['access_token']];
    // Refresh
    $ch=curl_init('https://accounts.spotify.com/api/token');
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_TIMEOUT=>15,CURLOPT_SSL_VERIFYPEER=>false,
        CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic '.base64_encode($cfg['client_id'].':'.$cfg['client_secret'])],
        CURLOPT_POSTFIELDS=>http_build_query(['grant_type'=>'refresh_token','refresh_token'=>$cfg['refresh_token']])]);
    $b=curl_exec($ch); $c=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    if($c!==200) return ['success'=>false,'error'=>'Token-Refresh fehlgeschlagen (HTTP '.$c.')','raw'=>$b];
    $d=json_decode($b,true);
    if(empty($d['access_token'])) return ['success'=>false,'error'=>'Kein access_token in Antwort','raw'=>$b];
    $cfg['access_token']=$d['access_token'];
    $cfg['token_expires']=time()+($d['expires_in']??3600);
    sp_cfg_save($cfg);
    return ['success'=>true,'token'=>$d['access_token']];
}

// ── Spotify API call ──────────────────────────────────────────────
function sp_call(string $method, string $endpoint, ?array $body=null, ?array $query=null): array {
    $tr=sp_get_access_token();
    if(!$tr['success']) return $tr;
    $url='https://api.spotify.com/v1'.$endpoint;
    if($query) $url.='?'.http_build_query($query);
    $ch=curl_init($url);
    $headers=['Authorization: Bearer '.$tr['token'],'Content-Type: application/json'];
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>strtoupper($method),
        CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>20,CURLOPT_SSL_VERIFYPEER=>false]);
    if($body!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($body));
    $raw=curl_exec($ch); $err=curl_error($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    if($raw===false||$err) return ['success'=>false,'error'=>'cURL: '.$err];
    $decoded = $raw ? json_decode($raw,true) : null;
    return ['success'=>($code>=200&&$code<300),'status'=>$code,'data'=>$decoded,'raw'=>$raw];
}

// ── respond ───────────────────────────────────────────────────────
function sp_respond(array $p, int $s=200): void {
    $p['csrf_token']=wcr_csrf_token();
    http_response_code($s);
    echo json_encode($p,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}

$action=$_POST['action']??'';

switch($action) {

    // ── Einstellungen speichern
    case 'save_settings':
        $cfg=sp_cfg_load();
        $new=[
            'client_id'    =>trim($_POST['client_id']??''),
            'client_secret'=>trim($_POST['client_secret']??''),
            'redirect_uri' =>trim($_POST['redirect_uri']??''),
        ];
        if($new['client_secret']==='') $new['client_secret']=$cfg['client_secret']??'';
        $merged=array_merge($cfg,$new);
        if(!sp_cfg_save($merged)) sp_respond(['success'=>false,'error'=>'Speichern fehlgeschlagen'],500);
        sp_respond(['success'=>true,'message'=>'✅ Gespeichert']);
        break;

    // ── OAuth URL generieren
    case 'start_auth':
        $cfg=sp_cfg_load();
        if(empty($cfg['client_id'])||empty($cfg['redirect_uri']))
            sp_respond(['success'=>false,'error'=>'Client ID und Redirect URI müssen gespeichert sein'],422);
        $scopes='user-read-playback-state user-modify-playback-state user-read-currently-playing playlist-read-private playlist-read-collaborative';
        $url='https://accounts.spotify.com/authorize?'.http_build_query([
            'client_id'    =>$cfg['client_id'],
            'response_type'=>'code',
            'redirect_uri' =>$cfg['redirect_uri'],
            'scope'        =>$scopes,
            'show_dialog'  =>'true',
        ]);
        sp_respond(['success'=>true,'auth_url'=>$url]);
        break;

    // ── Auth Code → Refresh Token tauschen (nach Callback)
    case 'exchange_code':
        $cfg=sp_cfg_load();
        $code=trim($_POST['code']??'');
        if(!$code) sp_respond(['success'=>false,'error'=>'code fehlt'],422);
        $ch=curl_init('https://accounts.spotify.com/api/token');
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_TIMEOUT=>15,CURLOPT_SSL_VERIFYPEER=>false,
            CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded',
                'Authorization: Basic '.base64_encode($cfg['client_id'].':'.$cfg['client_secret'])],
            CURLOPT_POSTFIELDS=>http_build_query(['grant_type'=>'authorization_code','code'=>$code,'redirect_uri'=>$cfg['redirect_uri']])]);
        $b=curl_exec($ch); $c=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
        if($c!==200) sp_respond(['success'=>false,'error'=>'Code-Exchange fehlgeschlagen','raw'=>$b],500);
        $d=json_decode($b,true);
        if(empty($d['refresh_token'])) sp_respond(['success'=>false,'error'=>'Kein Refresh Token','raw'=>$b],500);
        $cfg['refresh_token']=$d['refresh_token'];
        $cfg['access_token']=$d['access_token']??'';
        $cfg['token_expires']=time()+($d['expires_in']??3600);
        sp_cfg_save($cfg);
        sp_respond(['success'=>true,'message'=>'✅ Verbunden! Refresh Token gespeichert.']);
        break;

    // ── Geräte abrufen
    case 'get_devices':
        $r=sp_call('GET','/me/player/devices');
        sp_respond($r,$r['success']?200:500);
        break;

    // ── Playlists abrufen
    case 'get_playlists':
        $r=sp_call('GET','/me/playlists',null,['limit'=>50]);
        sp_respond($r,$r['success']?200:500);
        break;

    // ── Playlist abspielen
    case 'play_playlist':
        $uri =trim($_POST['uri']??'');
        $did =trim($_POST['device_id']??'');
        if(!$uri) sp_respond(['success'=>false,'error'=>'uri fehlt'],422);
        $body=['context_uri'=>$uri];
        $query=$did?['device_id'=>$did]:null;
        $r=sp_call('PUT','/me/player/play',$body,$query);
        sp_respond(array_merge($r,['message'=>$r['success']?'✅ Wiedergabe gestartet':'❌ Fehler']),$r['success']?200:500);
        break;

    // ── Track suchen
    case 'search_tracks':
        $q=trim($_POST['q']??'');
        if(!$q) sp_respond(['success'=>false,'error'=>'Suchbegriff fehlt'],422);
        $r=sp_call('GET','/search',null,['q'=>$q,'type'=>'track','limit'=>20,'market'=>'DE']);
        sp_respond($r,$r['success']?200:500);
        break;

    // ── Track zur Queue hinzufügen
    case 'add_to_queue':
        $uri=trim($_POST['uri']??'');
        $did=trim($_POST['device_id']??'');
        if(!$uri) sp_respond(['success'=>false,'error'=>'uri fehlt'],422);
        $q=$did?['device_id'=>$did]:null;
        $r=sp_call('POST','/me/player/queue',null,array_merge(['uri'=>$uri],$q??[]));
        sp_respond(array_merge($r,['message'=>$r['success']?'✅ Song zur Warteschlange hinzugefügt':'❌ Fehler']),$r['success']?200:500);
        break;

    // ── Aktuellen Track abrufen
    case 'get_current':
        $r=sp_call('GET','/me/player/currently-playing');
        sp_respond($r,$r['success']?200:500);
        break;

    // ── Wiedergabe pausieren / fortsetzen
    case 'pause':
        $r=sp_call('PUT','/me/player/pause');
        sp_respond(array_merge($r,['message'=>$r['success']?'⏸ Pausiert':'❌ Fehler']),$r['success']?200:500);
        break;

    case 'resume':
        $r=sp_call('PUT','/me/player/play');
        sp_respond(array_merge($r,['message'=>$r['success']?'▶️ Fortgesetzt':'❌ Fehler']),$r['success']?200:500);
        break;

    case 'next_track':
        $r=sp_call('POST','/me/player/next');
        sp_respond(array_merge($r,['message'=>$r['success']?'⏭ Nächster Track':'❌ Fehler']),$r['success']?200:500);
        break;

    default:
        sp_respond(['success'=>false,'error'=>'Unbekannte action: '.htmlspecialchars($action)],400);
}
