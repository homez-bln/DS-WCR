<?php
if (!defined('ABSPATH')) exit;

/* ══════════════════════════════════════════════════════════════════════════════
   WCR Digital Signage – REST API
   
   SICHERHEITSKONZEPT:
   ✅ Öffentlich (permission_callback = __return_true):
      - Alle Content-Routen (drinks, food, kino, events, etc.)
      - Instagram-Daten (bereits gefiltert durch WCR_Instagram Klasse)
      - Obstacles + Map-Config GET (Frontend-Rendering)
      - Ping (harmlose Statistiken)
      - playlist-check (Read-Only Stock-Check für piSignage)
   
   🔒 Geschützt (permission_callback mit Auth-Check):
      - /ds-settings GET + POST → NUR WordPress-Admins
      - /options GET + POST → NUR mit Secret (Backend-Zugriff)
      - /obstacles/map-config POST → Secret ODER Admin ODER Nonce
   
   WICHTIG:
   - Sensible Konfigurationsdaten (Instagram-Token) sind NICHT öffentlich
   - Schreibende Routen haben Auth-Checks
   - Backend (be/) greift direkt auf WordPress-Options zu, nicht über REST
══════════════════════════════════════════════════════════════════════════════ */

define('WCR_DS_API_SECRET', 'WCR_DS_2026');

