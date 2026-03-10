/* =============================================================
   WCR Wetter Display – wcr-wetter.js  v2
============================================================= */
(function () {

    var CFG = { lat: 52.7963, lon: 13.5415 };

    var ICONS = {
        sun:   '<path fill="#fbbf24" d="M12 7c-2.76 0-5 2.24-5 5s2.24 5 5 5 5-2.24 5-5-2.24-5-5-5zm0 2c1.65 0 3 1.35 3 3s-1.35 3-3 3-3-1.35-3-3 1.35-3 3-3zM2 13h2c.55 0 1-.45 1-1s-.45-1-1-1H2c-.55 0-1 .45-1 1s.45 1 1 1zm18 0h2c.55 0 1-.45 1-1s-.45-1-1-1h-2c-.55 0-1 .45-1 1s.45 1 1 1zM11 2v2c0 .55.45 1 1 1s1-.45 1-1V2c0-.55-.45-1-1-1s-1 .45-1 1zm0 18v2c0 .55.45 1 1 1s1-.45 1-1v-2c0-.55-.45-1-1-1s-1 .45-1 1zM5.99 4.58a.996.996 0 00-1.41 0 .996.996 0 000 1.41l1.06 1.06c.39.39 1.03.39 1.41 0s.39-1.03 0-1.41L5.99 4.58zm12.37 12.37a.996.996 0 00-1.41 0 .996.996 0 000 1.41l1.06 1.06c.39.39 1.03.39 1.41 0s.39-1.03 0-1.41l-1.06-1.06zm1.06-10.96a.996.996 0 000-1.41.996.996 0 00-1.41 0l-1.06 1.06c-.39.39-.39 1.03 0 1.41s1.03.39 1.41 0l1.06-1.06zM7.05 18.36a.996.996 0 000-1.41.996.996 0 00-1.41 0l-1.06 1.06c-.39.39-.39 1.03 0 1.41s1.03.39 1.41 0l1.06-1.06z"/>',
        cloud: '<path fill="#94a3b8" d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96z"/>',
        rain:  '<path fill="#38bdf8" d="M12 4c-3.64 0-6.67 2.59-7.35 6.04C2.34 10.36 0 12.91 0 16c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96C18.67 6.59 15.64 4 12 4zm-4 13c-.55 0-1 .45-1 1s.45 1 1 1 1-.45 1-1-.45-1-1-1zm4 0c-.55 0-1 .45-1 1s.45 1 1 1 1-.45 1-1-.45-1-1-1zm4 0c-.55 0-1 .45-1 1s.45 1 1 1 1-.45 1-1-.45-1-1-1z"/>',
        snow:  '<path fill="#e2e8f0" d="M20.79 13.95L18.46 12l2.33-1.95a1 1 0 00-1.28-1.54l-2.81 2.35-.95-.46V8a1 1 0 00-2 0v2.4l-.95.46-2.81-2.35a1 1 0 00-1.28 1.54L11.04 12l-2.33 1.95a1 1 0 001.28 1.54l2.81-2.35.95.46V16a1 1 0 002 0v-2.4l.95-.46 2.81 2.35a1 1 0 001.28-1.54zM12 1a11 11 0 100 22A11 11 0 0012 1zm0 20a9 9 0 110-18 9 9 0 010 18z"/>',
        storm: '<path fill="#6366f1" d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM13 17.5l-3 5-1-3H7l3-5 1 3h2z"/>'
    };

    function getIcon(code) {
        var p = ICONS.cloud;
        if      (code === 0)  p = ICONS.sun;
        else if (code <= 3)   p = ICONS.cloud;
        else if (code <= 57)  p = ICONS.cloud;
        else if (code <= 67)  p = ICONS.rain;
        else if (code <= 77)  p = ICONS.snow;
        else if (code <= 82)  p = ICONS.rain;
        else if (code <= 86)  p = ICONS.snow;
        else if (code <= 99)  p = ICONS.storm;
        return '<svg viewBox="0 0 24 24">' + p + '</svg>';
    }

    function getText(code) {
        var map = {
            0:'Klar', 1:'Leicht bew\u00f6lkt', 2:'Bew\u00f6lkt', 3:'Bedeckt',
            45:'Nebel', 48:'Raureif',
            51:'Nieselregen', 53:'Nieselregen', 55:'Starker Niesel',
            61:'Leichter Regen', 63:'Regen', 65:'Starker Regen',
            71:'Leichter Schnee', 73:'Schneefall', 75:'Starker Schnee',
            80:'Regenschauer', 81:'Starke Schauer',
            95:'Gewitter', 96:'Gewitter & Hagel'
        };
        return map[code] || 'Unbekannt';
    }

    function set(id, html, isText) {
        var el = document.getElementById(id);
        if (!el) return;
        if (isText) { el.innerText = html; } else { el.innerHTML = html; }
    }

    function updateClock() {
        var now = new Date();
        set('clock-time', now.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' }), true);
        set('clock-date', now.toLocaleDateString('de-DE', { weekday: 'long', day: 'numeric', month: 'long' }), true);
    }

    async function fetchWeather() {
        try {
            var url = 'https://api.open-meteo.com/v1/forecast'
                + '?latitude='  + CFG.lat
                + '&longitude=' + CFG.lon
                + '&current=temperature_2m,apparent_temperature,weather_code,wind_speed_10m'
                + '&hourly=precipitation_probability'
                + '&daily=weather_code,temperature_2m_max,temperature_2m_min,sunset'
                + '&timezone=Europe%2FBerlin&forecast_days=6';

            var res = await fetch(url);
            if (!res.ok) { throw new Error('HTTP ' + res.status); }
            var data   = await res.json();
            var cur    = data.current;
            var daily  = data.daily;
            var hourly = data.hourly;

            set('cur-temp', Math.round(cur.temperature_2m) + '<span class="hero-unit">&deg;</span>');
            set('cur-desc', getText(cur.weather_code), true);
            set('cur-icon', getIcon(cur.weather_code));
            set('cur-feel', Math.round(cur.apparent_temperature) + '\u00b0', true);
            set('cur-wind', Math.round(cur.wind_speed_10m) + ' <span class="dc-sub">km/h</span>');

            var h = new Date().getHours();
            var slice = hourly.precipitation_probability.slice(h, h + 3);
            var rainProb = slice.length ? Math.max.apply(null, slice) : 0;
            set('cur-rain', rainProb + ' <span class="dc-sub">%</span>');
            set('cur-sunset', daily.sunset[0].slice(11, 16), true);

            var days = ['So','Mo','Di','Mi','Do','Fr','Sa'];
            var html = '';
            for (var i = 1; i <= 5; i++) {
                var date    = new Date(daily.time[i]);
                var dayName = days[date.getDay()];
                var dateFmt = date.toLocaleDateString('de-DE', { day:'2-digit', month:'2-digit' });
                html += '<div class="forecast-card glass">'
                      + '<div class="fc-day">'  + dayName + '</div>'
                      + '<div class="fc-date">' + dateFmt + '</div>'
                      + '<div class="fc-icon">' + getIcon(daily.weather_code[i]) + '</div>'
                      + '<div class="fc-temp">' + Math.round(daily.temperature_2m_max[i]) + '&deg;</div>'
                      + '<div class="fc-low">'  + Math.round(daily.temperature_2m_min[i]) + '&deg;</div>'
                      + '</div>';
            }
            set('forecast-grid', html);

        } catch (e) {
            console.error('WCR Wetter Fehler:', e);
            set('cur-desc', 'API Fehler: ' + e.message, true);
        }
    }

    function init() {
        updateClock();
        setInterval(updateClock, 1000);
        fetchWeather();
        setInterval(fetchWeather, 900000);
        setInterval(function () { location.reload(); }, 3600000);
    }

    if (document.readyState === 'complete') {
        init();
    } else {
        window.addEventListener('load', init);
    }

})();