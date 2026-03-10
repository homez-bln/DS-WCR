<?php
/**
 * Plugin Name: WCR Digital Signage
 * Description: Digital Signage System für Wake & Camp Ruhlsdorf
 * Version:     2.0.1
 * Author:      WCR
 */

if (!defined('ABSPATH')) exit;

/* ════════════════════════════════════════════════════════════════════════════
   PLUGIN SETUP
══════════════════════════════════════════════════════════════════════════════ */

define( 'WCR_DS_VERSION', '2.0.1' );
define( 'WCR_DS_URL',     plugin_dir_url( __FILE__ ) );
define( 'WCR_DS_PATH',    plugin_dir_path( __FILE__ ) );

/* ═══════════════════════════════════════════════════════════════════════════════
   INCLUDES
   Reihenfolge: Core → Shortcodes → Spezial
══════════════════════════════════════════════════════════════════════════════ */

// Core-Funktionalität
require_once WCR_DS_PATH . 'includes/db.php';
require_once WCR_DS_PATH . 'includes/instagram.php';
require_once WCR_DS_PATH . 'includes/enqueue.php';
require_once WCR_DS_PATH . 'includes/rest-api.php';
require_once WCR_DS_PATH . 'includes/screenshot.php';

// Shortcode-Funktionen (thematisch gruppiert)
require_once WCR_DS_PATH . 'includes/shortcodes.php';         // Theme/Utility-Shortcodes
require_once WCR_DS_PATH . 'includes/shortcodes-content.php'; // Menü & Preislisten
require_once WCR_DS_PATH . 'includes/shortcodes-display.php'; // Live-Daten & Wetter
require_once WCR_DS_PATH . 'includes/shortcodes-widgets.php'; // Animationen & Widgets

// Spezial-Shortcodes (separate Dateien)
require_once WCR_DS_PATH . 'includes/shortcode-produkte.php'; // 3-Produkt Spotlight
require_once WCR_DS_PATH . 'includes/shortcode-kino.php';     // 🎬 Kino Shortcode

/* ═══════════════════════════════════════════════════════════════════════════════
   PLUGIN ACTIVATION: DB-TABELLEN ERSTELLEN
══════════════════════════════════════════════════════════════════════════════ */

register_activation_hook( __FILE__, 'wcr_ds_create_tables' );
function wcr_ds_create_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();

    // 🎬 Kino-Tabelle
    $table_kino = $wpdb->prefix . 'wcr_kino';
    $sql_kino = "CREATE TABLE IF NOT EXISTS $table_kino (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL,
        cover_url TEXT NOT NULL,
        date DATE NOT NULL,
        sort_order INT DEFAULT 0,
        PRIMARY KEY (id),
        INDEX idx_date (date)
    ) $charset;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql_kino );
}

/* ═══════════════════════════════════════════════════════════════════════════════
   HELPER FUNCTIONS
══════════════════════════════════════════════════════════════════════════════ */

/**
 * wcr_ds_defaults() - Standard-Werte für Design-Optionen
 * @return array Standardwerte für Farben, Fonts, Viewport
 */
function wcr_ds_defaults() {
    return array(
        'clr_green'    => '#679467',
        'clr_blue'     => '#019ee3',
        'clr_white'    => '#ffffff',
        'clr_text'     => '#eeeeee',
        'clr_muted'    => '#7a8a8a',
        'clr_bg'       => '#080808',
        'clr_bg_dark'  => '#0d0d0d',
        'clr_bg_glass' => 'rgba(10,14,24,0.65)',
        'font_family'  => 'Segoe UI',
        'viewport_w'   => '1920',
        'viewport_h'   => '1080',
    );
}

/**
 * wcr_ds_get() - Holt eine Design-Option mit Fallback auf Default
 * @param string $key Option-Key
 * @return string Option-Wert oder Default
 */
function wcr_ds_get( $key ) {
    $raw  = get_option( 'wcr_ds_options', array() );
    $opts = is_array( $raw ) ? $raw : array();
    $defs = wcr_ds_defaults();
    if ( isset( $opts[ $key ] ) && '' !== $opts[ $key ] ) return $opts[ $key ];
    return isset( $defs[ $key ] ) ? $defs[ $key ] : '';
}

/**
 * wcr_ds_hex_to_rgb() - Konvertiert Hex-Farbe zu RGB-String
 * @param string $hex Hex-Farbe (mit oder ohne #)
 * @return string RGB-Werte als "R,G,B" String
 */
