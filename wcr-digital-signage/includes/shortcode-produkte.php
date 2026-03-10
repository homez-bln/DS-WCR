<?php
/**
 * WCR Produkte Spotlight Shortcode
 *
 * Verwendung:
 *   [wcr_produkte id1="42" id2="17" id3="99"]
 *   [wcr_produkte id1="42" id2="17" id3="99" titel="Unsere Empfehlungen" table="food"]
 *
 * Parameter:
 *   id1, id2, id3  – Datenbank-Nummer (Spalte `nummer`) der Produkte
 *   titel          – Überschrift (Standard: "Unsere Empfehlungen")
 *   table          – Optional: Tabelle eingrenzen (food, drinks, cable, camping, extra, ice)
 *   img1,img2,img3 – Optional: URL zum Produkt-Bild (zeigt Dampf-Effekt darunter)
 *   show_menge     – Optional: "1" zeigt Mengenangaben, sonst ausgeblendet (Standard: "0")
 *
 * Jede Card zeigt: [Bild + Dampf] · Produktname · Preis · [optional: Menge]
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! function_exists( 'wcr_sc_produkte' ) ) {

    function wcr_sc_produkte( $atts ) {

        $atts = shortcode_atts( [
            'id1'        => '',
            'id2'        => '',
            'id3'        => '',
            'titel'      => 'Unsere Empfehlungen',
            'table'      => '',
            'img1'       => '',
            'img2'       => '',
            'img3'       => '',
            'show_menge' => '0',  // Standard: ausgeblendet
        ], $atts, 'wcr_produkte' );

        $show_menge = ( $atts['show_menge'] === '1' || $atts['show_menge'] === 'true' );

        // ── Assets laden ──
        wp_enqueue_style(
            'wcr-produkte',
            WCR_DS_URL . 'assets/css/wcr-produkte.css',
            [],
            WCR_DS_VERSION
        );
        wp_enqueue_script(
            'wcr-produkte-js',
            WCR_DS_URL . 'assets/js/wcr-produkte.js',
            [],
            WCR_DS_VERSION,
            true
        );

        // ── DB-Verbindung ──
        $db = get_ionos_db_connection();

        $erlaubte_tabellen = [ 'food', 'drinks', 'cable', 'camping', 'extra', 'ice' ];
        $tabellen = ( ! empty( $atts['table'] ) && in_array( $atts['table'], $erlaubte_tabellen, true ) )
            ? [ $atts['table'] ]
            : $erlaubte_tabellen;

        $get_produkt = function( $nummer ) use ( $db, $tabellen ) {
            if ( ! $nummer || ! $db ) return null;
            $id = (int) $nummer;
            if ( $id <= 0 ) return null;
            foreach ( $tabellen as $tabelle ) {
                $row = $db->get_row(
                    $db->prepare(
                        "SELECT produkt, preis, menge, typ FROM `$tabelle` WHERE nummer = %d LIMIT 1",
                        $id
                    ),
                    ARRAY_A
                );
                if ( $row ) return $row;
            }
            return null;
        };

        $ids    = [ $atts['id1'],  $atts['id2'],  $atts['id3']  ];
        $imgs   = [ $atts['img1'], $atts['img2'], $atts['img3'] ];
        $produkte = [];
        foreach ( $ids as $raw_id ) {
            $produkte[] = $get_produkt( $raw_id );
        }

        $titel = esc_html( $atts['titel'] );

        ob_start();
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
                <?php foreach ( $produkte as $i => $p ) :
                    $img_url = ! empty( $imgs[ $i ] ) ? esc_url( $imgs[ $i ] ) : '';
                ?>
                <div class="wcr-produkte-card<?php echo ( ! $p ) ? ' is-error' : ''; ?>">

                    <?php if ( $p ) : ?>

                        <?php if ( $img_url ) : ?>
                        <div class="wcr-produkte-img-wrap">
                            <!-- Dampf-Partikel -->
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
