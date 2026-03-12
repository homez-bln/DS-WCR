<?php
/**
 * api/pisignage.php — piSignage REST-Proxy
 * NUR für cernal zugänglich (wcr_is_cernal())
 * Konfiguration wird via wp_options gespeichert
 *
 * v2: + Preset-System (save_presets, load_presets, trigger_preset)
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

// ── Preset Helpers ───────────────────────────────────────────────────

function wcr_pi_presets_load(): array {
    // Lädt Presets aus wp_options via REST API (gleiche Methode wie Config)
    if (!defined('DSC_WP_API_BASE')) return [];
    $ch = curl_init(DSC_WP_API_BASE . '/options/wcr_pisignage_presets?wcr_secret=' . urlencode(DSC_WP_SECRET));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code === 200 && $body) {
        $json = json_decode($body, true);
        if (isset($json['value']) && is_array($json['value'])) return $json['value'];
    }
    return [];
}

function wcr_pi_presets_save(array $presets): bool {
    if (!defined('DSC_WP_API_BASE')) return false;
    $ch = curl_init(DSC_WP_API_BASE . '/options');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode([
            'wcr_secret' => DSC_WP_SECRET,
            'key'        => 'wcr_pisignage_presets',
            'value'      => $presets,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code === 200;
}

// ── Hilfsfunktionen ──────────────────────────────────────────────────

function wcr_pi_respond(array $payload, int $status = 200): void {
    $payload['csrf_token'] = wcr_csrf_token();
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function wcr_pi_request(string $method, string $endpoint, ?array $body = null, ?string $token = null, string $baseOverride = ''): array {
    $config  = wcr_pisignage_load_config();
    $baseUrl = $baseOverride !== '' ? $baseOverride : rtrim($config['base_url'] ?? '', '/');
    if ($baseUrl === '') return ['success' => false, 'error' => 'piSignage Base URL fehlt — bitte erst konfigurieren'];
    $url     = $baseUrl . $endpoint;
    $headers = ['Content-Type: application/json'];
    if (!empty($token)) $headers[] = 'x-access-token: ' . $token;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_SLASHES));
    $raw     = curl_exec($ch);
    $curlErr = curl_error($ch);
    $status  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $curlErr) return ['success' => false, 'error' => 'cURL Fehler: ' . $curlErr];
    return [
        'success' => ($status >= 200 && $status < 300),
        'status'  => $status,
        'data'    => json_decode($raw, true),
        'raw'     => $raw,
    ];
}

function wcr_pi_login(?string $otp = null): array {
    $config = wcr_pisignage_load_config();
    if (empty($config['email']) || empty($config['password']))
        return ['success' => false, 'error' => 'E-Mail oder Passwort fehlt'];
    $payload = ['email' => $config['email'], 'password' => $config['password'], 'getToken' => true];
    if ($otp !== null && $otp !== '') $payload['code'] = $otp;
    return wcr_pi_request('POST', '/api/session', $payload, null, 'https://piathome.com');
}

function wcr_pi_extract_token(array $response): ?string {
    $data = $response['data'] ?? null;
    if (!is_array($data)) return null;
    foreach ([
        $data['token'] ?? null,
        $data['accessToken'] ?? null,
        $data['data']['token'] ?? null,
        $data['data']['accessToken'] ?? null,
    ] as $c) {
        if (is_string($c) && trim($c) !== '') return trim($c);
    }
    return null;
}

// ── Action-Router ────────────────────────────────────────────────────

switch ($action) {

    case 'save_settings':
        $newConfig = [
            'base_url'  => rtrim(trim($_POST['base_url']  ?? ''), '/'),
            'api_token' => trim($_POST['api_token'] ?? ''),
            'email'     => trim($_POST['email']     ?? ''),
            'password'  => trim($_POST['password']  ?? ''),
        ];
        if ($newConfig['base_url'] !== '' && !preg_match('~^https?://~', $newConfig['base_url']))
            wcr_pi_respond(['success' => false, 'error' => 'Base URL muss mit https:// beginnen'], 422);
        if ($newConfig['password'] === '') $newConfig['password'] = $config['password'] ?? '';
        $merged = array_merge($config, $newConfig);
        if (!wcr_pisignage_save_config($merged))
            wcr_pi_respond(['success' => false, 'error' => 'Fehler beim Speichern'], 500);
        wcr_pi_respond(['success' => true, 'message' => '✅ Konfiguration gespeichert']);
        break;

    case 'request_token':
        $otp   = trim($_POST['otp'] ?? '');
        $login = wcr_pi_login($otp !== '' ? $otp : null);
        if (!$login['success'])
            wcr_pi_respond(['success' => false, 'error' => 'Token-Abruf fehlgeschlagen — prüfe E-Mail/Passwort oder OTP', 'details' => $login], 401);
        $token = wcr_pi_extract_token($login);
        if (!$token)
            wcr_pi_respond(['success' => false, 'error' => 'Kein Token in der API-Antwort gefunden', 'details' => $login], 500);
        $config['api_token'] = $token;
        if (!wcr_pisignage_save_config($config))
            wcr_pi_respond(['success' => false, 'error' => 'Token erhalten, aber Speichern fehlgeschlagen'], 500);
        wcr_pi_respond(['success' => true, 'message' => '✅ Token gespeichert', 'token_masked' => wcr_pisignage_mask_token($token)]);
        break;

    case 'test_connection':
        if (empty($config['api_token'])) wcr_pi_respond(['success' => false, 'error' => 'Kein API-Token gespeichert'], 422);
        $result = wcr_pi_request('GET', '/api/groups', null, $config['api_token']);
        wcr_pi_respond(array_merge($result, ['message' => $result['success'] ? '✅ Verbindung erfolgreich' : '❌ Verbindung fehlgeschlagen']), $result['success'] ? 200 : 500);
        break;

    case 'get_groups':
        if (empty($config['api_token'])) wcr_pi_respond(['success' => false, 'error' => 'Kein API-Token gespeichert'], 422);
        $result = wcr_pi_request('GET', '/api/groups', null, $config['api_token']);
        wcr_pi_respond($result, $result['success'] ? 200 : 500);
        break;

    case 'get_playlists':
        if (empty($config['api_token'])) wcr_pi_respond(['success' => false, 'error' => 'Kein API-Token gespeichert'], 422);
        $result = wcr_pi_request('GET', '/api/playlists', null, $config['api_token']);
        wcr_pi_respond($result, $result['success'] ? 200 : 500);
        break;

    case 'set_playlist':
        if (empty($config['api_token'])) wcr_pi_respond(['success' => false, 'error' => 'Kein API-Token gespeichert'], 422);
        $groupId    = trim($_POST['group_id']    ?? '');
        $playlistId = trim($_POST['playlist_id'] ?? '');
        if ($groupId === '' || $playlistId === '')
            wcr_pi_respond(['success' => false, 'error' => 'Gruppe und Playlist müssen ausgewählt sein'], 422);
        $result = wcr_pi_request('PUT', '/api/groups/' . rawurlencode($groupId), ['currentPlaylist' => $playlistId], $config['api_token']);
        wcr_pi_respond(array_merge($result, ['message' => $result['success'] ? '✅ Playlist erfolgreich getriggert' : '❌ Trigger fehlgeschlagen']), $result['success'] ? 200 : 500);
        break;

    // ── PRESETS ──────────────────────────────────────────────────────

    case 'load_presets':
        wcr_pi_respond(['success' => true, 'presets' => wcr_pi_presets_load()]);
        break;

    case 'save_presets':
        $raw = $_POST['presets'] ?? '';
        $presets = json_decode($raw, true);
        if (!is_array($presets))
            wcr_pi_respond(['success' => false, 'error' => 'Ungültiges Preset-Format'], 422);
        // Sanitize: nur erlaubte Felder
        $clean = [];
        foreach ($presets as $p) {
            if (empty($p['id']) || empty($p['label'])) continue;
            $actions = [];
            foreach ((array)($p['actions'] ?? []) as $a) {
                if (!empty($a['group_id']) && !empty($a['playlist_id'])) {
                    $actions[] = [
                        'group_id'    => (string)$a['group_id'],
                        'playlist_id' => (string)$a['playlist_id'],
                        'label'       => (string)($a['label'] ?? ''),
                    ];
                }
            }
            $clean[] = [
                'id'      => preg_replace('/[^a-z0-9_-]/', '', strtolower((string)$p['id'])),
                'label'   => substr((string)$p['label'],  0, 60),
                'icon'    => substr((string)($p['icon']  ?? '🎬'), 0, 8),
                'color'   => preg_match('/^#[0-9a-fA-F]{3,6}$/', $p['color'] ?? '') ? $p['color'] : '#019ee3',
                'actions' => $actions,
            ];
        }
        if (!wcr_pi_presets_save($clean))
            wcr_pi_respond(['success' => false, 'error' => 'Fehler beim Speichern der Presets'], 500);
        wcr_pi_respond(['success' => true, 'message' => '✅ Presets gespeichert', 'count' => count($clean)]);
        break;

    case 'trigger_preset':
        if (empty($config['api_token'])) wcr_pi_respond(['success' => false, 'error' => 'Kein API-Token gespeichert'], 422);
        $presetId = trim($_POST['preset_id'] ?? '');
        if ($presetId === '') wcr_pi_respond(['success' => false, 'error' => 'preset_id fehlt'], 422);
        $presets = wcr_pi_presets_load();
        $preset  = null;
        foreach ($presets as $p) { if ($p['id'] === $presetId) { $preset = $p; break; } }
        if (!$preset) wcr_pi_respond(['success' => false, 'error' => 'Preset nicht gefunden: ' . $presetId], 404);
        $results = [];
        $allOk   = true;
        foreach ($preset['actions'] as $a) {
            $r = wcr_pi_request('PUT', '/api/groups/' . rawurlencode($a['group_id']), ['currentPlaylist' => $a['playlist_id']], $config['api_token']);
            $results[] = [
                'group_id'    => $a['group_id'],
                'playlist_id' => $a['playlist_id'],
                'label'       => $a['label'] ?? '',
                'ok'          => $r['success'],
                'status'      => $r['status'] ?? null,
            ];
            if (!$r['success']) $allOk = false;
        }
        wcr_pi_respond([
            'success' => $allOk,
            'message' => $allOk ? '✅ Alle ' . count($results) . ' Aktionen erfolgreich' : '⚠️ Teilweise fehlgeschlagen',
            'results' => $results,
        ], $allOk ? 200 : 207);
        break;

    default:
        wcr_pi_respond(['success' => false, 'error' => 'Ungültige action: ' . htmlspecialchars($action)], 400);
}
