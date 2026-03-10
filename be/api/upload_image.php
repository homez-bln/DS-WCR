<?php
/**
 * api/upload_image.php — Bild-Upload für Produkte
 * v11: + Sichere Fehlerbehandlung (kein Exception-Leaking)
 *
 * POST-Parameter:
 *   file    → Bilddatei (multipart)
 *   table   → Tabellenname (whitelist)
 *   nummer  → Produkt-ID
 *   csrf_token → CSRF-Token (erforderlich)
 *
 * Ablauf:
 *   1. Bild auf max. 800×800 skalieren (quadratisch, Cover-Modus)
 *   2. Als WebP oder JPEG in /uploads/products/{table}/ speichern
 *   3. bild_url in DB aktualisieren
 *   4. JSON zurückgeben (inkl. neues CSRF-Token)
 */

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/error_handler.php';
require_once __DIR__ . '/../inc/db.php';

header('Content-Type: application/json');

// ── SECURITY: Login + Admin erforderlich ──
if (!is_logged_in() || !wcr_is_admin()) {
    http_response_code(403);
    exit(json_encode(['ok' => false, 'error' => 'Keine Berechtigung']));
}

// ── CSRF-Schutz ──
if (!wcr_verify_csrf_silent()) {
    http_response_code(403);
    exit(json_encode(['ok' => false, 'error' => 'CSRF-Token ungültig']));
}

// Whitelist
$ALLOWED_TABLES = ['drinks', 'food', 'cable', 'camping', 'ice', 'extra'];
$table  = trim($_POST['table']  ?? '');
$nummer = (int)($_POST['nummer'] ?? 0);

if (!in_array($table, $ALLOWED_TABLES, true) || $nummer <= 0) {
    http_response_code(400);
    exit(json_encode(['ok' => false, 'error' => 'Ungültige Parameter']));
}

// ── Bild löschen ─────────────────────────────────────────────────────────────
if (!empty($_POST['delete'])) {
    try {
        $old = $pdo->prepare("SELECT bild_url FROM `{$table}` WHERE nummer = ?");
        $old->execute([$nummer]);
        $row = $old->fetch();
        if ($row && !empty($row['bild_url'])) {
            $path = $_SERVER['DOCUMENT_ROOT'] . $row['bild_url'];
            if (strpos($row['bild_url'], '/uploads/products/') === 0 && file_exists($path)) {
                unlink($path);
            }
        }
        $pdo->prepare("UPDATE `{$table}` SET bild_url = NULL WHERE nummer = ?")->execute([$nummer]);
        
        // ── Token nach erfolgreicher Rotation zurückgeben ──
        exit(json_encode([
            'ok' => true,
            'csrf_token' => wcr_csrf_token()
        ]));
        
    } catch (Exception $e) {
        // ── Internes Logging: Volle Fehlerdetails ──
        wcr_log_error('upload_image:delete', $e, [
            'table' => $table,
            'nummer' => $nummer
        ]);
        
        // ── User-Ausgabe: Generisch ──
        exit(json_encode(['ok' => false, 'error' => 'Löschen fehlgeschlagen']));
    }
}

// Datei prüfen
if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $code = $_FILES['file']['error'] ?? 99;
    exit(json_encode(['ok' => false, 'error' => "Upload-Fehler (Code {$code})"]));
}

$file         = $_FILES['file'];
$allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$mime         = mime_content_type($file['tmp_name']);

if (!in_array($mime, $allowedMimes, true)) {
    exit(json_encode(['ok' => false, 'error' => 'Nur JPG, PNG, GIF oder WebP erlaubt']));
}

if ($file['size'] > 10 * 1024 * 1024) {
    exit(json_encode(['ok' => false, 'error' => 'Datei zu groß (max. 10 MB)']));
}

