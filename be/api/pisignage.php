<?php
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/pisignage-config.php';

require_login();
wcr_require('admin');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

wcr_verify_csrf();

$action = $_POST['action'] ?? '';

function wcr_pisignage_response(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function wcr_pisignage_request(string $method, string $endpoint, array $body = null, ?string $token = null): array {
    $config = wcr_pisignage_load_config();
    $baseUrl = rtrim($config['base_url'] ?? '', '/');

    if ($baseUrl === '') {
        return ['success' => false, 'error' => 'piSignage base_url fehlt'];
    }

    $url = $baseUrl . $endpoint;
    $headers = ['Content-Type: application/json'];

    if (!empty($token)) {
        $headers[] = 'x-access-token: ' . $token;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 25,
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_SLASHES));
    }

    $raw = curl_exec($ch);
    $curlErr = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false || $curlErr) {
        return ['success' => false, 'error' => 'cURL Fehler: ' . $curlErr];
    }

    $decoded = json_decode($raw, true);

    return [
        'success' => $status >= 200 && $status < 300,
        'status' => $status,
        'data' => $decoded,
        'raw' => $raw,
    ];
}

function wcr_pisignage_login_and_get_token(?string $otp = null): array {
    $config = wcr_pisignage_load_config();

    if (empty($config['email']) || empty($config['password'])) {
        return ['success' => false, 'error' => 'E-Mail oder Passwort fehlt'];
    }

    $payload = [
        'email' => $config['email'],
        'password' => $config['password'],
        'getToken' => true,
    ];

    if ($otp) {
        $payload['code'] = $otp;
    }

    return wcr_pisignage_request('POST', '/api/session', $payload, null);
}

function wcr_pisignage_extract_token(array $response): ?string {
    $data = $response['data'] ?? null;

    if (!is_array($data)) {
        return null;
    }

    $candidates = [
        $data['token'] ?? null,
        $data['accessToken'] ?? null,
        $data['data']['token'] ?? null,
    ];

    foreach ($candidates as $candidate) {
        if (is_string($candidate) && trim($candidate) !== '') {
            return trim($candidate);
        }
    }

    return null;
}

$config = wcr_pisignage_load_config();

switch ($action) {
    case 'save_settings':
        $config['base_url'] = trim($_POST['base_url'] ?? '');
        $config['api_token'] = trim($_POST['api_token'] ?? '');
        $config['email'] = trim($_POST['email'] ?? '');
        $config['password'] = trim($_POST['password'] ?? '');

        if (!preg_match('~^https?://~', $config['base_url'])) {
            wcr_pisignage_response(['success' => false, 'error' => 'Base URL muss mit http:// oder https:// beginnen'], 422);
        }

        if (!wcr_pisignage_save_config($config)) {
            wcr_pisignage_response(['success' => false, 'error' => 'Konfiguration konnte nicht gespeichert werden'], 500);
        }

        wcr_pisignage_response(['success' => true, 'message' => 'Konfiguration gespeichert']);
        break;

    case 'request_token':
        $otp = trim($_POST['otp'] ?? '');
        $login = wcr_pisignage_login_and_get_token($otp !== '' ? $otp : null);

        if (!$login['success']) {
            wcr_pisignage_response([
                'success' => false,
                'error' => 'Token konnte nicht geholt werden',
                'details' => $login
            ], 401);
        }

        $token = wcr_pisignage_extract_token($login);

        if (!$token) {
            wcr_pisignage_response([
                'success' => false,
                'error' => 'Kein Token in der Antwort gefunden',
                'details' => $login
            ], 500);
        }

        $config['api_token'] = $token;
        wcr_pisignage_save_config($config);

        wcr_pisignage_response([
            'success' => true,
            'message' => 'Token gespeichert',
            'token_masked' => wcr_pisignage_mask_token($token)
        ]);
        break;

    case 'test_connection':
        if (empty($config['api_token'])) {
            wcr_pisignage_response(['success' => false, 'error' => 'Kein API-Token gespeichert'], 422);
        }

        $result = wcr_pisignage_request('GET', '/api/groups', null, $config['api_token']);
        wcr_pisignage_response($result, $result['success'] ? 200 : 500);
        break;

    case 'get_groups':
        if (empty($config['api_token'])) {
            wcr_pisignage_response(['success' => false, 'error' => 'Kein API-Token gespeichert'], 422);
        }

        $result = wcr_pisignage_request('GET', '/api/groups', null, $config['api_token']);
        wcr_pisignage_response($result, $result['success'] ? 200 : 500);
        break;

    case 'get_playlists':
        if (empty($config['api_token'])) {
            wcr_pisignage_response(['success' => false, 'error' => 'Kein API-Token gespeichert'], 422);
        }

        $result = wcr_pisignage_request('GET', '/api/playlists', null, $config['api_token']);
        wcr_pisignage_response($result, $result['success'] ? 200 : 500);
        break;

    case 'set_playlist':
        if (empty($config['api_token'])) {
            wcr_pisignage_response(['success' => false, 'error' => 'Kein API-Token gespeichert'], 422);
        }

        $groupId = trim($_POST['group_id'] ?? '');
        $playlistId = trim($_POST['playlist_id'] ?? '');

        if ($groupId === '' || $playlistId === '') {
            wcr_pisignage_response(['success' => false, 'error' => 'group_id und playlist_id sind erforderlich'], 422);
        }

        $payload = [
            'currentPlaylist' => $playlistId
        ];

        $result = wcr_pisignage_request('PUT', '/api/groups/' . rawurlencode($groupId), $payload, $config['api_token']);
        wcr_pisignage_response($result, $result['success'] ? 200 : 500);
        break;

    default:
        wcr_pisignage_response(['success' => false, 'error' => 'Ungültige action'], 400);
}

