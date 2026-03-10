<?php
/**
 * api/update_ticket.php
 * v11: + Sicheres Error-Logging (kein Exception-Leaking)
 * v12: + Support für wp_drinks_gruppen zusätzlich zu wp_food_gruppen
 *
 * Interner Backend-Endpunkt für Produkt-Updates:
 *  - Toggle stock (aktiv/inaktiv)
 *  - Preisänderung (nur admin/cernal)
 *  - Gruppen-Toggle (food + drinks)
 *
 * Wird verwendet von: be/js/ctrl-shared.js
 * Aufgerufen aus: drinks.php, food.php, kino.php, etc.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/error_handler.php';
require_once __DIR__ . '/../inc/db.php';

// ── SECURITY 1: Login erforderlich ──
if (!is_logged_in()) {
    http_response_code(403);
    exit(json_encode(['ok' => false, 'error' => 'Login erforderlich']));
}

// ── SECURITY 2: CSRF-Schutz (silent mode für JSON-API) ──
// wcr_verify_csrf_silent() validiert Token und rotiert automatisch.
// Frontend muss neues Token aus Response übernehmen (siehe unten).
if (!wcr_verify_csrf_silent()) {
    http_response_code(403);
    exit(json_encode(['ok' => false, 'error' => 'CSRF-Token ungültig']));
}

// ── Whitelist + Parameter ──
$allowed = ['cable', 'drinks', 'food', 'ice', 'camping', 'extra', 'wp_food_gruppen', 'wp_drinks_gruppen'];
$table   = trim($_POST['table']  ?? '');
$nr      = $_POST['nummer'] ?? '';
$mode    = trim($_POST['mode']   ?? '');
$val     = $_POST['value']  ?? '';

if (!in_array($table, $allowed, true)) {
    http_response_code(400);
    exit(json_encode(['ok' => false, 'error' => 'Ungültige Tabelle']));
}

// ── SECURITY 3: Preisänderungen nur für admin/cernal ──
if ($mode === 'price' && !wcr_can('edit_prices')) {
    http_response_code(403);
    exit(json_encode(['ok' => false, 'error' => 'Keine Berechtigung: Preise ändern erfordert admin oder cernal']));
}

// ── Business Logic ──
try {
    if ($mode === 'price') {
        $stmt = $pdo->prepare("UPDATE `{$table}` SET preis = ? WHERE nummer = ?");
        $stmt->execute([(float)$val, (int)$nr]);

    } elseif ($mode === 'toggle') {
        $stock = ($val === '1' || $val === 'true') ? 1 : 0;
        $stmt  = $pdo->prepare("UPDATE `{$table}` SET stock = ? WHERE nummer = ?");
        $stmt->execute([$stock, (int)$nr]);

    } elseif ($mode === 'gruppe') {
        // FIX v12: Tabelle aus $_POST['table'] verwenden (wp_food_gruppen ODER wp_drinks_gruppen)
        $typ   = trim($nr);
        $aktiv = ($val === '1') ? 1 : 0;
        
        // Validierung: Nur Gruppen-Tabellen erlaubt
        if (!in_array($table, ['wp_food_gruppen', 'wp_drinks_gruppen'], true)) {
            http_response_code(400);
            exit(json_encode(['ok' => false, 'error' => 'Ungültige Gruppen-Tabelle']));
        }
        
        $stmt = $pdo->prepare("UPDATE `{$table}` SET aktiv = ? WHERE typ = ?");
        $stmt->execute([$aktiv, $typ]);

    } else {
        http_response_code(400);
        exit(json_encode(['ok' => false, 'error' => 'Unbekannter Modus']));
    }

    // ── Erfolg + neues CSRF-Token zurückgeben ──
    // wcr_verify_csrf_silent() hat Token bereits rotiert.
    // Frontend MUSS dieses neue Token in document.body.dataset.csrf speichern,
    // sonst schlägt der nächste Request fehl (Token-Rotation!).
    echo json_encode([
        'ok' => true,
        'csrf_token' => wcr_csrf_token()
    ]);

} catch (Exception $e) {
    // ── Internes Logging: Volle Fehlerdetails ──
    wcr_log_error('update_ticket', $e, [
        'table' => $table,
        'nummer' => $nr,
        'mode' => $mode
    ]);
    
    // ── User-Ausgabe: Generisch (bereits sicher) ──
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB-Fehler']);
}
