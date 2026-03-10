<?php
/**
 * API ENDPOINT: Liefert neuestes Foto
 * Datei: be/api/get_latest_photo.php
 * v2: + Sichere Fehlerbehandlung
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Erlaubt Zugriff von WordPress

require_once __DIR__ . '/../inc/db.php';

// ── Lightweight Error-Handler für externe API ──
if (!function_exists('wcr_log_error')) {
    require_once __DIR__ . '/../inc/error_handler.php';
}

try {
    $db = isset($conn) ? $conn : (isset($pdo) ? $pdo : null);

    if (!$db) {
        throw new Exception('Keine Datenbankverbindung');
    }

    // Neuestes Foto holen
    if ($db instanceof mysqli) {
        $res = $db->query("SELECT filename, uploaded_at FROM opening_hours_photos ORDER BY uploaded_at DESC LIMIT 1");
        $photo = $res->fetch_assoc();
    } elseif ($db instanceof PDO) {
        $stmt = $db->query("SELECT filename, uploaded_at FROM opening_hours_photos ORDER BY uploaded_at DESC LIMIT 1");
        $photo = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$photo) {
        echo json_encode([
            'success' => false,
            'error' => 'Kein Foto vorhanden'
        ]);
        exit;
    }

    // Erfolgreiche Antwort
    echo json_encode([
        'success' => true,
        'filename' => $photo['filename'],
        'uploaded_at' => $photo['uploaded_at'],
        'url' => 'https://wcr-webpage.de/uploads/opening_hours/' . $photo['filename']
    ]);

} catch (Exception $e) {
    // ── Internes Logging: Volle Fehlerdetails ──
    wcr_log_error('get_latest_photo', $e);
    
    // ── User-Ausgabe: Generisch (kein Exception-Message) ──
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Serverfehler'
    ]);
}
