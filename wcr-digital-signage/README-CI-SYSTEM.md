# 🎨 WCR Digital Signage – CI System v2.0

## Übersicht
Einheitliches Design-System für alle Digital-Signage-Seiten.
Alle Komponenten nutzen dieselben Farben, Abstände, Typografie und Visual Language.

## Farben (aus DS-Controller)
```css
--clr-green:        #679467  /* Primärfarbe */
--clr-blue:         #011e45  /* Sekundärfarbe */
--clr-white:        #f1f1f1  /* Highlights */
--clr-text:         #ececec  /* Haupttext */
--clr-text-muted:   #7a8a8a  /* Sekundärtext */
--clr-bg:           #000806  /* Hintergrund */
--clr-bg-dark:      #0b0b0b  /* Dunkel */
--clr-bg-glass:     rgba(10, 14, 24, 0.65) /* Glas-Effekt */
```

## Struktur
```
/assets/css/
  ├── wcr-ds-global.css         ← HAUPTDATEI (neue Version)
  ├── wcr-ds-global_old.css     ← Backup (vor CI v2.0)
  └── wcr-ds-components.css     ← Komponenten-Bibliothek
```

## Komponenten (in wcr-ds-components.css)
- `.ds-btn`, `.ds-btn-primary`, `.ds-btn-secondary`, `.ds-btn-ghost`
- `.ds-badge`, `.ds-badge-success`, `.ds-badge-info`
- `.ds-card-modern`
- `.ds-ticker`, `.ds-progress`, `.ds-divider`
- `.ds-tooltip`, `.ds-auto-grid`

## Verwendung
### Bestehende Seiten
Bleiben **100% kompatibel** (alte Klassen wie `.glass`, `.ds-card`, `.k-card` funktionieren weiterhin).

### Neue Seiten
Nutze neue Komponenten:
```html
<div class="ds-card-modern">
  <h3 class="ds-h3">Überschrift</h3>
  <p class="ds-body">Text hier...</p>
  <button class="ds-btn ds-btn-primary">Action</button>
</div>
```

## Migration (optional)
Alte Klassen können schrittweise durch neue ersetzt werden:
- `.glass` → `.ds-card-modern`
- Inline Styles → CSS-Variablen (`var(--clr-green)`)

## Theme-Unterstützung
Weiterhin via PHP `wcr_ds_dynamic_css()` gesteuert:
- `glass` (default)
- `flat`
- `aurora`
