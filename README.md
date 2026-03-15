# 🖥️ WCR Digital Signage System

**Enterprise Digital Signage Solution für Freizeiteinrichtungen**

[![Deploy Status](https://img.shields.io/badge/deploy-automated-success)](https://github.com/homez-bln/wcr-digital-signage/actions)
[![WordPress](https://img.shields.io/badge/WordPress-6.4+-blue)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-8.0+-purple)](https://php.net)
[![License](https://img.shields.io/badge/license-Proprietary-red)](LICENSE)

---

## 📋 Projekt-Übersicht

**WCR Digital Signage** ist eine spezialisierte Lösung zur Verwaltung und Anzeige von Content (Menükaarten, Preislisten, Wetterdaten, Kino-Programm, etc.) auf Digital Signage Displays.

### Kernfunktionen

- 🍹 **Menü-Management** – Getränkekarten, Speisekarten, Kaffee, Eis
- 💰 **Preislisten** – Cable-Park, Camping, dynamische Preisänderungen
- 🌤️ **Live-Daten** – Wetter-Widget, Windkarte mit Open-Meteo API
- 🎬 **Content-Management** – Kino-Programm, Obstacles-Verwaltung
- 🏟️ **Ticket-System** – Event-Tickets mit Live-Status
- 👥 **Benutzer-Verwaltung** – Rollen-basiertes Zugriffssystem
- 📺 **DS-Seiten Steuerung** – Seiten per DB-Status an/ausschalten (piSignage)

---

## 🏗️ Architektur-Übersicht

> **Hinweis:** Die folgende Darstellung ist eine **konzeptionelle Übersicht** zur Verdeutlichung der System-Architektur. Die tatsächliche Dateistruktur weicht davon ab – siehe Abschnitt "Verzeichnisstruktur" für exakte Pfade.

**Zwei-System-Architektur** für optimale Trennung von Frontend und Backend:

```
┌─────────────────────────────────────┐
│   WordPress Installation            │
│   /WebSpace/wordpress/              │
│                                     │
│   ┌───────────────────────────────┐ │
│   │  wcr-digital-signage/         │ │  ← WordPress-Plugin
│   │  (PUBLIC FRONTEND)            │ │     • Öffentliche Anzeige
│   │  • Shortcodes                 │ │     • Read-Only REST-API
│   │  • REST-API (Read-Only)       │ │     • Frontend-Assets
│   └───────────────────────────────┘ │
└─────────────────────────────────────┘
            ↓ (DB-Zugriff)
    ┌───────────────────┐
    │  MySQL Database   │
    │  be_*-Tabellen    │
    └───────────────────┘
            ↑ (DB-Zugriff)
┌─────────────────────────────────────┐
│   Standalone PHP Application        │
│   /WebSpace/be/                     │
│                                     │
│   be/ (PROTECTED BACKEND)           │  ← Separates Backend
│   • Session-basiertes Login         │     • Content-Verwaltung
│   • Schreibende REST-APIs           │     • Admin-Interface
│   • Admin-Interface                 │     • User-Management
│   • Rollen-System (3 Rollen)        │
└─────────────────────────────────────┘
```

---

## 🚀 Schnelleinstieg für Entwickler

### Voraussetzungen

- **WordPress:** Version 6.4+
- **PHP:** Version 8.0+
- **MySQL/MariaDB:** Version 5.7+
- **Git:** Für Deployment
- **GitHub Actions:** Für automatisches Deployment

### Verzeichnisstruktur (Reale Dateiorte)

```
wcr-digital-signage/
├── wcr-digital-signage/          # WordPress-Plugin
│   ├── wcr-digital-signage.php   # Haupt-Plugin-Datei
│   ├── includes/                 # Plugin-Logik
│   │   ├── rest-api.php          # Read-Only REST-API (Namespace: wakecamp/v1)
│   │   ├── rest-screenshot.php   # Screenshot-API (Sonderfall mit CSRF)
│   │   ├── shortcodes.php        # Shortcode-Registrierung (zentral)
│   │   ├── shortcodes-content.php   # Content-Shortcodes (Menü, Preislisten)
│   │   ├── shortcodes-display.php   # Display-Shortcodes (Wetter, Windkarte)
│   │   ├── shortcodes-widgets.php   # Widget-Shortcodes (Animationen)
│   │   ├── shortcode-kino.php    # Legacy Kino-Shortcode
│   │   ├── shortcode-produkte.php   # Produkte Spotlight (stock=0 wird nicht gerendert)
│   │   ├── ds-pages.php          # DS-Seiten Aktivierungssystem (is_ds_page_active())
│   │   ├── admin-ds-pages.php    # WP-Admin: DS-Seiten verwalten
│   │   ├── instagram.php         # Instagram-API-Klasse
│   │   ├── screenshot.php        # Screenshot-Generator
│   │   ├── enqueue.php           # CSS/JS Assets Enqueue
│   │   └── db.php                # WordPress-DB-Connection
│   └── assets/                   # Frontend-Assets
│
├── be/                           # Standalone Backend
│   ├── index.php
│   ├── inc/
│   │   ├── auth.php              # Session + Rollen + CSRF
│   │   ├── db.php                # DB-Verbindung (PDO)
│   │   ├── ds-rules.json         # DS-Seiten Regelwerk (auto generiert)
│   │   └── ...
│   ├── ctrl/
│   │   ├── ds-seiten.php         # DS-Seiten Vorschau + Aktivierungssteuerung
│   │   └── ...
│   └── ...
└── README.md
```

---

## 🔐 Sicherheitskonzept

### Rollen-System

| Rolle | Berechtigungen | Anzahl |
|-------|----------------|--------|
| **cernal** | Alle Rechte + Debug-Panel | 1 (Owner) |
| **admin** | Content-Management + User-Management | 1-3 (Team) |
| **user** | Toggle-Funktionen (An/Aus) | Beliebig |

### Session-Sicherheit

- ✅ **Secure Cookies** (HTTPS-only, HttpOnly, SameSite=Strict)
- ✅ **Session-Fingerprinting** (User-Agent + IP-Präfix)
- ✅ **8-Stunden-Timeout** (automatischer Logout)
- ✅ **Session-Regeneration** bei Login (verhindert Session-Fixation)

### CSRF-Protection

- ✅ **Token-Rotation** (nach erfolgreicher Validierung neues Token)
- ✅ **Constant-Time Comparison** (verhindert Timing-Angriffe)
- ✅ **Alle Write-APIs geschützt** (POST/PUT/DELETE)

---

## 🌐 REST-API

### Plugin REST-API (Read-Only)

**Namespace:** `wakecamp/v1`

| Route | Zweck | Zugriff |
|-------|-------|--------|
| `GET /wakecamp/v1/drinks` | Getränkekarte | ✅ Öffentlich |
| `GET /wakecamp/v1/food` | Speisekarte | ✅ Öffentlich |
| `GET /wakecamp/v1/ice` | Eiskarte | ✅ Öffentlich |
| `GET /wakecamp/v1/cable` | Cable-Park-Preise | ✅ Öffentlich |
| `GET /wakecamp/v1/camping` | Camping-Preise | ✅ Öffentlich |
| `GET /wakecamp/v1/kino` | Kino-Programm | ✅ Öffentlich |
| `GET /wakecamp/v1/events` | Events | ✅ Öffentlich |
| `GET /wakecamp/v1/obstacles` | Obstacles-Map | ✅ Öffentlich |
| `GET /wakecamp/v1/instagram` | Instagram-Posts | ✅ Öffentlich |
| `GET /wakecamp/v1/playlist-check` | Prüft ob Playlist/Seite aktiv ist (stock > 0) | ✅ Öffentlich |

**`/playlist-check` Parameter:**

| Parameter | Typ | Beschreibung |
|-----------|-----|--------------|
| `ids` | string | Komma-getrennte Produktnummern (z.B. `3010,3089,3162`) |
| `table` | string | Optional: Tabelle eingrenzen (`food`, `drinks`, `cable`, `camping`, `extra`, `ice`) |
| `typ` | string | Optional: Typ-Filter (z.B. `Burger`) |
| `mode` | string | `any` (Standard) oder `all` |

**Response:**
```json
{ "active": true, "count": 2, "total": 3, "mode": "any", "reason": "ids_any" }
```

**Beispiele:**
```
# Einzelne IDs prüfen (mode=any: mind. 1 aktiv)
/wp-json/wakecamp/v1/playlist-check?ids=3010,3089,3162

# Alle müssen aktiv sein
/wp-json/wakecamp/v1/playlist-check?ids=3010,3089&mode=all

# Typ prüfen (alle Burger in food)
/wp-json/wakecamp/v1/playlist-check?table=food&typ=Burger

# Ganze Tabelle prüfen
/wp-json/wakecamp/v1/playlist-check?table=food
```

**Sonderfälle:**

| Route | Methoden | Zugriff |
|-------|----------|---------|
| `/wakecamp/v1/obstacles/map-config` | GET | ✅ Öffentlich |
| `/wakecamp/v1/obstacles/map-config` | POST | 🔒 Secret/Admin |
| `/wakecamp/v1/ds-settings` | GET/POST | 🔒 Admin |

### Backend REST-API (Write)

**Basis-URL:** `https://your-domain.de/be/api/`

| API | HTTP-Methoden | Zugriff |
|-----|---------------|---------|
| `be/api/drinks.php` | POST, PUT, DELETE | 🔒 Session + CSRF |
| `be/api/food.php` | POST, PUT, DELETE | 🔒 Session + CSRF |
| `be/api/users.php` | POST, PUT, DELETE | 🔒 Session + CSRF |

---

## 🎨 Shortcodes

### Verfügbare Shortcodes

**Content-Shortcodes (Menü & Preislisten):**
- `[wcr_getraenke]` – Getränkekarte
- `[wcr_softdrinks]` – Softdrinks-Karte
- `[wcr_essen]` – Speisekarte
- `[wcr_kaffee]` – Kaffeekarte
- `[wcr_eis]` – Eiskarte
- `[wcr_cable]` – Cable-Park-Preise
- `[wcr_camping]` – Camping-Preise

**Display-Shortcodes (Live-Daten):**
- `[wcr_windmap]` – Windkarte mit Leaflet + Open-Meteo
- `[wcr_wetter]` – Wetter-Widget mit 7-Tage-Forecast

**Widget-Shortcodes (Animationen):**
- `[wcr_starter_pack]` – Starter-Pack-Animation (GSAP)

**Produkt-Spotlight:**
- `[wcr_produkte id1="..." id2="..." id3="..." table="food" typ="Burger" mode="any"]`
  - `stock=0` → Produkt wird nicht gerendert
  - Alle Produkte `stock=0` → Shortcode gibt leeren Output (piSignage lädt Seite nicht)

---

## 📺 DS-Seiten Steuerung

Jede WordPress-DS-Seite kann im Backend `/be/ctrl/ds-seiten.php` mit einer Aktivierungsregel verknüpft werden.

### Regel-Felder

| Feld | Beschreibung |
|------|--------------|
| **Override** | `auto` (DB-Check) / `force_on` / `force_off` |
| **Tabellen** | Welche Tabellen geprüft werden (leer = alle 6) |
| **Typ** | Typ-Filter z.B. `Burger` – prüft `WHERE typ = ? AND stock > 0` |
| **IDs** | Komma-Liste von `nummer`-Werten |
| **Mode** | `any` = mind. 1 aktiv / `all` = alle müssen aktiv sein |

### Logik

```
Typ "Burger" in food → mind. 1 Burger stock > 0
  → ✅ Seite wird von piSignage geladen
  → Produkte mit stock=0 werden im Shortcode übersprungen
  → Produkte mit stock > 0 werden normal angezeigt

Alle Burger stock = 0
  → ⛔ Seite wird NICHT geladen (Shortcode gibt nur HTML-Kommentare zurück)
```

Regeln werden in `be/inc/ds-rules.json` gespeichert.

---

## 🚀 Deployment

### Automatisches Deployment via GitHub Actions

**Trigger:**
- ✅ Push auf `main`-Branch
- ✅ Manueller Workflow-Trigger

**Deployment-Prozess:**
1. Code auschecken
2. SFTP-Upload zu IONOS WebSpace
3. Delete-Mode: Alte Dateien entfernen
4. Exclude: `.git/`, `.github/`, `node_modules/`

---

## 📝 Changelog

Siehe [CHANGELOG.md](wcr-digital-signage/CHANGELOG.md) für detaillierte Änderungen.

---

## 📧 Support

**Entwickler:** Marcus Kempe  
**E-Mail:** marcus.kempe88@gmail.com  
**Repository:** [github.com/homez-bln/DS-WCR](https://github.com/homez-bln/DS-WCR)

---

**Stand:** März 2026  
**Version:** 2.1
