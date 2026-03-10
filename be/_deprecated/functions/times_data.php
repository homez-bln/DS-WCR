<?php
/**
 * ⚠️ ARCHIVIERT – NICHT VERWENDEN ⚠️
 *
 * Archiviert am: 2026-03-07
 * Original-SHA: 66ef161b72c409f873dfd3f9e40fa2b6fb0fc158
 *
 * GRUND:
 *   Keine aktive Verwendung gefunden. Möglicherweise wurde die Logik
 *   inline in ctrl/times.php integriert oder ist nicht mehr notwendig.
 *
 * ERSETZT DURCH:
 *   - ctrl/times.php (enthält möglicherweise ähnliche Logik inline)
 *   - Unklare Ersetzung – Funktion könnte für Modularisierung nützlich sein
 *
 * SICHERHEITSPROBLEME:
 *   - ✅ KEINE (Read-only Helper-Funktion)
 *   - ✅ Keine POST-Verarbeitung
 *   - ✅ Keine DB-Schreibzugriffe
 *   - ✅ Keine User-Input-Verarbeitung
 *
 * WIEDERVERWENDUNG:
 *   Code enthält modulare Daten-Loader-Logik:
 *   - Sunset-API Integration (Open-Meteo mit cURL + Timeout)
 *   - DB-Abfragen für Öffnungszeiten
 *   - Datums-Zeitraum-Generierung (-5 bis +11 Tage)
 *   - Kalenderwoche-Gruppierung
 *   - Deutsche Wochentag-Labels
 *
 *   Sichere Wiederverwendung möglich:
 *   - Code ist read-only und hat keine Sicherheitsprobleme
 *   - Kann als Include in ctrl/times.php verwendet werden
 *   - Nützlich für Refactoring/Modularisierung
 *   - Aufrufende Datei muss Login/Permission prüfen (nicht diese Datei)
 *
 * VORSICHT:
 *   Nach Archivierung ZWINGEND ctrl/times.php testen:
 *   - Öffnungszeiten anzeigen
 *   - Sunset-Zeiten anzeigen
 *   - Datums-Navigation funktioniert
 *   Falls Fehler auftreten → Datei SOFORT wiederherstellen!
 *
 * TESTS:
 *   Nach Archivierung zu testen:
 *   - ✅ ctrl/times.php – Öffnungszeiten-Anzeige
 *   - ✅ ctrl/times.php – Sunset-Zeiten
 *   - ✅ ctrl/times.php – Datums-Navigation
 *
 * === ORIGINAL-DOKUMENTATION ===
 *
 * DATEI: be/functions/times_data.php
 *
 * Verantwortlich für:
 *  1. Sonnenuntergangs-Zeiten via Open-Meteo API laden
 *  2. Gespeicherte Öffnungszeiten aus DB laden
 *  3. Datums-Zeitraum (heute -5 bis +11 Tage) aufbauen
 *
 * Voraussetzungen (müssen vor dem Include gesetzt sein):
 *  - $db   (mysqli oder PDO, aus db.php)
 *  - $LAT  (float, Breitengrad)
 *  - $LNG  (float, Längengrad)
 *
 * Setzt folgende Variablen in den aufrufenden Scope:
 *  - $sunsetMap    array   ['YYYY-MM-DD' => 'HH:MM', ...]
 *  - $savedData    array   ['YYYY-MM-DD' => DB-Zeile, ...]
 *  - $today        DateTime
 *  - $periodArray  array   Alle DateTime-Objekte des Zeitraums
 *  - $keys         array   Integer-Keys von $periodArray
 *  - $lastIndex    int     Letzter Index
 *  - $daysByKw     array   ['KW-Nr' => Anzahl Tage, ...]
 *  - $deWochentage array   Deutsche Wochentag-Kürzel
 */

// ----------------------------------------------------------------
// 1. SUNSET-API (Open-Meteo)
// Zeitraum: 5 Tage zurück + 11 Tage voraus
// ----------------------------------------------------------------
$sunsetMap = [];

$apiUrl  = sprintf(
    'https://api.open-meteo.com/v1/forecast?latitude=%s&longitude=%s'
    . '&daily=sunset&timezone=Europe%%2FBerlin&past_days=5&forecast_days=11',
    $LAT, $LNG
);
// FIX v6: @file_get_contents hat keinen Timeout → friert ein wenn API langsam.
$_ch = curl_init($apiUrl);
curl_setopt_array($_ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5]);
$apiJson = curl_exec($_ch) ?: '';
curl_close($_ch);
$apiData = json_decode($apiJson, true);

if (isset($apiData['daily']['time'])) {
    foreach ($apiData['daily']['time'] as $idx => $date) {
        // Sonnenuntergangszeit: ISO-String "YYYY-MM-DDTHH:MM" → nur "HH:MM"
        $sunsetMap[$date] = substr($apiData['daily']['sunset'][$idx], 11, 5);
    }
}

// ----------------------------------------------------------------
// 2. GESPEICHERTE ÖFFNUNGSZEITEN AUS DB
// ----------------------------------------------------------------
$savedData = [];

if ($db) {
    if ($db instanceof mysqli) {
        $res = $db->query("SELECT * FROM opening_hours");
        while ($row = $res->fetch_assoc()) {
            $savedData[$row['datum']] = $row;
        }

    } elseif ($db instanceof PDO) {
        $stmt = $db->query("SELECT * FROM opening_hours");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $savedData[$row['datum']] = $row;
        }
    }
}

// ----------------------------------------------------------------
// 3. DATUMS-ZEITRAUM AUFBAUEN
// ----------------------------------------------------------------
$today        = new DateTime();
$start        = (clone $today)->modify('-5 days');
$end          = (clone $today)->modify('+11 days');
$period       = new DatePeriod($start, new DateInterval('P1D'), $end);

// Deutsche Wochentag-Kürzel (Sonntag=0 bis Samstag=6)
$deWochentage = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];

// Anzahl Tage pro Kalenderwoche (für grid-row: span in der Tabelle)
$daysByKw = [];
foreach ($period as $dt) {
    $kw = $dt->format('W');
    $daysByKw[$kw] = ($daysByKw[$kw] ?? 0) + 1;
}

// Array für direkten Index-Zugriff im Template
$periodArray = iterator_to_array($period);
$keys        = array_keys($periodArray);
$lastIndex   = count($keys) - 1;
