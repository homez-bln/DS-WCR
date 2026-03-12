<?php
/**
 * api/pisignage.php — piSignage REST-Proxy
 * NUR für cernal zugänglich (wcr_is_cernal())
 * Konfiguration wird via wp_options gespeichert
 */
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/pisignage-config.php';

require_login();

// ── NUR CERNAL ──────────────────────────────────────────────────────
if (!wcr_is_cernal()) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Kein Zugriff']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// ── Nur POST erlaubt ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// ── CSRF prüfen ──────────────────────────────────────────────────────
wcr_verify_csrf();

$action = $_POST['action'] ?? '';
$config = wcr_pisignage_load_config();

// ── Hilfsfunktionen ──────────────────────────────────────────────────

function wcr_pi_respond(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function wcr_pi_request(string $method, string $endpoint, ?array $body = null, ?string $token = null): array {
    $config  = wcr_pisignage_load_config();
    $baseUrl = rtrim($config['base_url'] ?? '', '/');

    if ($baseUrl === '') {
        return ['success' => false, 'error' => 'piSignage Base URL fehlt — bitte erst konfigurieren'];
    }

    $url     = $baseUrl . $endpoint;
    $headers = ['Content-Type: application/json'];

    if (!empty($token)) {
        $headers[] = 'x-access-token: ' . $token;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_SLASHES));
    }

    $raw     = curl_exec($ch);
    $curlErr = curl_error($ch);
    $status  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false || $curlErr) {
        return ['success' => false, 'error' => 'cURL Fehler: ' . $curlErr];
    }

    $decoded = json_decode($raw, true);

    return [
        'success' => ($status >= 200 && $status < 300),
        'status'  => $status,
        'data'    => $decoded,
    ];
}

function wcr_pi_login(?string $otp = null): array {
    $config = wcr_pisignage_load_config();

    if (empty($config['email']) || empty($config['password'])) {
        return ['success' => false, 'error' => 'E-Mail oder Passwort fehlt — bitte erst unter Verbindung eintragen'];
    }

    $payload = [
        'email'    => $config['email'],
        'password' => $config['password'],
        'getToken' => true,
    ];

    if ($otp !== null && $otp !== '') {
        $payload['code'] = $otp;
    }

    return wcr_pi_request('POST', '/api/session', $payload, null);
}

function wcr_pi_extract_token(array $response): ?string {
    $data = $response['data'] ?? null;
    if (!is_array($data)) return null;

    foreach ([
        $data['token']              ?? null,
        $data['accessToken']        ?? null,
        $data['data']['token']      ?? null,
        $data['data']['accessToken'] ?? null,
    ] as $candidate) {
        if (is_string($candidate) && trim($candidate) !== '') {
            return trim($candidate);
        }
    }

    return null;
}

// ── Action-Router ────────────────────────────────────────────────────

switch ($action) {

    // ── Einstellungen speichern ──────────────────────────────────────
    case 'save_settings':
        $newConfig = [
            'base_url'  => rtrim(trim($_POST['base_url']  ?? ''), '/'),
            'api_token' => trim($_POST['api_token'] ?? ''),
            'email'     => trim($_POST['email']     ?? ''),
            'password'  => trim($_POST['password']  ?? ''),
        ];

        if ($newConfig['base_url'] !== '' && !preg_match('~^https?://~', $newConfig['base_url'])) {
            wcr_pi_respond(['success' => false, 'error' => 'Base URL muss mit https:// beginnen'], 422);
        }

        // Passwort: leer = altes behalten
        if ($newConfig['password'] === '') {
            $newConfig['password'] = $config['password'] ?? '';
        }

        $merged = array_merge($config, $newConfig);

        if (!wcr_pisignage_save_config($merged)) {
            wcr_pi_respond(['success' => false, 'error' => 'Fehler beim Speichern in der Datenbank'], 500);
        }

        wcr_pi_respond(['success' => true, 'message' => '✅ Konfiguration gespeichert']);
        break;

    // ── Token automatisch abrufen ────────────────────────────────────
    case 'request_token':
        $otp   = trim($_POST['otp'] ?? '');
        $login = wcr_pi_login($otp !== '' ? $otp : null);

        if (!$login['success']) {
            wcr_pi_respond([
                'success' => false,
                'error'   => 'Token-Abruf fehlgeschlagen — prüfe E-Mail/Passwort oder OTP',
                'details' => $login,
            ], 401);
        }

        $token = wcr_pi_extract_token($login);

        if (!$token) {
            wcr_pi_respond([
                'success' => false,
                'error'   => 'Kein Token in der API-Antwort gefunden',
                'details' => $login,
            ], 500);
        }

        $config['api_token'] = $token;

        if (!wcr_pisignage_save_config($config)) {
            wcr_pi_respond(['success' => false, 'error' => 'Token erhalten, aber Speichern fehlgeschlagen'], 500);
        }

        wcr_pi_respond([
            'success'      => true,
            'message'      => '✅ Token gespeichert',
            'token_masked' => wcr_pisignage_mask_token($token),
        ]);
        break;

    // ── Verbindung testen ────────────────────────────────────────────
    case 'test_connection':
        if (empty($config['api_token'])) {
            wcr_pi_respond(['success' => false, 'error' => 'Kein API-Token gespeichert'], 422);
        }

        $result = wcr_pi_request('GET', '/api/groups', null, $config['api_token']);
        wcr_pi_respond(array_merge($result, [
            'message' => $result['success'] ? '✅ Verbindung erfolgreich' : '❌ Verbindung fehlgeschlagen',
        ]), $result['success'] ? 200 : 500);
        break;

    // ── Gruppen laden ────────────────────────────────────────────────
    case 'get_groups':
        if (empty($config['api_token'])) {
            wcr_pi_respond(['success' => false, 'error' => 'Kein API-Token gespeichert'], 422);
        }

        $result = wcr_pi_request('GET', '/api/groups', null, $config['api_token']);
        wcr_pi_respond($result, $result['success'] ? 200 : 500);
        break;

    // ── Playlists laden ──────────────────────────────────────────────
    case 'get_playlists':
        if (empty($config['api_token'])) {
            wcr_pi_respond(['success' => false, 'error' => 'Kein API-Token gespeichert'], 422);
        }

        $result = wcr_pi_request('GET', '/api/playlists', null, $config['api_token']);
        wcr_pi_respond($result, $result['success'] ? 200 : 500);
        break;

    // ── Playlist triggern ────────────────────────────────────────────
    case 'set_playlist':
        if (empty($config['api_token'])) {
            wcr_pi_respond(['success' => false, 'error' => 'Kein API-Token gespeichert'], 422);
        }

        $groupId    = trim($_POST['group_id']    ?? '');
        $playlistId = trim($_POST['playlist_id'] ?? '');

        if ($groupId === '' || $playlistId === '') {
            wcr_pi_respond(['success' => false, 'error' => 'Gruppe und Playlist müssen ausgewählt sein'], 422);
        }

        $result = wcr_pi_request(
            'PUT',
            '/api/groups/' . rawurlencode($groupId),
            ['currentPlaylist' => $playlistId],
            $config['api_token']
        );

        wcr_pi_respond(array_merge($result, [
            'message' => $result['success'] ? '✅ Playlist erfolgreich getriggert' : '❌ Trigger fehlgeschlagen',
        ]), $result['success'] ? 200 : 500);
        break;

    // ── Fallback ─────────────────────────────────────────────────────
    default:
        wcr_pi_respond(['success' => false, 'error' => 'Ungültige action: ' . htmlspecialchars($action)], 400);
}
