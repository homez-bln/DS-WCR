<?php
/**
 * ⚠️ ARCHIVIERT – NICHT VERWENDEN ⚠️
 *
 * Archiviert am: 2026-03-07
 * Original-SHA: e7486b63ba0e09ad2c848ccbe18b40569545daef
 *
 * GRUND:
 *   Keine aktive Verwendung gefunden. Funktion wurde ersetzt durch:
 *   - be/api/save_opening_hours.php (Öffnungszeiten + Foto-Upload)
 *   - be/api/upload_image.php (Produkt-Bilder mit CSRF-Schutz)
 *
 * ERSETZT DURCH:
 *   - be/api/save_opening_hours.php (POST + Foto-Upload inkl. CSRF)
 *   - be/api/upload_image.php (Produkt-Upload mit Session-Auth + CSRF)
 *
 * SICHERHEITSPROBLEME:
 *   - ❌ Kein Login-Check (jeder kann hochladen)
 *   - ❌ Kein Permission-Check (keine Rollen-Prüfung)
 *   - ❌ Kein CSRF-Schutz (anfällig für Cross-Site-Angriffe)
 *   - ❌ Direkte POST-Verarbeitung ohne Validierung
 *   - ❌ Keine Rate-Limiting (Upload-Spam möglich)
 *
 * WIEDERVERWENDUNG:
 *   Code enthält wertvolle Upload-Logik:
 *   - Portrait-Format 1080×1920 (Story-Format)
 *   - Cover-Mode Skalierung (kein Letterboxing)
 *   - GD-Library Bildverarbeitung
 *   - JPEG-Qualitätsoptimierung
 *
 *   Vor Wiederverwendung ZWINGEND erforderlich:
 *   1. wcr_require('edit_content') hinzufügen (Login + Permission)
 *   2. wcr_verify_csrf_silent() implementieren (CSRF-Schutz)
 *   3. Mit upload_image.php abgleichen (bereits CSRF-geschützt)
 *   4. Input-Validierung erweitern (Dateigröße, MIME-Type)
 *   5. Error-Handling modernisieren (JSON-Response)
 *
 * TESTS:
 *   Nach Archivierung erfolgreich getestet:
 *   - be/api/save_opening_hours.php – Öffnungszeiten + Foto-Upload
 *   - be/api/upload_image.php – Produkt-Bilder hochladen
 *   - ctrl/times.php – Öffnungszeiten-Verwaltung
 *
 * === ORIGINAL-DOKUMENTATION ===
 *
 * DATEI: be/functions/upload_handler.php
 *
 * Verantwortlich für:
 *  1. Foto-Upload (POST): Bild auf 1080×1920 (Cover) skalieren + als JPEG speichern
 *  2. Aktuelles Foto aus DB laden
 *
 * Voraussetzungen (müssen vor dem Include gesetzt sein):
 *  - $db  (mysqli oder PDO, aus db.php)
 *
 * Setzt folgende Variablen in den aufrufenden Scope:
 *  - $uploadMessage  string  Erfolgs- oder Fehlermeldung für die UI
 *  - $currentPhoto   string  Dateiname des aktuell aktiven Fotos
 */

$uploadMessage = '';
$currentPhoto  = '';

