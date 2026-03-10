/* ???????????????????????????????????????????????????????????????
   WCR Windmap – wcr-windmap.js
   Leaflet Karte + Dual-Layer Partikel (Wind + Böen dynamisch)
   Version: 3.1.0
??????????????????????????????????????????????????????????????? */
(function () {

    const LAT     = 52.821313251670844;
    const LON     = 13.575415512498893;
    const MAP_LAT = 52.821428251670844;
    const MAP_LON = 13.5770999960116;
    const ZOOM          = 17.9;
    const HOURS         = 24;
    const CYCLE_SECONDS = 48;
    const W = 1920, H = 1080;

    // Wind-Partikel
    const PARTICLE_COUNT = 300;
    const MAX_AGE        = 160;
    const SPEED_SCALE    = 0.28;
    const FADE_ALPHA     = 0.96;
    const LINE_WIDTH     = 1.1;

    // Böen-Partikel (Pool-Maximum, aktive Anzahl ist dynamisch)
    const GUST_COUNT       = 100;
    const GUST_MAX_AGE     = 55;
    const GUST_SPEED_SCALE = 0.52;
    const GUST_LINE_WIDTH  = 1.4;

    /* ?? WIND FARBE (blau ? cyan ? grün ? gelb ? orange ? rot) ?? */
    function windColor(ms) {
        const stops = [
            [0,  [30,100,200]], [3,  [0,180,220]],  [6,  [80,210,160]],
            [10, [200,220,80]], [15, [255,160,30]],  [22, [220,40,40]]
        ];
        ms = Math.max(0, ms);
        for (let i = 1; i < stops.length; i++) {
            if (ms <= stops[i][0]) {
                const t = (ms - stops[i-1][0]) / (stops[i][0] - stops[i-1][0]);
                const a = stops[i-1][1], b = stops[i][1];
                return [
                    Math.round(a[0]+(b[0]-a[0])*t),
                    Math.round(a[1]+(b[1]-a[1])*t),
                    Math.round(a[2]+(b[2]-a[2])*t)
                ];
            }
        }
        return stops[stops.length-1][1];
    }

    /* ?? BÖEN FARBE (warm: gelb ? orange ? rot ? violett) ?? */
    function gustColor(kn) {
        const stops = [
            [0,  [255, 220,  80]],
            [8,  [255, 170,  20]],
            [15, [255, 100,   0]],
            [22, [230,  30,  30]],
            [30, [180,   0,  60]],
            [40, [120,   0, 120]]
        ];
        kn = Math.max(0, kn);
        for (let i = 1; i < stops.length; i++) {
            if (kn <= stops[i][0]) {
                const t = (kn - stops[i-1][0]) / (stops[i][0] - stops[i-1][0]);
                const a = stops[i-1][1], b = stops[i][1];
                return [
                    Math.round(a[0]+(b[0]-a[0])*t),
                    Math.round(a[1]+(b[1]-a[1])*t),
                    Math.round(a[2]+(b[2]-a[2])*t)
                ];
            }
        }
        return stops[stops.length-1][1];
    }

    /* ?? KARTE ?? */
    const map = L.map('map', {
        zoomControl: false, attributionControl: true,
        dragging: false, scrollWheelZoom: false,
        doubleClickZoom: false, touchZoom: false,
        zoomSnap: 0, preferCanvas: true
    }).setView([MAP_LAT, MAP_LON], ZOOM);

    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_nolabels/{z}/{x}/{y}{r}.png',
        { attribution: '© OpenStreetMap © CARTO', maxZoom: 19 }).addTo(map);

    /* ?? CANVAS (ein einziges für beide Schichten) ?? */
    const canvas = document.getElementById('wind-canvas');
    canvas.width = W; canvas.height = H;
    const ctx = canvas.getContext('2d');

    /* ?? WIND STATE ?? */
    let windU = 0, windV = 0, windMs = 0;
    let targetU = 0, targetV = 0, targetMs = 0;

    /* ?? BÖEN STATE ?? */
    let gustU = 0, gustV = 0, gustMs = 0;
    let targetGustU = 0, targetGustV = 0, targetGustMs = 0;
    let targetGustKn = 0, currentGustKn = 0;

    /* ?? PARTIKEL POOLS ?? */
    function newParticle() {
        return { x: Math.random()*W, y: Math.random()*H, age: Math.floor(Math.random()*MAX_AGE) };
    }
    function newGustParticle() {
        return { x: Math.random()*W, y: Math.random()*H, age: Math.floor(Math.random()*GUST_MAX_AGE) };
    }

    let particles     = Array.from({ length: PARTICLE_COUNT }, newParticle);
    let gustParticles = Array.from({ length: GUST_COUNT },     newGustParticle);

    /* ?? ANIMATION LOOP ?? */
    function animate() {
        requestAnimationFrame(animate);

        // Interpolation Wind
        windU  += (targetU  - windU)  * 0.05;
        windV  += (targetV  - windV)  * 0.05;
        windMs += (targetMs - windMs) * 0.05;

        // Interpolation Böen
        gustU         += (targetGustU  - gustU)         * 0.04;
        gustV         += (targetGustV  - gustV)         * 0.04;
        gustMs        += (targetGustMs - gustMs)        * 0.04;
        currentGustKn += (targetGustKn - currentGustKn) * 0.04;

        // Fade
        ctx.globalCompositeOperation = 'destination-in';
        ctx.fillStyle = `rgba(0,0,0,${FADE_ALPHA})`;
        ctx.fillRect(0, 0, W, H);
        ctx.globalCompositeOperation = 'source-over';

        /* ?? SCHICHT 1: Wind-Partikel (kühl) ?? */
        const [wr, wg, wb] = windColor(windMs);
        ctx.lineWidth = LINE_WIDTH;
        ctx.lineCap   = 'round';

        particles.forEach(p => {
            const nx = p.x + windU * SPEED_SCALE;
            const ny = p.y - windV * SPEED_SCALE;
            const lr    = p.age / MAX_AGE;
            const alpha = lr < 0.1 ? lr / 0.1 : 1 - (lr - 0.1) / 0.9;
            ctx.globalAlpha = Math.max(0, alpha) * 0.85;
            ctx.strokeStyle = `rgb(${wr},${wg},${wb})`;
            ctx.beginPath();
            ctx.moveTo(p.x, p.y);
            ctx.lineTo(nx, ny);
            ctx.stroke();
            p.x = nx; p.y = ny; p.age++;
            if (p.age >= MAX_AGE || nx < -20 || nx > W+20 || ny < -20 || ny > H+20) {
                const n = newParticle(); p.x = n.x; p.y = n.y; p.age = 0;
            }
        });

        /* ?? SCHICHT 2: Böen-Partikel (dynamisch) ?? */
        if (currentGustKn >= 3) {
            // Aktive Partikel: 0 bei 3 kn ? volles Pool bei 30+ kn
            const activeCount = Math.floor(
                GUST_COUNT * Math.min(1, (currentGustKn - 3) / 27)
            );
            // Opacity: 0.15 bei 3 kn ? 0.50 bei 30+ kn
            const gustOpacity = 0.15 + 0.35 * Math.min(1, (currentGustKn - 3) / 27);
            // Speed steigt leicht mit Böenstärke
            const dynamicSpeed = GUST_SPEED_SCALE * (0.8 + 0.4 * Math.min(1, currentGustKn / 25));

            const [gr, gg, gb] = gustColor(currentGustKn);
            ctx.lineWidth = GUST_LINE_WIDTH;

            for (let i = 0; i < activeCount; i++) {
                const p  = gustParticles[i];
                const nx = p.x + gustU * dynamicSpeed;
                const ny = p.y - gustV * dynamicSpeed;
                const lr    = p.age / GUST_MAX_AGE;
                const alpha = lr < 0.1 ? lr / 0.1 : 1 - (lr - 0.1) / 0.9;
                ctx.globalAlpha = Math.max(0, alpha) * gustOpacity;
                ctx.strokeStyle = `rgb(${gr},${gg},${gb})`;
                ctx.beginPath();
                ctx.moveTo(p.x, p.y);
                ctx.lineTo(nx, ny);
                ctx.stroke();
                p.x = nx; p.y = ny; p.age++;
                if (p.age >= GUST_MAX_AGE || nx < -20 || nx > W+20 || ny < -20 || ny > H+20) {
                    const n = newGustParticle(); p.x = n.x; p.y = n.y; p.age = 0;
                }
            }
        }

        ctx.globalAlpha = 1;
    }
    animate();

    /* ?? WIND + BÖEN SETZEN ?? */
    function setWind(speedKn, dirDeg, gustKn) {
        const ms  = speedKn * 0.5144;
        const rad = dirDeg * Math.PI / 180;
        targetU  = -ms * Math.sin(rad);
        targetV  = -ms * Math.cos(rad);
        targetMs = ms;

        const gms = (gustKn || 0) * 0.5144;
        targetGustU   = -gms * Math.sin(rad);
        targetGustV   = -gms * Math.cos(rad);
        targetGustMs  = gms;
        targetGustKn  = gustKn || 0;
    }

    /* ?? TIMELINE ?? */
    function buildTimeline(startTime) {
        const labelEl = document.getElementById('tl-labels');
        const rail    = document.getElementById('tl-rail');
        rail.querySelectorAll('.tl-tick').forEach(e => e.remove());
        labelEl.innerHTML = '';
        for (let i = 0; i <= HOURS; i++) {
            const pct  = (i / HOURS) * 100;
            const tick = document.createElement('div');
            tick.className = 'tl-tick' + (i % 3 === 0 ? ' major' : '');
            tick.style.left = pct + '%';
            rail.appendChild(tick);
            if (i % 3 === 0) {
                const t   = new Date(startTime.getTime() + i * 3600000);
                const lbl = document.createElement('span');
                lbl.style.cssText = `position:absolute;left:${pct}%;transform:translateX(-50%);white-space:nowrap`;
                lbl.textContent   = i === 0
                    ? 'Jetzt'
                    : t.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
                labelEl.appendChild(lbl);
            }
        }
    }

    /* ?? FORECAST ?? */
    let forecast = [], currentIdx = 0, playTimer = null;

    async function fetchForecast() {
        const url =
            'https://api.open-meteo.com/v1/forecast' +
            '?latitude=' + LAT + '&longitude=' + LON +
            '&hourly=temperature_2m,wind_speed_10m,wind_direction_10m,wind_gusts_10m,weather_code' +
            '&wind_speed_unit=kn&timezone=Europe%2FBerlin&forecast_days=2';
        const data = await (await fetch(url)).json();
        const now  = new Date();
        const si   = data.hourly.time.findIndex(t => new Date(t) >= now);
        forecast   = Array.from({ length: HOURS }, (_, i) => {
            const idx = si + i;
            return {
                time : new Date(data.hourly.time[idx]),
                speed: data.hourly.wind_speed_10m[idx],
                gust : data.hourly.wind_gusts_10m[idx],
                dir  : data.hourly.wind_direction_10m[idx],
                temp : data.hourly.temperature_2m[idx],
                wmo  : data.hourly.weather_code[idx]
            };
        });
        buildTimeline(forecast[0].time);
        renderHour(0);
        startPlay();
    }

    function renderHour(idx) {
        currentIdx = idx;
        const f = forecast[idx];
        if (!f) return;

        setWind(f.speed, f.dir, f.gust);

        document.getElementById('kn-speed').textContent = f.speed.toFixed(1);

        const label = f.time.toLocaleString('de-DE', {
            weekday: 'short', day: '2-digit', month: '2-digit',
            hour: '2-digit', minute: '2-digit'
        });
        document.getElementById('tc-forecast').textContent = label;
        document.getElementById('tc-realtime').textContent =
            'Live: ' + new Date().toLocaleTimeString('de-DE');
        document.getElementById('tc-badge').textContent = idx === 0 ? 'Jetzt' : '+' + idx + 'h';

        document.getElementById('tl-label-text').textContent = label;
        document.getElementById('tl-delta').textContent =
            idx === 0 ? '? Aktuelle Stunde' : '+' + idx + ' Stunden';

        const pct = (idx / (HOURS - 1)) * 100;
        document.getElementById('tl-fill').style.width  = pct + '%';
        document.getElementById('tl-cursor').style.left = pct + '%';

        document.getElementById('wr-arrow').setAttribute('transform', 'rotate(' + f.dir + ' 50 50)');
        document.getElementById('wr-degs').textContent = f.dir + '°';
    }

    const MS_PER_HOUR = (CYCLE_SECONDS * 1000) / HOURS;

    function startPlay() {
        clearInterval(playTimer);
        playTimer = setInterval(() => {
            renderHour(currentIdx < HOURS - 1 ? currentIdx + 1 : 0);
        }, MS_PER_HOUR);
    }

    fetchForecast();
    setInterval(() => { clearInterval(playTimer); fetchForecast(); }, 10 * 60 * 1000);

})();
