<?php
if (!defined('ABSPATH')) exit;

// ── Random Background ──
if (!function_exists('random_background_shortcode')) {
    function random_background_shortcode($atts, $content = null) {
        $atts = shortcode_atts(array(
            'folder'  => 'images/backgrounds',
            'element' => 'div',
            'id'      => '',
            'class'   => '',
            'width'   => '100%',
            'height'  => '',
        ), $atts, 'random_bg');
        $folder_path = trailingslashit(get_stylesheet_directory() . '/' . $atts['folder']);
        $folder_url  = trailingslashit(get_stylesheet_directory_uri() . '/' . $atts['folder']);
        $images      = glob($folder_path . '*.{jpg,jpeg,png,gif}', GLOB_BRACE);
        $bg_url = ($images && count($images) > 0)
            ? $folder_url . basename($images[array_rand($images)])
            : '';
        $style = '';
        if ($bg_url) $style .= "background-image: url('$bg_url');background-size:cover;background-position:center center;";
        if (!empty($atts['width']))  $style .= 'width:'  . esc_attr($atts['width'])  . ';';
        if (!empty($atts['height'])) $style .= 'height:' . esc_attr($atts['height']) . ';';
        $output = '<' . esc_attr($atts['element']);
        if (!empty($atts['id']))    $output .= ' id="'    . esc_attr($atts['id'])    . '"';
        if (!empty($atts['class'])) $output .= ' class="' . esc_attr($atts['class']) . '"';
        if ($style)                 $output .= ' style="' . esc_attr($style)         . '"';
        $output .= '>';
        if ($content) $output .= do_shortcode($content);
        $output .= '</' . esc_attr($atts['element']) . '>';
        return $output;
    }
    add_shortcode('random_bg', 'random_background_shortcode');
}

