<?php
/**
 * Content Shortcodes: Menü & Preislisten
 * 
 * Enthält alle Shortcode-Funktionen für Menü- und Preisanzeigen.
 * Gemeinsames Merkmal: Nutzen WCR.renderDrinksList() aus wcr-ds-utils.js
 * 
 * KATEGORIEN:
 * - Getränke (Alkohol & Alkoholfrei)
 * - Speisen (Essen, Kaffee, Eis)
 * - Preise (Cablepark, Camping)
 * 
 * WICHTIG: Keine add_shortcode() hier – Registrierung bleibt in Hauptdatei!
 */

if (!defined('ABSPATH')) exit;

/* ════════════════════════════════════════════════════════════════════════════
   GETRÄNKE-KARTEN
   Zeigen Getränke-Sortiment aus Tabelle `drinks`
════════════════════════════════════════════════════════════════════════════ */

/**
 * [wcr_getraenke] – Alkoholische Getränke
 * 
 * ZEIGT: Bier, Weißbier, Wein, Mix-Getränke, Longdrinks, Shots
 * API: /wp-json/wakecamp/v1/drinks
 * RENDERER: WCR.renderDrinksList()
 */
function wcr_sc_getraenke( $atts ) {
    $out  = '<div id="drinks-display"></div>' . "\n";
    $out .= '<script>document.addEventListener("DOMContentLoaded",function(){';
    $out .= 'WCR.renderDrinksList("drinks-display",';
    $out .= '[{label:"Bier",types:["bier","weissbier","wein"]},';
    $out .= '{label:"Mix",types:["bier-mix","brlo","weinmix"]},';
    $out .= '{label:"Drinks",types:["longdrink","shots"]}],';
    $out .= '"/wp-json/wakecamp/v1/drinks"';
    $out .= ');});</script>' . "\n";
    return $out;
}

/**
 * [wcr_softdrinks] – Alkoholfreie Getränke
 * 
 * ZEIGT: Fritz-Kola, Fritz-Limo, Energy, Wasser, Tee, Chocolate
 * API: /wp-json/wakecamp/v1/drinks
 * RENDERER: WCR.renderDrinksList()
 */
function wcr_sc_softdrinks( $atts ) {
    $out  = '<div id="drinks-display"></div>' . "\n";
    $out .= '<script>document.addEventListener("DOMContentLoaded",function(){';
    $out .= 'WCR.renderDrinksList("drinks-display",';
    $out .= '[{label:"Softdrinks",types:["fritz-kola","fritz-limo","fritz-spritz","energy"]},';
    $out .= '{label:"Cold",types:["homemade","wasser"]},';
    $out .= '{label:"Hot",types:["tee","chocolate"]}],';
    $out .= '"/wp-json/wakecamp/v1/drinks"';
    $out .= ');});</script>' . "\n";
    return $out;
}

/* ════════════════════════════════════════════════════════════════════════════
   SPEISE-KARTEN
   Zeigen Speisen-Sortiment aus verschiedenen Tabellen
════════════════════════════════════════════════════════════════════════════ */

/**
 * [wcr_essen] – Speisekarte
 * 
 * ZEIGT: Bar, Grill, Küche mit Food-Gruppen-Status
 * API: /wp-json/wakecamp/v1/food + /wp-json/wakecamp/v1/food/gruppen
 * RENDERER: WCR.renderDrinksList()
 * BESONDERHEIT: Unterstützt Gruppen-Status (aktiv/inaktiv)
 */
function wcr_sc_essen( $atts ) {
    $out  = '<div id="drinks-display"></div>' . "\n";
    $out .= '<script>document.addEventListener("DOMContentLoaded",function(){';
    $out .= 'WCR.renderDrinksList("drinks-display",';
    $out .= '[{label:"Bar",types:["bar"]},';
    $out .= '{label:"Grill",types:["grill"]},';
    $out .= '{label:"Kueche",types:["kueche"]}],';
    $out .= '"/wp-json/wakecamp/v1/food",';
    $out .= '"/wp-json/wakecamp/v1/food/gruppen"';
    $out .= ');});</script>' . "\n";
    return $out;
}

