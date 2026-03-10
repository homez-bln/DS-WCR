<?php
/**
 * inc/design-tokens.php
 * Design-Token-Bridge: Backend ← ds-settings.php
 * 
 * ZIEL:
 * Backend-CSS-Variablen aus ds-settings.php laden,
 * um CI-relevante Werte (Farben, Font) zentral steuerbar zu machen.
 * 
 * PRINZIP:
 * - Frontend-Tokens (clr_green, clr_blue, font_family) werden gemappt
 * - Backend erhält neue Brand-Tokens (--brand-primary, --brand-success, --font-family)
 * - Bestehende Variablen bleiben als Alias erhalten (--primary, --success, --font)
 * - Keine visuellen Änderungen, nur interne Struktur
 * 
 * PHASE 1: Foundation (nur 3 zentrale CI-Werte)
 * Später: Schrittweise Migration auf Brand-Tokens in CSS
 */

// ── API-Zugriff (nutzt bestehende ds-settings.php Infrastruktur) ──────────

if (!defined('DSC_WP_API_BASE')) {
    define('DSC_WP_API_BASE', 'https://wcr-webpage.de/wp-json/wakecamp/v1');
}
if (!defined('DSC_WP_SECRET')) {
    define('DSC_WP_SECRET', 'WCR_DS_2026');
}

if (!function_exists('dt_curl')) {
    function dt_curl(string $url): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 3, // Kurzer Timeout für schnellen Fallback
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        return [
            'ok'   => ($code === 200 && !$err),
            'json' => json_decode($body ?: '', true),
        ];
    }
}

// ── Token-Loader mit sicheren Fallbacks ───────────────────────────────────

// Static Defaults (Apple Design System — identisch mit style.css)
$_dt_defaults = [
    'primary' => '#0071e3', // iOS Blau
    'success' => '#34c759', // iOS Grün
    'font'    => 'Segoe UI',
];

// GET-Request zu /ds-settings mit wcr_secret absichern (konsistent mit dsc_api_load und ig_get)
$_dt_result = dt_curl(DSC_WP_API_BASE . '/ds-settings?wcr_secret=' . urlencode(DSC_WP_SECRET));

if ($_dt_result['ok'] && isset($_dt_result['json']['options'])) {
    $wpOpts = $_dt_result['json']['options'];
    
    // Token-Mapping: Frontend → Backend
    $_dt_primary = $wpOpts['clr_blue']    ?? $_dt_defaults['primary'];
    $_dt_success = $wpOpts['clr_green']   ?? $_dt_defaults['success'];
    $_dt_font    = $wpOpts['font_family'] ?? $_dt_defaults['font'];
    
    // Validierung: Farben müssen hex oder rgba sein
    if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $_dt_primary) && 
        !preg_match('/^rgba?\([\d,.\s]+\)$/', $_dt_primary)) {
        $_dt_primary = $_dt_defaults['primary'];
    }
    if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $_dt_success) && 
        !preg_match('/^rgba?\([\d,.\s]+\)$/', $_dt_success)) {
        $_dt_success = $_dt_defaults['success'];
    }
    
    // Font: Nur sichere Zeichen
    $_dt_font = preg_replace('/[^a-zA-Z0-9\s-]/', '', $_dt_font);
    if (empty($_dt_font)) {
        $_dt_font = $_dt_defaults['font'];
    }
} else {
    // Fallback: API nicht erreichbar oder Secret falsch → Statische Defaults
    $_dt_primary = $_dt_defaults['primary'];
    $_dt_success = $_dt_defaults['success'];
    $_dt_font    = $_dt_defaults['font'];
}

// Sichere Ausgabe für CSS (XSS-Schutz)
$_dt_primary = htmlspecialchars($_dt_primary, ENT_QUOTES, 'UTF-8');
$_dt_success = htmlspecialchars($_dt_success, ENT_QUOTES, 'UTF-8');
$_dt_font    = htmlspecialchars($_dt_font, ENT_QUOTES, 'UTF-8');

?>
<!-- Design-Token-Bridge: Backend ← ds-settings.php -->
<style>
:root {
    /* ═══════════════════════════════════════════════════════════════
     * DESIGN-TOKEN-BRIDGE (Phase 1)
     * 
     * Diese Tokens werden aus ds-settings.php geladen und
     * überschreiben die statischen Werte in style.css
     * ═══════════════════════════════════════════════════════════════ */
    
    /* Brand Tokens (dynamisch aus ds-settings.php) */
    --brand-primary:  <?= $_dt_primary ?>; /* ← clr_blue aus Frontend */
    --brand-success:  <?= $_dt_success ?>; /* ← clr_green aus Frontend */
    --font-family:    '<?= $_dt_font ?>', -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif;
    
    /* Alias-Variablen (Kompatibilität mit bestehendem CSS) */
    --primary:        var(--brand-primary);
    --success:        var(--brand-success);
    --font:           var(--font-family);
    
    /* ═══════════════════════════════════════════════════════════════
     * HINWEIS:
     * Alle anderen Variablen bleiben in style.css statisch.
     * Schrittweise Migration auf Brand-Tokens folgt in späteren Phasen.
     * ═══════════════════════════════════════════════════════════════ */
}
</style>