// ── Öffnungszeiten Mo–So ──
if (!function_exists('opening_hours_pixel_perfect')) {
    function opening_hours_pixel_perfect($atts) {
        $atts       = shortcode_atts(array('tage' => 7), $atts);
        $externe_db = get_ionos_db_connection();
        if (!$externe_db) return '';

        $tz      = new DateTimeZone('Europe/Berlin');
        $today   = new DateTime('now', $tz);
        $dow     = (int)$today->format('N');
        $montag  = (clone $today)->modify('-' . ($dow - 1) . ' days')->format('Y-m-d');
        $sonntag = (clone $today)->modify('+' . (7 - $dow) . ' days')->format('Y-m-d');

        $query = $externe_db->prepare(
            "SELECT datum, start_time, end_time, is_closed,
                    COALESCE(course1, 0)      AS course1,
                    COALESCE(course1_text,'') AS course1_text,
                    COALESCE(course2, 0)      AS course2,
                    COALESCE(course2_text,'') AS course2_text,
                    updated_at
             FROM opening_hours
             WHERE datum >= %s AND datum <= %s
             ORDER BY datum ASC",
            $montag, $sonntag
        );
        $results         = $externe_db->get_results($query);
        $wochentage_kurz = [1=>'Mo',2=>'Di',3=>'Mi',4=>'Do',5=>'Fr',6=>'Sa',7=>'So'];
        $lat = 52.1750;
        $lon = 13.1500;
        $todayStr  = $today->format('Y-m-d');
        $todayDate = $today->format('Y-m-d');

        ob_start();

        static $css_done = false;
        if (!$css_done) {
            $css_done = true;
            echo '<style>
.oh-stoerer {
    display: inline-block;
    vertical-align: middle;
    margin-left: 12px;
    padding: 5px 11px;
    background: var(--clr-green, #5d9c5d);
    color: #fff;
    font-size: 12px;
    font-weight: 900;
    letter-spacing: .07em;
    text-transform: uppercase;
    line-height: 1.25;
    border-radius: 4px;
    transform: rotate(-3deg);
    white-space: nowrap;
    box-shadow: 2px 2px 6px rgba(0,0,0,.35);
}
.oh-stoerer .st-title { display: block; font-size: 11px; opacity: .85; letter-spacing: .1em; }
.oh-stoerer .st-time  { display: block; font-size: 13px; letter-spacing: .04em; }
.oh-weekend-sep td { border-top: 1px solid rgba(255,255,255,.10) !important; padding-top: 12px !important; }
.oh-table td.col-5-unit { white-space: nowrap; }
.oh-table tr.today { background: rgba(93,156,93,.12); box-shadow: 0 0 18px rgba(93,156,93,.25); position: relative; }
.oh-table tr.today td { color: #fff !important; font-weight: 700; }
.oh-updated-badge {
    display: inline-block; vertical-align: middle; margin-left: 8px;
    padding: 2px 7px; background: rgba(255,200,0,.18); border: 1px solid rgba(255,200,0,.45);
    color: #ffc800; font-size: 11px; font-weight: 700; border-radius: 20px;
    letter-spacing: .04em; text-transform: uppercase; white-space: nowrap;
    animation: oh-updated-pulse 2s ease-in-out infinite;
}
@keyframes oh-updated-pulse {
    0%,100% { opacity: 1;   box-shadow: 0 0 0 rgba(255,200,0,0); }
    50%      { opacity: .75; box-shadow: 0 0 8px rgba(255,200,0,.4); }
}
</style>' . "\n";
        }

        if ($results) {
            echo '<div class="oh-glass"><table class="oh-table">';
            $firstWeekend = true;
            foreach ($results as $row) {
                $timestamp  = strtotime($row->datum);
                $day_number = (int)date('N', $timestamp);
                $tag        = $wochentage_kurz[$day_number] ?? date('D', $timestamp);
                $isWeekend  = ($day_number >= 6);
                $isClosed   = !empty($row->is_closed) && (int)$row->is_closed === 1;
                $hasStart   = !empty($row->start_time) && $row->start_time !== 'NULL';
                $hasEnd     = !empty($row->end_time)   && $row->end_time   !== 'NULL';
                $c1    = (int)($row->course1 ?? 0);
                $c2    = (int)($row->course2 ?? 0);
                $c1txt = trim($row->course1_text ?? '');
                $c2txt = trim($row->course2_text ?? '');
                $hasKurs  = $isWeekend && ($c1 || $c2);
                $sepClass = '';
                if ($isWeekend && $firstWeekend) { $sepClass = ' oh-weekend-sep'; $firstWeekend = false; }
                if ($isWeekend && !$hasStart && !$hasKurs) continue;
                if (!$isWeekend && !$hasStart) continue;

                $isToday         = ($row->datum === $todayStr);
                $updatedAt       = !empty($row->updated_at) ? substr($row->updated_at, 0, 10) : '';
                $wasUpdatedToday = ($updatedAt === $todayDate && !$isToday);
                $trClass         = trim(($isToday ? 'today' : '') . $sepClass);
                $updatedBadge    = $wasUpdatedToday ? '<span class="oh-updated-badge">↑ Update</span>' : '';

                if ($isClosed) {
                    echo '<tr class="geschlossen' . ($trClass ? ' ' . $trClass : '') . '">';
                    echo '<td class="col-1-day">' . esc_html($tag) . ':</td>';
                    echo '<td class="col-2-start" colspan="3" style="text-align:center;letter-spacing:.15em;font-size:28px;color:rgba(255,59,48,.7)">GESCHLOSSEN</td>';
                    echo '<td class="col-5-unit">' . $updatedBadge . '</td></tr>';
                    continue;
                }

                $start = '';
                if ($hasStart) { $start = substr($row->start_time, 0, 5); if (substr($start, 2) === ':00') $start = substr($start, 0, 2); }
                $end = '';
                if ($hasEnd) { $end = substr($row->end_time, 0, 5); if (substr($end, 2) === ':00') $end = substr($end, 0, 2); }
                elseif ($hasStart) {
                    $sun_info = date_sun_info($timestamp, $lat, $lon);
                    $dt = new DateTime('@' . $sun_info['sunset']); $dt->setTimezone($tz);
                    $end = $dt->format('H');
                }

                $stoerer = '';
                if ($hasKurs) {
                    $parts = [];
                    if ($c1 && $c1txt) $parts[] = $c1txt;
                    if ($c2 && $c2txt) $parts[] = $c2txt;
                    $timeStr = !empty($parts) ? implode(' / ', $parts) : '';
                    $stoerer = '<span class="oh-stoerer"><span class="st-title">Anfaenger-</span><span class="st-title">kurs</span>';
                    if ($timeStr) $stoerer .= '<span class="st-time">' . esc_html($timeStr) . '</span>';
                    $stoerer .= '</span>';
                }

                if ($hasStart) {
                    echo '<tr' . ($trClass ? ' class="' . $trClass . '"' : '') . '>';
                    echo '<td class="col-1-day">'   . esc_html($tag)   . ':</td>';
                    echo '<td class="col-2-start">' . esc_html($start) . '</td>';
                    echo '<td class="col-3-sep">–</td>';
                    echo '<td class="col-4-end">'   . esc_html($end)   . '</td>';
                    echo '<td class="col-5-unit">UHR' . $stoerer . $updatedBadge . '</td></tr>';
                } elseif ($hasKurs) {
                    echo '<tr' . ($trClass ? ' class="' . $trClass . '"' : '') . '>';
                    echo '<td class="col-1-day">' . esc_html($tag) . ':</td>';
                    echo '<td class="col-2-start" colspan="3" style="color:var(--clr-text-muted,#7a8a8a);font-size:22px;">Kein Wakeboard</td>';
                    echo '<td class="col-5-unit">' . $stoerer . $updatedBadge . '</td></tr>';
                }
            }
            echo '</table></div>';
        }
        return ob_get_clean();
    }
    add_shortcode('oeffnungszeiten', 'opening_hours_pixel_perfect');
}

// ── Random Split Photos ──
if (!shortcode_exists('random_split_photos')) {
    add_shortcode('random_split_photos', function ($atts) {
        $atts    = shortcode_atts(['folder' => 'split', 'ttl' => 3600, 'class' => 'rs-split'], $atts, 'random_split_photos');
        $uploads = wp_upload_dir();
        $rel     = trim((string)$atts['folder'], "/ \t\n\r\0\x0B");
        if ($rel === '' || strpos($rel, '..') !== false) return '';
        $dir = trailingslashit($uploads['basedir']) . $rel;
        $url = trailingslashit($uploads['baseurl']) . $rel;
        if (!is_dir($dir) || !is_readable($dir)) return '';
        $files = [];
        foreach (['jpg','jpeg','png','webp','gif'] as $ext) {
            $g = glob($dir . '/*.' . $ext);
            if (is_array($g)) $files = array_merge($files, $g);
        }
        $files = array_values(array_filter($files, fn($p) => is_file($p)));
        if (count($files) < 2) return '';
        try {
            $ionos = get_ionos_db_connection();
            if ($ionos) {
                $activeNames = $ionos->get_col($ionos->prepare(
                    "SELECT filename FROM media_files WHERE folder = %s AND is_active = 1", $rel
                ));
                if (!empty($activeNames)) {
                    $files = array_values(array_filter($files, function($path) use ($activeNames) {
                        return in_array(basename($path), $activeNames, true);
                    }));
                }
            }
        } catch (Exception $e) {}
        if (count($files) < 2) return '';
        $key        = 'rs_last_pair_' . md5($rel);
        $last       = get_transient($key);
        $exclude    = is_array($last) ? $last : [];
        $candidates = array_values(array_diff($files, $exclude));
        if (count($candidates) < 2) { $candidates = $files; }
        shuffle($candidates);
        $pair = array_slice($candidates, 0, 2);
        set_transient($key, $pair, max(60, (int)$atts['ttl']));
        $src1  = esc_url(trailingslashit($url) . basename($pair[0]));
        $src2  = esc_url(trailingslashit($url) . basename($pair[1]));
        $anim1 = (rand(0,1) === 1) ? 'rs-zoom-in' : 'rs-zoom-out';
        $anim2 = (rand(0,1) === 1) ? 'rs-zoom-in' : 'rs-zoom-out';
        return '<div class="' . esc_attr($atts['class']) . '">
          <figure class="rs-enter-1"><img src="' . $src1 . '" class="' . $anim1 . '" alt="" loading="eager"></figure>
          <figure class="rs-enter-2"><img src="' . $src2 . '" class="' . $anim2 . '" alt="" loading="eager"></figure>
        </div>';
    });
}

// ── Öffnungszeiten Foto ──
if (!defined('PHOTO_API_URL')) {
    define('PHOTO_API_URL', 'https://wcr-webpage.de/be/api/get_latest_photo.php');
}
if (!function_exists('simple_opening_hours_photo')) {
    function simple_opening_hours_photo($atts) {
        $atts     = shortcode_atts(array('width'=>'100%','height'=>'auto','class'=>'','style'=>''), $atts);
        $response = @file_get_contents(PHOTO_API_URL);
        if (!$response) return '<!-- API nicht erreichbar -->';
        $data = json_decode($response, true);
        if (!$data || !$data['success']) return '<!-- Kein Foto verfügbar -->';
        $inline_style = "width:{$atts['width']};height:{$atts['height']};";
        if (!empty($atts['style'])) $inline_style .= ' ' . $atts['style'];
        return sprintf('<img src="%s" alt="Öffnungszeiten" class="%s" style="%s" loading="lazy">',
            esc_url($data['url']), esc_attr($atts['class']), esc_attr($inline_style));
    }
    add_shortcode('opening_hours_photo', 'simple_opening_hours_photo');
}

// ── WCR DB Item Shortcode ──
if (!function_exists('wcr_db_item_shortcode')) {
    function wcr_db_item_shortcode($atts) {
        $atts = shortcode_atts(array('id' => '', 'feld' => 'preis', 'table' => ''), $atts, 'wcr_db');
        $id = (int) $atts['id'];
        if (!$id) return '';
        $erlaubte_felder   = ['produkt', 'preis', 'menge', 'typ', 'stock'];
        $erlaubte_tabellen = ['food', 'drinks', 'cable', 'camping', 'extra', 'ice'];
        if (!in_array($atts['feld'], $erlaubte_felder)) return '';
        $db = get_ionos_db_connection();
        if (!$db) return '';
        $tabellen = (!empty($atts['table']) && in_array($atts['table'], $erlaubte_tabellen))
            ? [$atts['table']] : $erlaubte_tabellen;
        $row = null;
        foreach ($tabellen as $tabelle) {
            $result = $db->get_row($db->prepare(
                "SELECT produkt, preis, menge, typ, stock FROM `$tabelle` WHERE nummer = %d LIMIT 1", $id
            ), ARRAY_A);
            if ($result) { $row = $result; break; }
        }
        if (!$row || !isset($row[$atts['feld']])) return '–';
        $wert = $row[$atts['feld']];
        if ($atts['feld'] === 'preis' && $wert !== null)
            return number_format((float)$wert, 2, ',', '.') . ' €';
        if ($atts['feld'] === 'menge' && $wert !== null)
            return rtrim(rtrim(number_format((float)$wert, 3, ',', '.'), '0'), ',') . ' l';
        return esc_html($wert ?? '–');
    }
    add_shortcode('wcr_db', 'wcr_db_item_shortcode');
}

// ════════════════════════════════════════════════════════
// OBSTACLES MAP  [wcr_obstacles_map]
//
// Verwendung:
//   [wcr_obstacles_map]                     → Landscape 1920×1080
//   [wcr_obstacles_map mode="landscape"]     → Landscape 1920×1080
//   [wcr_obstacles_map mode="portrait"]      → Portrait  1080×1920
// ════════════════════════════════════════════════════════
if (!function_exists('wcr_sc_obstacles_map')) {
    function wcr_sc_obstacles_map($atts) {

        $atts = shortcode_atts([
            'mode' => 'landscape',
        ], $atts, 'wcr_obstacles_map');

        $is_portrait = (strtolower(trim($atts['mode'])) === 'portrait');
        $mode        = $is_portrait ? 'portrait' : 'landscape';
        $W = $is_portrait ? 1080 : 1920;
        $H = $is_portrait ? 1920 : 1080;

        // ── Assets laden ──
        wcr_ds_load_leaflet();
        wp_enqueue_style(
            'wcr-obstacles-map-css',
            WCR_DS_URL . 'assets/css/wcr-obstacles-map.css',
            ['leaflet-css'],
            defined('WCR_DS_VERSION') ? WCR_DS_VERSION : '1.0.0'
        );
        wp_enqueue_script(
            'wcr-obstacles-map',
            WCR_DS_URL . 'assets/js/wcr-obstacles-map.js',
            ['leaflet-js'],
            defined('WCR_DS_VERSION') ? WCR_DS_VERSION : '1.0.0',
            true
        );
        $api_url        = rest_url('wakecamp/v1/obstacles');
        $map_config_url = rest_url('wakecamp/v1/obstacles/map-config');
        wp_localize_script('wcr-obstacles-map', 'wcrObstaclesMap', [
            'apiUrl'        => esc_url_raw($api_url),
            'mapConfigUrl'  => esc_url_raw($map_config_url),
            'mode'          => $mode,
        ]);

        // ── Obstacles aus DB — alle 6 Positions-Spalten laden ──
        $obstacles      = [];
        $is_placeholder = true;
        $db             = get_ionos_db_connection();
        if ($db) {
            $rows = $db->get_results(
                "SELECT name, type, icon_url,
                        pos_x, pos_y,
                        pos_x_l, pos_y_l,
                        pos_x_p, pos_y_p,
                        lat, lon, rotation
                 FROM obstacles WHERE active = 1 ORDER BY id ASC",
                ARRAY_A
            );
            if (!empty($rows)) {
                $obstacles      = $rows;
                $is_placeholder = false;
            }
        }

        if ($is_placeholder) {
            $obstacles = [
                ['name'=>'Kicker 1',  'type'=>'kicker','icon_url'=>'','pos_x_l'=>20,'pos_y_l'=>35,'pos_x_p'=>20,'pos_y_p'=>35,'pos_x'=>20,'pos_y'=>35,'lat'=>0,'lon'=>0,'rotation'=>0],
                ['name'=>'Kicker 2',  'type'=>'kicker','icon_url'=>'','pos_x_l'=>55,'pos_y_l'=>25,'pos_x_p'=>55,'pos_y_p'=>25,'pos_x'=>55,'pos_y'=>25,'lat'=>0,'lon'=>0,'rotation'=>15],
                ['name'=>'Rail Links','type'=>'rail',  'icon_url'=>'','pos_x_l'=>35,'pos_y_l'=>60,'pos_x_p'=>35,'pos_y_p'=>60,'pos_x'=>35,'pos_y'=>60,'lat'=>0,'lon'=>0,'rotation'=>45],
                ['name'=>'Rail Mitte','type'=>'rail',  'icon_url'=>'','pos_x_l'=>65,'pos_y_l'=>55,'pos_x_p'=>65,'pos_y_p'=>55,'pos_x'=>65,'pos_y'=>55,'lat'=>0,'lon'=>0,'rotation'=>0],
                ['name'=>'Box',       'type'=>'box',   'icon_url'=>'','pos_x_l'=>80,'pos_y_l'=>40,'pos_x_p'=>80,'pos_y_p'=>40,'pos_x'=>80,'pos_y'=>40,'lat'=>0,'lon'=>0,'rotation'=>0],
            ];
        }

        $type_icons = [
            'kicker' => '🚀', 'rail' => '🟧', 'box' => '🟦',
            'fun'    => '⭐',  'slider' => '🟩', 'default' => '🟣',
        ];

        $icon_size = $is_portrait ? 60   : 44;
        $font_size = $is_portrait ? '36px' : '28px';
        $lbl_size  = $is_portrait ? '15px' : '11px';

        $wrap_class = 'wcr-obstacles-map-wrap' . ($is_portrait ? ' portrait' : '');

        ob_start();

        echo '<div id="wcr-obstacles-map-wrap" class="' . esc_attr($wrap_class) . '" data-mode="' . esc_attr($mode) . '">';
        echo '<div id="wcr-obstacles-stage" class="wcr-obstacles-stage">';
        echo '<div id="wcr-obstacles-map" data-api="' . esc_url($api_url) . '"></div>';

        // ── PHP-Fallback: richtige Spalte je nach Mode, Fallback auf pos_x/pos_y ──
        foreach ($obstacles as $obs) {
            $lat = (float)($obs['lat'] ?? 0);
            $lon = (float)($obs['lon'] ?? 0);
            if ($lat != 0 || $lon != 0) continue; // Geo-Obstacles macht JS

            // Mode-abhängige Position wählen
            if ($is_portrait) {
                $raw_x = ($obs['pos_x_p'] !== null && $obs['pos_x_p'] !== '') ? $obs['pos_x_p'] : ($obs['pos_x'] ?? 50);
                $raw_y = ($obs['pos_y_p'] !== null && $obs['pos_y_p'] !== '') ? $obs['pos_y_p'] : ($obs['pos_y'] ?? 50);
            } else {
                $raw_x = ($obs['pos_x_l'] !== null && $obs['pos_x_l'] !== '') ? $obs['pos_x_l'] : ($obs['pos_x'] ?? 50);
                $raw_y = ($obs['pos_y_l'] !== null && $obs['pos_y_l'] !== '') ? $obs['pos_y_l'] : ($obs['pos_y'] ?? 50);
            }

            // Wenn kein Wert für diesen Mode → Obstacle nicht rendern
            if ($raw_x === null || $raw_x === '') continue;

            $px  = (float)$raw_x / 100 * $W;
            $py  = (float)$raw_y / 100 * $H;
            $rot = (float)($obs['rotation'] ?? 0);
            $ico = !empty($obs['icon_url']) ? esc_url($obs['icon_url']) : '';
            $lbl = esc_html($obs['name'] ?? '');
            $type  = strtolower(trim($obs['type'] ?? 'default'));
            $emoji = $type_icons[$type] ?? $type_icons['default'];

            $transform = 'translate(-50%,-50%)';
            if ($rot) $transform .= ' rotate(' . $rot . 'deg)';

            $icon_style   = $ico
                ? 'background-image:url(' . $ico . ');width:' . $icon_size . 'px;height:' . $icon_size . 'px;background-size:contain;background-repeat:no-repeat;'
                : 'font-size:' . $font_size . ';line-height:1;filter:drop-shadow(0 2px 6px rgba(0,0,0,.5))';
            $icon_content = $ico ? '' : $emoji;

            echo '<div class="wcr-obstacle' . ($is_placeholder ? ' is-placeholder' : '') . '"'
               . ' style="position:absolute;left:' . $px . 'px;top:' . $py . 'px;transform:' . $transform . ';z-index:500;display:flex;flex-direction:column;align-items:center;gap:4px;pointer-events:none"'
               . ' data-type="' . esc_attr($type) . '">';
            echo '<div style="' . $icon_style . '">' . $icon_content . '</div>';
            echo '<span style="font-size:' . $lbl_size . ';font-weight:700;color:#fff;text-shadow:0 1px 4px rgba(0,0,0,.8);white-space:nowrap">' . $lbl . '</span>';
            echo '</div>';
        }

        echo '</div>'; // stage

        if ($is_placeholder) {
            echo '<div class="wcr-obstacles-placeholder-hint">Platzhalter – bitte Obstacles im Backend pflegen</div>';
        }

        echo '</div>'; // #wcr-obstacles-map-wrap

        return ob_get_clean();
    }
    add_shortcode('wcr_obstacles_map', 'wcr_sc_obstacles_map');
}

// ── Merch Seite ──
if (!function_exists('wcr_sc_merch')) {
    function wcr_sc_merch($atts) {
        wp_enqueue_script('wcr-merch', plugins_url('assets/js/wcr-merch.js', dirname(__FILE__)), [], '1.0.0', true);
        $slides     = [6000, 6001, 6004];
        $highlights = [6003, 6005, 6001];
        return '<div id="merch-wrap"
                     data-slides="'     . esc_attr(json_encode($slides))     . '"
                     data-highlights="' . esc_attr(json_encode($highlights)) . '"
                     data-api="/wp-json/wakecamp/v1/extra"
                     data-interval="4000">
            <div class="merch-main">
                <div class="merch-slider" id="merch-slider">
                    <div class="merch-slide-inner" id="merch-slide-inner"></div>
                </div>
                <div class="merch-dots" id="merch-dots"></div>
            </div>
            <aside class="merch-sidebar" id="merch-sidebar"></aside>
        </div>';
    }
    add_shortcode('wcr_merch', 'wcr_sc_merch');
}

// ════════════════════════════════════════════════════════
// INSTAGRAM GRID  [wcr_instagram]
// ════════════════════════════════════════════════════════
add_shortcode('wcr_instagram', function () {
    wp_enqueue_style('wcr-instagram',  WCR_DS_URL . 'assets/css/wcr-instagram.css', [], WCR_DS_VERSION);
    wp_enqueue_script('wcr-instagram', WCR_DS_URL . 'assets/js/wcr-instagram.js',  [], WCR_DS_VERSION, true);
    $ig_token     = (string) get_option('wcr_instagram_token',   '');
    $ig_user_id   = (string) get_option('wcr_instagram_user_id', '');
    $ig_has_token = ($ig_token !== '' && $ig_user_id !== '');
    wp_localize_script('wcr-instagram', 'wcrInstagram', [
        'restUrl'   => rest_url(),
        'hashtag'   => ltrim(get_option('wcr_instagram_hashtags', 'wakecampruhlsdorf'), "#\n\r "),
        'refresh'   => get_option('wcr_instagram_refresh',   '10'),
        'newHours'  => get_option('wcr_instagram_new_hours', '2'),
        'showUser'  => get_option('wcr_instagram_show_user', '1'),
        'hasToken'  => $ig_has_token,
    ]);
    $posts       = WCR_Instagram::get_posts();
    $location    = get_option('wcr_instagram_location_label', '');
    $cta_active  = get_option('wcr_instagram_cta_active', 1);
    $cta_text    = get_option('wcr_instagram_cta_text', 'Markiere uns auf Instagram und erscheine hier! 📸');
    $qr_active   = get_option('wcr_instagram_qr_active', 0);
    $qr_url      = get_option('wcr_instagram_qr_url', '');
    $weekly      = (get_option('wcr_instagram_weekly_best', 0) && date('N') == 7) ? WCR_Instagram::get_weekly_best() : null;
    $hashtag_str = '#' . ltrim(get_option('wcr_instagram_hashtags', 'wakecampruhlsdorf'), "#\n\r ");
    $new_ms      = (int)get_option('wcr_instagram_new_hours', 2) * 3600;
    $show_user   = (bool)get_option('wcr_instagram_show_user', 1);
    ob_start(); ?>
    <div class="wcr-instagram-wrap">
        <?php if ($weekly): ?>
        <div class="wcr-insta-weekly">
            <img src="<?= esc_url($weekly['media_url'] ?? $weekly['thumbnail_url'] ?? '') ?>" alt="">
            <div class="wcr-insta-weekly-label">📸 Post der Woche</div>
            <div class="wcr-insta-weekly-user"><?= !empty($weekly['username']) ? '@' . esc_html($weekly['username']) : '' ?></div>
        </div>
        <?php endif; ?>
        <div class="wcr-insta-header">
            <svg viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
            <span>Wake &amp; Camp · Instagram</span>
            <?php if ($location): ?><span class="wcr-insta-location">📍 <?= esc_html($location) ?></span><?php endif; ?>
        </div>
        <div class="wcr-insta-grid" id="wcr-insta-grid">
            <?php foreach (array_slice($posts, 0, 8) as $p):
                $is_new = (time() - strtotime($p['timestamp'])) < $new_ms;
                $user   = !empty($p['username']) ? '@' . $p['username'] : $hashtag_str;
                $age    = human_time_diff(strtotime($p['timestamp']), time());
            ?>
            <div class="wcr-insta-post">
                <?php if ($p['media_type'] === 'VIDEO'): ?>
                    <video src="<?= esc_url($p['media_url']) ?>" autoplay muted loop playsinline poster="<?= esc_url($p['thumbnail_url'] ?? '') ?>"></video>
                    <span class="wcr-insta-badge-type">▶</span>
                <?php elseif ($p['media_type'] === 'CAROUSEL_ALBUM'): ?>
                    <img src="<?= esc_url($p['media_url'] ?? $p['thumbnail_url'] ?? '') ?>" alt="" loading="lazy">
                    <span class="wcr-insta-badge-type">⊞</span>
                <?php else: ?>
                    <img src="<?= esc_url($p['media_url'] ?? $p['thumbnail_url'] ?? '') ?>" alt="" loading="lazy">
                <?php endif; ?>
                <?php if ($is_new): ?><span class="wcr-insta-badge-new">Neu</span><?php endif; ?>
                <?php if ($show_user): ?>
                <div class="wcr-insta-overlay">
                    <span class="wcr-insta-username"><?= esc_html($user) ?></span>
                    <span class="wcr-insta-time"><?= esc_html($age) ?></span>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="wcr-insta-footer">
            <?php if ($cta_active && $cta_text): ?><div class="wcr-insta-footer-cta"><?= esc_html($cta_text) ?></div><?php endif; ?>
            <div class="wcr-insta-footer-sub"><?= esc_html($hashtag_str) ?></div>
        </div>
        <?php if ($qr_active && $qr_url): ?>
        <div class="wcr-insta-qr"><img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=<?= urlencode($qr_url) ?>" alt="QR"></div>
        <?php endif; ?>
    </div>
    <?php return ob_get_clean();
});

// ════════════════════════════════════════════════════════
// INSTAGRAM VIDEO  [wcr_instagram_video]
// ════════════════════════════════════════════════════════
add_shortcode('wcr_instagram_video', function () {
    wp_enqueue_style('wcr-iv',  WCR_DS_URL . 'assets/css/wcr-instagram-video.css', [], WCR_DS_VERSION);
    wp_enqueue_script('wcr-iv', WCR_DS_URL . 'assets/js/wcr-instagram-video.js',  [], WCR_DS_VERSION, true);
    $ig_token     = (string) get_option('wcr_instagram_token',   '');
    $ig_user_id   = (string) get_option('wcr_instagram_user_id', '');
    $ig_has_token = ($ig_token !== '' && $ig_user_id !== '');
    wp_localize_script('wcr-iv', 'wcrInstagramVideo', [
        'restUrl'  => rest_url(),
        'hashtag'  => ltrim(get_option('wcr_instagram_hashtags', 'wakecampruhlsdorf'), "#\n\r "),
        'hasToken' => $ig_has_token,
    ]);
    $videos   = WCR_Instagram::get_videos();
    $count    = count($videos);
    $hashtag  = '#' . ltrim(get_option('wcr_instagram_hashtags', 'wakecampruhlsdorf'), "#\n\r ");
    $first    = $videos[0] ?? null;
    ob_start(); ?>
    <div class="wcr-iv-wrap">
        <video class="wcr-iv-player" id="wcr-iv-player"
               <?= $first ? 'src="' . esc_url($first['media_url'] ?? '') . '"' : '' ?>
               autoplay muted playsinline preload="auto"
               <?= $first && !empty($first['thumbnail_url']) ? 'poster="' . esc_url($first['thumbnail_url']) . '"' : '' ?>></video>
        <div class="wcr-iv-fade"></div>
        <div class="wcr-iv-mute" id="wcr-iv-mute">🔇</div>
        <div class="wcr-iv-top">
            <div class="wcr-iv-logo"><svg viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg></div>
            <div class="wcr-iv-meta">
                <span class="wcr-iv-username" id="wcr-iv-username"><?= $first && !empty($first['username']) ? '@' . esc_html($first['username']) : esc_html($hashtag) ?></span>
                <span class="wcr-iv-time"     id="wcr-iv-time"><?= $first ? esc_html(human_time_diff(strtotime($first['timestamp']), time())) : '' ?></span>
            </div>
            <span class="wcr-iv-counter" id="wcr-iv-counter">1 / <?= $count ?></span>
        </div>
        <div class="wcr-iv-bottom">
            <div class="wcr-iv-hashtag"><?= esc_html($hashtag) ?></div>
            <div class="wcr-iv-dots">
                <?php for ($i = 0; $i < $count; $i++): ?>
                <div class="wcr-iv-dot <?= $i === 0 ? 'active' : '' ?>"><div class="wcr-iv-dot-fill"></div></div>
                <?php endfor; ?>
            </div>
        </div>
    </div>
    <?php return ob_get_clean();
});
