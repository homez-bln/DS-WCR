<?php
if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function () {
    register_rest_route('wakecamp/v1', '/screenshot', [
        'methods'             => 'POST',
        'permission_callback' => '__return_true',
        'callback'            => function (WP_REST_Request $req) {

            if (($req->get_param('wcr_secret') ?? '') !== 'WCR_DS_2026') {
                return new WP_Error('forbidden', 'Nicht autorisiert', ['status' => 403]);
            }

            $dataUrl = $req->get_param('image') ?? '';
            if (!preg_match('#^data:image/jpeg;base64,#', $dataUrl)) {
                return new WP_Error('invalid', 'Kein gültiges JPEG', ['status' => 400]);
            }

            $base64 = preg_replace('#^data:image/jpeg;base64,#', '', $dataUrl);
            $binary = base64_decode($base64);
            if (!$binary) {
                return new WP_Error('decode', 'Base64-Fehler', ['status' => 400]);
            }

            $upload = wp_upload_dir();
            $dir    = trailingslashit($upload['basedir']) . 'opening-hours';
            wp_mkdir_p($dir);

            $filename = 'oeffnungszeiten_' . date('Y-m-d') . '.jpg';
            $path     = $dir . '/' . $filename;
            file_put_contents($path, $binary);

            // Auch als "latest" speichern (für den Shortcode)
            file_put_contents($dir . '/latest.jpg', $binary);

            return rest_ensure_response([
                'ok'  => true,
                'url' => trailingslashit($upload['baseurl']) . 'opening-hours/' . $filename,
            ]);
        },
    ]);
});
