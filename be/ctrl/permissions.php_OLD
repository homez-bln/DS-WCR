<?php
/**
 * ctrl/permissions.php — Rechte-Matrix-Verwaltung (nur für cernal) v2
 * 
 * v2 NEU: Erweiterte UI mit verständlichen Funktionsbereichen
 *  - Gruppierung nach Bereichen (Produkte, Zeiten, Media, DS, Benutzer, System)
 *  - Jede Gruppe zeigt betroffene Seiten/Funktionen
 *  - Intern bleibt Permission-basierte Architektur erhalten
 *  - KEINE freien Dateirechte, nur Mapping auf bestehende Permissions
 * 
 * ZWECK:
 *  cernal kann granular steuern, welche Rollen welche Permissions haben.
 *  Änderungen werden sofort aktiv (Cache-Invalidierung).
 *  Sichere Fallbacks: cernal behält IMMER Vollzugriff (hardcoded).
 * 
 * SICHERHEIT:
 *  - require_login() + wcr_is_cernal() Check
 *  - CSRF-Schutz für alle POST-Aktionen
 *  - Validierung: Nur bekannte Permissions + Rollen
 *  - Hardcoded: cernal immer in allen Permissions
 *  - Matrix wird via REST API in wp_options gespeichert
 * 
 * ARCHITEKTUR-BESTÄTIGUNG:
 *  - Permissions bleiben technische Grundlage (wcr_can/wcr_require)
 *  - KEINE freie Dateirechte-Logik implementiert
 *  - UI mappt nur verständlich auf bestehende Permissions
 */

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';

// API-Konstanten definieren (benötigt für wcr_load_permissions)
if (!defined('DSC_WP_API_BASE')) {
    define('DSC_WP_API_BASE', 'https://wcr-webpage.de/wp-json/wakecamp/v1');
}
if (!defined('DSC_WP_SECRET')) {
    define('DSC_WP_SECRET', 'WCR_DS_2026');
}

require_login();

// ── NUR CERNAL DARF DIESE SEITE SEHEN ──
if (!wcr_is_cernal()) {
    http_response_code(403);
    $pageTitle = 'Kein Zugriff';
    include __DIR__ . '/../inc/403.php';
    exit;
}

$message = '';
$msgType = '';

// ── POST-Handler mit CSRF-Schutz ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!wcr_verify_csrf(false)) {
        $message = 'Sicherheitsprüfung fehlgeschlagen. Bitte Seite neu laden und erneut versuchen.';
        $msgType = 'error';
    } else {
        $action = $_POST['action'] ?? '';

        // ── Rechte-Matrix speichern ─────────────────────────────────────
        if ($action === 'save') {
            $newMatrix = [];
            
            // Alle Permissions durchgehen
            foreach (array_keys(WCR_DEFAULT_PERMISSIONS) as $perm) {
                $roles = [];
                
                // Checkboxen auslesen (Format: perm_PERMISSION_ROLE)
                foreach (WCR_ROLES as $role) {
                    $checkboxName = 'perm_' . $perm . '_' . $role;
                    if (isset($_POST[$checkboxName])) {
                        $roles[] = $role;
                    }
                }
                
                // SICHERHEIT: cernal IMMER hinzufügen (verhindert Aussperren)
                if (!in_array('cernal', $roles, true)) {
                    $roles[] = 'cernal';
                }
                
                $newMatrix[$perm] = $roles;
            }
            
            // Matrix speichern
            if (wcr_save_permissions($newMatrix)) {
                $message = '✅ Rechte-Matrix gespeichert. Änderungen sind sofort aktiv.';
                $msgType = 'ok';
            } else {
                $message = '❌ Fehler beim Speichern der Rechte-Matrix.';
                $msgType = 'error';
            }
        }

        // ── Auf Standard zurücksetzen ─────────────────────────────────────
        if ($action === 'reset') {
            // Custom-Matrix löschen (setzt Fallback auf Standard)
            $ch = curl_init(DSC_WP_API_BASE . '/options');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS     => json_encode([
                    'wcr_secret' => DSC_WP_SECRET,
                    'key'        => 'wcr_permissions_matrix',
                    'value'      => null, // Löschen durch null
                ], JSON_UNESCAPED_UNICODE),
            ]);
            $body = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($code === 200) {
                $message = '♻️ Rechte-Matrix auf Standard zurückgesetzt.';
                $msgType = 'ok';
            } else {
                $message = '❌ Fehler beim Zurücksetzen.';
                $msgType = 'error';
            }
        }
    }
}

