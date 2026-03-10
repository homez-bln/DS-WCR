<?php
/**
 * DATEI: be/api/toggle_media.php
 * Reihenfolge: auth (session_start) ZUERST → dann header() → dann Logik
 * v4: + Sichere Fehlerbehandlung (kein Exception-Leaking)
 */

// 1. Auth als Allererstes (session_start() ist hier drin)
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/error_handler.php';
require_once __DIR__ . "/../inc/db.php";
require_login();

$db = $pdo;

// 2. Header DANACH (kein Output vor session_start mehr möglich)
header('Content-Type: application/json; charset=utf-8');

// ── CSRF-Schutz ──
if (!wcr_verify_csrf_silent()) {
    http_response_code(403);
    exit(json_encode(['ok' => false, 'error' => 'CSRF-Token ungültig']));
}

// ── Whitelist ─────────────────────────────────────────────────────────────────────
$ALLOWED_FOLDERS = ['ticket'];

// ── Input lesen ─────────────────────────────────────────────────────────────────────
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

$folder    = isset($data['folder'])    ? trim((string)$data['folder'])   : '';
$filename  = isset($data['filename'])  ? trim((string)$data['filename']) : '';
$is_active = isset($data['is_active']) ? (int)$data['is_active']         : -1;

// ── Validierung ─────────────────────────────────────────────────────────────────────
if (empty($folder) || empty($filename)) {
    exit(json_encode(['ok' => false, 'error' => 'folder und filename fehlen']));
}
if (!in_array($is_active, [0, 1], true)) {
    exit(json_encode(['ok' => false, 'error' => 'is_active muss 0 oder 1 sein']));
}
if (!in_array($folder, $ALLOWED_FOLDERS, true)) {
    exit(json_encode(['ok' => false, 'error' => 'Ordner nicht erlaubt']));
}
if (!preg_match('/^[a-zA-Z0-9._\\- ]+$/', $filename)) {
    exit(json_encode(['ok' => false, 'error' => 'Ungültiger Dateiname']));
}

// ── DB Upsert ─────────────────────────────────────────────────────────────────────
try {
    $stmt = $db->prepare("
        INSERT INTO media_files (folder, filename, is_active)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE is_active = ?
    ");
    $stmt->execute([$folder, $filename, $is_active, $is_active]);

    // ── Token nach erfolgreicher Rotation zurückgeben ──
    echo json_encode([
        'ok' => true,
        'csrf_token' => wcr_csrf_token()
    ]);

} catch (Exception $e) {
    // ── Internes Logging: Volle Fehlerdetails ──
    wcr_log_error('toggle_media', $e, [
        'folder' => $folder,
        'filename' => $filename,
        'is_active' => $is_active
    ]);
    
    // ── User-Ausgabe: Generisch (kein PDOException-Message) ──
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Datenbank-Fehler']);
}
