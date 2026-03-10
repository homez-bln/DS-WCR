<?php
declare(strict_types=1);
/**
 * inc/db.php — Singleton PDO
 *
 * FIX v6: Früher neue DB-Verbindung bei jedem ctrl/API-Aufruf → jetzt 1x pro Request.
 * TIPP: Credentials als Umgebungsvariable hinterlegen (SetEnv in .htaccess),
 *       damit das Passwort nicht im Klartext in PHP-Dateien steht.
 */
function wcr_pdo(): PDO {
    static $instance = null;
    if ($instance !== null) return $instance;

    $host = getenv('WCR_DB_HOST') ?: 'db5002164484.hosting-data.io';
    $name = getenv('WCR_DB_NAME') ?: 'dbs1751670';
    $user = getenv('WCR_DB_USER') ?: 'dbu1070971';
    $pass = getenv('WCR_DB_PASS') ?: 'Wakeboard2021!';

    try {
        $instance = new PDO(
            "mysql:host={$host};port=3306;dbname={$name};charset=utf8mb4",
            $user, $pass,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    } catch (PDOException $e) {
        http_response_code(500);
        exit(json_encode(['ok' => false, 'error' => 'DB nicht erreichbar']));
    }
    return $instance;
}

// Rückwärtskompatibel: $pdo bleibt verfügbar
$pdo = wcr_pdo();
