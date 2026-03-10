<?php
/**
 * update_ticket.php — Proxy zu api/update_ticket.php
 *
 * SECURITY v9: Erfordert Login + edit_products/edit_tickets Permission + CSRF Token
 *
 * FIX v13: Ruft interne API direkt auf (include) statt via HTTP.
 *          Grund: Session-Sharing funktioniert nicht über HTTP-Roundtrip.
 *          CSRF-Token-Rotation muss in derselben Session stattfinden.
 *
 * FIX v6: Sendet nach erfolgreichem Update einen WP-REST-Request um den
 *   WordPress-Transient-Cache zu leeren, damit Preisänderungen
 *   sofort auf den Screens erscheinen (nicht erst nach 15 Min).
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/error_handler.php';

header('Content-Type: application/json; charset=utf-8');

// ── SECURITY: Login + Permission + CSRF erforderlich ──
$table = $_POST['table'] ?? '';
if (in_array($table, ['ice', 'cable', 'camping', 'extra', 'food', 'drinks', 'wp_food_gruppen', 'wp_drinks_gruppen'], true)) {
    wcr_require('edit_products');
} else {
    wcr_require('edit_tickets');
}

// CSRF-Prüfung (rotiert Token automatisch)
if (!wcr_verify_csrf_silent()) {
    http_response_code(403);
    echo json_encode([
        'ok' => false, 
        'error' => 'CSRF-Token ungültig',
        'csrf_token' => wcr_csrf_token()
    ]);
    exit;
}

// ── Business Logic (direkt hier statt HTTP-Roundtrip) ──
$allowed = ['cable', 'drinks', 'food', 'ice', 'camping', 'extra', 'wp_food_gruppen', 'wp_drinks_gruppen'];
$nr      = $_POST['nummer'] ?? '';
$mode    = trim($_POST['mode']   ?? '');
$val     = $_POST['value']  ?? '';

if (!in_array($table, $allowed, true)) {
    http_response_code(400);
    echo json_encode([
        'ok' => false, 
        'error' => 'Ungültige Tabelle',
        'csrf_token' => wcr_csrf_token()
    ]);
    exit;
}

// Preisänderungen nur für admin/cernal
if ($mode === 'price' && !wcr_can('edit_prices')) {
    http_response_code(403);
    echo json_encode([
        'ok' => false, 
        'error' => 'Keine Berechtigung: Preise ändern erfordert admin oder cernal',
        'csrf_token' => wcr_csrf_token()
    ]);
    exit;
}

try {
    if ($mode === 'price') {
        $stmt = $pdo->prepare("UPDATE `{$table}` SET preis = ? WHERE nummer = ?");
        $stmt->execute([(float)$val, (int)$nr]);

    } elseif ($mode === 'toggle') {
        $stock = ($val === '1' || $val === 'true') ? 1 : 0;
        $stmt  = $pdo->prepare("UPDATE `{$table}` SET stock = ? WHERE nummer = ?");
        $stmt->execute([$stock, (int)$nr]);

    } elseif ($mode === 'gruppe') {
        $typ   = trim($nr);
        $aktiv = ($val === '1') ? 1 : 0;
        
        if (!in_array($table, ['wp_food_gruppen', 'wp_drinks_gruppen'], true)) {
            http_response_code(400);
            echo json_encode([
                'ok' => false, 
                'error' => 'Ungültige Gruppen-Tabelle',
                'csrf_token' => wcr_csrf_token()
            ]);
            exit;
        }
        
        $stmt = $pdo->prepare("UPDATE `{$table}` SET aktiv = ? WHERE typ = ?");
        $stmt->execute([$aktiv, $typ]);

    } else {
        http_response_code(400);
        echo json_encode([
            'ok' => false, 
            'error' => 'Unbekannter Modus',
            'csrf_token' => wcr_csrf_token()
        ]);
        exit;
    }

    // Nach erfolgreichem Update → WP-Transient-Cache leeren
    if ($mode === 'price' || $mode === 'toggle' || $mode === 'gruppe') {
        $TOKEN = '5f581e2655f5b36d05a8ad3db821e5da4d0a0ea4dfe66314dcab1dd86bb64ed3';
        $flush = curl_init('https://www.wcr-webpage.de/wp-json/wcr/v1/flush-cache');
        curl_setopt_array($flush, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query(['table' => $table, 'nummer' => $nr]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_HTTPHEADER     => ['X-WCR-Token: ' . $TOKEN],
        ]);
        curl_exec($flush);
        curl_close($flush);
    }

    // Erfolg + neues CSRF-Token zurückgeben
    echo json_encode([
        'ok' => true,
        'csrf_token' => wcr_csrf_token()
    ]);

} catch (Exception $e) {
    wcr_log_error('update_ticket', $e, [
        'table' => $table,
        'nummer' => $nr,
        'mode' => $mode
    ]);
    
    http_response_code(500);
    echo json_encode([
        'ok' => false, 
        'error' => 'DB-Fehler',
        'csrf_token' => wcr_csrf_token()
    ]);
}
