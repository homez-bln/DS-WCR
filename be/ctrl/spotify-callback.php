<?php
/**
 * ctrl/spotify-callback.php
 * Spotify OAuth Redirect URI — tauscht Code gegen Refresh Token
 */
require_once __DIR__ . '/../inc/auth.php';
require_login();
if (!wcr_can('manage_users') && !wcr_is_cernal()) {
    http_response_code(403); echo 'Kein Zugriff'; exit;
}

$error = $_GET['error'] ?? '';
$code  = $_GET['code']  ?? '';

if ($error) {
    $msg = 'Spotify-Fehler: '.htmlspecialchars($error);
    $ok  = false;
} elseif ($code) {
    // Code via eigene API tauschen
    $csrf = wcr_csrf_token();
    $ch   = curl_init('https://'.$_SERVER['HTTP_HOST'].'/be/api/spotify.php');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_POSTFIELDS     => http_build_query(['action'=>'exchange_code','code'=>$code,'csrf_token'=>$csrf]),
        CURLOPT_HTTPHEADER     => ['Cookie: '.$_SERVER['HTTP_COOKIE']??''],
    ]);
    $raw  = curl_exec($ch); curl_close($ch);
    $resp = json_decode($raw, true);
    $ok   = !empty($resp['success']);
    $msg  = $resp['message'] ?? ($resp['error'] ?? 'Unbekannter Fehler');
} else {
    $msg = 'Kein Code erhalten.'; $ok = false;
}
?>
<!DOCTYPE html>
<html lang="de">
<head><meta charset="UTF-8"><title>Spotify Verbindung</title>
<style>
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f5f5f7;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;}
.box{background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,.1);padding:40px 48px;text-align:center;max-width:420px;}
.icon{font-size:52px;margin-bottom:16px;}
.title{font-size:20px;font-weight:700;margin-bottom:8px;color:#1d1d1f;}
.msg{font-size:14px;color:#86868b;margin-bottom:24px;}
.btn{display:inline-block;padding:12px 28px;background:#1DB954;color:#fff;border-radius:10px;text-decoration:none;font-weight:600;font-size:14px;transition:opacity .15s;}
.btn:hover{opacity:.85;}
.btn.err{background:#ff3b30;}
</style></head>
<body>
<div class="box">
  <div class="icon"><?= $ok ? '✅' : '❌' ?></div>
  <div class="title"><?= $ok ? 'Spotify verbunden!' : 'Verbindung fehlgeschlagen' ?></div>
  <div class="msg"><?= htmlspecialchars($msg) ?></div>
  <a href="/be/ctrl/spotify.php" class="btn <?= $ok?'':'err' ?>">
    <?= $ok ? '→ Zu Spotify' : '← Zurück' ?>
  </a>
</div>
</body></html>