// ----------------------------------------------------------------
// 1. UPLOAD VERARBEITEN (nur bei POST mit Datei-Input)
// ----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['opening_hours_photo'])) {

    $uploadDir    = $_SERVER['DOCUMENT_ROOT'] . '/uploads/opening_hours/';
    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
    $file         = $_FILES['opening_hours_photo'];

    // Zielverzeichnis anlegen falls nicht vorhanden
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    if (in_array($file['type'], $allowedTypes) && $file['error'] === 0) {

        // Bildtyp erkennen und Quell-Ressource laden
        $imageType = exif_imagetype($file['tmp_name']);
        switch ($imageType) {
            case IMAGETYPE_JPEG: $sourceImage = imagecreatefromjpeg($file['tmp_name']); break;
            case IMAGETYPE_PNG:  $sourceImage = imagecreatefrompng($file['tmp_name']);  break;
            default:
                $uploadMessage = '✗ Ungültiges Bildformat';
                $sourceImage   = false;
        }

        if ($sourceImage) {

            // Zielformat: 9:16 Story-Format
            $targetWidth  = 1080;
            $targetHeight = 1920;

            // Leeres Zielbild mit weißem Hintergrund erstellen
            $resizedImage = imagecreatetruecolor($targetWidth, $targetHeight);
            imagefill($resizedImage, 0, 0, imagecolorallocate($resizedImage, 255, 255, 255));

            // Originalgröße auslesen
            $origWidth  = imagesx($sourceImage);
            $origHeight = imagesy($sourceImage);

            // Cover-Modus: Bild füllt Frame komplett aus (kein Letterboxing)
            $scale = max($targetWidth / $origWidth, $targetHeight / $origHeight);

            // (int)-Cast PFLICHT: imagecopyresampled erwartet Integer-Parameter
            $newWidth  = (int)($origWidth  * $scale);
            $newHeight = (int)($origHeight * $scale);
            $offsetX   = (int)(($targetWidth  - $newWidth)  / 2);
            $offsetY   = (int)(($targetHeight - $newHeight) / 2);

            imagecopyresampled(
                $resizedImage, $sourceImage,
                $offsetX, $offsetY, 0, 0,
                $newWidth, $newHeight,
                $origWidth, $origHeight
            );

            // Dateiname mit Timestamp (verhindert Browser-Caching-Probleme)
            $filename   = 'opening_hours_' . date('Y-m-d_H-i-s') . '.jpg';
            $targetPath = $uploadDir . $filename;

            // Als JPEG (Qualität 90) speichern
            if (imagejpeg($resizedImage, $targetPath, 90)) {

                // Dateiname in DB speichern — alten Eintrag ersetzen (immer nur 1 Foto aktiv)
                if ($db instanceof mysqli) {
                    // Alle alten Einträge löschen, dann neu anlegen
                    $db->query("DELETE FROM opening_hours_photos");
                    $stmt = $db->prepare("INSERT INTO opening_hours_photos (filename, uploaded_at) VALUES (?, NOW())");
                    $stmt->bind_param('s', $filename);
                    $stmt->execute();

                } elseif ($db instanceof PDO) {
                    $db->exec("DELETE FROM opening_hours_photos");
                    $stmt = $db->prepare("INSERT INTO opening_hours_photos (filename, uploaded_at) VALUES (?, NOW())");
                    $stmt->execute([$filename]);
                }

                $uploadMessage = '✓ Hochgeladen: ' . $filename;

            } else {
                $uploadMessage = '✗ Fehler beim Speichern des Bildes';
            }

            // GD-Speicher freigeben
            imagedestroy($sourceImage);
            imagedestroy($resizedImage);

        } else {
            $uploadMessage = '✗ Bildverarbeitung fehlgeschlagen';
        }

    } else {
        $uploadMessage = $file['error'] !== 0
            ? '✗ Upload-Fehler (Code: ' . $file['error'] . ')'
            : '✗ Nur JPG/PNG erlaubt';
    }
}

// ----------------------------------------------------------------
// 2. AKTUELLES FOTO AUS DB LADEN
// (nur wenn noch kein Foto durch Upload gesetzt wurde)
// ----------------------------------------------------------------
if ($db && empty($currentPhoto)) {
    if ($db instanceof mysqli) {
        $res = $db->query("SELECT filename FROM opening_hours_photos
                           ORDER BY uploaded_at DESC LIMIT 1");
        if ($row = $res->fetch_assoc()) {
            $currentPhoto = $row['filename'];
        }

    } elseif ($db instanceof PDO) {
        $stmt = $db->query("SELECT filename FROM opening_hours_photos
                            ORDER BY uploaded_at DESC LIMIT 1");
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $currentPhoto = $row['filename'];
        }
    }
}
