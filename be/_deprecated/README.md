# Archivierte Backend-Dateien

Dieses Verzeichnis enthält veralteten Backend-Code, der nicht mehr aktiv verwendet wird,
aber als Referenz oder für zukünftige Implementierungen aufbewahrt wird.

## ⚠️ Wichtige Warnung

**Dateien in diesem Verzeichnis sind NICHT sicher für Produktionsumgebungen!**

Alle archivierten Dateien haben bekannte Sicherheitsprobleme und entsprechen nicht
den aktuellen Backend-Sicherheitsstandards (Session-Auth + Permission + CSRF).

## Archivierte Dateien

### `functions/upload_handler.php`
- **Archiviert am:** 2026-03-07
- **Grund:** Ersetzt durch `api/save_opening_hours.php` + `api/upload_image.php`
- **Sicherheit:** ❌ Kein Login-Check, kein CSRF → NICHT reaktivieren ohne Umbau
- **Wert:** ✅ Enthält vollständige Bild-Upload-Logik (Cover-Mode, 1080×1920 Portrait-Skalierung)
- **Wiederverwendung:** Code kann als Referenz für neue Upload-Features dienen
- **Original-Funktion:** Upload von Öffnungszeiten-Fotos in separate DB-Tabelle

### `functions/times_data.php`
- **Archiviert am:** 2026-03-07
- **Grund:** Keine aktive Verwendung gefunden
- **Sicherheit:** ✅ Read-only, keine direkten Probleme
- **Wert:** ✅ Modulare Helper-Funktion (Sunset-API, DB-Abfragen, Zeitraum-Generierung)
- **Wiederverwendung:** Kann für Refactoring von `ctrl/times.php` nützlich sein
- **Original-Funktion:** Daten-Loader für Öffnungszeiten-Verwaltung

## Gelöschte Dateien

### `api/save_map_config.php`
- **Gelöscht am:** 2026-03-07
- **Grund:** Vollständig ersetzt durch `ctrl/obstacles.php` (Zeile 117-141)
- **Redundanz:** 1:1 Duplikat der Map-Config-Speicherung
- **Git-Historie:** Datei bleibt in Git-Historie erhalten und kann bei Bedarf wiederhergestellt werden

## Vor Wiederverwendung beachten

Wenn du Code aus diesem Archiv wiederverwenden möchtest:

1. **Sicherheitsprüfung durchführen:**
   - Login-Check hinzufügen: `wcr_require('permission_name')`
   - CSRF-Schutz implementieren: `wcr_verify_csrf()` oder `wcr_verify_csrf_silent()`
   - Permission-Checks prüfen: `wcr_can('permission_name')`

2. **Mit aktueller Architektur abgleichen:**
   - Prüfen, ob ähnliche Funktionalität bereits existiert
   - Konsistenz mit anderen APIs sicherstellen
   - Token-Rotation bei JSON-APIs implementieren

3. **Code-Review durchführen:**
   - Input-Validierung prüfen
   - SQL-Injection-Schutz sicherstellen (PDO Prepared Statements)
   - Error-Handling aktualisieren

4. **Tests durchführen:**
   - Funktionale Tests
   - Sicherheitstests
   - Edge-Case-Tests

## Architektur-Standards (2026-03-07)

Das aktive Backend folgt diesen Sicherheitsstandards:

### JSON-APIs (POST-Endpunkte)
- ✅ Session-basierte Authentifizierung
- ✅ Permission-basierte Autorisierung
- ✅ CSRF-Schutz mit `wcr_verify_csrf_silent()`
- ✅ Token-Rückgabe für Frontend-Rotation
- ✅ JSON-Response mit `csrf_token` Feld

### Controller (POST-Formulare)
- ✅ Session-basierte Authentifizierung
- ✅ Permission-basierte Autorisierung
- ✅ CSRF-Schutz mit `wcr_verify_csrf()`
- ✅ Token-Felder in allen Formularen via `wcr_csrf_field()`
- ✅ PRG-Pattern (Post-Redirect-Get) wo sinnvoll

### Read-Only Controller
- ✅ Session-basierte Authentifizierung
- ✅ Permission-basierte Autorisierung
- ❌ Kein CSRF-Schutz nötig (keine schreibenden Aktionen)

## Weitere Informationen

- **CHANGELOG.md** – Detaillierte Archivierungs-Historie
- **Git-Commits** – Vollständige Änderungs-Historie
- **Security-Audit 2026-03** – Dokumentation des CSRF-Security-Audits

## Kontakt

Bei Fragen zur Wiederverwendung archivierten Codes:
1. Git-Historie konsultieren (`git log -- <pfad>`)
2. Security-Audit-Dokumentation prüfen
3. Mit aktuellen API-Implementierungen vergleichen
