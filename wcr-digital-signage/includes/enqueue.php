<?php
if (!defined('ABSPATH')) exit;

/* ====================================================
   WCR Digital Signage — Asset-Einbindung v3
   
   Portrait  (1080×1920):  /oeffnungszeiten-story/
   Landscape (1920×1080):  alle anderen DS-Seiten
   
   Themes:  glass | flat | aurora
            gespeichert in wp_options: wcr_ds_theme
   ==================================================== */

const WCR_DS_PAGE_SLUGS = [
    'starter-pack', 'wetter', 'windmap', 'tickets',
    'oeffnungszeiten-story', 'getraenke', 'soft',
    'essen', 'kaffee', 'obst', 'kino', 'sup', 'merch',
    'eis', 'cable-list', 'camping-list',
];
const WCR_DS_PORTRAIT_SLUGS = ['oeffnungszeiten-story'];
const WCR_DS_THEMES         = ['glass', 'flat', 'aurora'];

function wcr_ds_get_current_slug(): string {
    global $post;
    if (!$post) return '';
    return (string)($post->post_name ?? '');
}
function wcr_ds_is_ds_page(): bool {
    return in_array(wcr_ds_get_current_slug(), WCR_DS_PAGE_SLUGS, true);
}
function wcr_ds_is_portrait(): bool {
    return in_array(wcr_ds_get_current_slug(), WCR_DS_PORTRAIT_SLUGS, true);
}
function wcr_ds_get_theme(): string {
    $t = get_option('wcr_ds_theme', 'glass');
    return in_array($t, WCR_DS_THEMES, true) ? $t : 'glass';
}

/* ── Fonts ─────────────────────────────────────────────────── */
if (!function_exists('wcr_enqueue_ds_fonts')) {
    function wcr_enqueue_ds_fonts() {
        if (!wcr_ds_is_ds_page()) return;
        if (wcr_ds_is_portrait()) {
            wp_enqueue_style('wcr-font-caveat',
                'https://fonts.googleapis.com/css2?family=Caveat:wght@400;700&display=swap',
                [], null);
        }
        // Aurora lädt Inter über @import im Theme-CSS selbst
    }
    add_action('wp_enqueue_scripts', 'wcr_enqueue_ds_fonts', 5);
}

/* ── Stylesheets ───────────────────────────────────────────── */
if (!function_exists('wcr_enqueue_ds_styles')) {
    function wcr_enqueue_ds_styles() {
        if (!wcr_ds_is_ds_page()) return;

        $base = wcr_ds_is_portrait() ? 'wcr-ds-portrait' : 'wcr-ds-landscape';

        // 1. Basis-CSS (Layout + Struktur)
        wp_enqueue_style('wcr-ds-base',
            plugin_dir_url(dirname(__FILE__)) . 'assets/css/' . $base . '.css',
            [], '3.0.0');

        // 2. Theme-CSS (visuelle Overrides)
        $theme = wcr_ds_get_theme();
        wp_enqueue_style('wcr-ds-theme',
            plugin_dir_url(dirname(__FILE__)) . 'assets/css/themes/wcr-ds-theme-' . $theme . '.css',
            ['wcr-ds-base'], '3.0.0');

        // 3. Utils JS — mit Cache-Buster
        wp_enqueue_script('wcr-ds-utils',
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/wcr-ds-utils.js',
            [], '3.0.1', true);
    }
    add_action('wp_enqueue_scripts', 'wcr_enqueue_ds_styles', 10);
}

/* ── FOUC-Schutz ───────────────────────────────────────────── */
if (!function_exists('wcr_block_render_until_loaded')) {
    function wcr_block_render_until_loaded() {
        if (!wcr_ds_is_ds_page()) return;
        ?>
        <style>html{visibility:hidden;opacity:0}html.loaded{visibility:visible;opacity:1;transition:opacity .25s ease}</style>
        <script>window.addEventListener('load',function(){document.documentElement.classList.add('loaded')})</script>
        <?php
    }
    add_action('wp_head', 'wcr_block_render_until_loaded', 1);
}

/* ── Admin-Bar ausblenden ──────────────────────────────────── */
if (!function_exists('wcr_ds_disable_admin_bar')) {
    function wcr_ds_disable_admin_bar() {
        if (wcr_ds_is_ds_page()) add_filter('show_admin_bar', '__return_false');
    }
    add_action('after_setup_theme', 'wcr_ds_disable_admin_bar');
}
