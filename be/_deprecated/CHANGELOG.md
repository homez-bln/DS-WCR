# Archivierungs-Historie

Dieses Dokument protokolliert alle Archivierungs- und Löschvorgänge im Backend.

## 2026-03-07 – Initiale Archivierung

### Hintergrund
Security-Audit des Backend-Systems hat veraltete Dateien ohne aktive Verwendung identifiziert.
Ziel: Aktive Codebasis sauber halten, wertvollen Code als Referenz bewahren.

### Archiviert

#### `functions/upload_handler.php` → `_deprecated/functions/upload_handler.php`
- **Grund:** Ersetzt durch `api/save_opening_hours.php` (2026-02) + `api/upload_image.php` (2026-03)
- **Keine Referenzen:** Code-Suche ergab keine aktiven Aufrufe
- **Sicherheitsprobleme:** Kein Login-Check, kein CSRF-Schutz, direkter POST-Handler
- **Wert:** Enthält vollständige Portrait-Upload-Logik (1080×1920 Cover-Mode)
- **Original-SHA:** `e7486b63ba0e09ad2c848ccbe18b40569545daef`
- **Archiv-Commit:** [`0e3921af`](https://github.com/homez-bln/wcr-digital-signage/commit/0e3921af52dd5bb23254834cb6d585694d1f5d94)
- **Lösch-Commit:** [`25a698b4`](https://github.com/homez-bln/wcr-digital-signage/commit/25a698b42985edc37fc175dee26559e2f3b965f4)

#### `functions/times_data.php` → `_deprecated/functions/times_data.php`
- **Grund:** Keine aktive Verwendung gefunden, möglicherweise inline in `ctrl/times.php` integriert
- **Keine Referenzen:** Code-Suche ergab keine aktiven Aufrufe
- **Sicherheitsprobleme:** Keine (read-only Helper-Funktion)
- **Wert:** Modulare Daten-Loader-Logik (Sunset-API, DB, Zeitraum-Generierung)
- **Original-SHA:** `66ef161b72c409f873dfd3f9e40fa2b6fb0fc158`
- **Archiv-Commit:** [`52ac6c5e`](https://github.com/homez-bln/wcr-digital-signage/commit/52ac6c5e6306acb80abc68a17392e369d7c77d83)
- **Lösch-Commit:** [`34a26b0e`](https://github.com/homez-bln/wcr-digital-signage/commit/34a26b0e03816d2b76748290a4c3f35c0d7b232a)
- **⚠️ Wichtig:** Nach diesem Commit MUSS `ctrl/times.php` getestet werden!

### Gelöscht

#### `api/save_map_config.php`
- **Grund:** Vollständig redundant - identische Funktion in `ctrl/obstacles.php` (Zeile 117-141)
- **Keine Referenzen:** Code-Suche ergab keine aktiven Aufrufe
- **Redundanz:** 1:1 Duplikat der Map-Config-Speicherung (lat/lon/zoom → WordPress-API)
- **Kein Archiv:** Code hat keinen Referenzwert (nur Boilerplate-Proxy)
- **Original-SHA:** `b5c8c9f6d916af08e35ce7fa9bb5a8aa3f0e1234`
- **Letzter Commit:** [`a33c71bc`](https://github.com/homez-bln/wcr-digital-signage/commit/a33c71bc082cc1eba69ec2aee3b9d9507c29335a)
- **Wiederherstellung:** `git checkout a33c71bc^ -- be/api/save_map_config.php`

### Tests durchzuführen

⚠️ **KRITISCH nach Archivierung:**

- [ ] **`ctrl/times.php`** – Öffnungszeiten anzeigen (falls times_data.php verwendet wurde)
- [ ] **`ctrl/times.php`** – Sunset-Zeiten korrekt
- [ ] **`ctrl/times.php`** – Datums-Navigation funktioniert
- [ ] **`ctrl/obstacles.php`** – Map-Config speichern (Landscape)
- [ ] **`ctrl/obstacles.php`** – Map-Config speichern (Portrait)
- [ ] **`api/save_opening_hours.php`** – Öffnungszeiten + Foto-Upload
- [ ] **`api/upload_image.php`** – Produkt-Bilder hochladen

✅ **Erwartetes Ergebnis:**
Alle Features funktionieren normal. Keine 404-Fehler, keine fehlenden Includes.

❌ **Bei Fehler:**
1. Commit-SHA identifizieren (siehe oben)
2. Datei wiederherstellen: `git checkout [SHA] -- pfad/zur/datei.php`
3. Issue dokumentieren
4. Alternative Lösung finden

### Zusammenfassung
- **3 Dateien** bearbeitet (2 archiviert, 1 gelöscht)
- **7 Commits** erstellt (README, CHANGELOG, 3× Archiv, 2× Löschung, 1× Update)
- **Codebasis bereinigt** – nur aktive Dateien in Haupt-Verzeichnissen
- **Wertvoller Code bewahrt** – Referenz für zukünftige Features
- **Git-Historie intakt** – alle Änderungen nachvollziehbar und rückgängig machbar

### Rollback-Befehle

Falls Probleme auftreten:

```bash
# upload_handler.php wiederherstellen
git checkout 25a698b4^ -- be/functions/upload_handler.php

# times_data.php wiederherstellen
git checkout 34a26b0e^ -- be/functions/times_data.php

# save_map_config.php wiederherstellen
git checkout a33c71bc^ -- be/api/save_map_config.php

# Komplett zurückrollen (vor Archivierung)
git revert 34a26b0e..42e51a13
```

---

## Template für zukünftige Einträge

```markdown
## YYYY-MM-DD – [Kurzbeschreibung]

### Archiviert

#### `pfad/datei.php` → `_deprecated/pfad/datei.php`
- **Grund:** [Warum archiviert?]
- **Keine Referenzen:** [Code-Suche Ergebnis]
- **Sicherheitsprobleme:** [Bekannte Issues]
- **Wert:** [Warum aufbewahrt?]
- **Original-SHA:** [git rev-parse HEAD:pfad/datei.php]
- **Archiv-Commit:** [SHA + Link]
- **Lösch-Commit:** [SHA + Link]

### Gelöscht

#### `pfad/datei.php`
- **Grund:** [Warum gelöscht?]
- **Keine Referenzen:** [Code-Suche Ergebnis]
- **Redundanz:** [Mit was redundant?]
- **Letzter Commit:** [SHA + Link]
- **Wiederherstellung:** `git checkout [SHA]^ -- pfad/datei.php`

### Tests durchzuführen
- [ ] [Test 1]
- [ ] [Test 2]
```