add_action('rest_api_init', function() {

    /* ════════════════════════════════════════════════════════════════════════════
       ÖFFENTLICHE READ-ONLY CONTENT-ROUTEN
       Zeigen nur aktive/vorrätige Items für Digital Signage Frontend
    ════════════════════════════════════════════════════════════════════════════ */

    // ── Alle Drinks ──
    register_rest_route('wakecamp/v1', '/drinks', [
        'methods'             => 'GET',
        'callback'            => function() {
            $db = get_ionos_db_connection();
            if (!$db) return new WP_Error('db_error', 'DB fehlgeschlagen', ['status' => 500]);
            return rest_ensure_response(
                $db->get_results("SELECT nummer, produkt, typ, menge, preis, stock, bild_url
                                  FROM drinks WHERE stock != 0 AND stock IS NOT NULL
                                  ORDER BY typ, produkt", ARRAY_A) ?: []
            );
        },
        'permission_callback' => '__return_true',
    ]);

    // ── Drinks nach Typ ──
    register_rest_route('wakecamp/v1', '/drinks/(?P<typ>[a-zA-Z0-9_-]+)', [
        'methods'             => 'GET',
        'callback'            => function($req) {
            $db  = get_ionos_db_connection();
            $typ = sanitize_text_field($req['typ']);
            if (!$db) return new WP_Error('db_error', 'DB fehlgeschlagen', ['status' => 500]);
            return rest_ensure_response(
                $db->get_results($db->prepare(
                    "SELECT nummer, produkt, typ, menge, preis, stock, bild_url
                     FROM drinks WHERE typ = %s AND stock != 0 AND stock IS NOT NULL
                     ORDER BY produkt", $typ
                ), ARRAY_A) ?: []
            );
        },
        'permission_callback' => '__return_true',
    ]);

    // ── Alle Food ──
    register_rest_route('wakecamp/v1', '/food', [
        'methods'             => 'GET',
        'callback'            => function() {
            $db = get_ionos_db_connection();
            if (!$db) return new WP_Error('db_error', 'DB fehlgeschlagen', ['status' => 500]);
            return rest_ensure_response(
                $db->get_results("SELECT nummer, produkt, typ, menge, preis, stock
                                  FROM food WHERE stock != 0 AND stock IS NOT NULL
                                  ORDER BY typ, produkt", ARRAY_A) ?: []
            );
        },
        'permission_callback' => '__return_true',
    ]);

    // ── Food-Gruppen Status ──
    register_rest_route('wakecamp/v1', '/food/gruppen', [
        'methods'             => 'GET',
        'callback'            => function() {
            $db = get_ionos_db_connection();
            if (!$db) return new WP_Error('db_error', 'DB fehlgeschlagen', ['status' => 500]);
            return rest_ensure_response(
                $db->get_results("SELECT typ, aktiv FROM wp_food_gruppen", ARRAY_A) ?: []
            );
        },
        'permission_callback' => '__return_true',
    ]);

    // ── Food nach Typ ──
    register_rest_route('wakecamp/v1', '/food/(?P<typ>[a-zA-Z0-9_-]+)', [
        'methods'             => 'GET',
        'callback'            => function($req) {
            $db  = get_ionos_db_connection();
            $typ = sanitize_text_field($req['typ']);
            if (!$db) return new WP_Error('db_error', 'DB fehlgeschlagen', ['status' => 500]);
            return rest_ensure_response(
                $db->get_results($db->prepare(
                    "SELECT nummer, produkt, typ, menge, preis, stock
                     FROM food WHERE typ = %s AND stock != 0 AND stock IS NOT NULL
                     ORDER BY produkt", $typ
                ), ARRAY_A) ?: []
            );
        },
        'permission_callback' => '__return_true',
    ]);

    // ── Alle Ice (Eiskarte) ──
    register_rest_route('wakecamp/v1', '/ice', [
        'methods'             => 'GET',
        'callback'            => function() {
            $db = get_ionos_db_connection();
            if (!$db) return new WP_Error('db_error', 'DB fehlgeschlagen', ['status' => 500]);
            return rest_ensure_response(
                $db->get_results("SELECT nummer, produkt, typ, menge, preis, stock
                                  FROM ice WHERE stock != 0 AND stock IS NOT NULL
                                  ORDER BY typ, produkt", ARRAY_A) ?: []
            );
        },
        'permission_callback' => '__return_true',
    ]);

    // ── Ice nach Typ ──
    register_rest_route('wakecamp/v1', '/ice/(?P<typ>[a-zA-Z0-9_-]+)', [
        'methods'             => 'GET',
        'callback'            => function($req) {
            $db  = get_ionos_db_connection();
            $typ = sanitize_text_field($req['typ']);
            if (!$db) return new WP_Error('db_error', 'DB fehlgeschlagen', ['status' => 500]);
            return rest_ensure_response(
                $db->get_results($db->prepare(
                    "SELECT nummer, produkt, typ, menge, preis, stock
                     FROM ice WHERE typ = %s AND stock != 0 AND stock IS NOT NULL
                     ORDER BY produkt", $typ
                ), ARRAY_A) ?: []
            );
        },
        'permission_callback' => '__return_true',
    ]);

    // ── Alle Cable (Cablepark-Preise) ──
    register_rest_route('wakecamp/v1', '/cable', [
        'methods'             => 'GET',
        'callback'            => function() {
            $db = get_ionos_db_connection();
            if (!$db) return new WP_Error('db_error', 'DB fehlgeschlagen', ['status' => 500]);
            return rest_ensure_response(
                $db->get_results("SELECT nummer, produkt, typ, menge, preis, stock
                                  FROM cable WHERE stock != 0 AND stock IS NOT NULL
                                  ORDER BY typ, produkt", ARRAY_A) ?: []
            );
        },
        'permission_callback' => '__return_true',
    ]);

    // ── Cable nach Typ ──
    register_rest_route('wakecamp/v1', '/cable/(?P<typ>[a-zA-Z0-9_-]+)', [
        'methods'             => 'GET',
        'callback'            => function($req) {
            $db  = get_ionos_db_connection();
            $typ = sanitize_text_field($req['typ']);
            if (!$db) return new WP_Error('db_error', 'DB fehlgeschlagen', ['status' => 500]);
            return rest_ensure_response(
                $db->get_results($db->prepare(
                    "SELECT nummer, produkt, typ, menge, preis, stock
                     FROM cable WHERE typ = %s AND stock != 0 AND stock IS NOT NULL
                     ORDER BY produkt", $typ
                ), ARRAY_A) ?: []
            );
        },
        'permission_callback' => '__return_true',
    ]);

    // ── Alle Camping ──
    register_rest_route('wakecamp/v1', '/camping', [
        'methods'             => 'GET',
        'callback'            => function() {
            $db = get_ionos_db_connection();
            if (!$db) return new WP_Error('db_error', 'DB fehlgeschlagen', ['status' => 500]);
            return rest_ensure_response(
                $db->get_results("SELECT nummer, produkt, typ, menge, preis, stock
                                  FROM camping WHERE stock != 0 AND stock IS NOT NULL
                                  ORDER BY typ, produkt", ARRAY_A) ?: []
            );
        },
        'permission_callback' => '__return_true',
    ]);

    // ── Camping nach Typ ──
    register_rest_route('wakecamp/v1', '/camping/(?P<typ>[a-zA-Z0-9_-]+)', [
        'methods'             => 'GET',
        'callback'            => function($req) {
            $db  = get_ionos_db_connection();
            $typ = sanitize_text_field($req['typ']);
            if (!$db) return new WP_Error('db_error', 'DB fehlgeschlagen', ['status' => 500]);
            return rest_ensure_response(
                $db->get_results($db->prepare(
                    "SELECT nummer, produkt, typ, menge, preis, stock
                     FROM camping WHERE typ = %s AND stock != 0 AND stock IS NOT NULL
                     ORDER BY produkt", $typ
                ), ARRAY_A) ?: []
            );
        },
        'permission_callback' => '__return_true',
    ]);

    // ── Events ──
    register_rest_route('wakecamp/v1', '/events', [
        'methods'             => 'GET',
        'callback'            => function() {
            $db = get_ionos_db_connection();
            if (!$db) return new WP_Error('db_error', 'DB fehlgeschlagen', ['status' => 500]);
            return rest_ensure_response(
                $db->get_results("SELECT id, titel, beschreibung, datum, uhrzeit, bild_url
                                  FROM events WHERE aktiv = 1
                                  ORDER BY datum ASC", ARRAY_A) ?: []
            );
        },
        'permission_callback' => '__return_true',
    ]);

    // ── Merch ──
    register_rest_route('wakecamp/v1', '/extra', [
        'methods'             => 'GET',
        'callback'            => function() {
            $db = get_ionos_db_connection();
            if (!$db) return new WP_Error('db_error', 'DB fehlgeschlagen', ['status' => 500]);
            return rest_ensure_response(
                $db->get_results(
                    "SELECT nummer, produkt, preis, bild_url
                     FROM extra WHERE nummer >= 6000 AND stock != 0
                     ORDER BY nummer ASC", ARRAY_A
                ) ?: []
            );
        },
        'permission_callback' => '__return_true',
    ]);

    // ── 🎬 Kino Films (für Frontend Shortcode) ──
    register_rest_route('wakecamp/v1', '/kino', [
        'methods'             => 'GET',
        'callback'            => function() {
            $db = get_ionos_db_connection();
            if (!$db) return new WP_Error('db_error', 'DB fehlgeschlagen', ['status' => 500]);
            $today = date('Y-m-d');
            $films = $db->get_results($db->prepare(
                "SELECT id, title, cover_url, date, sort_order
                 FROM wp_wcr_kino
                 WHERE date >= %s
                 ORDER BY date ASC, sort_order ASC",
                $today
            ), ARRAY_A);
            return rest_ensure_response($films ?: []);
        },
        'permission_callback' => '__return_true',
    ]);

    // ── Obstacles Map (für Frontend Karte) ──
    register_rest_route('wakecamp/v1', '/obstacles', [
        'methods'             => 'GET',
        'callback'            => function() {
            $db = get_ionos_db_connection();
            if (!$db) return new WP_Error('db_error', 'DB fehlgeschlagen', ['status' => 500]);
            $rows = $db->get_results(
                "SELECT id, name, type, icon_url,
                        pos_x, pos_y,
                        pos_x_l, pos_y_l,
                        pos_x_p, pos_y_p,
                        lat, lon, rotation, active
                 FROM obstacles
                 WHERE active = 1
                 ORDER BY id ASC",
                ARRAY_A
            ) ?: [];
            return rest_ensure_response($rows);
        },
        'permission_callback' => '__return_true',
    ]);

    /* ════════════════════════════════════════════════════════════════════════════
       PLAYLIST CHECK — piSignage Aktivierungscheck
       GET /wakecamp/v1/playlist-check

       Parameter:
         ids   = Komma-getrennte Produktnummern (Spalte `nummer`)
         table = Optionale Tabelle (food|drinks|cable|camping|extra|ice)
         typ   = Optionaler Typ-Filter (z.B. "Burger")
         mode  = "any" (Standard) | "all"

       Verhalten:
         - IDs gesetzt    → prüft stock jeder ID
         - Typ gesetzt    → prüft COUNT(*) WHERE typ = ? AND stock > 0
         - Nur table      → prüft COUNT(*) FROM <table> WHERE stock > 0
         - Nichts gesetzt → active = true (Fallback)

       Response:
         { "active": true, "count": 2, "total": 3, "mode": "any" }
    ════════════════════════════════════════════════════════════════════════════ */

    register_rest_route('wakecamp/v1', '/playlist-check', [
        'methods'             => 'GET',
        'callback'            => 'wakecamp_playlist_check',
        'permission_callback' => '__return_true',
    ]);

    /* ════════════════════════════════════════════════════════════════════════════
       OBSTACLES MAP-CONFIG
       GET = öffentlich (Frontend-Rendering)
       POST = geschützt (Backend-Verwaltung)
    ════════════════════════════════════════════════════════════════════════════ */

    register_rest_route('wakecamp/v1', '/obstacles/map-config', [
        [
            'methods'             => 'GET',
            'callback'            => function(WP_REST_Request $req) {
                $mode = strtolower(sanitize_text_field($req->get_param('mode') ?? 'landscape'));
                if (!in_array($mode, ['landscape', 'portrait'], true)) $mode = 'landscape';
                $def_lat=52.821428251670844; $def_lon=13.5770999960116; $def_zoom=17.9; $def_rot=0.0; $def_style='voyager-nolabels';
                $lat   = get_option('wcr_obstacles_map_lat_'.$mode,   null);
                $lon   = get_option('wcr_obstacles_map_lon_'.$mode,   null);
                $zoom  = get_option('wcr_obstacles_map_zoom_'.$mode,  null);
                $rot   = get_option('wcr_obstacles_map_rot_'.$mode,   null);
                $style = get_option('wcr_obstacles_map_style_'.$mode, null);
                if (!is_numeric($lat))  $lat  = get_option('wcr_obstacles_map_lat',  $def_lat);
                if (!is_numeric($lon))  $lon  = get_option('wcr_obstacles_map_lon',  $def_lon);
                if (!is_numeric($zoom)) $zoom = get_option('wcr_obstacles_map_zoom', $def_zoom);
                if (!is_numeric($rot))  $rot  = get_option('wcr_obstacles_map_rot',  $def_rot);
                if (empty($style))      $style = $def_style;
                $valid_styles = ['voyager-nolabels','satellite','dark','light','satellite-labels'];
                if (!in_array($style, $valid_styles, true)) $style = $def_style;
                return rest_ensure_response(['mode'=>$mode,'lat'=>(float)$lat,'lon'=>(float)$lon,'zoom'=>(float)$zoom,'rot'=>(float)$rot,'style'=>$style]);
            },
            'permission_callback' => '__return_true',
        ],
        [
            'methods'             => 'POST',
            'callback'            => function(WP_REST_Request $req) {
                $secret = $req->get_param('wcr_secret') ?? '';
                $nonce  = $req->get_header('X-WP-Nonce') ?: ($req->get_param('_wpnonce') ?? '');
                $ok = ($secret === WCR_DS_API_SECRET) || current_user_can('manage_options') || wp_verify_nonce($nonce, 'wcr_obstacles_map_config');
                if (!$ok) return new WP_Error('forbidden', 'Nicht autorisiert', ['status' => 403]);
                $mode = strtolower(sanitize_text_field($req->get_param('mode') ?? 'landscape'));
                if (!in_array($mode, ['landscape', 'portrait'], true)) $mode = 'landscape';
                $lat=$req->get_param('lat'); $lon=$req->get_param('lon'); $zoom=$req->get_param('zoom');
                $rot=(float)($req->get_param('rot')??0); $style=sanitize_text_field($req->get_param('style')?? 'voyager-nolabels');
                $lat=(float)$lat; $lon=(float)$lon; $zoom=(float)$zoom;
                $valid_styles=['voyager-nolabels','satellite','dark','light','satellite-labels'];
                if (!in_array($style,$valid_styles,true)) $style='voyager-nolabels';
                if ($lat<-90||$lat>90)   return new WP_Error('invalid','Lat ungültig',  ['status'=>400]);
                if ($lon<-180||$lon>180) return new WP_Error('invalid','Lon ungültig',  ['status'=>400]);
                if ($zoom<1||$zoom>21)   return new WP_Error('invalid','Zoom ungültig', ['status'=>400]);
                if ($rot<-360||$rot>360) return new WP_Error('invalid','Rotation ungültig',['status'=>400]);
                update_option('wcr_obstacles_map_lat_'.$mode,   $lat);
                update_option('wcr_obstacles_map_lon_'.$mode,   $lon);
                update_option('wcr_obstacles_map_zoom_'.$mode,  $zoom);
                update_option('wcr_obstacles_map_rot_'.$mode,   $rot);
                update_option('wcr_obstacles_map_style_'.$mode, $style);
                if ($mode==='landscape') {
                    update_option('wcr_obstacles_map_lat',$lat); update_option('wcr_obstacles_map_lon',$lon);
                    update_option('wcr_obstacles_map_zoom',$zoom); update_option('wcr_obstacles_map_rot',$rot);
                }
                return rest_ensure_response(['ok'=>true,'mode'=>$mode,'lat'=>$lat,'lon'=>$lon,'zoom'=>$zoom,'rot'=>$rot,'style'=>$style]);
            },
            'permission_callback' => '__return_true',
        ],
    ]);

    /* ════════════════════════════════════════════════════════════════════════════
       UTILITY-ROUTEN
    ════════════════════════════════════════════════════════════════════════════ */

    register_rest_route('wakecamp/v1', '/item/(?P<id>[0-9]+)', [
        'methods'             => 'GET',
        'callback'            => function($req) {
            $db       = get_ionos_db_connection();
            if (!$db) return new WP_Error('db_error', 'DB fehlgeschlagen', ['status' => 500]);
            $id       = (int) $req['id'];
            $tabellen = ['food', 'drinks', 'cable', 'camping', 'extra', 'ice'];
            foreach ($tabellen as $tabelle) {
                $row = $db->get_row($db->prepare(
                    "SELECT nummer, produkt, preis, menge, typ 
                     FROM `$tabelle` 
                     WHERE nummer = %d AND (stock != 0 OR stock IS NULL)
                     LIMIT 1", $id
                ), ARRAY_A);
                if ($row) return rest_ensure_response($row + ['table' => $tabelle]);
            }
            return new WP_Error('not_found', 'ID nicht gefunden oder nicht vorrätig', ['status' => 404]);
        },
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('wakecamp/v1', '/ping', [
        'methods'             => 'GET',
        'callback'            => function() {
            $db = get_ionos_db_connection();
            if (!$db) return ['status' => 'FEHLER'];
            return [
                'status'       => 'OK',
                'drinks_count' => (int) $db->get_var("SELECT COUNT(*) FROM drinks WHERE stock != 0"),
                'food_count'   => (int) $db->get_var("SELECT COUNT(*) FROM food WHERE stock != 0"),
            ];
        },
        'permission_callback' => '__return_true',
    ]);

    /* ════════════════════════════════════════════════════════════════════════════
       🔒 GESCHÜTZTE VERWALTUNGS-ROUTEN
    ════════════════════════════════════════════════════════════════════════════ */

    register_rest_route('wakecamp/v1', '/ds-settings', [
        [
            'methods'             => 'GET',
            'callback'            => function(WP_REST_Request $req) {
                $ig_keys = [
                    'wcr_instagram_token', 'wcr_instagram_user_id', 'wcr_instagram_hashtags',
                    'wcr_instagram_excluded', 'wcr_instagram_location_label', 'wcr_instagram_cta_text',
                    'wcr_instagram_qr_url', 'wcr_instagram_max_age_value', 'wcr_instagram_max_age_unit',
                    'wcr_instagram_max_posts', 'wcr_instagram_refresh', 'wcr_instagram_new_hours',
                    'wcr_instagram_video_pool', 'wcr_instagram_video_count', 'wcr_instagram_min_likes',
                    'wcr_instagram_use_tagged', 'wcr_instagram_use_hashtag', 'wcr_instagram_show_user',
                    'wcr_instagram_cta_active', 'wcr_instagram_qr_active', 'wcr_instagram_weekly_best',
                ];
                $instagram = [];
                foreach ($ig_keys as $k) $instagram[$k] = get_option($k, '');
                return rest_ensure_response([
                    'options'   => get_option('wcr_ds_options', []),
                    'theme'     => get_option('wcr_ds_theme', 'glass'),
                    'instagram' => $instagram,
                ]);
            },
            'permission_callback' => function(WP_REST_Request $req) {
                $secret = $req->get_header('X-WCR-Secret') ?: $req->get_param('wcr_secret');
                return current_user_can('manage_options') || ($secret === WCR_DS_API_SECRET);
            },
        ],
        [
            'methods'             => 'POST',
            'callback'            => function(WP_REST_Request $req) {
                $action = $req->get_param('action') ?? '';
                if ($action === 'theme') {
                    $theme = sanitize_text_field($req->get_param('theme') ?? '');
                    if (in_array($theme, ['glass', 'flat', 'aurora'], true))
                        update_option('wcr_ds_theme', $theme);
                    return rest_ensure_response(['ok' => true, 'action' => $action]);
                }
                if ($action === 'save') {
                    $opts = $req->get_param('options');
                    if (is_array($opts)) {
                        $allowed = ['clr_green','clr_blue','clr_white','clr_text','clr_muted',
                                    'clr_bg','clr_bg_dark','clr_bg_glass','font_family',
                                    'viewport_w','viewport_h'];
                        $clean = [];
                        foreach ($allowed as $k) {
                            if (isset($opts[$k])) $clean[$k] = sanitize_text_field((string)$opts[$k]);
                        }
                        update_option('wcr_ds_options', $clean);
                        global $wpdb;
                        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient%wcr%'");
                    }
                    return rest_ensure_response(['ok' => true, 'action' => $action]);
                }
                if ($action === 'reset') {
                    update_option('wcr_ds_options', wcr_ds_defaults());
                    return rest_ensure_response(['ok' => true, 'action' => $action]);
                }
                if ($action === 'ig_save') {
                    $opts = $req->get_param('options');
                    if (!is_array($opts)) return new WP_Error('invalid_options', 'Keine Options übergeben', ['status' => 400]);
                    $str_keys  = ['wcr_instagram_token','wcr_instagram_user_id','wcr_instagram_hashtags',
                                  'wcr_instagram_excluded','wcr_instagram_location_label','wcr_instagram_cta_text',
                                  'wcr_instagram_qr_url','wcr_instagram_max_age_unit'];
                    $int_keys  = ['wcr_instagram_max_age_value','wcr_instagram_max_posts','wcr_instagram_refresh',
                                  'wcr_instagram_new_hours','wcr_instagram_video_pool','wcr_instagram_video_count',
                                  'wcr_instagram_min_likes'];
                    $bool_keys = ['wcr_instagram_use_tagged','wcr_instagram_use_hashtag','wcr_instagram_show_user',
                                  'wcr_instagram_cta_active','wcr_instagram_qr_active','wcr_instagram_weekly_best'];
                    foreach ($str_keys  as $k) { if (array_key_exists($k,$opts)) update_option($k, sanitize_textarea_field((string)$opts[$k])); }
                    foreach ($int_keys  as $k) { if (array_key_exists($k,$opts)) update_option($k, (int)$opts[$k]); }
                    foreach ($bool_keys as $k) { if (array_key_exists($k,$opts)) update_option($k, (int)(bool)$opts[$k]); }
                    delete_transient('wcr_instagram_posts');
                    return rest_ensure_response(['ok' => true, 'action' => 'ig_save']);
                }
                return new WP_Error('invalid_action', 'Unbekannte Action', ['status' => 400]);
            },
            'permission_callback' => function(WP_REST_Request $req) {
                $secret = $req->get_param('wcr_secret') ?? '';
                return current_user_can('manage_options') || ($secret === WCR_DS_API_SECRET);
            },
        ],
    ]);

    register_rest_route('wakecamp/v1', '/options/(?P<key>[a-zA-Z0-9_-]+)', [
        'methods'             => 'GET',
        'callback'            => function(WP_REST_Request $req) {
            $key   = sanitize_key($req['key']);
            $value = get_option($key, null);
            return rest_ensure_response(['key' => $key, 'value' => $value, 'found' => ($value !== null)]);
        },
        'permission_callback' => function(WP_REST_Request $req) {
            $secret = $req->get_header('X-WCR-Secret') ?: $req->get_param('wcr_secret');
            return ($secret === WCR_DS_API_SECRET);
        },
    ]);

    register_rest_route('wakecamp/v1', '/options', [
        'methods'             => 'POST',
        'callback'            => function(WP_REST_Request $req) {
            $key   = sanitize_key($req->get_param('key') ?? '');
            $value = $req->get_param('value');
            if (empty($key)) return new WP_Error('invalid_key', 'Key ist erforderlich', ['status' => 400]);
            if ($value === null) {
                $deleted = delete_option($key);
                return rest_ensure_response(['ok' => $deleted, 'action' => 'delete', 'key' => $key]);
            }
            update_option($key, $value);
            return rest_ensure_response(['ok' => true, 'action' => 'update', 'key' => $key]);
        },
        'permission_callback' => function(WP_REST_Request $req) {
            $secret = $req->get_param('wcr_secret') ?? '';
            return ($secret === WCR_DS_API_SECRET);
        },
    ]);

    /* ════════════════════════════════════════════════════════════════════════════
       INSTAGRAM REST ENDPOINTS
    ════════════════════════════════════════════════════════════════════════════ */

    register_rest_route('wakecamp/v1', '/instagram', [
        'methods'             => 'GET',
        'callback'            => fn() => rest_ensure_response(WCR_Instagram::get_posts()),
        'permission_callback' => '__return_true',
    ]);
    register_rest_route('wakecamp/v1', '/instagram/videos', [
        'methods'             => 'GET',
        'callback'            => fn() => rest_ensure_response(WCR_Instagram::get_videos()),
        'permission_callback' => '__return_true',
    ]);
    register_rest_route('wakecamp/v1', '/instagram/status', [
        'methods'             => 'GET',
        'callback'            => fn() => rest_ensure_response(WCR_Instagram::get_status()),
        'permission_callback' => '__return_true',
    ]);

});

/* ══════════════════════════════════════════════════════════════════════════════
   wakecamp_playlist_check( WP_REST_Request $req )

   GET /wp-json/wakecamp/v1/playlist-check

   ?ids=3010,3089,3162          → prüft Nummern in allen Tabellen
   &table=food                  → nur diese Tabelle
   &typ=Burger                  → nur Einträge mit typ = 'Burger'
   &mode=any|all                → Aktivierungsmodus
══════════════════════════════════════════════════════════════════════════════ */
function wakecamp_playlist_check( WP_REST_Request $req ) {

    $allowed_tables = ['food','drinks','cable','camping','extra','ice'];

    // ── Parameter ──
    $ids_raw = sanitize_text_field( $req->get_param('ids') ?? '' );
    $table   = sanitize_key( $req->get_param('table') ?? '' );
    $typ     = sanitize_text_field( $req->get_param('typ') ?? '' );
    $mode    = ( $req->get_param('mode') === 'all' ) ? 'all' : 'any';

    // Tabelle validieren
    if ( $table !== '' && ! in_array( $table, $allowed_tables, true ) ) {
        return new WP_Error( 'invalid_table', 'Tabelle nicht erlaubt', ['status' => 400] );
    }

    // Tabellenliste
    $tables = ( $table !== '' ) ? [ $table ] : $allowed_tables;

    // ── Fallback: Nichts gesetzt → immer aktiv ──
    if ( $ids_raw === '' && $typ === '' && $table === '' ) {
        return rest_ensure_response([
            'active' => true,
            'count'  => 0,
            'total'  => 0,
            'mode'   => $mode,
            'reason' => 'no_filter',
        ]);
    }

    $db = get_ionos_db_connection();
    if ( ! $db ) {
        // Fail-open: DB-Fehler → Seite wird trotzdem geladen
        return rest_ensure_response([
            'active' => true,
            'count'  => 0,
            'total'  => 0,
            'mode'   => $mode,
            'reason' => 'db_error_fail_open',
        ]);
    }

    // ── IDs-Modus ──
    if ( $ids_raw !== '' ) {
        $ids   = array_values( array_filter( array_map( 'intval', explode( ',', $ids_raw ) ) ) );
        $total = count( $ids );

        if ( $total === 0 ) {
            return rest_ensure_response(['active'=>true,'count'=>0,'total'=>0,'mode'=>$mode,'reason'=>'ids_empty']);
        }

        $count_active = 0;
        foreach ( $ids as $id ) {
            foreach ( $tables as $tbl ) {
                $stock = $db->get_var( $db->prepare(
                    "SELECT stock FROM `{$tbl}` WHERE nummer = %d LIMIT 1", $id
                ));
                if ( $stock !== null && (int) $stock > 0 ) {
                    $count_active++;
                    break; // nächste ID
                }
            }
        }

        $active = ( $mode === 'all' )
            ? ( $count_active === $total )
            : ( $count_active > 0 );

        return rest_ensure_response([
            'active' => $active,
            'count'  => $count_active,
            'total'  => $total,
            'mode'   => $mode,
            'reason' => 'ids_' . $mode,
        ]);
    }

    // ── Typ-Modus ──
    if ( $typ !== '' ) {
        $count_active = 0;
        foreach ( $tables as $tbl ) {
            $n = (int) $db->get_var( $db->prepare(
                "SELECT COUNT(*) FROM `{$tbl}` WHERE typ = %s AND stock > 0", $typ
            ));
            $count_active += $n;
        }
        return rest_ensure_response([
            'active' => $count_active > 0,
            'count'  => $count_active,
            'total'  => null, // Gesamtzahl unbekannt ohne extra Query
            'mode'   => $mode,
            'reason' => 'typ',
        ]);
    }

    // ── Nur Tabelle(n) ──
    $count_active = 0;
    foreach ( $tables as $tbl ) {
        $n = (int) $db->get_var( "SELECT COUNT(*) FROM `{$tbl}` WHERE stock > 0" );
        $count_active += $n;
    }
    return rest_ensure_response([
        'active' => $count_active > 0,
        'count'  => $count_active,
        'total'  => null,
        'mode'   => $mode,
        'reason' => 'table',
    ]);
}
