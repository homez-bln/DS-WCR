# CSS-Variablen Guide – WCR Backend

> **Stand:** März 2026  
> **Status:** Variablen definiert, Migration folgt schrittweise

---

## 📋 Übersicht

Dieses Dokument beschreibt das erweiterte CSS-Variablen-System für das WCR Backend (`be/inc/style.css`). Die neuen Variablen schaffen eine konsistente Basis für Transparenzen, Abstände und Farbvarianten.

**Ziel:** Hardcoded-Werte (z.B. `rgba(52,199,89,0.10)`, `#1c7c34`) durch wiederverwendbare Variablen ersetzen.

---

## 🎨 Neue Variablen

### Alpha-Werte (Transparenz)

```css
--alpha-05:  0.05;   /* Sehr leicht (z.B. Info-Hintergründe) */
--alpha-08:  0.08;   /* Leicht (z.B. Error-Messages) */
--alpha-10:  0.10;   /* Mittel (z.B. Success-Messages) */
--alpha-12:  0.12;   /* Standard (z.B. Status-Banner) */
--alpha-15:  0.15;   /* Stark (z.B. Gruppen-Badge) */
```

**Verwendung:**
```css
/* ❌ Vorher */
background: rgba(52,199,89,0.10);

/* ✅ Nachher */
background: rgba(var(--success-rgb), var(--alpha-10));
```

---

### Spacing-Scale (Abstände)

```css
--sp-1:  4px;    /* Extra klein (z.B. Badge-Padding) */
--sp-2:  8px;    /* Klein (z.B. Button-Padding vertikal) */
--sp-3:  12px;   /* Mittel (z.B. Card-Padding klein) */
--sp-4:  16px;   /* Standard (z.B. Section-Padding) */
--sp-5:  20px;   /* Groß (z.B. Card-Padding standard) */
--sp-6:  24px;   /* Extra groß (z.B. Layout-Gaps) */
```

**Verwendung:**
```css
/* ❌ Vorher */
padding: 20px;
margin-bottom: 24px;

/* ✅ Nachher */
padding: var(--sp-5);
margin-bottom: var(--sp-6);
```

---

### RGB-Farben (für rgba())

```css
--success-rgb:  52, 199, 89;      /* Grün für Success-States */
--danger-rgb:   255, 59, 48;      /* Rot für Error/Danger-States */
--primary-rgb:  0, 113, 227;      /* Blau für Primary-Actions */
--warning-rgb:  240, 173, 78;     /* Gelb für Warning-States */
```

**Verwendung:**
```css
/* ❌ Vorher */
background: rgba(52,199,89,0.12);

/* ✅ Nachher */
background: rgba(var(--success-rgb), var(--alpha-12));
```

---

### Abgeleitete Farben

```css
--success-dark:  #1c7c34;   /* Dunkleres Grün für Text auf Success-Hintergrund */
--danger-dark:   #c0392b;   /* Dunkleres Rot für Text auf Danger-Hintergrund */
```

**Verwendung:**
```css
/* ❌ Vorher */
background: rgba(52,199,89,0.12);
color: #1c7c34;

/* ✅ Nachher */
background: rgba(var(--success-rgb), var(--alpha-12));
color: var(--success-dark);
```

---

## 📦 Bestehende Variablen

Die folgenden Variablen sind bereits seit Projekt-Start verfügbar:

### Basis-Farben
```css
--primary:   #0071e3;  /* Blau */
--success:   #34c759;  /* Grün */
--danger:    #ff3b30;  /* Rot */
--warning:   #f0ad4e;  /* Gelb */
```

### Hintergründe
```css
--bg-body:    #f5f5f7;  /* Body-Hintergrund */
--bg-card:    #ffffff;  /* Karten-Hintergrund */
--bg-subtle:  #fafafa;  /* Subtiler Hintergrund */
```

### Text
```css
--text-main:   #1d1d1f;  /* Haupttext */
--text-muted:  #86868b;  /* Sekundärtext */
--text-light:  #aeaeb2;  /* Leichter Text */
```

### Borders
```css
--border:        #d2d2d7;  /* Standard-Border */
--border-light:  #e5e5ea;  /* Heller Border */
--border-xlight: #f2f2f7;  /* Extra-heller Border */
```

### Radius & Shadows
```css
--radius:        12px;  /* Standard-Border-Radius */
--radius-sm:      8px;  /* Kleiner Border-Radius */
--radius-pill:   20px;  /* Pill-Border-Radius */
--shadow:         0 2px 10px rgba(0,0,0,0.06);   /* Standard-Shadow */
--shadow-hover:   0 8px 25px rgba(0,0,0,0.10);   /* Hover-Shadow */
```

---

## 🔄 Migration-Beispiele

### Beispiel 1: Status-Banner

**❌ Vorher:**
```css
.status-banner.ok {
    background: rgba(52,199,89,.12);
    color: #1c7c34;
    border: 1px solid rgba(52,199,89,.3);
}
```