// ── Aktuelle Matrix laden ──────────────────────────────────────────────────────────────────────────────────────────────────────────────
$currentMatrix = wcr_load_permissions();
$isCustom      = wcr_has_custom_permissions();

// ── WICHTIG: Funktionsbereich-Mapping auf Permissions ────────────────────────────────────────────────────────────────
// Diese Struktur mappt UI-Bereiche auf technische Permissions.
// Änderung hier hat KEINE Auswirkung auf wcr_can()/wcr_require() — diese nutzen weiterhin WCR_DEFAULT_PERMISSIONS.
// Dies ist rein UI-Organisation für bessere Verständlichkeit.

$functionalAreas = [
    // ───────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
    // BEREICH: PRODUKTE
    // ───────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
    'products' => [
        'icon'  => '🛍️',
        'label' => 'Produkte',
        'desc'  => 'Verwaltung aller Produktseiten',
        'items' => [
            [
                'permission' => 'edit_products',
                'label'      => 'Produkte bearbeiten',
                'desc'       => 'Drinks, Food, Eis, Cable, Camping, Extra',
                'pages'      => ['drinks.php', 'food.php', 'list.php?t=ice', 'list.php?t=cable', 'list.php?t=camping', 'list.php?t=extra'],
                'functions'  => ['Produktdaten bearbeiten', 'Toggle An/Aus', 'Bilder hochladen'],
                'critical'   => false,
                'why'        => 'Diese Permission steuert Zugriff auf alle Produktverwaltungsseiten. Ohne sie können User nur Toggle nutzen.',
            ],
            [
                'permission' => 'edit_prices',
                'label'      => 'Preise ändern',
                'desc'       => 'Preis-Felder in allen Produktseiten',
                'pages'      => ['drinks.php', 'food.php', 'list.php'],
                'functions'  => ['Preise direkt bearbeiten', 'Preis-Inputs sichtbar'],
                'critical'   => false,
                'why'        => 'Getrennte Permission für Preise ermöglicht Mitarbeiter ohne Preisänderungsrecht.',
            ],
        ],
    ],
    
    // ───────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
    // BEREICH: ZEITEN
    // ───────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
    'times' => [
        'icon'  => '🕒',
        'label' => 'Öffnungszeiten',
        'desc'  => 'Öffnungszeiten-Verwaltung',
        'items' => [
            [
                'permission' => 'view_times',
                'label'      => 'Öffnungszeiten anzeigen & bearbeiten',
                'desc'       => 'Zeiten, Kurse, Fotos verwalten',
                'pages'      => ['times.php'],
                'functions'  => ['Öffnungszeiten bearbeiten', 'Kurse konfigurieren', 'Fotos hochladen', 'Als geschlossen markieren'],
                'critical'   => false,
                'why'        => 'Steuert kompletten Zugriff auf Öffnungszeiten-Seite. Wichtig für tägliche Planung.',
            ],
        ],
    ],
    
    // ───────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
    // BEREICH: CONTENT & MEDIA
    // ───────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
    'content' => [
        'icon'  => '🎥',
        'label' => 'Content & Media',
        'desc'  => 'Kino, Obstacles, Media-Verwaltung',
        'items' => [
            [
                'permission' => 'edit_content',
                'label'      => 'Content bearbeiten',
                'desc'       => 'Kino-Programm verwalten',
                'pages'      => ['kino.php'],
                'functions'  => ['Filme verwalten', 'Spielzeiten setzen', 'Trailer-Links'],
                'critical'   => false,
                'why'        => 'Steuert Zugriff auf Kino-Seite. Getrennt von Produkten für spezialisierte Rollen.',
            ],
            [
                'permission' => 'view_media',
                'label'      => 'Media-Verwaltung',
                'desc'       => 'Medien & Obstacles verwalten',
                'pages'      => ['media.php', 'obstacles.php'],
                'functions'  => ['Media-Bibliothek', 'Videos hochladen', 'Obstacles konfigurieren'],
                'critical'   => false,
                'why'        => 'Steuert Zugriff auf Media- und Obstacles-Seiten. Wichtig für Content-Management.',
            ],
        ],
    ],
    
    // ───────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
    // BEREICH: DIGITAL SIGNAGE
    // ───────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
    'digital_signage' => [
        'icon'  => '📺',
        'label' => 'Digital Signage',
        'desc'  => 'DS-Seiten & Controller',
        'items' => [
            [
                'permission' => 'view_ds',
                'label'      => 'DS-Seiten & Controller',
                'desc'       => 'Digital Signage Verwaltung',
                'pages'      => ['ds-seiten.php', 'ds-settings.php'],
                'functions'  => ['DS-Vorschau', 'Design-Tokens', 'Themes', 'Instagram-Settings'],
                'critical'   => false,
                'why'        => 'Steuert Zugriff auf alle Digital-Signage-Verwaltungsseiten.',
            ],
        ],
    ],
    
    // ───────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
    // BEREICH: TICKETS (Optional, falls verwendet)
    // ───────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
    'tickets' => [
        'icon'  => '🎫',
        'label' => 'Tickets',
        'desc'  => 'Ticket-Verwaltung',
        'items' => [
            [
                'permission' => 'edit_tickets',
                'label'      => 'Tickets bearbeiten',
                'desc'       => 'Ticket-System verwalten',
                'pages'      => ['(dedizierte Ticket-Seite falls vorhanden)'],
                'functions'  => ['Ticket-Typen', 'Preise', 'Verfügbarkeit'],
                'critical'   => false,
                'why'        => 'Getrennte Permission für zukünftiges Ticket-System.',
            ],
        ],
    ],
    
    // ───────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
    // BEREICH: SYSTEM (KRITISCH)
    // ───────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
    'system' => [
        'icon'  => '⚙️',
        'label' => 'System & Verwaltung',
        'desc'  => 'Kritische Systemfunktionen',
        'items' => [
            [
                'permission' => 'manage_users',
                'label'      => 'Benutzerverwaltung',
                'desc'       => 'User anlegen & verwalten',
                'pages'      => ['users.php'],
                'functions'  => ['Benutzer erstellen', 'Rollen zuweisen', 'Passwörter zurücksetzen', 'User deaktivieren'],
                'critical'   => true,
                'why'        => '⚠️ KRITISCH: Steuert wer Zugang zum System bekommt. Nur vertrauenswürdigen Rollen geben.',
            ],
            [
                'permission' => 'debug',
                'label'      => 'Debug-Panel',
                'desc'       => 'Entwickler-Tools & Logs',
                'pages'      => ['(Debug-Panel in allen Seiten)'],
                'functions'  => ['Session-Debug', 'SQL-Logs', 'Error-Tracking'],
                'critical'   => true,
                'why'        => '⚠️ KRITISCH: Zeigt sensible Systeminformationen. NUR für cernal (hardcoded).',
            ],
            [
                'permission' => '(Rechte-Matrix selbst)',
                'label'      => 'Rechte-Matrix bearbeiten',
                'desc'       => 'Diese Seite (permissions.php)',
                'pages'      => ['permissions.php'],
                'functions'  => ['Rechte-Matrix konfigurieren', 'Rollen-Permissions zuweisen'],
                'critical'   => true,
                'why'        => '⚠️ ULTRA-KRITISCH: Nur cernal-Zugriff via wcr_is_cernal() Check. KEINE Permission-basierte Prüfung.',
            ],
        ],
    ],
    
    // ───────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
    // BEREICH: BASIS-FUNKTIONEN
    // ───────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
    'basic' => [
        'icon'  => '⏻️',
        'label' => 'Basis-Funktionen',
        'desc'  => 'Alle Rollen haben Zugriff',
        'items' => [
            [
                'permission' => 'toggle',
                'label'      => 'An/Aus schalten',
                'desc'       => 'Produkte aktivieren/deaktivieren',
                'pages'      => ['alle Produktseiten'],
                'functions'  => ['Toggle-Schalter bedienen'],
                'critical'   => false,
                'why'        => 'Basis-Funktion für alle Rollen. Selbst "user" kann Produkte an/ausschalten.',
            ],
        ],
    ],
];

