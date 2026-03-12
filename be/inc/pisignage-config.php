<?php
/**
 * inc/pisignage-config.php — piSignage Konfiguration
 * Speicherung in wp_options via REST API (identisch zu permissions-System)
 * Option-Key: wcr_pisignage_config
 */

// API-Konstanten sicherstellen (falls nicht via auth.php geladen)
if (!defined('DSC_WP_API_BASE')) {
    define('DSC_WP_API_BASE', getenv('WCR_WP_API_BASE') ?: 'https://wcr-webpage.de/wp-json/wakecamp/v1');
}
if (!defined('DSC_WP_SECRET')) {
    define('DSC_WP_SECRET', getenv('WCR_WP_SECRET') ?: 'WCR_DS_2026');
}

/**
 * Standard-Konfiguration
 */
function wcr_pisignage_default_config(): array {
    return [
        'base_url'     => '',
        'api_token'    => '',
        'email'        => '',
        'password'     => '',
        'last_updated' => null,
    ];
}

/**
 * Konfiguration aus wp_options laden (via REST API)
 */
function wcr_pisignage_load_config(): array {
    // In-Memory-Cache für diesen Request
    if (array_key_exists('wcr_pisignage_config_cache', $GLOBALS) && $GLOBALS['wcr_pisignage_config_cache'] !== null) {
        return $GLOBALS['wcr_pisignage_config_cache'];
    }

    $ch = curl_init(DSC_WP_API_BASE . '/options/wcr_pisignage_config?wcr_secret=' . urlencode(DSC_WP_SECRET));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $default = wcr_pisignage_default_config();

    if ($code === 200 && $body) {
        $json = json_decode($body, true);
        if (isset($json['value']) && is_array($json['value'])) {
            $config = array_merge($default, $json['value']);
            $GLOBALS['wcr_pisignage_config_cache'] = $config;
            return $config;
        }
    }

    $GLOBALS['wcr_pisignage_config_cache'] = $default;
    return $default;
}

/**
 * Konfiguration in wp_options speichern (via REST API)
 * NUR für cernal zugänglich (Caller-Verantwortung)
 */
function wcr_pisignage_save_config(array $config): bool {
    $config['last_updated'] = date('c');

    $ch = curl_init(DSC_WP_API_BASE . '/options');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode([
            'wcr_secret' => DSC_WP_SECRET,
            'key'        => 'wcr_pisignage_config',
            'value'      => $config,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $success = ($code === 200);

    // Cache invalidieren
    if ($success) {
        unset($GLOBALS['wcr_pisignage_config_cache']);
    }

    return $success;
}

/**
 * Token maskieren für sichere Anzeige
 */
function wcr_pisignage_mask_token(string $token): string {
    $len = strlen($token);
    if ($len <= 8) return str_repeat('*', $len);
    return substr($token, 0, 4) . str_repeat('*', max(4, $len - 8)) . substr($token, -4);
}