/**
 * [wcr_kaffee] – Kaffeekarte
 * 
 * ZEIGT: Kaffee-Sortiment in Grid-Layout
 * API: Lädt Daten über wcr-kaffee.js
 * RENDERER: wcr-kaffee.js (spezialisiertes Script)
 * BESONDERHEIT: Eigenes JS-Script statt WCR.renderDrinksList()
 */
function wcr_sc_kaffee( $atts ) {
    wp_enqueue_script( 'wcr-kaffee', WCR_DS_URL . 'assets/js/wcr-kaffee.js', array(), WCR_DS_VERSION, true );
    
    $out  = '<div class="ds-header">';
    $out .= '<div class="ds-header-line"></div>';
    $out .= '<div class="ds-header-inner">';
    $out .= '<div class="ds-dot"></div>Kaffeekarte<div class="ds-dot"></div>';
    $out .= '</div>';
    $out .= '<div class="ds-header-line right"></div>';
    $out .= '</div>' . "\n";
    $out .= '<div class="kaffee-grid" id="kaffee-grid"></div>' . "\n";
    
    return $out;
}

/**
 * [wcr_eis] – Eiskarte
 * 
 * ZEIGT: Eis-Sortiment aus Tabelle `ice`
 * API: /wp-json/wakecamp/v1/ice
 * RENDERER: WCR.renderDrinksList()
 */
function wcr_sc_eis( $atts ) {
    $out  = '<div id="drinks-display"></div>' . "\n";
    $out .= '<script>document.addEventListener("DOMContentLoaded",function(){';
    $out .= 'WCR.renderDrinksList("drinks-display",';
    $out .= '[{label:"Eis",types:["obenlinks","obenrechts","untenlinks","untenrechts"]}],';
    $out .= '"/wp-json/wakecamp/v1/ice"';
    $out .= ');});</script>' . "\n";
    return $out;
}

/* ════════════════════════════════════════════════════════════════════════════
   PREIS-KARTEN
   Zeigen Preise für Services aus verschiedenen Tabellen
════════════════════════════════════════════════════════════════════════════ */

/**
 * [wcr_cable] – Cablepark-Preise
 * 
 * ZEIGT: Tickets, Verleih, Spezial aus Tabelle `cable`
 * API: /wp-json/wakecamp/v1/cable
 * RENDERER: WCR.renderDrinksList()
 */
function wcr_sc_cable( $atts ) {
    $out  = '<div id="drinks-display"></div>' . "\n";
    $out .= '<script>document.addEventListener("DOMContentLoaded",function(){';
    $out .= 'WCR.renderDrinksList("drinks-display",';
    $out .= '[{label:"Tickets",types:["Ticket","ticket","ticket-s","ticket-e","ticket-ed","ticket-a","ticket-d"]},';
    $out .= '{label:"Verleih",types:["board","hardware"]},';
    $out .= '{label:"Spezial",types:["spezial"]}],';
    $out .= '"/wp-json/wakecamp/v1/cable"';
    $out .= ');});</script>' . "\n";
    return $out;
}

/**
 * [wcr_camping] – Camping-Preise
 * 
 * ZEIGT: Personen, Übernachtung, Extras aus Tabelle `camping`
 * API: /wp-json/wakecamp/v1/camping
 * RENDERER: WCR.renderDrinksList()
 */
function wcr_sc_camping( $atts ) {
    $out  = '<div id="drinks-display"></div>' . "\n";
    $out .= '<script>document.addEventListener("DOMContentLoaded",function(){';
    $out .= 'WCR.renderDrinksList("drinks-display",';
    $out .= '[{label:"Personen",types:["personen"]},';
    $out .= '{label:"Fahrzeuge & Übernachtung",types:["night"]},';
    $out .= '{label:"Extras",types:["extra"]}],';
    $out .= '"/wp-json/wakecamp/v1/camping"';
    $out .= ');});</script>' . "\n";
    return $out;
}
