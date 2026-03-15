<?php
/**
 * inc/auth.php — Session + Rollen-System v11 + Konfigurierbare Rechte-Matrix
 * Rollen: cernal | admin | user
 *
 * v11 NEU: Konfigurierbare Rechte-Matrix
 *  - cernal kann Rechte für admin/user granular steuern
 *  - Sicherer Fallback auf statische Standard-Matrix
 *  - cernal behält IMMER Vollzugriff (hardcoded)
 *  - Matrix wird in wp_options gespeichert (via REST API)
 *
 * Standard-Berechtigungsmatrix (Fallback wenn keine Custom-Matrix existiert):
 *  edit_prices      → cernal, admin      (Preise ändern)
 *  edit_products    → cernal, admin      (Produkte verwalten: Drinks, Food, Cable, etc.)
 *  create_products  → cernal, admin      (Neuen Artikel anlegen)
 *  edit_content     → cernal, admin      (Content verwalten: Kino, Obstacles, etc.)
 *  edit_tickets     → cernal, admin      (Tickets bearbeiten)
 *  view_times       → cernal, admin      (Öffnungszeiten-Seite)
 *  view_media       → cernal, admin      (Media-Verwaltung)
 *  view_ds          → cernal, admin      (Digital Signage Seiten)
 *  manage_users     → cernal, admin      (Benutzer anlegen/verwalten)
 *  debug            → cernal only        (Debug-Panel)
 *  toggle           → cernal, admin, user (An/Aus schalten)
 *
 * CSRF Protection:
 *  Alle schreibenden Aktionen (POST/PUT/DELETE) müssen ein gültiges Token haben.
 *  Token wird automatisch rotiert nach jeder Verwendung.
 *
 * Session Security v10:
 *  - Secure Cookie Flag (nur HTTPS in Production)
 *  - HttpOnly Cookie (kein JavaScript-Zugriff)
 *  - SameSite=Strict (maximaler CSRF-Schutz)
 *  - Session Fingerprint (User-Agent + IP-Präfix)
 *  - Strict Session Mode (keine Session-ID in URL)
 *  - Session Timeout: 8 Stunden
 *
 * DB: be_users braucht Spalte `role` VARCHAR(20) DEFAULT 'user'
 *     SQL: ALTER TABLE be_users ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'user';
 */

// ──────────────────────────────────────────────────────────────────────
// Session Configuration (Hardened Security)
// ──────────────────────────────────────────────────────────────────────

