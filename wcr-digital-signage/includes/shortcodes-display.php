<?php
/**
 * Display Shortcodes: Live-Daten & Wetter
 * 
 * Enthält Shortcode-Funktionen für Live-Daten-Anzeigen mit externen APIs.
 * Gemeinsames Merkmal: Nutzen externe APIs (Open-Meteo) und komplexe UI-Komponenten
 * 
 * KATEGORIEN:
 * - Wetter (Live-Wetterdaten)
 * - Wind (Live-Windkarten)
 * 
 * WICHTIG: Keine add_shortcode() hier – Registrierung bleibt in Hauptdatei!
 */

if (!defined('ABSPATH')) exit;

/* ════════════════════════════════════════════════════════════════════════════
   WIND-DISPLAY
   Live-Windkarte mit Leaflet.js und Canvas-Visualisierung
════════════════════════════════════════════════════════════════════════════ */

/**
 * [wcr_windmap] – Wind-Karte mit Live-Daten
 * 
 * ZEIGT:
 * - Leaflet-Karte mit Spot-Location
 * - Live-Windstärke in Knoten
 * - Windrichtung mit animiertem Kompass
 * - Timeline mit Forecast/Realtime Toggle
 * - Canvas-Overlays für Wind-Visualisierung
 * 
 * TECHNOLOGIE:
 * - Leaflet.js für Kartendarstellung
 * - Canvas für Wind-/Gust-Overlay
 * - Open-Meteo API für Wetterdaten
 * - wcr-windmap.js für Interaktivität
 * 
 * KOMPONENTEN:
 * - #map: Leaflet-Karte
 * - #wind-canvas, #gust-canvas: Visualisierungen
 * - #spot-header: Location-Info
 * - #time-card: Forecast/Realtime Toggle
 * - #right-panel: Windstärke + Windrose
 * - #timeline: Interaktive Zeitleiste
 * 
 * DEPENDENCIES: wcr_ds_load_leaflet() lädt Leaflet-Bibliothek
 */
function wcr_sc_windmap( $atts ) {
    wcr_ds_load_leaflet();
    wp_enqueue_script( 'wcr-windmap', WCR_DS_URL . 'assets/js/wcr-windmap.js', array('leaflet-js'), WCR_DS_VERSION, true );
    
    $out  = '<div id="wcr-windmap-wrap">';
    
    // Leaflet-Karte + Canvas-Overlays
    $out .= '<div id="map"></div>';
    $out .= '<canvas id="wind-canvas"></canvas>';
    $out .= '<canvas id="gust-canvas"></canvas>';
    
    // Spot-Header (Location-Info mit Live-Dot)
    $out .= '<div class="glass" id="spot-header">';
    $out .= '<div><div class="ds-live-dot"></div></div>';
    $out .= '<div>';
    $out .= '<div class="spot-name">📍 Wake &amp; Camp Ruhlsdorf</div>';
    $out .= '<div class="spot-coords">52.8213° N · 13.5754° E</div>';
    $out .= '</div>';
    $out .= '</div>';
    
    // Time-Card (Forecast/Realtime Toggle)
    $out .= '<div class="glass" id="time-card">';
    $out .= '<div id="tc-forecast">–</div>';
    $out .= '<div id="tc-realtime">–</div>';
    $out .= '<div id="tc-badge">Jetzt</div>';
    $out .= '</div>';
    
    // Right-Panel (Windstärke + Windrose)
    $out .= '<div id="right-panel">';
    
    // Windstärke-Box
    $out .= '<div id="kn-box">';
    $out .= '<div class="kn-icon">💨</div>';
    $out .= '<div class="kn-val" id="kn-speed">–</div>';
    $out .= '<div class="kn-unit">Knoten</div>';
    $out .= '<div class="kn-label">Windstärke</div>';
    $out .= '</div>';
    
    // Windrose (SVG-Kompass)
    $out .= '<div id="windrose-box">';
    $out .= '<svg width="100" height="100" viewBox="0 0 100 100">';
    $out .= '<circle cx="50" cy="50" r="44" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="1.5"/>';
    $out .= '<text x="50" y="12" text-anchor="middle" fill="#7a9abc" font-size="11">N</text>';
    $out .= '<text x="50" y="96" text-anchor="middle" fill="#7a9abc" font-size="11">S</text>';
    $out .= '<text x="92" y="54" text-anchor="middle" fill="#7a9abc" font-size="11">O</text>';
    $out .= '<text x="8" y="54" text-anchor="middle" fill="#7a9abc" font-size="11">W</text>';
    $out .= '<g id="wr-arrow" transform="rotate(0 50 50)">';
    $out .= '<polygon points="50,14 55,42 50,38 45,42" fill="#00c8ff" opacity="0.9"/>';
    $out .= '<polygon points="50,86 55,58 50,62 45,58" fill="rgba(0,200,255,0.2)"/>';
    $out .= '</g>';
    $out .= '</svg>';
    $out .= '<div id="wr-degs">–°</div>';
    $out .= '<div id="wr-label">Windrichtung</div>';
    $out .= '</div>';
    
    $out .= '</div>'; // #right-panel
    
    // Timeline (Interaktive Zeitleiste)
    $out .= '<div id="timeline">';
    $out .= '<div id="tl-current-time">';
    $out .= '<span id="tl-label-text">–</span>';
    $out .= '<span id="tl-delta"></span>';
    $out .= '</div>';
    $out .= '<div id="tl-track-wrap">';
    $out .= '<div id="tl-labels"></div>';
    $out .= '<div id="tl-rail">';
    $out .= '<div id="tl-fill"></div>';
    $out .= '<div id="tl-cursor"></div>';
    $out .= '</div>';
    $out .= '</div>';
    $out .= '</div>';
    
    $out .= '</div>'; // #wcr-windmap-wrap
    
    return $out;
}

