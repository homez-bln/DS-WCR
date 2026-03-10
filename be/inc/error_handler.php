<?php
/**
 * inc/error_handler.php — Sichere Fehlerbehandlung + internes Logging
 * 
 * Zweck:
 *  - Nach außen nur generische, sichere Fehlermeldungen
 *  - Interne Details nur im Server-Log (error_log)
 *  - Verhindert Leaking von SQL-Queries, Stacktraces, Pfaden
 * 
 * Verwendung:
 *   wcr_log_error('Kontext', $exception);
 *   wcr_json_error('Benutzermeldung', 500);
 */

/**
 * Loggt technische Fehlerdetails ins Server-Log
 * 
 * Loggt intern:
 *  - Exception-Message
 *  - Exception-Typ
 *  - Datei + Zeile
 *  - Stack-Trace (erste 3 Zeilen)
 *  - User-ID (falls eingeloggt)
 *  - Request-URI
 * 
 * NICHT geloggt:
 *  - Passwörter (auch nicht aus POST)
 *  - Session-Daten (außer User-ID)
 *  - Vollständige SQL-Queries mit User-Daten
 * 
 * @param string $context Beschreibung wo Fehler auftrat (z.B. "save_opening_hours")
 * @param Exception|Throwable $exception Exception-Objekt
 * @param array $extra Optionale zusätzliche Infos (ohne sensible Daten!)
 */
function wcr_log_error(string $context, $exception, array $extra = []): void {
    
    // ── Basis-Informationen ──
    $errorType    = get_class($exception);
    $errorMessage = $exception->getMessage();
    $errorFile    = $exception->getFile();
    $errorLine    = $exception->getLine();
    
    // ── Stack-Trace (nur erste 3 Zeilen für Kontext, nicht den ganzen Trace) ──
    $trace = $exception->getTraceAsString();
    $traceLines = explode("\n", $trace);
    $shortTrace = implode("\n", array_slice($traceLines, 0, 3));
    
    // ── User-Kontext (falls eingeloggt) ──
    $userId = function_exists('wcr_user_id') ? wcr_user_id() : 0;
    $userRole = function_exists('wcr_role') ? wcr_role() : 'unknown';
    
    // ── Request-Kontext ──
    $requestUri = $_SERVER['REQUEST_URI'] ?? 'unknown';
    $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'unknown';
    
    // ── Zusätzliche Infos (gefiltert) ──
    $extraStr = '';
    if (!empty($extra)) {
        // Sensible Schlüssel entfernen
        $filtered = array_diff_key($extra, array_flip(['password', 'csrf_token', 'session']));
        $extraStr = json_encode($filtered, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    
    // ── Log-Nachricht zusammenbauen ──
    $logMessage = sprintf(
        "[WCR Backend Error] Context: %s | Type: %s | Message: %s | File: %s:%d | User: #%d (%s) | Request: %s %s%s | Trace: %s",
        $context,
        $errorType,
        $errorMessage,
        $errorFile,
        $errorLine,
        $userId,
        $userRole,
        $requestMethod,
        $requestUri,
        $extraStr ? " | Extra: {$extraStr}" : '',
        str_replace("\n", " | ", $shortTrace)
    );
    
    // ── In PHP error_log schreiben ──
    // Erscheint in PHP-Error-Log (meist /var/log/apache2/error.log oder php_errors.log)
    error_log($logMessage);
}

/**
 * Gibt sichere JSON-Fehlerantwort aus und beendet Script
 * 
 * Gibt nach außen:
 *  - Generische Fehlermeldung für User
 *  - HTTP-Statuscode
 *  - ok: false
 * 
 * Gibt NICHT nach außen:
 *  - SQL-Queries
 *  - Stacktraces
 *  - Datei-Pfade
 *  - Exception-Details
 * 
 * @param string $userMessage Nutzerfreundliche Fehlermeldung (z.B. "Speichern fehlgeschlagen")
 * @param int $httpCode HTTP-Statuscode (default: 500)
 * @param bool $includeToken CSRF-Token trotz Fehler zurückgeben? (default: false)
 */
function wcr_json_error(string $userMessage, int $httpCode = 500, bool $includeToken = false): void {
    http_response_code($httpCode);
    
    $response = [
        'ok' => false,
        'success' => false,  // Kompatibilität mit alten APIs
        'error' => $userMessage
    ];
    
    // ── Optional: CSRF-Token trotz Fehler zurückgeben ──
    // Sinnvoll bei Input-Validierungsfehlern (User kann Form korrigieren + nochmal senden)
    if ($includeToken && function_exists('wcr_csrf_token')) {
        $response['csrf_token'] = wcr_csrf_token();
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Gibt sichere JSON-Erfolgsantwort aus
 * 
 * @param array $data Zusätzliche Daten für Response
 * @param bool $includeToken CSRF-Token zurückgeben? (default: true)
 */
function wcr_json_success(array $data = [], bool $includeToken = true): void {
    $response = array_merge([
        'ok' => true,
        'success' => true,  // Kompatibilität
    ], $data);
    
    // ── CSRF-Token nach erfolgreicher Aktion zurückgeben ──
    // wcr_verify_csrf_silent() hat bereits neues Token generiert (Token-Rotation)
    if ($includeToken && function_exists('wcr_csrf_token')) {
        $response['csrf_token'] = wcr_csrf_token();
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Sanitiert Fehlermeldung für User-Ausgabe
 * 
 * Entfernt:
 *  - SQL-Keywords (SELECT, INSERT, UPDATE, DELETE, WHERE, etc.)
 *  - Dateipfade (/var/www, /home, C:\\)
 *  - PHP-Funktionsnamen mit Klammern
 *  - Backticks (SQL)
 * 
 * @param string $message Technische Fehlermeldung
 * @return string Bereinigte User-Meldung
 */
function wcr_sanitize_error_message(string $message): string {
    // SQL-Keywords entfernen
    $sqlKeywords = [
        'SELECT', 'INSERT', 'UPDATE', 'DELETE', 'DROP', 'CREATE', 'ALTER', 'TRUNCATE',
        'WHERE', 'FROM', 'JOIN', 'INNER', 'LEFT', 'RIGHT', 'ON', 'GROUP BY', 'ORDER BY',
        'LIMIT', 'OFFSET', 'UNION', 'HAVING', 'INTO', 'VALUES', 'SET'
    ];
    
    foreach ($sqlKeywords as $keyword) {
        $message = str_ireplace($keyword, '***', $message);
    }
    
    // Dateipfade entfernen
    $message = preg_replace('#(/[a-z0-9_\-./]+|[A-Z]:\\\\[a-z0-9_\-\\\\]+)#i', '[path]', $message);
    
    // PHP-Funktionsaufrufe entfernen (z.B. "in function_name()")
    $message = preg_replace('/\b[a-z_][a-z0-9_]*\\(/i', '[function](', $message);
    
    // SQL-Backticks entfernen
    $message = str_replace('`', '', $message);
    
    // Zu lang? Abschneiden
    if (strlen($message) > 100) {
        $message = substr($message, 0, 97) . '...';
    }
    
    return $message;
}

/**
 * Prüft ob aktuelle Umgebung Development ist
 * 
 * @return bool True wenn Development (localhost, .local, .test)
 */
function wcr_is_development(): bool {
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'production';
    
    return (
        strpos($host, 'localhost') !== false ||
        strpos($host, '127.0.0.1') !== false ||
        strpos($host, '.local') !== false ||
        strpos($host, '.test') !== false ||
        strpos($host, '.dev') !== false
    );
}
