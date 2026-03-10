/**
 * WCR Produkte Spotlight – Auto-Highlight Loop
 * Hebt Cards automatisch nacheinander hervor (card 1 → 2 → 3 → 1 ...)
 */
(function () {
    'use strict';

    var ACTIVE_DURATION = 2800;  // ms: wie lange eine Card leuchtet
    var PAUSE_DURATION  = 600;   // ms: kurze Pause zwischen den Cards

    function initProdukte() {
        var grids = document.querySelectorAll('.wcr-produkte-grid');
        if (!grids.length) return;

        grids.forEach(function (grid) {
            var cards = Array.from(grid.querySelectorAll('.wcr-produkte-card:not(.is-error)'));
            if (cards.length < 2) return;

            var current = 0;

            function highlight() {
                // Alle deaktivieren
                cards.forEach(function (c) { c.classList.remove('is-active'); });

                // Aktuelle aktivieren
                cards[current].classList.add('is-active');

                // Nächste berechnen
                var next = (current + 1) % cards.length;
                current  = next;

                // Nach ACTIVE_DURATION + PAUSE_DURATION weiterschalten
                setTimeout(highlight, ACTIVE_DURATION + PAUSE_DURATION);
            }

            // Ersten Durchlauf leicht verzögert starten
            setTimeout(highlight, 800);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initProdukte);
    } else {
        initProdukte();
    }
}());
