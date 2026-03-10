<?php
if (!defined('ABSPATH')) exit;

/**
 * Singleton-Datenbankverbindung.
 * Erstellt die wpdb-Instanz nur EINMAL pro Request – egal wie oft die Funktion aufgerufen wird.
 *
 * SICHERHEITSHINWEIS: Credentials besser in wp-config.php auslagern:
 *   define('WCR_DB_HOST', 'db5002164484.hosting-data.io');
 *   define('WCR_DB_NAME', 'dbs1751670');
 *   define('WCR_DB_USER', 'dbu1070971');
 *   define('WCR_DB_PASS', 'IhrPasswort');
 */
if (!function_exists('get_ionos_db_connection')) {
    function get_ionos_db_connection() {
        static $instance = null;

        if ($instance !== null) {
            return $instance; // Bereits verbunden – sofort zurückgeben
        }

        $db_host = defined('WCR_DB_HOST') ? WCR_DB_HOST : 'db5002164484.hosting-data.io';
        $db_name = defined('WCR_DB_NAME') ? WCR_DB_NAME : 'dbs1751670';
        $db_user = defined('WCR_DB_USER') ? WCR_DB_USER : 'dbu1070971';
        $db_pass = defined('WCR_DB_PASS') ? WCR_DB_PASS : 'Wakeboard2021!';

        $db = new wpdb($db_user, $db_pass, $db_name, $db_host);

        if (!empty($db->error)) {
            $instance = false;
            return false;
        }

        $instance = $db;
        return $instance;
    }
}
