<?php
/**
 * be/api/create.php — Generische Write-API für alle Produkt-Tabellen
 * POST → Neuen Artikel anlegen (create_products Permission)
 *
 * Aufruf: POST /be/api/create.php?t=food|ice|cable|camping|extra
 *
 * Security: Session + CSRF + Permission-Check + Whitelist
 * KEINE const — nur $array (WordPress-safe, kein Fatal bei Mehrfach-Include)
 */
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/db.php';

header('Content-Type: application/json; charset=utf-8');

// ── Whitelist ($array statt const → kein PHP-Fatal bei Mehrfach-Include) ──
$ALLOWED_TABLES = ['food', 'ice', 'cable', 'camping', 'extra'];

$t = trim($_GET['t'] ?? '');
if (!in_array($t, $ALLOWED_TABLES, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Ungültige Tabelle. Erlaubt: ' . implode(', ', $ALLOWED_TABLES)]);
    exit;
}

// ── Login erforderlich ──
require_login();

$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$csrf   = $body['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

// ── CSRF-Check ──
if (!wcr_verify_csrf_silent()) {
    $sentToken  = $csrf;
    $validToken = $_SESSION['wcr_csrf_token'] ?? '';
    if ($sentToken === '' || $validToken === '' || !hash_equals($validToken, $sentToken)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'CSRF invalid']);
        exit;
    }
}

// ── POST: Neuen Artikel anlegen ──
if ($method === 'POST') {

    if (!wcr_can('create_products')) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Keine Berechtigung']);
        exit;
    }

    $produkt = trim($body['produkt'] ?? '');
    $menge   = trim($body['menge']   ?? '');
    $preis   = round((float)($body['preis'] ?? 0), 2);
    $typ     = trim($body['typ']     ?? 'Sonstige');
    $stock   = (int)(bool)($body['stock'] ?? 1);

    if ($produkt === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Produktname darf nicht leer sein']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO `{$t}` (produkt, menge, preis, typ, stock)
            VALUES (:produkt, :menge, :preis, :typ, :stock)
        ");
        $stmt->execute([
            ':produkt' => $produkt,
            ':menge'   => $menge,
            ':preis'   => $preis,
            ':typ'     => $typ !== '' ? $typ : 'Sonstige',
            ':stock'   => $stock,
        ]);
        $newId = (int)$pdo->lastInsertId();
        echo json_encode(['ok' => true, 'id' => $newId, 'message' => 'Artikel angelegt']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Datenbankfehler: ' . $e->getMessage()]);
    }
    exit;
}

// ── Unsupported Method ──
http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