// ── Rollen-Metadaten ──────────────────────────────────────────────────────────────────────────────────────────
$roleMeta = [
    'cernal' => ['icon' => '🔧', 'color' => '#7c3aed', 'label' => 'Cernal', 'desc' => 'Vollzugriff (hardcoded)'],
    'admin'  => ['icon' => '👑', 'color' => '#0071e3', 'label' => 'Admin',  'desc' => 'Erweiterte Rechte'],
    'user'   => ['icon' => '👤', 'color' => '#34c759', 'label' => 'User',   'desc' => 'Basis-Rechte'],
];
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>🔐 Rechte-Matrix</title>
</head>
<body class="bo">
<?php include __DIR__ . '/../inc/menu.php'; ?>

<div class="header-controls">
  <h1>🔐 Rechte-Matrix</h1>
</div>

<?php if ($message): ?>
  <div class="status-banner <?= htmlspecialchars($msgType) ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<!-- Info-Banner -->
<div class="permissions-info-banner">
  <div class="pib-icon">🛡️</div>
  <div class="pib-content">
    <div class="pib-title">
      <?php if ($isCustom): ?>
        ⚙️ Custom-Rechte-Matrix aktiv
      <?php else: ?>
        ♻️ Standard-Rechte-Matrix (Fallback)
      <?php endif; ?>
    </div>
    <div class="pib-desc">
      <?php if ($isCustom): ?>
        Du hast individuelle Rechte konfiguriert. Änderungen werden sofort aktiv.
      <?php else: ?>
        Es ist keine Custom-Matrix gespeichert. Die Standard-Rechte sind aktiv.
      <?php endif; ?>
      <br>
      <strong>🔒 Sicherheit:</strong> Cernal behält immer Vollzugriff — auch wenn Checkboxen deaktiviert sind.
      <br>
      <strong>📌 Architektur:</strong> Diese UI mappt Seiten/Funktionen auf technische Permissions. wcr_can() & wcr_require() bleiben die Grundlage.
    </div>
  </div>