if (session_status() === PHP_SESSION_NONE) {
    
    // ── Sichere Cookie-Parameter ──
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            || (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
    
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/be',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_trans_sid', '0');
    
    session_start();
    
    $currentFingerprint = hash('sha256', 
        ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown') . 
        substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, strrpos($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', '.'))
    );
    
    if (isset($_SESSION['wcr_fingerprint'])) {
        if (isset($_SESSION['be_user_id']) && $_SESSION['wcr_fingerprint'] !== $currentFingerprint) {
            session_unset();
            session_destroy();
            session_start();
        }
    }
    
    $_SESSION['wcr_fingerprint'] = $currentFingerprint;
}

define('WCR_SESSION_TIMEOUT', 8 * 3600);

const WCR_ROLES = ['cernal', 'admin', 'user'];

// ──────────────────────────────────────────────────────────────────────
// Standard-Berechtigungsmatrix (Fallback)
// ──────────────────────────────────────────────────────────────────────
const WCR_DEFAULT_PERMISSIONS = [
    // Preis-Management
    'edit_prices'      => ['cernal', 'admin'],
    
    // Content-Management
    'edit_products'    => ['cernal', 'admin'],  // Drinks, Food, Cable, Camping, Ice, Extra
    'create_products'  => ['cernal', 'admin'],  // Neuen Artikel anlegen (Drawer)
    'edit_content'     => ['cernal', 'admin'],  // Kino, Obstacles, etc.
    'edit_tickets'     => ['cernal', 'admin'],  // Ticket-Verwaltung
    
    // View-Permissions
    'view_times'       => ['cernal', 'admin'],  // Öffnungszeiten-Seite
    'view_media'       => ['cernal', 'admin'],  // Media-Verwaltung
    'view_ds'          => ['cernal', 'admin'],  // Digital Signage Seiten
    
    // System-Permissions
    'manage_users'     => ['cernal', 'admin'],  // User-Management
    'debug'            => ['cernal'],           // Debug-Panel (nur Cernal)
    'toggle'           => ['cernal', 'admin', 'user'], // An/Aus schalten (alle)
];

// Alias für alte Konstante (Rückwärtskompatibilität)
if (!defined('WCR_PERMISSIONS')) {
    define('WCR_PERMISSIONS', WCR_DEFAULT_PERMISSIONS);
}


// ──────────────────────────────────────────────────────────────────────
// Zentrale API-Konfiguration
// ──────────────────────────────────────────────────────────────────────
if (!defined('DSC_WP_API_BASE')) {
    define('DSC_WP_API_BASE', getenv('WCR_WP_API_BASE') ?: 'https://wcr-webpage.de/wp-json/wakecamp/v1');
}
if (!defined('DSC_WP_SECRET')) {
    define('DSC_WP_SECRET', getenv('WCR_WP_SECRET') ?: 'WCR_DS_2026');
}

// ──────────────────────────────────────────────────────────────────────
// Konfigurierbare Rechte-Matrix (v11)
// ──────────────────────────────────────────────────────────────────────

function wcr_load_permissions(): array {
    if (array_key_exists('wcr_permissions_cache', $GLOBALS) && $GLOBALS['wcr_permissions_cache'] !== null) {
        return $GLOBALS['wcr_permissions_cache'];
    }
    
    if (!defined('DSC_WP_API_BASE') || !defined('DSC_WP_SECRET')) {
        $GLOBALS['wcr_permissions_cache'] = WCR_DEFAULT_PERMISSIONS;
        return $GLOBALS['wcr_permissions_cache'];
    }
    
    if (!function_exists('wcr_api_curl')) {
        function wcr_api_curl(string $url): array {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 3,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            ]);
            $body = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return [
                'ok'   => ($code === 200),
                'json' => json_decode($body ?: '', true),
            ];
        }
    }
    
    $result = wcr_api_curl(DSC_WP_API_BASE . '/options/wcr_permissions_matrix?wcr_secret=' . urlencode(DSC_WP_SECRET));
    
    if ($result['ok'] && isset($result['json']['value']) && is_array($result['json']['value'])) {
        $customMatrix = $result['json']['value'];
        
        $validated = [];
        foreach (array_keys(WCR_DEFAULT_PERMISSIONS) as $perm) {
            if (isset($customMatrix[$perm]) && is_array($customMatrix[$perm])) {
                $validated[$perm] = array_values(array_intersect($customMatrix[$perm], WCR_ROLES));
                if (!in_array('cernal', $validated[$perm], true)) {
                    $validated[$perm][] = 'cernal';
                }
            } else {
                // Permission fehlt in Custom-Matrix → Standard verwenden
                $validated[$perm] = WCR_DEFAULT_PERMISSIONS[$perm];
            }
        }
        
        $GLOBALS['wcr_permissions_cache'] = $validated;
        return $GLOBALS['wcr_permissions_cache'];
    }
    
    $GLOBALS['wcr_permissions_cache'] = WCR_DEFAULT_PERMISSIONS;
    return $GLOBALS['wcr_permissions_cache'];
}

function wcr_save_permissions(array $matrix): bool {
    if (!wcr_is_cernal()) return false;
    if (!defined('DSC_WP_API_BASE') || !defined('DSC_WP_SECRET')) return false;
    
    $validated = [];
    foreach (array_keys(WCR_DEFAULT_PERMISSIONS) as $perm) {
        if (isset($matrix[$perm]) && is_array($matrix[$perm])) {
            $roles = array_values(array_intersect($matrix[$perm], WCR_ROLES));
            if (!in_array('cernal', $roles, true)) $roles[] = 'cernal';
            $validated[$perm] = $roles;
        } else {
            $validated[$perm] = WCR_DEFAULT_PERMISSIONS[$perm];
        }
    }
    
    $ch = curl_init(DSC_WP_API_BASE . '/options');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode([
            'wcr_secret' => DSC_WP_SECRET,
            'key'        => 'wcr_permissions_matrix',
            'value'      => $validated,
        ], JSON_UNESCAPED_UNICODE),
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $success = ($code === 200);
    if ($success) wcr_invalidate_permissions_cache();
    return $success;
}