if ( ! function_exists( 'wcr_ds_hex_to_rgb' ) ) {
    function wcr_ds_hex_to_rgb( string $hex ): string {
        $hex = ltrim( $hex, '#' );
        if ( strlen($hex) === 3 ) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        if ( strlen($hex) !== 6 ) return '128,128,128';
        return hexdec(substr($hex,0,2)).','.hexdec(substr($hex,2,2)).','.hexdec(substr($hex,4,2));
    }
}

/* ═══════════════════════════════════════════════════════════════════════════════
   DYNAMIC CSS
   Generiert CSS-Variablen aus Plugin-Optionen im <head>
══════════════════════════════════════════════════════════════════════════════ */

add_action( 'wp_head', 'wcr_ds_dynamic_css', 99 );
function wcr_ds_dynamic_css() {
    $g   = wcr_ds_get( 'clr_green' );
    $b   = wcr_ds_get( 'clr_blue' );
    $w   = wcr_ds_get( 'clr_white' );
    $t   = wcr_ds_get( 'clr_text' );
    $m   = wcr_ds_get( 'clr_muted' );
    $bg  = wcr_ds_get( 'clr_bg' );
    $bgd = wcr_ds_get( 'clr_bg_dark' );
    $bgg = wcr_ds_get( 'clr_bg_glass' );
    $ff  = wcr_ds_get( 'font_family' );
    $vw  = wcr_ds_get( 'viewport_w' );
    $vh  = wcr_ds_get( 'viewport_h' );

    $theme = get_option( 'wcr_ds_theme', 'glass' );
    if ( ! in_array( $theme, array( 'glass', 'flat', 'aurora' ), true ) ) $theme = 'glass';

    // Google Fonts laden (nur bei Nicht-System-Fonts)
    $sys = array( 'Segoe UI', 'Arial', 'Helvetica', 'Georgia', 'Verdana', 'Tahoma' );
    if ( ! in_array( $ff, $sys, true ) ) {
        echo '<link rel="stylesheet" href="' . esc_url( 'https://fonts.googleapis.com/css2?family=' . rawurlencode( $ff ) . ':wght@400;600;700;800;900&display=swap' ) . ">\n";
    }

    $green_rgb = wcr_ds_hex_to_rgb( $g );
    $blue_rgb  = wcr_ds_hex_to_rgb( $b );

    echo '<style id="wcr-ds-css">' . "\n";
    echo ':root {' . "\n";
    echo '  --clr-green:         ' . esc_attr($g)   . ";\n";
    echo '  --clr-green-dim:     rgba(' . $green_rgb . ',0.70)' . ";\n";
    echo '  --clr-green-glow:    rgba(' . $green_rgb . ',0.08)' . ";\n";
    echo '  --clr-blue:          ' . esc_attr($b)   . ";\n";
    echo '  --clr-blue-dim:      rgba(' . $blue_rgb  . ',0.18)' . ";\n";
    echo '  --clr-white:         ' . esc_attr($w)   . ";\n";
    echo '  --clr-text:          ' . esc_attr($t)   . ";\n";
    echo '  --clr-text-muted:    ' . esc_attr($m)   . ";\n";
    echo '  --clr-bg:            ' . esc_attr($bg)  . ";\n";
    echo '  --clr-bg-dark:       ' . esc_attr($bgd) . ";\n";
    echo '  --clr-border:        rgba(255,255,255,0.09)' . ";\n";
    echo '  --font-main:         \'' . esc_attr($ff) . '\', system-ui, sans-serif' . ";\n";
    echo '  --viewport-w:        ' . esc_attr($vw)  . "px;\n";
    echo '  --viewport-h:        ' . esc_attr($vh)  . "px;\n";
    echo '}' . "\n";
    echo 'html,body{width:var(--viewport-w);height:var(--viewport-h);font-family:var(--font-main);background:var(--clr-bg);color:var(--clr-text);}' . "\n";
    echo '.ds-dot      {background:var(--clr-green);box-shadow:0 0 10px var(--clr-green);}' . "\n";
    echo '.ds-live-dot {background:var(--clr-blue);box-shadow:0 0 10px var(--clr-blue);}' . "\n";
    
    // Theme-spezifisches CSS
    if ( $theme === 'glass' ) {
        echo '.glass{background:' . esc_attr($bgg) . ';backdrop-filter:blur(20px) saturate(160%);-webkit-backdrop-filter:blur(20px) saturate(160%);border-radius:18px;}' . "\n";
        echo '.k-card,.ds-card{backdrop-filter:blur(20px) saturate(160%);-webkit-backdrop-filter:blur(20px) saturate(160%);}' . "\n";
    } elseif ( $theme === 'flat' ) {
        echo '.glass,.k-card,.ds-card{background:' . esc_attr($bgd) . ';backdrop-filter:none;}' . "\n";
        echo 'body::before{display:none;}' . "\n";
    } elseif ( $theme === 'aurora' ) {
        echo '.glass,.k-card,.ds-card{background:rgba(' . $blue_rgb . ',0.06);backdrop-filter:none;border:1px solid rgba(' . $blue_rgb . ',0.18);}' . "\n";
    }
    echo '</style>' . "\n";
}

