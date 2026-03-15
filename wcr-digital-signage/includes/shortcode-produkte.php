<?php
/**
 * WCR Produkte Spotlight Shortcode
 * v3: stock=0 werden NICHT gerendert + DS-Seiten-Aktivierungscheck
 *
 * [wcr_produkte id1="42" id2="17" id3="99" table="food" mode="any" titel="Empfehlungen"]
 *
 * Parameter:
 *   id1..id3   – Produkt-Nummern (Spalte `nummer`)
 *   titel      – Überschrift (Standard: "Unsere Empfehlungen")
 *   table      – Optional: Tabelle eingrenzen (food, drinks, cable, camping, extra, ice)
 *   mode       – Optional: "any" (mind. 1 aktiv) | "all" (alle müssen aktiv sein) → steuert Seite
 *   img1..img3 – Optional: Bild-URL (zeigt Dampf-Effekt)
 *   show_menge – Optional: "1" zeigt Mengenangaben (Standard: "0")
 *
 * Wichtig:
 *   - stock=0 → Produkt wird NICHT angezeigt
 *   - DS-Seitencheck: wenn alle relevanten Produkte stock=0 → nur Kommentare (piSignage lädt Seite nicht)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( file_exists( __DIR__ . '/ds-pages.php' ) ) {
    require_once __DIR__ . '/ds-pages.php';
}

if ( ! function_exists( 'wcr_sc_produkte' ) ) {

    function wcr_sc_produkte( $atts ) {

        $atts = shortcode_atts( [
            'id1'        => '',
            'id2'        => '',
            'id3'        => '',
            'titel'      => 'Unsere Empfehlungen',
            'table'      => '',
            'mode'       => 'any',
            'img1'       => '',
            'img2'       => '',
            'img3'       => '',
            'show_menge' => '0',
        ], $atts, 'wcr_produkte' );

        $show_menge = ( $atts['show_menge'] === '1' || $atts['show_menge'] === 'true' );
        $mode       = in_array( $atts['mode'], [ 'any', 'all' ], true ) ? $atts['mode'] : 'any';

        // ── Assets ──
        wp_enqueue_style(  'wcr-produkte',    WCR_DS_URL . 'assets/css/wcr-produkte.css', [], WCR_DS_VERSION );
        wp_enqueue_script( 'wcr-produkte-js', WCR_DS_URL . 'assets/js/wcr-produkte.js',  [], WCR_DS_VERSION, true );

        // ── Seiten-ID ──
        $page_id = (int) get_queried_object_id();

        // ── Check-URL Kommentar (für späteren piSignage REST-Endpoint) ──
        $raw_ids = array_values( array_filter( [ $atts['id1'], $atts['id2'], $atts['id3'] ] ) );
        $check_params = [];
        if ( ! empty( $atts['table'] ) )  $check_params['table'] = $atts['table'];
        if ( ! empty( $raw_ids ) )        $check_params['ids']   = implode( ',', $raw_ids );
        if ( $mode !== 'any' )            $check_params['mode']  = $mode;
        $check_url     = '/wp-json/wcr/v1/playlist-check' . ( ! empty( $check_params ) ? '?' . http_build_query( $check_params ) : '' );
        $check_comment = '<!-- wcr-playlist-check: ' . esc_attr( $check_url ) . ' -->';

        // ── DS-Seiten-Aktivierungscheck ──
        if ( function_exists( 'is_ds_page_active' ) && $page_id > 0 ) {
            $rule_fallback = [
                'table' => $atts['table'],
                'ids'   => implode( ',', $raw_ids ),
                'mode'  => $mode,
            ];
            if ( ! is_ds_page_active( $page_id, $rule_fallback ) ) {
                return $check_comment . "\n" . '<!-- wcr-ds-page-inactive -->';
            }
        }

        // ── DB ──
        $db = get_ionos_db_connection();

        $erlaubte_tabellen = [ 'food', 'drinks', 'cable', 'camping', 'extra', 'ice' ];
        $tabellen = ( ! empty( $atts['table'] ) && in_array( $atts['table'], $erlaubte_tabellen, true ) )
            ? [ $atts['table'] ]
            : $erlaubte_tabellen;

        // ── Produkt laden (nur wenn stock > 0) ──
        $get_produkt = function( $nummer ) use ( $db, $tabellen ) {
            if ( ! $nummer || ! $db ) return null;
            $id = (int) $nummer;
            if ( $id <= 0 ) return null;
            foreach ( $tabellen as $tabelle ) {
                $row = $db->get_row(
                    $db->prepare(
                        "SELECT produkt, preis, menge, typ, stock FROM `{$tabelle}` WHERE nummer = %d LIMIT 1",
                        $id
                    ),
                    ARRAY_A
                );
                // NUR rendern wenn stock > 0
                if ( $row && (int)($row['stock'] ?? 0) > 0 ) return $row;
            }
            return null; // nicht gefunden ODER stock=0
        };

        $ids      = [ $atts['id1'], $atts['id2'], $atts['id3'] ];
        $imgs     = [ $atts['img1'], $atts['img2'], $atts['img3'] ];
        $produkte = [];
        foreach ( $ids as $idx => $raw_id ) {
            $p = $get_produkt( $raw_id );
            $produkte[] = [ 'data' => $p, 'img' => $imgs[ $idx ] ?? '' ];
        }

        // Nur Slots mit gültigen Produkten
        $aktive = array_filter( $produkte, fn($p) => $p['data'] !== null );

        // Wenn gar keine aktiven Produkte → leerer Output (statt Fehler-Cards)
        if ( empty( $aktive ) ) {
            return $check_comment . "\n" . '<!-- wcr-ds-no-active-products -->';
        }

        $titel = esc_html( $atts['titel'] );

        ob_start();
        echo $check_comment . "\n";
        ?>
        <div class="wcr-produkte-wrap">

            <div class="wcr-produkte-header">
                <div class="wcr-produkte-header-line"></div>
                <div class="wcr-produkte-header-inner">
                    <div class="wcr-produkte-dot"></div>
                    <?php echo $titel; ?>
                    <div class="wcr-produkte-dot"></div>
                </div>
                <div class="wcr-produkte-header-line right"></div>
            </div>

            <div class="wcr-produkte-grid">
                <?php foreach ( $aktive as $slot ):
                    $p       = $slot['data'];
                    $img_url = ! empty( $slot['img'] ) ? esc_url( $slot['img'] ) : '';
                ?>
                <div class="wcr-produkte-card">

                    <?php if ( $img_url ) : ?>
                    <div class="wcr-produkte-img-wrap">
                        <div class="wcr-steam">
                            <span></span><span></span><span></span><span></span><span></span>
                        </div>
                        <img src="<?php echo $img_url; ?>" alt="<?php echo esc_attr( $p['produkt'] ?? '' ); ?>" loading="eager">
                    </div>
                    <?php endif; ?>

                    <div class="wcr-produkte-name">
                        <?php echo esc_html( $p['produkt'] ?? '–' ); ?>
                    </div>

                    <div class="wcr-produkte-divider"></div>

                    <div class="wcr-produkte-preis">
                        <?php
                        $preis = isset( $p['preis'] ) && $p['preis'] !== null
                            ? number_format( (float) $p['preis'], 2, ',', '.' )
                            : '–';
                        echo esc_html( $preis );
                        if ( $p['preis'] !== null ) :
                        ?><span class="wp-currency">€</span><?php endif; ?>
                    </div>

                    <?php if ( $show_menge && ! empty( $p['menge'] ) ) : ?>
                    <div class="wcr-produkte-menge">
                        <?php
                        $menge = rtrim( rtrim( number_format( (float) $p['menge'], 3, ',', '.' ), '0' ), ',' );
                        echo esc_html( $menge ) . ' l';
                        ?>
                    </div>
                    <?php endif; ?>

                </div>
                <?php endforeach; ?>
            </div>

        </div>
        <?php
        return ob_get_clean();
    }

    add_shortcode( 'wcr_produkte', 'wcr_sc_produkte' );
}