function wcr_invalidate_permissions_cache(): void {
    unset($GLOBALS['wcr_permissions_cache']);
}

function wcr_has_custom_permissions(): bool {
    if (!defined('DSC_WP_API_BASE') || !defined('DSC_WP_SECRET')) return false;
    if (!function_exists('wcr_api_curl')) return false;
    $result = wcr_api_curl(DSC_WP_API_BASE . '/options/wcr_permissions_matrix?wcr_secret=' . urlencode(DSC_WP_SECRET));
    return ($result['ok'] && isset($result['json']['value']) && is_array($result['json']['value']));
}

// ──────────────────────────────────────────────────────────────────────
// CSRF Protection
// ──────────────────────────────────────────────────────────────────────

function wcr_csrf_token(): string {
    if (empty($_SESSION['wcr_csrf_token'])) {
        $_SESSION['wcr_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['wcr_csrf_token'];
}

function wcr_verify_csrf(bool $autoFail = true): bool {
    $sentToken  = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $validToken = $_SESSION['wcr_csrf_token'] ?? '';

    if ($sentToken === '' || $validToken === '' || !hash_equals($validToken, $sentToken)) {
        if ($autoFail) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok'         => false,
                'success'    => false,
                'error'      => 'Invalid CSRF token',
                'csrf_token' => wcr_csrf_token()
            ]);
            exit;
        }
        return false;
    }

    return true;
}

function wcr_verify_csrf_silent(): bool {
    return wcr_verify_csrf(false);
}

function wcr_csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(wcr_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function wcr_csrf_attr(): string {
    return htmlspecialchars(wcr_csrf_token(), ENT_QUOTES, 'UTF-8');
}

// ──────────────────────────────────────────────────────────────────────
// Session & Authentication
// ──────────────────────────────────────────────────────────────────────

function login_user(int $user_id, string $role = 'user'): void {
    session_regenerate_id(true);
    $_SESSION['be_user_id']   = $user_id;
    $_SESSION['be_role']      = in_array($role, WCR_ROLES, true) ? $role : 'user';
    $_SESSION['be_last_seen'] = time();
    $newFingerprint = hash('sha256', 
        ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown') . 
        substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, strrpos($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', '.'))
    );
    $_SESSION['wcr_fingerprint'] = $newFingerprint;
    unset($_SESSION['wcr_csrf_token']);
    wcr_csrf_token();
}

function is_logged_in(): bool {
    if (empty($_SESSION['be_user_id']) || !is_int($_SESSION['be_user_id'])) return false;
    if ((time() - ($_SESSION['be_last_seen'] ?? 0)) > WCR_SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        return false;
    }
    $_SESSION['be_last_seen'] = time();
    return true;
}

function require_login(): void {
    if (!is_logged_in()) {
        session_unset();
        session_destroy();
        header('Location: /be/login.php');
        exit;
    }
}

function wcr_role(): string {
    return $_SESSION['be_role'] ?? 'user';
}

function wcr_user_id(): int {
    return (int)($_SESSION['be_user_id'] ?? 0);
}

function wcr_can(string $action): bool {
    $permissions = wcr_load_permissions();
    $allowed = $permissions[$action] ?? [];
    return in_array(wcr_role(), $allowed, true);
}

function wcr_require(string $action): void {
    require_login();
    if (!wcr_can($action)) {
        http_response_code(403);
        $pageTitle = 'Kein Zugriff';
        include __DIR__ . '/403.php';
        exit;
    }
}

function wcr_is_admin(): bool {
    return in_array(wcr_role(), ['cernal', 'admin'], true);
}

function wcr_is_cernal(): bool {
    return wcr_role() === 'cernal';
}

function wcr_role_badge(string $role = ''): string {
    if ($role === '') $role = wcr_role();
    $cfg = [
        'cernal' => ['#7c3aed', 'Cernal'],
        'admin'  => ['#0071e3', 'Admin'],
        'user'   => ['#34c759', 'User'],
    ];
    $icons = ['cernal' => '🔧', 'admin' => '👑', 'user' => '👤'];
    $c = $cfg[$role] ?? ['#999', $role];
    $icon = $icons[$role] ?? '?';
    return '<span class="role-badge" data-role="' . htmlspecialchars($role) . '">'
         . $icon . ' ' . htmlspecialchars($c[1]) . '</span>';
}
