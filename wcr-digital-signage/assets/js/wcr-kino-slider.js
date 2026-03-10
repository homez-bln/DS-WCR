/* ═══════════════════════════════════════════════════════
   WCR Kino Slider — wcr-kino-slider.js
   Endlos-horizontaler Film-Slider mit Duplikation für Loop
═══════════════════════════════════════════════════════ */
(function () {

    function initKinoSlider() {
        var wraps = document.querySelectorAll('.wcr-kino-slider-wrap');
        if (!wraps.length) return;

        wraps.forEach(function (wrap) {
            var apiUrl = wrap.getAttribute('data-api');
            var track  = wrap.querySelector('.wcr-kino-track');
            var placeholder = wrap.querySelector('.wcr-kino-placeholder');

            if (!apiUrl || !track) return;

            fetch(apiUrl, { credentials: 'same-origin' })
                .then(function (r) { return r.ok ? r.json() : []; })
                .then(function (films) {
                    if (!Array.isArray(films) || films.length === 0) {
                        if (placeholder) placeholder.innerHTML = '<p>🎬 Keine Filme verfügbar</p>';
                        return;
                    }

                    // ── Placeholder entfernen ──
                    if (placeholder) placeholder.remove();

                    // ── Film-Items rendern ──
                    var filmHTML = films.map(function (film) {
                        var title  = escapeHTML(film.title || 'Unbekannt');
                        var cover  = film.cover_url || '';
                        var date   = film.date || '';
                        var dateFmt = formatDate(date);

                        return '<div class="wcr-kino-item">' +
                               '  <div class="wcr-kino-cover">' +
                               '    <img src="' + cover + '" alt="' + title + '" loading="lazy" />' +
                               '  </div>' +
                               '  <div class="wcr-kino-info">' +
                               '    <h3 class="wcr-kino-title">' + title + '</h3>' +
                               '    <p class="wcr-kino-date">' + dateFmt + '</p>' +
                               '  </div>' +
                               '</div>';
                    }).join('');

                    // ── Duplikation für endlosen Loop ──
                    track.innerHTML = filmHTML + filmHTML;

                    // ── Animation-Dauer dynamisch anpassen (20 Filme = 60s, 10 Filme = 30s) ──
                    var duration = Math.max(30, films.length * 3); // 3s pro Film
                    track.style.animationDuration = duration + 's';
                })
                .catch(function (err) {
                    console.error('🚫 Kino API Fehler:', err);
                    if (placeholder) placeholder.innerHTML = '<p>❌ Fehler beim Laden</p>';
                });
        });
    }

    // ── Helper: HTML escapen ──
    function escapeHTML(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // ── Helper: Datum formatieren (YYYY-MM-DD → DD.MM.YYYY oder "Heute") ──
    function formatDate(dateStr) {
        if (!dateStr) return '';
        var parts = dateStr.split('-');
        if (parts.length !== 3) return dateStr;

        var y = parseInt(parts[0], 10);
        var m = parseInt(parts[1], 10);
        var d = parseInt(parts[2], 10);

        var filmDate = new Date(y, m - 1, d);
        var today    = new Date();
        today.setHours(0, 0, 0, 0);
        filmDate.setHours(0, 0, 0, 0);

        if (filmDate.getTime() === today.getTime()) {
            return '🍿 Heute';
        }

        // DD.MM.YYYY
        return ('0' + d).slice(-2) + '.' + ('0' + m).slice(-2) + '.' + y;
    }

    // ── Init on ready ──
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initKinoSlider);
    } else {
        initKinoSlider();
    }

})();