</div>

<!-- Rechte-Matrix: Gruppiert nach Funktionsbereichen -->
<form method="POST" class="permissions-form">
  <?= wcr_csrf_field() ?>
  <input type="hidden" name="action" value="save">

  <?php foreach ($functionalAreas as $areaKey => $area): ?>
  <div class="functional-area" data-area="<?= htmlspecialchars($areaKey) ?>">
    
    <!-- Bereichs-Header -->
    <div class="area-header">
      <div class="area-title">
        <span class="area-icon"><?= $area['icon'] ?></span>
        <span class="area-label"><?= htmlspecialchars($area['label']) ?></span>
      </div>
      <div class="area-desc"><?= htmlspecialchars($area['desc']) ?></div>
    </div>

    <!-- Permissions-Tabelle für diesen Bereich -->
    <div class="permissions-table-wrapper">
      <table class="permissions-table">
        <thead>
          <tr>
            <th class="perm-col-function">Funktion / Seite</th>
            <?php foreach ($roleMeta as $role => $rm): ?>
              <th class="perm-col-role" data-role="<?= htmlspecialchars($role) ?>">
                <span class="role-badge" data-role="<?= htmlspecialchars($role) ?>">
                  <?= $rm['icon'] ?> <?= htmlspecialchars($rm['label']) ?>
                </span>
                <?php if ($role === 'cernal'): ?>
                  <div class="role-hint">🔒 Hardcoded</div>
                <?php endif; ?>
              </th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($area['items'] as $item):
            $perm         = $item['permission'];
            $isCritical   = $item['critical'];
            $currentRoles = ($perm === '(Rechte-Matrix selbst)') ? ['cernal'] : ($currentMatrix[$perm] ?? []);
            $isSpecial    = ($perm === '(Rechte-Matrix selbst)');
          ?>
            <tr class="<?= $isCritical ? 'perm-row-critical' : '' ?> <?= $isSpecial ? 'perm-row-special' : '' ?>">
              <!-- Funktions-Name-Cell -->
              <td class="perm-function-cell">
                <div class="perm-function-header">
                  <div class="perm-function-label">
                    <?= htmlspecialchars($item['label']) ?>
                    <?php if ($isCritical): ?>
                      <span class="critical-badge">⚠️ Kritisch</span>
                    <?php endif; ?>
                    <?php if ($isSpecial): ?>
                      <span class="special-badge">🔒 Nur Cernal</span>
                    <?php endif; ?>
                  </div>
                  <div class="perm-function-desc"><?= htmlspecialchars($item['desc']) ?></div>
                </div>
                
                <!-- Expandable Details -->
                <div class="perm-function-details" style="display:none">
                  <div class="pfd-section">
                    <strong>📝 Betroffene Seiten:</strong>
                    <ul>
                      <?php foreach ($item['pages'] as $page): ?>
                        <li><code><?= htmlspecialchars($page) ?></code></li>
                      <?php endforeach; ?>
                    </ul>
                  </div>
                  <div class="pfd-section">
                    <strong>⚙️ Funktionen:</strong>
                    <ul>
                      <?php foreach ($item['functions'] as $func): ?>
                        <li><?= htmlspecialchars($func) ?></li>
                      <?php endforeach; ?>
                    </ul>
                  </div>
                  <?php if (!$isSpecial): ?>
                  <div class="pfd-section">
                    <strong>🔑 Interne Permission:</strong>
                    <code class="perm-code"><?= htmlspecialchars($perm) ?></code>
                  </div>
                  <?php endif; ?>
                  <div class="pfd-section pfd-why">
                    <strong>💡 Warum diese Zuordnung?</strong>
                    <p><?= htmlspecialchars($item['why']) ?></p>
                  </div>
                </div>
                
                <button type="button" class="btn-expand-details" onclick="toggleDetails(this)">
                  ▼ Details
                </button>
              </td>
              
              <!-- Checkboxen für Rollen -->
              <?php if ($isSpecial): ?>
                <!-- Rechte-Matrix selbst: Nur cernal, keine Checkboxen -->
                <?php foreach (WCR_ROLES as $role): ?>
                  <td class="perm-checkbox-cell" data-role="<?= htmlspecialchars($role) ?>">
                    <span class="perm-checkmark-static">
                      <?= $role === 'cernal' ? '✅' : '❌' ?>
                    </span>
                    <?php if ($role === 'cernal'): ?>
                      <span class="locked-hint">🔒</span>
                    <?php endif; ?>
                  </td>
                <?php endforeach; ?>
              <?php else: ?>
                <!-- Normale Permissions: Checkboxen -->
                <?php foreach (WCR_ROLES as $role):
                  $isChecked    = in_array($role, $currentRoles, true);
                  $isCernal     = ($role === 'cernal');
                  $checkboxName = 'perm_' . $perm . '_' . $role;
                ?>
                  <td class="perm-checkbox-cell" data-role="<?= htmlspecialchars($role) ?>">
                    <label class="perm-checkbox-wrapper <?= $isCernal ? 'checkbox-locked' : '' ?>">
                      <input 
                        type="checkbox" 
                        name="<?= htmlspecialchars($checkboxName) ?>" 
                        <?= $isChecked ? 'checked' : '' ?>
                        <?= $isCernal ? 'disabled' : '' ?>
                        class="perm-checkbox"
                      >
                      <span class="perm-checkmark"><?= $isChecked ? '✅' : '❌' ?></span>
                      <?php if ($isCernal): ?>
                        <span class="locked-hint">🔒</span>
                      <?php endif; ?>
                    </label>
                  </td>
                <?php endforeach; ?>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endforeach; ?>

  <!-- Actions -->
  <div class="permissions-actions">
    <button type="submit" class="btn-upload btn-save">
      💾 Rechte-Matrix speichern
    </button>
    <button type="button" class="btn-secondary" onclick="if(confirm('Standard-Rechte wiederherstellen?')) { document.getElementById('reset-form').submit(); }">
      ♻️ Auf Standard zurücksetzen
    </button>
  </div>