/* ═══════════════════════════════════════════════════════════════════════════════
   ASSET ENQUEUE
   Lädt globale CSS/JS Assets
══════════════════════════════════════════════════════════════════════════════ */

add_action( 'wp_enqueue_scripts', 'wcr_ds_enqueue' );
function wcr_ds_enqueue() {
    wp_enqueue_style(  'wcr-ds-global',     WCR_DS_URL . 'assets/css/wcr-ds-global.css',     array(), WCR_DS_VERSION );
    wp_enqueue_style(  'wcr-ds-components', WCR_DS_URL . 'assets/css/wcr-ds-components.css', array('wcr-ds-global'), WCR_DS_VERSION );
    wp_enqueue_script( 'wcr-ds-utils',      WCR_DS_URL . 'assets/js/wcr-ds-utils.js',        array(), WCR_DS_VERSION, true );
}

/**
 * wcr_ds_load_gsap() - Lädt GSAP-Bibliothek bei Bedarf
 * Wird von Shortcodes aufgerufen, die GSAP benötigen
 */
function wcr_ds_load_gsap() {
    if ( ! wp_script_is( 'gsap', 'enqueued' ) )
        wp_enqueue_script( 'gsap', 'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js', array(), '3.12.5', true );
}

/**
 * wcr_ds_load_leaflet() - Lädt Leaflet-Bibliothek bei Bedarf
 * Wird von Shortcodes aufgerufen, die Leaflet-Karten benötigen
 */
function wcr_ds_load_leaflet() {
    if ( ! wp_style_is( 'leaflet-css', 'enqueued' ) ) {
        wp_enqueue_style(  'leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4' );
        wp_enqueue_script( 'leaflet-js',  'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',  array(), '1.9.4', true );
    }
}

/* ═══════════════════════════════════════════════════════════════════════════════
   SHORTCODE REGISTRATIONS
   Funktionen sind in includes/shortcodes-*.php definiert
══════════════════════════════════════════════════════════════════════════════ */

// Content-Shortcodes (Menü & Preislisten)
add_shortcode( 'wcr_getraenke',    'wcr_sc_getraenke' );    // → shortcodes-content.php
add_shortcode( 'wcr_softdrinks',   'wcr_sc_softdrinks' );   // → shortcodes-content.php
add_shortcode( 'wcr_essen',        'wcr_sc_essen' );        // → shortcodes-content.php
add_shortcode( 'wcr_kaffee',       'wcr_sc_kaffee' );       // → shortcodes-content.php
add_shortcode( 'wcr_eis',          'wcr_sc_eis' );          // → shortcodes-content.php
add_shortcode( 'wcr_cable',        'wcr_sc_cable' );        // → shortcodes-content.php
add_shortcode( 'wcr_camping',      'wcr_sc_camping' );      // → shortcodes-content.php

// Display-Shortcodes (Live-Daten & Wetter)
add_shortcode( 'wcr_windmap',      'wcr_sc_windmap' );      // → shortcodes-display.php
add_shortcode( 'wcr_wetter',       'wcr_sc_wetter' );       // → shortcodes-display.php

// Widget-Shortcodes (Animationen & Widgets)
add_shortcode( 'wcr_starter_pack', 'wcr_sc_starter_pack' ); // → shortcodes-widgets.php

// Weitere Shortcodes werden in separaten Dateien registriert:
// - wcr_produkte → includes/shortcode-produkte.php
// - wcr_kino_slider → includes/shortcode-kino.php
// - Theme/Utility-Shortcodes → includes/shortcodes.php
