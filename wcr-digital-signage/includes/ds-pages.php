<?php
/**
 * includes/ds-pages.php
 * Zentrales DS-Seiten-Aktivierungssystem
 *
 * - Regel-Engine + is_ds_page_active()
 * - Storage: wp_options 'wcr_ds_pages'
 * - Transient-Cache (30s) pro Page+Rule
 * - Admin-Loader (lädt admin-ds-pages.php im WP-Admin)
 * - Fail-open bei DB-Fehler
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ── Konstanten ────────────────────────────────────────────
define( 'WCR_DS_TABLES_WHITELIST', [ 'food', 'drinks', 'cable', 'camping', 'extra', 'ice' ] );
define( 'WCR_DS_OPTION_KEY',       'wcr_ds_pages' );
define( 'WCR_DS_CACHE_TTL',        30 ); // Sekunden

// ── Admin-Loader ──────────────────────────────────────────
if ( is_admin() ) {
    $admin_ds = __DIR__ . '/admin-ds-pages.php';
    if ( file_exists( $admin_ds ) ) require_once $admin_ds;
}

// ─────────────────────────────────────────────────────────
// wcr_ds_get_rules() — alle gespeicherten Regeln holen
// ─────────────────────────────────────────────────────────
function wcr_ds_get_rules() {
    $raw = get_option( WCR_DS_OPTION_KEY, [] );
    return is_array( $raw ) ? $raw : [];
}

// ─────────────────────────────────────────────────────────
// wcr_ds_get_rule( $page_id ) — Regel für eine Seite
// ─────────────────────────────────────────────────────────
function wcr_ds_get_rule( $page_id ) {
    $rules = wcr_ds_get_rules();
    $key   = 'page_' . (int) $page_id;
    return isset( $rules[ $key ] ) ? $rules[ $key ] : null;
}

// ─────────────────────────────────────────────────────────
// wcr_ds_save_rule( $page_id, $rule ) — Regel speichern
// ─────────────────────────────────────────────────────────
function wcr_ds_save_rule( $page_id, array $rule ) {
    $rules = wcr_ds_get_rules();
    $key   = 'page_' . (int) $page_id;
    $rules[ $key ] = [
        'override' => in_array( $rule['override'] ?? 'auto', [ 'auto', 'force_on', 'force_off' ], true )
                        ? $rule['override']
                        : 'auto',
        'tables'   => array_values( array_intersect( (array) ( $rule['tables'] ?? [] ), WCR_DS_TABLES_WHITELIST ) ),
        'ids'      => sanitize_text_field( $rule['ids'] ?? '' ),
        'mode'     => in_array( $rule['mode'] ?? 'any', [ 'any', 'all' ], true ) ? $rule['mode'] : 'any',
    ];
    update_option( WCR_DS_OPTION_KEY, $rules, false );
    // Cache dieser Seite invalidieren
    delete_transient( 'wcr_ds_active_' . (int) $page_id );
}

// ─────────────────────────────────────────────────────────
// wcr_ds_eval_rule( $rule ) — Kern-Auswertung
// Rückgabe: [ 'active' => bool, 'reason' => string, 'db_ok' => bool ]
// ─────────────────────────────────────────────────────────
function wcr_ds_eval_rule( array $rule ) {

    $override = $rule['override'] ?? 'auto';
    $tables   = (array) ( $rule['tables'] ?? [] );
    $ids_raw  = trim( $rule['ids'] ?? '' );
    $mode     = ( ( $rule['mode'] ?? 'any' ) === 'all' ) ? 'all' : 'any';

    // Force-Overrides
    if ( $override === 'force_on'  ) return [ 'active' => true,  'reason' => 'force_on',  'db_ok' => true ];
    if ( $override === 'force_off' ) return [ 'active' => false, 'reason' => 'force_off', 'db_ok' => true ];

    // Keine Regel gesetzt → immer aktiv
    if ( empty( $tables ) && $ids_raw === '' ) {
        return [ 'active' => true, 'reason' => 'no_rule', 'db_ok' => true ];
    }

    // DB-Verbindung
    $db = function_exists( 'get_ionos_db_connection' ) ? get_ionos_db_connection() : null;
    if ( ! $db ) {
        return [ 'active' => true, 'reason' => 'db_error_fail_open', 'db_ok' => false ];
    }

    // Tabellen-Liste: falls leer → alle 6
    $check_tables = ! empty( $tables )
        ? array_values( array_intersect( $tables, WCR_DS_TABLES_WHITELIST ) )
        : WCR_DS_TABLES_WHITELIST;

    // ── IDs-Modus ───────────────────────────────────────
    if ( $ids_raw !== '' ) {
        $ids = array_filter( array_map( 'intval', explode( ',', $ids_raw ) ) );
        if ( empty( $ids ) ) {
            return [ 'active' => true, 'reason' => 'ids_empty_after_parse', 'db_ok' => true ];
        }

        $found_active = 0;
        $total        = count( $ids );

        foreach ( $ids as $id ) {
            $is_active = false;
            foreach ( $check_tables as $tbl ) {
                $row = $db->get_var( $db->prepare(
                    "SELECT stock FROM `{$tbl}` WHERE nummer = %d LIMIT 1",
                    $id
                ) );
                if ( $row !== null && (int) $row > 0 ) {
                    $is_active = true;
                    break;
                }
            }
            if ( $is_active ) $found_active++;
        }

        if ( $mode === 'all' ) {
            $active = ( $found_active === $total );
            $reason = 'ids_all:' . $found_active . '/' . $total;
        } else {
            $active = ( $found_active > 0 );
            $reason = 'ids_any:' . $found_active . '/' . $total;
        }
        return [ 'active' => $active, 'reason' => $reason, 'db_ok' => true ];
    }

    // ── Tabellen-Modus (keine IDs) ───────────────────────
    foreach ( $check_tables as $tbl ) {
        $count = (int) $db->get_var( "SELECT COUNT(*) FROM `{$tbl}` WHERE stock > 0" );
        if ( $count > 0 ) {
            return [ 'active' => true, 'reason' => 'table_any:' . $tbl . ':' . $count, 'db_ok' => true ];
        }
    }
    return [ 'active' => false, 'reason' => 'table_any:none_active', 'db_ok' => true ];
}

// ─────────────────────────────────────────────────────────
// is_ds_page_active( $page_id, $options ) — PUBLIC API
// ─────────────────────────────────────────────────────────
function is_ds_page_active( $page_id, array $options = [] ) {

    $page_id = (int) $page_id;
    if ( $page_id <= 0 ) return true; // keine gültige ID → fail-open

    $transient_key = 'wcr_ds_active_' . $page_id;
    $cached = get_transient( $transient_key );
    if ( $cached !== false ) return (bool) $cached;

    // Gespeicherte Regel laden (gewinnt immer)
    $rule = wcr_ds_get_rule( $page_id );

    // Fallback aus $options, falls Felder fehlen
    if ( ! $rule ) {
        $rule = [
            'override' => 'auto',
            'tables'   => isset( $options['table'] ) ? [ $options['table'] ] : [],
            'ids'      => $options['ids'] ?? '',
            'mode'     => $options['mode'] ?? 'any',
        ];
    } else {
        // Fehlende Felder aus $options ergänzen
        if ( empty( $rule['tables'] ) && ! empty( $options['table'] ) ) {
            $rule['tables'] = [ $options['table'] ];
        }
        if ( ( $rule['ids'] ?? '' ) === '' && ! empty( $options['ids'] ) ) {
            $rule['ids'] = $options['ids'];
        }
        if ( ( $rule['mode'] ?? '' ) === '' && ! empty( $options['mode'] ) ) {
            $rule['mode'] = $options['mode'];
        }
    }

    $result = wcr_ds_eval_rule( $rule );
    $active = (bool) $result['active'];

    set_transient( $transient_key, $active ? 1 : 0, WCR_DS_CACHE_TTL );
    return $active;
}