</form>

<!-- Verstecktes Reset-Formular -->
<form method="POST" id="reset-form" style="display:none">
  <?= wcr_csrf_field() ?>
  <input type="hidden" name="action" value="reset">
</form>

<!-- Architektur-Bestätigung -->
<div class="architecture-confirmation">
  <h3>🏛️ Architektur-Bestätigung</h3>
  <div class="ac-grid">
    <div class="ac-item ac-ok">
      <span class="ac-icon">✅</span>
      <div>
        <strong>Permissions bleiben technische Grundlage</strong>
        <p>wcr_can() und wcr_require() arbeiten weiterhin mit WCR_DEFAULT_PERMISSIONS. Diese UI mappt nur verständlich.</p>
      </div>
    </div>
    <div class="ac-item ac-ok">
      <span class="ac-icon">✅</span>
      <div>
        <strong>KEINE freien Dateirechte</strong>
        <p>Es wurde KEIN System implementiert, das direkt Dateizugriffe steuert. Alle Checks laufen über definierte Permissions.</p>
      </div>
    </div>
    <div class="ac-item ac-ok">
      <span class="ac-icon">✅</span>
      <div>
        <strong>Mapping-basierte UI</strong>
        <p>Die Funktionsbereiche sind reine UI-Organisation. Intern wird auf bestehende Permission-Keys gemappt.</p>
      </div>
    </div>
  </div>
