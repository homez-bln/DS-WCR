/**
 * WCR Merch Slideshow
 * assets/js/wcr-merch.js
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var wrap = document.getElementById('merch-wrap');
        if (!wrap) return;

        var slideIds    = JSON.parse(wrap.dataset.slides);
        var hlIds       = JSON.parse(wrap.dataset.highlights);
        var api         = wrap.dataset.api;
        var interval    = parseInt(wrap.dataset.interval) || 4000;

        var inner   = document.getElementById('merch-slide-inner');
        var dotsEl  = document.getElementById('merch-dots');
        var sidebar = document.getElementById('merch-sidebar');

        var items       = {};
        var current     = 0;
        var timer       = null;
        var isAnimating = false;

        // ── Daten laden ──────────────────────────────────────────
        fetch(api)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                data.forEach(function (d) { items[d.nummer] = d; });
                buildSlides();
                buildHighlights();
                showSlide(0, 'none');
                startTimer();
            })
            .catch(function (e) {
                console.warn('[WCR Merch] Fehler:', e);
            });

        // ── Slides bauen ─────────────────────────────────────────
        function buildSlides() {
            inner.innerHTML = '';
            slideIds.forEach(function (id, i) {
                var d = items[id];
                if (!d) return;

                var slide = document.createElement('div');
                slide.className = 'merch-slide';
                slide.dataset.index = i;

                slide.innerHTML =
                    '<div class="ms-img-wrap">' +
                        (d.bild_url
                            ? '<img src="' + d.bild_url + '" alt="' + esc(d.produkt) + '" class="ms-img">'
                            : '<div class="ms-img-placeholder">🛍</div>') +
                    '</div>' +
                    '<div class="ms-info glass">' +
                        '<div class="ms-name">'  + esc(d.produkt) + '</div>' +
                        '<div class="ms-price">' + fmtPreis(d.preis) + ' <span class="ms-euro">€</span></div>' +
                    '</div>';

                inner.appendChild(slide);
            });

            // Dots
            dotsEl.innerHTML = '';
            slideIds.forEach(function (_, i) {
                var dot = document.createElement('span');
                dot.className = 'merch-dot';
                dot.addEventListener('click', function () { goTo(i); });
                dotsEl.appendChild(dot);
            });
        }

        // ── Highlights bauen ─────────────────────────────────────
        function buildHighlights() {
            sidebar.innerHTML = '';
            hlIds.forEach(function (id) {
                var d = items[id];
                if (!d) return;

                var card = document.createElement('div');
                card.className = 'merch-hl glass';
                card.innerHTML =
                    (d.bild_url
                        ? '<img src="' + d.bild_url + '" alt="' + esc(d.produkt) + '" class="hl-img">'
                        : '<div class="hl-img-placeholder">🛍</div>') +
                    '<div class="hl-info">' +
                        '<div class="hl-name">'  + esc(d.produkt) + '</div>' +
                        '<div class="hl-price">' + fmtPreis(d.preis) + ' <span class="hl-euro">€</span></div>' +
                    '</div>';

                sidebar.appendChild(card);
            });
        }

        // ── Slide anzeigen ────────────────────────────────────────
        function showSlide(index, direction) {
            var slides = inner.querySelectorAll('.merch-slide');
            var dots   = dotsEl.querySelectorAll('.merch-dot');
            if (!slides.length) return;

            slides.forEach(function (s, i) {
                s.classList.remove('active', 'slide-in-right', 'slide-out-left', 'slide-in-left', 'slide-out-right');
            });
            dots.forEach(function (d) { d.classList.remove('active'); });

            if (direction === 'next') {
                slides[current] && slides[current].classList.add('slide-out-left');
                slides[index]   && slides[index].classList.add('slide-in-right', 'active');
            } else if (direction === 'prev') {
                slides[current] && slides[current].classList.add('slide-out-right');
                slides[index]   && slides[index].classList.add('slide-in-left', 'active');
            } else {
                slides[index] && slides[index].classList.add('active');
            }

            if (dots[index]) dots[index].classList.add('active');
            current = index;
        }

        function goTo(index) {
            if (isAnimating) return;
            var dir = index > current ? 'next' : 'prev';
            showSlide(index, dir);
            resetTimer();
        }

        function next() {
            var nextIndex = (current + 1) % slideIds.length;
            showSlide(nextIndex, 'next');
        }

        function startTimer() {
            timer = setInterval(next, interval);
        }

        function resetTimer() {
            clearInterval(timer);
            startTimer();
        }

        // ── Helpers ───────────────────────────────────────────────
        function esc(str) {
            return String(str || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function fmtPreis(v) {
            if (v === null || v === undefined || v === '') return '';
            return parseFloat(v).toFixed(2).replace('.', ',');
        }
    });
})();
