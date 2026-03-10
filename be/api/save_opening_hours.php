<?php
ob_start();

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/error_handler.php';
require_once __DIR__ . "/../inc/db.php";
require_login();

ob_end_clean();
header('Content-Type: application/json');

// ── CSRF-Schutz ──
if (!wcr_verify_csrf_silent()) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'error' => 'CSRF-Token ungültig']));
}

// ── Input ─────────────────────────────────────────────────────────────────────
$body  = json_decode(file_get_contents('php://input'), true);
$datum = trim($body['datum'] ?? '');
$field = trim($body['field'] ?? '');
$value =      $body['value'] ?? '';
$preserveStart = trim($body['preserve_start_time'] ?? '');

// ── Validierung ─────────────────────────────────────────────────────────────────
$allowedFields = ['start_time', 'end_time', 'course1', 'course2',
                  'course1_text', 'course2_text', 'is_closed'];

if (empty($datum) || !in_array($field, $allowedFields, true)) {
    // ── Sichere User-Meldung: Keine internen Details ──
    echo json_encode(['success' => false, 'error' => 'Ungültige Parameter']);
    exit;
}

// ── DB ─────────────────────────────────────────────────────────────────────
$db = isset($conn) ? $conn : (isset($pdo) ? $pdo : null);

if (!$db) {
    echo json_encode(['success' => false, 'error' => 'Datenbankverbindung fehlgeschlagen']);
    exit;
}

// ── PDO: Exceptions aktivieren für sauberes Error-Handling ──
if ($db instanceof PDO) {
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}

// ── SQL ─────────────────────────────────────────────────────────────────────
try {

    if ($db instanceof PDO) {

        if ($field === 'start_time' || $field === 'end_time') {
            // Zeitfelder: nur dieses Feld setzen
            $sql  = "INSERT INTO opening_hours (datum, `{$field}`)
                     VALUES (:datum, :value)
                     ON DUPLICATE KEY UPDATE `{$field}` = VALUES(`{$field}`)";
            $stmt = $db->prepare($sql);
            $ok   = $stmt->execute([':datum' => $datum, ':value' => $value]);

        } else {
            // Kursfelder: start_time beim INSERT mitgeben (end_time NICHT)
            $sql  = "INSERT INTO opening_hours (datum, `{$field}`, start_time)
                     VALUES (:datum, :value, :start_time)
                     ON DUPLICATE KEY UPDATE `{$field}` = VALUES(`{$field}`)";
            $stmt = $db->prepare($sql);
            $ok   = $stmt->execute([
                ':datum'      => $datum,
                ':value'      => $value,
                ':start_time' => $preserveStart,
            ]);
        }

        // ── Erfolg: Token nach erfolgreicher Rotation zurückgeben ──
        // wcr_verify_csrf_silent() hat bereits neues Token generiert,
        // Frontend muss es für nächsten Request aktualisieren
        echo json_encode([
            'success' => (bool)$ok,
            'csrf_token' => wcr_csrf_token()
        ]);

    } elseif ($db instanceof mysqli) {

        if ($field === 'start_time' || $field === 'end_time') {
            $sql  = "INSERT INTO opening_hours (datum, `{$field}`)
                     VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE `{$field}` = VALUES(`{$field}`)";
            $stmt = $db->prepare($sql);
            $stmt->bind_param('ss', $datum, $value);

        } else {
            $sql  = "INSERT INTO opening_hours (datum, `{$field}`, start_time)
                     VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE `{$field}` = VALUES(`{$field}`)";
            $stmt = $db->prepare($sql);
            $stmt->bind_param('sss', $datum, $value, $preserveStart);
        }

        $ok = $stmt->execute();
        
        // ── Erfolg: Token nach erfolgreicher Rotation zurückgeben ──
        echo json_encode([
            'success' => $ok,
            'csrf_token' => wcr_csrf_token()
        ]);
    }

} catch (Exception $e) {
    // ── Internes Logging: Volle Fehlerdetails für Debugging ──
    wcr_log_error('save_opening_hours', $e, [
        'datum' => $datum,
        'field' => $field,
        'db_type' => get_class($db)
    ]);
    
    // ── User-Ausgabe: Generisch und sicher ──
    // Kein $e->getMessage() (kann SQL, Pfade, interne Details enthalten)
    // Kein SQL-String (kann Struktur der DB offenlegen)
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Speichern fehlgeschlagen'
    ]);
}