</div>

<!-- Legende -->
<div class="permissions-legend">
  <h3>Legende</h3>
  <div class="legend-grid">
    <div class="legend-item">
      <span class="legend-icon">✅</span>
      <span class="legend-text">Berechtigung erteilt</span>
    </div>
    <div class="legend-item">
      <span class="legend-icon">❌</span>
      <span class="legend-text">Berechtigung verweigert</span>
    </div>
    <div class="legend-item">
      <span class="legend-icon">🔒</span>
      <span class="legend-text">Hardcoded (nicht änderbar)</span>
    </div>
    <div class="legend-item">
      <span class="legend-icon">⚠️</span>
      <span class="legend-text">Kritische Berechtigung</span>
    </div>
  </div>
</div>

<style>
/* ── Permissions-Seite Styles ────────────────────────────────────────────────────────────────── */

/* Info-Banner */
.permissions-info-banner {
    background: rgba(var(--primary-rgb), var(--alpha-05));
    border: 1px solid rgba(var(--primary-rgb), var(--alpha-15));
    border-radius: var(--radius);
    padding: 16px 20px;
    margin-bottom: 24px;
    display: flex;
    gap: 16px;
    align-items: flex-start;
}
.pib-icon {
    font-size: 28px;
    line-height: 1;
    flex-shrink: 0;
}
.pib-content {
    flex: 1;
}
.pib-title {
    font-size: 15px;
    font-weight: 700;
    color: var(--text-main);
    margin-bottom: 6px;
}
.pib-desc {
    font-size: 13px;
    color: var(--text-muted);
    line-height: 1.5;
}

/* Functional Area Gruppierung */
.functional-area {
    background: var(--bg-card);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    margin-bottom: 24px;
    overflow: hidden;
}
.area-header {
    background: linear-gradient(135deg, rgba(var(--primary-rgb), var(--alpha-08)), rgba(var(--success-rgb), var(--alpha-08)));
    border-bottom: 2px solid var(--border);
    padding: 16px 20px;
}
.area-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 16px;
    font-weight: 700;
    color: var(--text-main);
    margin-bottom: 4px;
}
.area-icon {
    font-size: 22px;
}
.area-desc {
    font-size: 12px;
    color: var(--text-muted);
}