// Verzeichnis anlegen
$uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/products/' . $table . '/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Bild laden
switch ($mime) {
    case 'image/jpeg': $src = imagecreatefromjpeg($file['tmp_name']); break;
    case 'image/png':  $src = imagecreatefrompng($file['tmp_name']);  break;
    case 'image/gif':  $src = imagecreatefromgif($file['tmp_name']);  break;
    case 'image/webp': $src = imagecreatefromwebp($file['tmp_name']); break;
    default: exit(json_encode(['ok' => false, 'error' => 'Bildformat nicht verarbeitbar']));
}

if (!$src) {
    exit(json_encode(['ok' => false, 'error' => 'Bild konnte nicht geladen werden']));
}

// Skalieren auf max 800×800 (Cover, quadratisch)
$origW = imagesx($src);
$origH = imagesy($src);
$target = 800;

// Cover: kleinste Seite füllt den Quadrat
$scale  = max($target / $origW, $target / $origH);
$newW   = (int)round($origW * $scale);
$newH   = (int)round($origH * $scale);
$offX   = (int)round(($target - $newW) / 2);
$offY   = (int)round(($target - $newH) / 2);

$dst = imagecreatetruecolor($target, $target);

// Transparenz-Hintergrund für PNG/WebP
$transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
imagefill($dst, 0, 0, $transparent);
imagesavealpha($dst, true);

imagecopyresampled($dst, $src, $offX, $offY, 0, 0, $newW, $newH, $origW, $origH);
imagedestroy($src);

// Speichern als WebP (beste Qualität/Größe) oder JPEG als Fallback
$filename = $table . '_' . $nummer . '_' . time();
$saved    = false;
$url      = '';

if (function_exists('imagewebp')) {
    $filename .= '.webp';
    $saved = imagewebp($dst, $uploadDir . $filename, 85);
} else {
    // Fallback: weißer Hintergrund für JPEG
    $bg = imagecreatetruecolor($target, $target);
    imagefill($bg, 0, 0, imagecolorallocate($bg, 255, 255, 255));
    imagecopy($bg, $dst, 0, 0, 0, 0, $target, $target);
    $filename .= '.jpg';
    $saved = imagejpeg($bg, $uploadDir . $filename, 88);
    imagedestroy($bg);
}

imagedestroy($dst);

if (!$saved) {
    exit(json_encode(['ok' => false, 'error' => 'Fehler beim Speichern']));
}

// URL zusammenbauen (relativ zur Domain)
$url = '/uploads/products/' . $table . '/' . $filename;

// Altes Bild löschen (optional — nur eigene /uploads/products/-Dateien)
try {
    $old = $pdo->prepare("SELECT bild_url FROM `{$table}` WHERE nummer = ?");
    $old->execute([$nummer]);
    $oldRow = $old->fetch();
    if ($oldRow && !empty($oldRow['bild_url'])) {
        $oldPath = $_SERVER['DOCUMENT_ROOT'] . $oldRow['bild_url'];
        if (
            strpos($oldRow['bild_url'], '/uploads/products/') === 0 &&
            file_exists($oldPath)
        ) {
            unlink($oldPath);
        }
    }
} catch (Exception $e) {
    // ── Alte Datei löschen ist optional → Fehler ignorieren ──
    // Nur intern loggen
    wcr_log_error('upload_image:cleanup_old', $e, ['table' => $table, 'nummer' => $nummer]);
}

// bild_url in DB aktualisieren
try {
    $stmt = $pdo->prepare("UPDATE `{$table}` SET bild_url = ? WHERE nummer = ?");
    $stmt->execute([$url, $nummer]);
    
} catch (Exception $e) {
    // ── Internes Logging: Volle Fehlerdetails ──
    wcr_log_error('upload_image:db_update', $e, [
        'table' => $table,
        'nummer' => $nummer,
        'url' => $url
    ]);
    
    // ── User-Ausgabe: Generisch (kein "DB-Fehler: ..." mit Exception-Message) ──
    exit(json_encode(['ok' => false, 'error' => 'Datenbank-Aktualisierung fehlgeschlagen']));
}

// ── Erfolg + Token nach erfolgreicher Rotation zurückgeben ──
exit(json_encode([
    'ok' => true,
    'url' => $url,
    'filename' => $filename,
    'csrf_token' => wcr_csrf_token()
]));
