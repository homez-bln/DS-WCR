<?php
/**
 * WCR Produkte Spotlight Shortcode — v2 (dynamische Anzahl)
 *
 * Verwendung:
 *   [wcr_produkte ids="3010,3089,3162" table="food" titel="Burger-Grill"]
 *   [wcr_produkte ids="10,20,30,40,50,60" table="drinks" titel="Getränke"]
 *
 *   Backward-kompatibel: id1/id2/id3 + img1/img2/img3 funktionieren weiterhin.
 *
 * Parameter:
 *   ids           – Komma-getrennte Produkt-Nummern (beliebige Anzahl)
 *   id1…id9      – Legacy-Einzelparameter (rückwärtskompatibel)
 *   titel         – Überschrift (Standard: "Unsere Empfehlungen")
 *   table         – Optional: Tabelle eingrenzen (food, drinks, cable, camping, extra, ice)
 *   imgs          – Komma-getrennte Bild-URLs passend zu ids
 *   img1…img9    – Legacy-Einzelbilder
 *   show_menge    – "1" zeigt Mengenangaben
 *   cols          – Spaltenanzahl erzwingen (Standard: auto je nach Anzahl)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! function_exists( 'wcr_sc_produkte' ) ) {

    function wcr_sc_produkte( $atts ) {

        $atts = shortcode_atts( [
            'ids'        => '',
            // Legacy id1–9
            'id1'=>'','id2'=>'','id3'=>'','id4'=>'','id5'=>'',
            'id6'=>'','id7'=>'','id8'=>'','id9'=>'',
            'titel'      => 'Unsere Empfehlungen',
            'table'      => '',
            'imgs'       => '',
            // Legacy img1–9
            'img1'=>'','img2'=>'','img3'=>'','img4'=>'','img5'=>'',
            'img6'=>'','img7'=>'','img8'=>'','img9'=>'',
            'show_menge' => '0',
            'cols'       => '',   // '' = auto
        ], $atts, 'wcr_produkte' );

        // ─ IDs sammeln ───────────────────────────────────
        $raw_ids = [];
        if ( ! empty( $atts['ids'] ) ) {
            $raw_ids = array_map( 'trim', explode( ',', $atts['ids'] ) );
        } else {
            // Legacy id1–9
            foreach ( ['id1','id2','id3','id4','id5','id6','id7','id8','id9'] as $k ) {
                if ( ! empty( $atts[ $k ] ) ) $raw_ids[] = $atts[ $k ];
            }
        }
        $raw_ids = array_values( array_filter( $raw_ids ) );
        if ( empty( $raw_ids ) ) return '<!-- wcr_produkte: keine IDs -->';
        $count = count( $raw_ids );

        // ─ Bilder sammeln ─────────────────────────────────
        $raw_imgs = [];
        if ( ! empty( $atts['imgs'] ) ) {
            $raw_imgs = array_map( 'trim', explode( ',', $atts['imgs'] ) );
        } else {
            foreach ( ['img1','img2','img3','img4','img5','img6','img7','img8','img9'] as $k ) {
                $raw_imgs[] = $atts[ $k ] ?? '';
            }
        }

        // ─ Auto-Spalten ──────────────────────────────────
        if ( ! empty( $atts['cols'] ) && (int) $atts['cols'] > 0 ) {
            $cols = (int) $atts['cols'];
        } else {
            // Auto: schöne Verteilung
            $cols = match( true ) {
                $count <= 2 => $count,
                $count <= 4 => 2,
                $count <= 6 => 3,
                $count <= 8 => 4,
                default     => 4,
            };
        }

        $show_menge = ( $atts['show_menge'] === '1' || $atts['show_menge'] === 'true' );

        // ─ Assets ───────────────────────────────────────
        wp_enqueue_style( 'wcr-produkte', WCR_DS_URL . 'assets/css/wcr-produkte.css', [], WCR_DS_VERSION );
        wp_enqueue_script( 'wcr-produkte-js', WCR_DS_URL . 'assets/js/wcr-produkte.js', [], WCR_DS_VERSION, true );

        // ─ DB ───────────────────────────────────────────
        $db = get_ionos_db_connection();
        $erlaubte = [ 'food', 'drinks', 'cable', 'camping', 'extra', 'ice' ];
        $tabellen = ( ! empty( $atts['table'] ) && in_array( $atts['table'], $erlaubte, true ) )
            ? [ $atts['table'] ] : $erlaubte;

        $get_produkt = function( $nummer ) use ( $db, $tabellen ) {
            if ( ! $nummer || ! $db ) return null;
            $id = (int) $nummer;
            if ( $id <= 0 ) return null;
            foreach ( $tabellen as $tabelle ) {
                $row = $db->get_row(
                    $db->prepare( "SELECT produkt, preis, menge, typ FROM `$tabelle` WHERE nummer = %d LIMIT 1", $id ),
                    ARRAY_A
                );
                if ( $row ) return $row;
            }
            return null;
        };

        $produkte = array_map( $get_produkt, $raw_ids );
        $titel    = esc_html( $atts['titel'] );

        ob_start();
        ?>
        <div class="wcr-produkte-wrap" data-cols="<?= (int)$cols ?>" data-count="<?= (int)$count ?>">

            <div class="wcr-produkte-header">
                <div class="wcr-produkte-header-line"></div>
                <div class="wcr-produkte-header-inner">
                    <div class="wcr-produkte-dot"></div>
                    <?php echo $titel; ?>
                    <div class="wcr-produkte-dot"></div>
                </div>
                <div class="wcr-produkte-header-line right"></div>
            </div>

            <div class="wcr-produkte-grid" style="--wcr-cols:<?= (int)$cols ?>">
                <?php foreach ( $produkte as $i => $p ) :
                    $img_url = ! empty( $raw_imgs[ $i ] ) ? esc_url( $raw_imgs[ $i ] ) : '';
                ?>
                <div class="wcr-produkte-card<?php echo ( ! $p ) ? ' is-error' : ''; ?>">

                    <?php if ( $p ) : ?>

                        <?php if ( $img_url ) : ?>
                        <div class="wcr-produkte-img-wrap">
                            <div class="wcr-steam">
                                <span></span><span></span><span></span><span></span><span></span>
                            </div>
                            <img src="<?php echo $img_url; ?>" alt="<?php echo esc_attr( $p['produkt'] ?? '' ); ?>" loading="eager">
                        </div>
                        <?php endif; ?>

                        <div class="wcr-produkte-name"><?php echo esc_html( $p['produkt'] ?? '–' ); ?></div>
                        <div class="wcr-produkte-divider"></div>
                        <div class="wcr-produkte-preis">
                            <?php
                            $preis = isset( $p['preis'] ) && $p['preis'] !== null
                                ? number_format( (float) $p['preis'], 2, ',', '.' ) : '–';
                            echo esc_html( $preis );
                            if ( $p['preis'] !== null ) : ?><span class="wp-currency">€</span><?php endif;
                            ?>
                        </div>

                        <?php if ( $show_menge && ! empty( $p['menge'] ) ) : ?>
                        <div class="wcr-produkte-menge">
                            <?php
                            $menge = rtrim( rtrim( number_format( (float) $p['menge'], 3, ',', '.' ), '0' ), ',' );
                            echo esc_html( $menge ) . ' l';
                            ?>
                        </div>
                        <?php endif; ?>

                    <?php else : ?>
                        <div class="wcr-produkte-name">Produkt nicht gefunden</div>
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
