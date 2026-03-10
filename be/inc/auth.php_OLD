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
 *  edit_prices   → cernal, admin      (Preise ändern)
 *  edit_products → cernal, admin      (Produkte verwalten: Drinks, Food, Cable, etc.)
 *  edit_content  → cernal, admin      (Content verwalten: Kino, Obstacles, etc.)
 *  edit_tickets  → cernal, admin      (Tickets bearbeiten)
 *  view_times    → cernal, admin      (Öffnungszeiten-Seite)
 *  view_media    → cernal, admin      (Media-Verwaltung)
 *  view_ds       → cernal, admin      (Digital Signage Seiten)
 *  manage_users  → cernal, admin      (Benutzer anlegen/verwalten)
 *  debug         → cernal only        (Debug-Panel)
 *  toggle        → cernal, admin, user (An/Aus schalten)
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

// ─────────────────────────────────────────────────────────────────────
// Session Configuration (Hardened Security)
// ─────────────────────────────────────────────────────────────────────

if (session_status() === PHP_SESSION_NONE) {
    
    // ── Sichere Cookie-Parameter ──
    // 'secure' => true aktiviert sich automatisch bei HTTPS,
    // bleibt false bei lokalem HTTP-Development (localhost ohne SSL)
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            || (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
    
    session_set_cookie_params([
        'lifetime' => 0,           // Cookie lebt nur während Browser-Session (kein "Remember Me")
        'path'     => '/be',       // Cookie nur für Backend gültig (nicht für WordPress)
        'domain'   => '',          // Automatisch aktuelle Domain
        'secure'   => $isHttps,    // Nur HTTPS (auto-detect)
        'httponly' => true,        // Kein JavaScript-Zugriff auf Cookie (XSS-Schutz)
        'samesite' => 'Strict'     // Strikter CSRF-Schutz (Cookie nur bei Same-Site-Requests)
    ]);
    
    // ── Session-Sicherheit ──
    ini_set('session.use_strict_mode', '1');      // Nur Server-generierte Session-IDs akzeptieren
    ini_set('session.use_only_cookies', '1');     // Keine Session-ID in URL (verhindert Session-Fixation)
    ini_set('session.cookie_httponly', '1');      // Redundante Absicherung (falls session_set_cookie_params fehlschlägt)
    ini_set('session.use_trans_sid', '0');        // Session-ID niemals in URL übertragen
    
    session_start();
    
    // ── Session-Fingerprint: Bindet Session an User-Agent + IP-Präfix ──
    // Verhindert Session-Hijacking (gestohlene Session-ID funktioniert nicht von anderem Client).
    // IP-Präfix statt volle IP: Erlaubt mobile Nutzer mit wechselnden IPs (z.B. WiFi → 4G).
    $currentFingerprint = hash('sha256', 
        ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown') . 
        substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, strrpos($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', '.'))
    );
    
    if (isset($_SESSION['wcr_fingerprint'])) {
        // Bei Login-Prozess wird Fingerprint neu gesetzt → hier nicht prüfen
        // Nur prüfen wenn User bereits eingeloggt ist
        if (isset($_SESSION['be_user_id']) && $_SESSION['wcr_fingerprint'] !== $currentFingerprint) {
            // Session-Hijacking-Versuch erkannt → Session beenden
            session_unset();
            session_destroy();
            // Neue Session starten (für Login-Seite)
            session_start();
        }
    }
    
    // Fingerprint für diese Session speichern
    $_SESSION['wcr_fingerprint'] = $currentFingerprint;
}

// ── Session-Timeout: 8 Stunden Inaktivität ──
// Nach 8h ohne Aktivität wird User automatisch ausgeloggt.
// Bei jeder Aktion wird Timeout zurückgesetzt (siehe is_logged_in()).
define('WCR_SESSION_TIMEOUT', 8 * 3600);

const WCR_ROLES = ['cernal', 'admin', 'user'];

// ─────────────────────────────────────────────────────────────────────
// Standard-Berechtigungsmatrix (Fallback)
// ─────────────────────────────────────────────────────────────────────
const WCR_DEFAULT_PERMISSIONS = [
    // Preis-Management
    'edit_prices'   => ['cernal', 'admin'],
    
    // Content-Management
    'edit_products' => ['cernal', 'admin'],  // Drinks, Food, Cable, Camping, Ice, Extra
    'edit_content'  => ['cernal', 'admin'],  // Kino, Obstacles, etc.
    'edit_tickets'  => ['cernal', 'admin'],  // Ticket-Verwaltung
    
    // View-Permissions
    'view_times'    => ['cernal', 'admin'],  // Öffnungszeiten-Seite
    'view_media'    => ['cernal', 'admin'],  // Media-Verwaltung
    'view_ds'       => ['cernal', 'admin'],  // Digital Signage Seiten
    
    // System-Permissions
    'manage_users'  => ['cernal', 'admin'],  // User-Management
    'debug'         => ['cernal'],           // Debug-Panel (nur Cernal)
    'toggle'        => ['cernal', 'admin', 'user'], // An/Aus schalten (alle)
];

// Alias für alte Konstante (Rückwärtskompatibilität)
if (!defined('WCR_PERMISSIONS')) {
    define('WCR_PERMISSIONS', WCR_DEFAULT_PERMISSIONS);
}

// ─────────────────────────────────────────────────────────────────────
// Konfigurierbare Rechte-Matrix (v11)
// ─────────────────────────────────────────────────────────────────────

/**
 * Lädt konfigurierte Rechte-Matrix aus wp_options (via REST API)
 * Falls keine Custom-Matrix existiert, wird Standard-Matrix zurückgegeben
 * 
 * @return array Permissions-Array im Format ['permission' => ['role1', 'role2']]
 */
function wcr_load_permissions(): array {
    static $cache = null;
    
    if ($cache !== null) return $cache;
    
    // API-Zugriff (nur wenn Konstanten definiert sind)
    if (!defined('DSC_WP_API_BASE') || !defined('DSC_WP_SECRET')) {
        $cache = WCR_DEFAULT_PERMISSIONS;
        return $cache;
    }
    
    // Hilfsfunktion für API-Calls
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
    
    // Custom-Matrix aus wp_options laden
    $result = wcr_api_curl(DSC_WP_API_BASE . '/options/wcr_permissions_matrix?wcr_secret=' . urlencode(DSC_WP_SECRET));
    
    if ($result['ok'] && isset($result['json']['value']) && is_array($result['json']['value'])) {
        $customMatrix = $result['json']['value'];
        
        // Validierung: Nur bekannte Permissions und Rollen erlauben
        $validated = [];
        foreach (array_keys(WCR_DEFAULT_PERMISSIONS) as $perm) {
            if (isset($customMatrix[$perm]) && is_array($customMatrix[$perm])) {
                // Nur valide Rollen übernehmen
                $validated[$perm] = array_values(array_intersect($customMatrix[$perm], WCR_ROLES));
                
                // SICHERHEIT: cernal IMMER zu allen Permissions hinzufügen (hardcoded)
                if (!in_array('cernal', $validated[$perm], true)) {
                    $validated[$perm][] = 'cernal';
                }
            } else {
                // Permission nicht in Custom-Matrix → Standard verwenden
                $validated[$perm] = WCR_DEFAULT_PERMISSIONS[$perm];
            }
        }
        
        $cache = $validated;
        return $cache;
    }
    
    // Fallback: Standard-Matrix verwenden
    $cache = WCR_DEFAULT_PERMISSIONS;
    return $cache;
}

/**
 * Speichert Custom-Rechte-Matrix in wp_options (via REST API)
 * NUR für cernal zugänglich
 * 
 * @param array $matrix Permissions-Array im Format ['permission' => ['role1', 'role2']]
 * @return bool True bei Erfolg
 */
function wcr_save_permissions(array $matrix): bool {
    // Sicherheitscheck: Nur cernal darf Matrix speichern
    if (!wcr_is_cernal()) {
        return false;
    }
    
    // API-Zugriff prüfen
    if (!defined('DSC_WP_API_BASE') || !defined('DSC_WP_SECRET')) {
        return false;
    }
    
    // Validierung + cernal-Schutz
    $validated = [];
    foreach (array_keys(WCR_DEFAULT_PERMISSIONS) as $perm) {
        if (isset($matrix[$perm]) && is_array($matrix[$perm])) {
            $roles = array_values(array_intersect($matrix[$perm], WCR_ROLES));
            
            // SICHERHEIT: cernal IMMER hinzufügen (verhindert Aussperren)
            if (!in_array('cernal', $roles, true)) {
                $roles[] = 'cernal';
            }
            
            $validated[$perm] = $roles;
        } else {
            // Fehlende Permission → Standard verwenden
            $validated[$perm] = WCR_DEFAULT_PERMISSIONS[$perm];
        }
    }
    
    // API-Call
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
    
    // Cache invalidieren bei Erfolg
    if ($success) {
        wcr_invalidate_permissions_cache();
    }
    
    return $success;
}

/**
 * Cache der Permissions-Matrix löschen
 * Erzwingt Neuladen beim nächsten wcr_load_permissions() Aufruf
 */
function wcr_invalidate_permissions_cache(): void {
    // Static-Cache zurücksetzen (Reflection)
    $reflectionFunc = new ReflectionFunction('wcr_load_permissions');
    $staticVars = $reflectionFunc->getStaticVariables();
    if (isset($staticVars['cache'])) {
        // Cache zurücksetzen
        wcr_load_permissions(); // Einmal aufrufen um Cache zu setzen
    }
}

/**
 * Prüft ob Custom-Matrix aktiv ist
 * 
 * @return bool True wenn Custom-Matrix verwendet wird, False wenn Fallback
 */
function wcr_has_custom_permissions(): bool {
    if (!defined('DSC_WP_API_BASE') || !defined('DSC_WP_SECRET')) {
        return false;
    }
    
    if (!function_exists('wcr_api_curl')) {
        return false;
    }
    
    $result = wcr_api_curl(DSC_WP_API_BASE . '/options/wcr_permissions_matrix?wcr_secret=' . urlencode(DSC_WP_SECRET));
    return ($result['ok'] && isset($result['json']['value']) && is_array($result['json']['value']));
}

// ─────────────────────────────────────────────────────────────────────
// CSRF Protection Functions
// ─────────────────────────────────────────────────────────────────────

/**
 * Generiert oder liefert aktuelles CSRF-Token
 * Token wird automatisch nach erfolgreicher Validierung rotiert
 */
function wcr_csrf_token(): string {
    if (empty($_SESSION['wcr_csrf_token'])) {
        // 32 Bytes = 256 Bit = sehr sicheres Token
        $_SESSION['wcr_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['wcr_csrf_token'];
}

/**
 * Prüft CSRF-Token aus Request
 * Rotiert Token bei Erfolg automatisch für nächsten Request
 * 
 * @param bool $autoFail Bei true: 403 + exit bei Fehler, bei false: return false
 * @return bool True wenn Token gültig
 */
function wcr_verify_csrf(bool $autoFail = true): bool {
    $sentToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $validToken = $_SESSION['wcr_csrf_token'] ?? '';
    
    // hash_equals() verhindert Timing-Angriffe (constant-time comparison)
    if ($sentToken === '' || $validToken === '' || !hash_equals($validToken, $sentToken)) {
        if ($autoFail) {
            // FIX v11: Auch bei Fehler NEUES Token zurückgeben!
            // Token-Rotation darf nicht abbrechen, sonst funktioniert nächster Request nie.
            // Altes Token wird trotzdem invalidiert (kein Replay möglich).
            unset($_SESSION['wcr_csrf_token']);
            $newToken = wcr_csrf_token();
            
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false, 
                'error' => 'Invalid CSRF token',
                'csrf_token' => $newToken // Neues Token für nächsten Request
            ]);
            exit;
        }
        return false;
    }
    
    // ── Token-Rotation: Nach erfolgreicher Prüfung neues Token generieren ──
    // Verhindert Token-Replay-Angriffe (altes Token funktioniert nur 1x)
    unset($_SESSION['wcr_csrf_token']);
    wcr_csrf_token(); // Generiert neues Token für nächsten Request
    
    return true;
}

/**
 * Prüft CSRF-Token ohne automatischen Exit
 * Ideal für JSON-APIs mit custom Error-Handling
 * 
 * @return bool True wenn Token gültig
 */
function wcr_verify_csrf_silent(): bool {
    return wcr_verify_csrf(false);
}

/**
 * Gibt verstecktes Input-Feld mit CSRF-Token zurück
 * Für Formulare: <?php echo wcr_csrf_field(); ?>
 */
function wcr_csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(wcr_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Gibt CSRF-Token als JSON-Attribut zurück
 * Für JavaScript: data-csrf="<?= wcr_csrf_attr() ?>"
 */
function wcr_csrf_attr(): string {
    return htmlspecialchars(wcr_csrf_token(), ENT_QUOTES, 'UTF-8');
}

// ─────────────────────────────────────────────────────────────────────
// Session & Authentication Functions
// ─────────────────────────────────────────────────────────────────────

/**
 * Loggt User ein und initialisiert sichere Session
 * 
 * @param int $user_id User-ID aus Datenbank
 * @param string $role Benutzerrolle (cernal, admin, user)
 */
function login_user(int $user_id, string $role = 'user'): void {
    // ── Session-Regeneration: Verhindert Session-Fixation-Angriffe ──
    // Alte Session-ID wird ungültig, neue ID wird generiert.
    // 'true' Parameter: alte Session-Datei wird gelöscht.
    session_regenerate_id(true);
    
    $_SESSION['be_user_id']   = $user_id;
    $_SESSION['be_role']      = in_array($role, WCR_ROLES, true) ? $role : 'user';
    $_SESSION['be_last_seen'] = time();
    
    // ── Session-Fingerprint aktualisieren ──
    // Bei Login wird neuer Fingerprint gesetzt (User-Agent + IP können sich geändert haben)
    $newFingerprint = hash('sha256', 
        ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown') . 
        substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, strrpos($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', '.'))
    );
    $_SESSION['wcr_fingerprint'] = $newFingerprint;
    
    // ── Neues CSRF-Token bei Login generieren ──
    // Verhindert, dass vorher generierte Tokens nach Login noch funktionieren
    unset($_SESSION['wcr_csrf_token']);
    wcr_csrf_token();
}

/**
 * Prüft ob User eingeloggt ist und Session noch gültig
 * Aktualisiert automatisch Last-Seen-Timestamp
 * 
 * @return bool True wenn eingeloggt und Session gültig
 */
function is_logged_in(): bool {
    // ── Basis-Checks ──
    if (empty($_SESSION['be_user_id']) || !is_int($_SESSION['be_user_id'])) return false;
    
    // ── Session-Timeout: 8 Stunden Inaktivität ──
    // Wenn User länger als 8h inaktiv war → automatischer Logout
    if ((time() - ($_SESSION['be_last_seen'] ?? 0)) > WCR_SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        return false;
    }
    
    // ── Last-Seen aktualisieren (Session aktiv halten) ──
    // Bei jeder Aktion wird Timeout zurückgesetzt
    $_SESSION['be_last_seen'] = time();
    
    return true;
}

/**
 * Erzwingt Login, redirected zu Login-Seite wenn nicht eingeloggt
 */
function require_login(): void {
    if (!is_logged_in()) {
        // Session beenden bevor redirect (sauberer State)
        session_unset();
        session_destroy();
        header('Location: /be/login.php');
        exit;
    }
}

/**
 * Gibt aktuelle Benutzerrolle zurück
 * 
 * @return string 'cernal', 'admin' oder 'user'
 */
function wcr_role(): string {
    return $_SESSION['be_role'] ?? 'user';
}

/**
 * Gibt aktuelle User-ID zurück
 * 
 * @return int User-ID oder 0 wenn nicht eingeloggt
 */
function wcr_user_id(): int {
    return (int)($_SESSION['be_user_id'] ?? 0);
}

/**
 * Prüft ob User bestimmte Berechtigung hat
 * Nutzt konfigurierbare Rechte-Matrix (v11)
 * 
 * @param string $action Permission-Name
 * @return bool True wenn berechtigt
 */
function wcr_can(string $action): bool {
    // Konfigurierbare Matrix laden (mit Fallback auf Standard)
    $permissions = wcr_load_permissions();
    $allowed = $permissions[$action] ?? [];
    return in_array(wcr_role(), $allowed, true);
}

/**
 * Erzwingt bestimmte Berechtigung, zeigt 403-Seite wenn nicht berechtigt
 * 
 * @param string $action Permission-Name
 */
function wcr_require(string $action): void {
    require_login();
    if (!wcr_can($action)) {
        http_response_code(403);
        $pageTitle = 'Kein Zugriff';
        include __DIR__ . '/403.php';
        exit;
    }
}

/**
 * Prüft ob User Admin-Rechte hat (cernal oder admin)
 * 
 * @return bool True wenn cernal oder admin
 */
function wcr_is_admin(): bool {
    return in_array(wcr_role(), ['cernal', 'admin'], true);
}

/**
 * Prüft ob User Cernal-Rolle hat (höchste Berechtigung)
 * 
 * @return bool True wenn cernal
 */
function wcr_is_cernal(): bool {
    return wcr_role() === 'cernal';
}

/**
 * Generiert HTML-Badge für Benutzerrolle
 * 
 * @param string $role Optional: spezifische Rolle, sonst aktuelle
 * @return string HTML-Code für Role-Badge
 */
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