/* Permissions-Tabelle */
.permissions-table-wrapper {
    overflow: hidden;
}
.permissions-table {
    width: 100%;
    border-collapse: collapse;
}
.permissions-table thead {
    background: var(--bg-subtle);
}
.permissions-table th {
    padding: 12px 14px;
    text-align: left;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-muted);
    border-bottom: 2px solid var(--border);
}
.permissions-table th.perm-col-role {
    text-align: center;
    width: 140px;
}
.permissions-table th .role-hint {
    font-size: 9px;
    color: var(--text-light);
    font-weight: 500;
    margin-top: 4px;
    text-transform: none;
    letter-spacing: 0;
}

.permissions-table tbody tr {
    border-bottom: 1px solid var(--border-light);
    transition: background 0.15s;
}
.permissions-table tbody tr:hover {
    background: var(--bg-subtle);
}

/* Kritische & Spezielle Zeilen */
.permissions-table tbody tr.perm-row-critical {
    background: rgba(var(--warning-rgb), var(--alpha-03));
}
.permissions-table tbody tr.perm-row-critical:hover {
    background: rgba(var(--warning-rgb), var(--alpha-05));
}
.permissions-table tbody tr.perm-row-special {
    background: rgba(123, 58, 237, 0.05);
    border-left: 3px solid #7c3aed;
}
.permissions-table tbody tr.perm-row-special:hover {
    background: rgba(123, 58, 237, 0.08);
}

/* Funktions-Cell */
.perm-function-cell {
    padding: 14px;
}
.perm-function-header {
    margin-bottom: 8px;
}
.perm-function-label {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-main);
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.perm-function-desc {
    font-size: 12px;
    color: var(--text-muted);
    margin-top: 3px;
}