/* ════════════════════════════════════════════════════════════════════════════
   WETTER-DISPLAY
   Live-Wetteranzeige mit aktuellen Daten und 7-Tage-Forecast
════════════════════════════════════════════════════════════════════════════ */

/**
 * [wcr_wetter] – Wetter-Anzeige
 * 
 * ZEIGT:
 * - Aktuelles Wetter mit Temperatur und Icon
 * - Gefühlte Temperatur
 * - Windgeschwindigkeit
 * - Regenwahrscheinlichkeit
 * - Sonnenuntergang
 * - 7-Tage-Vorhersage (Footer)
 * - Live-Uhr mit Datum
 * 
 * TECHNOLOGIE:
 * - Open-Meteo API für Wetterdaten
 * - wcr-wetter.js für Rendering und Updates
 * - Auto-Refresh alle 10 Minuten
 * 
 * KOMPONENTEN:
 * - header: Location + Live-Uhr
 * - main: Aktuelles Wetter (Hero + Details)
 * - footer: 7-Tage-Forecast-Grid
 * 
 * LAYOUT:
 * - Responsive Glass-Design
 * - Große Hero-Section für aktuelle Daten
 * - Grid-Layout für Detail-Cards
 */
function wcr_sc_wetter( $atts ) {
    wp_enqueue_script( 'wcr-wetter', WCR_DS_URL . 'assets/js/wcr-wetter.js', array(), '3.2.0', true );
    
    $out  = '<div id="wetter-wrap">';
    
    // Header (Location + Live-Uhr)
    $out .= '<header>';
    $out .= '<div>';
    $out .= '<div id="w-location">Ruhlsdorf</div>';
    $out .= '<div style="font-size:1.2rem;color:#64748b;">Aktuelles Wetter</div>';
    $out .= '</div>';
    $out .= '<div>';
    $out .= '<div id="clock-time">00:00</div>';
    $out .= '<div id="clock-date">Montag, 1. Januar</div>';
    $out .= '</div>';
    $out .= '</header>';
    
    // Main (Aktuelles Wetter)
    $out .= '<main>';
    
    // Hero-Section (Große Temperatur + Icon)
    $out .= '<div class="current-hero">';
    $out .= '<div class="hero-icon" id="cur-icon"></div>';
    $out .= '<div>';
    $out .= '<div class="hero-temp" id="cur-temp">--<span class="hero-unit">&deg;</span></div>';
    $out .= '<div class="hero-desc" id="cur-desc">Laden...</div>';
    $out .= '</div>';
    $out .= '</div>';
    
    // Detail-Cards (Gefühlt, Wind, Regen, Sonnenuntergang)
    $out .= '<div class="current-details">';
    $out .= '<div class="detail-card glass">';
    $out .= '<div class="dc-label">Gefühlt</div>';
    $out .= '<div class="dc-value" id="cur-feel">--&deg;</div>';
    $out .= '</div>';
    $out .= '<div class="detail-card glass">';
    $out .= '<div class="dc-label">Wind</div>';
    $out .= '<div class="dc-value" id="cur-wind">-- <span class="dc-sub">km/h</span></div>';
    $out .= '</div>';
    $out .= '<div class="detail-card glass">';
    $out .= '<div class="dc-label">Regenwahrsch.</div>';
    $out .= '<div class="dc-value" id="cur-rain">-- <span class="dc-sub">%</span></div>';
    $out .= '</div>';
    $out .= '<div class="detail-card glass">';
    $out .= '<div class="dc-label">Sonnenuntergang</div>';
    $out .= '<div class="dc-value" id="cur-sunset">--:--</div>';
    $out .= '</div>';
    $out .= '</div>';
    
    $out .= '</main>';
    
    // Footer (7-Tage-Forecast-Grid)
    $out .= '<footer id="forecast-grid"></footer>';
    
    $out .= '</div>'; // #wetter-wrap
    
    return $out;
}