**✅ Nachher:**
```css
.status-banner.ok {
    background: rgba(var(--success-rgb), var(--alpha-12));
    color: var(--success-dark);
    border: 1px solid rgba(var(--success-rgb), 0.3);
}
```

---

### Beispiel 2: Gruppen-Badge

**❌ Vorher:**
```css
.gruppe-on .gruppe-badge {
    background: rgba(52,199,89,0.15);
    color: #1a7a30;
}
```

**✅ Nachher:**
```css
.gruppe-on .gruppe-badge {
    background: rgba(var(--success-rgb), var(--alpha-15));
    color: var(--success-dark);
}
```

---

### Beispiel 3: Upload-Message

**❌ Vorher:**
```css
.upload-message.success {
    background: rgba(52,199,89,0.10);
    color: #1c7c34;
}
.upload-message.error {
    background: rgba(255,59,48,0.08);
    color: #c0392b;
}
```

**✅ Nachher:**
```css
.upload-message.success {
    background: rgba(var(--success-rgb), var(--alpha-10));
    color: var(--success-dark);
}
.upload-message.error {
    background: rgba(var(--danger-rgb), var(--alpha-08));
    color: var(--danger-dark);
}
```

---

### Beispiel 4: Card Padding

**❌ Vorher:**
```css
.upload-panel {
    padding: 20px;
    margin-bottom: 20px;
}
```

**✅ Nachher:**
```css
.upload-panel {
    padding: var(--sp-5);
    margin-bottom: var(--sp-5);
}
```

---

## ✅ Migration-Checkliste

### Abschnitt 5: Status-Meldungen
- [ ] `.upload-message.success` → `rgba(var(--success-rgb), var(--alpha-10))`
- [ ] `.upload-message.error` → `rgba(var(--danger-rgb), var(--alpha-08))`
- [ ] `.upload-msg.ok` → `rgba(var(--success-rgb), var(--alpha-10))`
- [ ] `.upload-msg.err` → `rgba(var(--danger-rgb), var(--alpha-08))`

### Abschnitt 8: Gruppen-Dropdown
- [ ] `.gruppe-on .gruppe-badge` → `rgba(var(--success-rgb), var(--alpha-15))`
- [ ] `.gruppe-off .gruppe-badge` → `rgba(var(--danger-rgb), var(--alpha-12))`
- [ ] `.group-body.gruppe-off .item-card` → Border `rgba(var(--danger-rgb), 0.2)`

### Abschnitt 10: Media-Verwaltung
- [ ] `.info-stat` → Background `rgba(var(--primary-rgb), var(--alpha-05))`
- [ ] `.sidebar-link.active` → Background `rgba(var(--primary-rgb), var(--alpha-08))`
- [ ] `.drop-zone.drag-over` → Background `rgba(var(--primary-rgb), 0.03)`

### Abschnitt 12: Dashboard
- [ ] `.status-banner.ok` → `rgba(var(--success-rgb), var(--alpha-12))`
- [ ] `.status-banner.err` → `rgba(var(--danger-rgb), var(--alpha-10))`

### Abschnitt 13: Rollen-System
- [ ] `.status-banner.ok` → `rgba(var(--success-rgb), var(--alpha-12))` (Duplikat entfernen)
- [ ] `.status-banner.error` → `rgba(var(--danger-rgb), var(--alpha-10))`

---

## 🎯 Vorteile

### 1. Konsistenz
- Gleiche Alpha-Werte für gleiche Zwecke
- Gleiche Status-Farben überall

### 2. Wartbarkeit
- Farben zentral änderbar
- Transparenzen zentral steuerbar

### 3. Lesbarkeit
```css
/* ❌ Was bedeutet 0.12? */
background: rgba(52,199,89,0.12);

/* ✅ Klar: Standard-Alpha für Success-Hintergründe */
background: rgba(var(--success-rgb), var(--alpha-12));
```

### 4. Skalierbarkeit
- Neue Farben einfach hinzufügbar
- Neue Alpha-Werte bei Bedarf ergänzbar

---

## ⚠️ Wichtige Hinweise

### Keine visuellen Änderungen
Die Migration ist **semantisch**, nicht visuell:
```css
/* Beide rendern identisch */
rgba(52,199,89,0.10)  ===  rgba(52, 199, 89, 0.10)
```

### Schrittweise Migration
**Nicht** alle Werte auf einmal ersetzen. Empfehlung:
1. Einen CSS-Abschnitt migrieren
2. Visuell testen (Backend-Seiten öffnen)
3. Commit
4. Nächster Abschnitt

### Plugin-CSS unberührt
**Wichtig:** Das Plugin-CSS (`wcr-digital-signage/assets/css/`) bleibt komplett unberührt. Dieses Variablen-System gilt **nur für das Backend** (`be/inc/style.css`).

---

## 📚 Weitere Dokumentation

- [ARCHITECTURE.md](../../ARCHITECTURE.md) – Vollständige Architektur-Dokumentation
- [README.md](../../README.md) – Projekt-Übersicht