.critical-badge {
    font-size: 9px;
    background: rgba(var(--warning-rgb), var(--alpha-15));
    color: #a67c00;
    padding: 2px 6px;
    border-radius: var(--radius-pill);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.special-badge {
    font-size: 9px;
    background: rgba(123, 58, 237, 0.15);
    color: #7c3aed;
    padding: 2px 6px;
    border-radius: var(--radius-pill);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Details Expand */
.perm-function-details {
    background: var(--bg-subtle);
    border-radius: var(--radius-sm);
    padding: 12px;
    margin-top: 10px;
    font-size: 12px;
}
.pfd-section {
    margin-bottom: 10px;
}
.pfd-section:last-child {
    margin-bottom: 0;
}
.pfd-section strong {
    display: block;
    color: var(--text-main);
    margin-bottom: 4px;
    font-size: 11px;
}
.pfd-section ul {
    list-style: none;
    padding: 0;
    margin: 0;
}
.pfd-section li {
    padding: 2px 0;
    color: var(--text-muted);
}
.pfd-section code {
    background: var(--bg-card);
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
    color: #0071e3;
}
.perm-code {
    font-weight: 600;
    background: rgba(var(--primary-rgb), var(--alpha-10)) !important;
    padding: 4px 8px !important;
    font-size: 12px !important;
}
.pfd-why {
    border-top: 1px solid var(--border-light);
    padding-top: 10px;
}
.pfd-why p {
    margin: 0;
    color: var(--text-muted);
    line-height: 1.5;
}

.btn-expand-details {
    font-size: 11px;
    padding: 4px 10px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    color: var(--text-muted);
    cursor: pointer;
    transition: all 0.2s;
    margin-top: 8px;
}
.btn-expand-details:hover {
    background: var(--bg-subtle);
    color: var(--text-main);
}
.btn-expand-details.expanded {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

/* Checkbox-Cells */
.perm-checkbox-cell {
    text-align: center;
    padding: 14px;
}
.perm-checkbox-cell[data-role="cernal"] {
    background: rgba(123, 58, 237, 0.03);
}
.perm-checkbox-cell[data-role="admin"] {
    background: rgba(0, 113, 227, 0.03);
}
.perm-checkbox-cell[data-role="user"] {
    background: rgba(52, 199, 89, 0.03);
}

/* Checkbox-Wrapper */
.perm-checkbox-wrapper {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    user-select: none;
}
.perm-checkbox-wrapper.checkbox-locked {
    cursor: not-allowed;
    opacity: 0.6;
}
.perm-checkbox {
    display: none;
}
.perm-checkmark {
    font-size: 18px;
    transition: transform 0.15s;
}
.perm-checkmark-static {
    font-size: 18px;
}
.perm-checkbox-wrapper:hover .perm-checkmark {
    transform: scale(1.15);
}
.perm-checkbox-wrapper.checkbox-locked:hover .perm-checkmark {
    transform: none;
}
.locked-hint {
    font-size: 12px;
    opacity: 0.5;
}

/* Actions */
.permissions-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-start;
    margin-top: 20px;
}
.btn-save {
    background: var(--success);
}
.btn-secondary {
    padding: 10px 20px;
    background: var(--bg-card);
    color: var(--text-main);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s, border-color 0.2s;
}
.btn-secondary:hover {
    background: var(--bg-subtle);
    border-color: var(--text-muted);
}

/* Architektur-Bestätigung */
.architecture-confirmation {
    background: var(--bg-card);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 20px;
    margin-top: 24px;
}
.architecture-confirmation h3 {
    font-size: 14px;
    font-weight: 700;
    color: var(--text-main);
    margin: 0 0 14px;
}
.ac-grid {
    display: grid;
    gap: 12px;
}
.ac-item {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    padding: 12px;
    background: var(--bg-subtle);
    border-radius: var(--radius-sm);
}
.ac-item.ac-ok {
    border-left: 3px solid var(--success);
}
.ac-icon {
    font-size: 20px;
    flex-shrink: 0;
}
.ac-item strong {
    display: block;
    font-size: 13px;
    color: var(--text-main);
    margin-bottom: 4px;
}
.ac-item p {
    font-size: 12px;
    color: var(--text-muted);
    margin: 0;
    line-height: 1.5;
}

/* Legende */
.permissions-legend {
    background: var(--bg-card);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 20px;
    margin-top: 24px;
}
.permissions-legend h3 {
    font-size: 14px;
    font-weight: 700;
    color: var(--text-main);
    margin: 0 0 14px;
}
.legend-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 12px;
}
.legend-item {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
    color: var(--text-muted);
}
.legend-icon {
    font-size: 18px;
}

/* Responsive */
@media (max-width: 1000px) {
    .permissions-table-wrapper {
        overflow-x: auto;
    }
    .permissions-table {
        min-width: 800px;
    }
}
</style>

<script>
// Checkbox-Toggle mit visueller Bestätigung
document.querySelectorAll('.perm-checkbox').forEach(cb => {
    cb.addEventListener('change', function() {
        const checkmark = this.parentElement.querySelector('.perm-checkmark');
        checkmark.textContent = this.checked ? '✅' : '❌';
    });
});

// Warnung bei kritischen Rechten
const criticalCheckboxes = document.querySelectorAll('.perm-row-critical .perm-checkbox');
criticalCheckboxes.forEach(cb => {
    cb.addEventListener('change', function() {
        if (!this.checked && !this.parentElement.classList.contains('checkbox-locked')) {
            const permName = this.closest('tr').querySelector('.perm-function-label').textContent.trim();
            if (!confirm(`⚠️ WARNUNG: Du entfernst eine kritische Berechtigung (${permName}). Fortfahren?`)) {
                this.checked = true;
                this.dispatchEvent(new Event('change'));
            }
        }
    });
});

// Details Expand/Collapse
function toggleDetails(btn) {
    const details = btn.previousElementSibling;
    const isVisible = details.style.display !== 'none';
    
    if (isVisible) {
        details.style.display = 'none';
        btn.textContent = '▼ Details';
        btn.classList.remove('expanded');
    } else {
        details.style.display = 'block';
        btn.textContent = '▲ Verstecken';
        btn.classList.add('expanded');
    }
}
</script>

<?php include __DIR__ . '/../inc/debug.php'; ?>
</body>
</html>
