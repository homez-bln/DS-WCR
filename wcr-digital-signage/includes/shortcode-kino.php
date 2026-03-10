<?php
if (!defined('ABSPATH')) exit;

/* ====================================================
   WCR Kino Shortcode
   Horizontal endlos-scrollender Film-Slider
   ==================================================== */

function wcr_kino_slider_shortcode($atts) {
    $atts = shortcode_atts([
        'mode' => 'landscape', // landscape | portrait
    ], $atts);

    $mode = in_array($atts['mode'], ['portrait', 'landscape']) ? $atts['mode'] : 'landscape';
    $api_url = rest_url('wakecamp/v1/kino');

    // Unique ID für mehrere Slider auf einer Seite
    static $instance = 0;
    $instance++;
    $wrap_id = 'wcr-kino-slider-' . $instance;

    ob_start();
    ?>
    <div id="<?php echo esc_attr($wrap_id); ?>" class="wcr-kino-slider-wrap <?php echo esc_attr($mode); ?>" data-api="<?php echo esc_url($api_url); ?>">
        <div class="wcr-kino-track">
            <!-- Filme werden per JS eingefügt -->
        </div>
        <div class="wcr-kino-placeholder">
            <p>🎬 Filme werden geladen...</p>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('wcr_kino_slider', 'wcr_kino_slider_shortcode');

// CSS + JS enqueuen
add_action('wp_enqueue_scripts', function() {
    if (!is_admin()) {
        wp_enqueue_style(
            'wcr-kino-slider',
            plugin_dir_url(__DIR__) . 'assets/css/wcr-kino-slider.css',
            [],
            filemtime(plugin_dir_path(__DIR__) . 'assets/css/wcr-kino-slider.css')
        );
        wp_enqueue_script(
            'wcr-kino-slider',
            plugin_dir_url(__DIR__) . 'assets/js/wcr-kino-slider.js',
            [],
            filemtime(plugin_dir_path(__DIR__) . 'assets/js/wcr-kino-slider.js'),
            true
        );
    }
});
