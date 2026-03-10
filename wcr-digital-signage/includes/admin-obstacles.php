<?php
if (!defined('ABSPATH')) exit;

/* ════════════════════════════════════════════════════════
   WCR Obstacles – Admin-Seite
   • Karten-Einstellungen (Center + Zoom + Style + Rotation) per Mode
   • Obstacles mit lat/lon positionieren – einmal eingeben, alle Modi korrekt
   • Klick auf Karte → setzt lat/lon der markierten Obstacle-Zeile
════════════════════════════════════════════════════════ */

function wcr_obstacles_admin_page() {

    // ── Karten-Einstellungen per Mode ──
    $modes = ['landscape', 'portrait'];
    $cfg   = [];
    foreach ($modes as $m) {
        $cfg[$m] = [
            'lat'   => (float) get_option('wcr_obstacles_map_lat_'   . $m, get_option('wcr_obstacles_map_lat',  52.821428251670844)),
            'lon'   => (float) get_option('wcr_obstacles_map_lon_'   . $m, get_option('wcr_obstacles_map_lon',  13.5770999960116)),
            'zoom'  => (float) get_option('wcr_obstacles_map_zoom_'  . $m, get_option('wcr_obstacles_map_zoom', 17.9)),
            'rot'   => (float) get_option('wcr_obstacles_map_rot_'   . $m, get_option('wcr_obstacles_map_rot',  0)),
            'style' => (string)get_option('wcr_obstacles_map_style_' . $m, 'voyager-nolabels'),
        ];
    }

    // ── Obstacles aus DB ──
    $db        = get_ionos_db_connection();
    $obstacles = [];
    if ($db) {
        $obstacles = $db->get_results(
            "SELECT id, name, type, icon_url, lat, lon, rotation, active FROM obstacles ORDER BY id ASC",
            ARRAY_A
        ) ?: [];
    }

    $nonce       = wp_create_nonce('wcr_obstacles_map_config');
    $save_url    = rest_url('wakecamp/v1/obstacles/map-config');
    $obs_url     = rest_url('wakecamp/v1/obstacles');
    $cfg_json    = json_encode($cfg);
    $obs_json    = json_encode($obstacles);

    ?>
    <div class="wrap" id="wcr-obs-admin">
    <h1>🏄 Obstacles</h1>
    <p style="color:#aaa;margin-top:-6px;">Karten-Einstellungen für <code>[wcr_obstacles_map]</code> und Obstacle-Positionen verwalten.</p>

    <!-- ── Mode-Tabs ── -->
    <div class="wcr-obs-tabs">
        <button class="wcr-obs-tab active" data-mode="landscape">🖥 Landscape (1920×1080)</button>
        <button class="wcr-obs-tab"        data-mode="portrait" >📱 Portrait (1080×1920)</button>
    </div>

    <div style="display:grid;grid-template-columns:340px 1fr;gap:24px;margin-top:16px;">

        <!-- ── Slider-Panel ── -->
        <div>
            <div class="wcr-obs-card">
                <h3 style="margin:0 0 16px;font-size:14px;color:#ccc;">🗺 Karten-Einstellungen</h3>

                <div class="wcr-obs-field">
                    <label>🔍 Zoom <span id="lbl-zoom"></span></label>
                    <input type="range" id="sl-zoom" min="10" max="21" step="0.1">
                    <div class="wcr-obs-sub">Empfohlen: 16–19 · Standard: 17.9</div>
                </div>
                <div class="wcr-obs-field">
                    <label>📍 Latitude <span id="lbl-lat"></span></label>
                    <input type="range" id="sl-lat" min="52.75" max="52.90" step="0.0001">
                    <div class="wcr-obs-sub">N–S verschieben</div>
                </div>
                <div class="wcr-obs-field">
                    <label>📍 Longitude <span id="lbl-lon"></span></label>
                    <input type="range" id="sl-lon" min="13.50" max="13.65" step="0.0001">
                    <div class="wcr-obs-sub">W–O verschieben</div>
                </div>
                <div class="wcr-obs-field">
                    <label>🔄 Rotation <span id="lbl-rot"></span></label>
                    <input type="range" id="sl-rot" min="-180" max="180" step="0.5">
                    <div class="wcr-obs-sub">Karte drehen (Grad)</div>
                </div>

                <hr style="border-color:#333;margin:16px 0;">

                <div class="wcr-obs-field">
                    <label>Kartenstil</label>
                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                        <?php foreach (['voyager-nolabels'=>'OSM (clean)','satellite'=>'Satellite','dark'=>'Dark','light'=>'Light','satellite-labels'=>'Sat + Labels'] as $val => $lbl): ?>
                        <button class="wcr-style-btn" data-style="<?= esc_attr($val) ?>"><?= esc_html($lbl) ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div style="display:flex;gap:10px;margin-top:8px;">
                    <button id="btn-reset" class="button">↩ Standard</button>
                    <button id="btn-save"  class="button button-primary" style="flex:1;">💾 Karten-Config speichern</button>
                </div>
                <div id="wcr-obs-msg" style="margin-top:12px;font-size:13px;min-height:18px;"></div>
            </div>

            <!-- ── Hinweis-Box ── -->
            <div class="wcr-obs-card" style="margin-top:16px;background:rgba(0,180,255,.06);border-color:rgba(0,180,255,.2);">
                <p style="font-size:12px;color:#8ae;margin:0;line-height:1.6;">
                    <strong>📍 Obstacle platzieren:</strong><br>
                    Zeile in der Tabelle aktivieren (↓) → auf Karte klicken → lat/lon wird automatisch gesetzt.<br><br>
                    Obstacles mit gesetztem lat/lon erscheinen in <strong>allen Modi</strong> (Landscape + Portrait) korrekt auf der Karte, egal wie sie gedreht ist.
                </p>
            </div>
        </div>

        <!-- ── Karte + Obstacle-Tabelle ── -->
        <div>
            <!-- Karten-Vorschau -->
            <div style="position:relative;margin-bottom:16px;">
                <div id="wcr-obs-mode-label" style="font-size:12px;color:#666;margin-bottom:6px;"></div>
                <div id="wcr-obs-preview"
                     style="width:100%;height:440px;border-radius:10px;border:1px solid #333;overflow:hidden;background:#1a1a2e;">
                </div>
                <div id="wcr-obs-coords"
                     style="position:absolute;bottom:14px;left:14px;background:rgba(0,0,0,.75);color:#0ff;
                            font-size:11px;padding:4px 10px;border-radius:20px;font-family:monospace;pointer-events:none;">
                    Klick auf Karte = neues Zentrum &ndash; oder lat/lon der aktiven Obstacle-Zeile setzen
                </div>
            </div>

            <!-- Obstacle-Tabelle -->
            <div class="wcr-obs-card">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                    <h3 style="margin:0;font-size:14px;color:#ccc;">🏄 Obstacles
                        <span style="font-size:11px;color:#555;font-weight:400;margin-left:8px;">
                            Obstacles mit separaten Positionen pro Modus. Leer lassen = Obstacle in diesem Modus nicht angezeigt
                        </span>
                    </h3>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <span style="font-size:11px;color:#555;">Aktiver Modus:</span>
                        <span id="tbl-mode-badge" style="font-size:11px;font-weight:700;color:#4af;"></span>
                    </div>
                </div>

                <div style="overflow-x:auto;">
                <table class="wcr-obs-table" id="obs-table">
                    <thead>
                        <tr>
                            <th style="width:32px;">ID</th>
                            <th>Name</th>
                            <th>Typ</th>
                            <th style="width:110px;">Lat</th>
                            <th style="width:110px;">Lon</th>
                            <th style="width:60px;">Rotation</th>
                            <th>Icon-URL</th>
                            <th style="width:50px;">Aktiv</th>
                        </tr>
                    </thead>
                    <tbody id="obs-tbody">
                        <?php if (empty($obstacles)): ?>
                        <tr><td colspan="8" style="text-align:center;color:#555;padding:20px;">Noch keine Obstacles in der DB</td></tr>
                        <?php endif; ?>
                        <?php foreach ($obstacles as $i => $obs): ?>
                        <tr class="obs-row" data-id="<?= (int)$obs['id'] ?>">
                            <td style="color:#555;font-size:11px;">#<?= (int)$obs['id'] ?></td>
                            <td><input class="obs-field" data-col="name"     type="text"   value="<?= esc_attr($obs['name']     ?? '') ?>" placeholder="Name"></td>
                            <td><input class="obs-field" data-col="type"     type="text"   value="<?= esc_attr($obs['type']     ?? '') ?>" placeholder="kicker / rail / box"></td>
                            <td><input class="obs-field obs-lat" data-col="lat" type="number" step="0.000001" value="<?= esc_attr($obs['lat'] ?? '') ?>" placeholder="52.821…"></td>
                            <td><input class="obs-field obs-lon" data-col="lon" type="number" step="0.000001" value="<?= esc_attr($obs['lon'] ?? '') ?>" placeholder="13.577…"></td>
                            <td><input class="obs-field" data-col="rotation" type="number" step="1"       value="<?= esc_attr($obs['rotation'] ?? 0)  ?>" placeholder="0"></td>
                            <td><input class="obs-field" data-col="icon_url" type="text"   value="<?= esc_attr($obs['icon_url'] ?? '') ?>" placeholder="https://…/icon.png" style="width:100%;min-width:140px;"></td>
                            <td style="text-align:center;">
                                <input class="obs-field obs-active" data-col="active" type="checkbox" <?= !empty($obs['active']) ? 'checked' : '' ?>>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <!-- 3 leere Zeilen für neue Obstacles -->
                        <?php for ($n = 0; $n < 3; $n++): ?>
                        <tr class="obs-row obs-new" data-id="">
                            <td style="color:#555;font-size:11px;">#neu</td>
                            <td><input class="obs-field" data-col="name"     type="text"   value="" placeholder="Name"></td>
                            <td><input class="obs-field" data-col="type"     type="text"   value="" placeholder="kicker / rail / box"></td>
                            <td><input class="obs-field obs-lat" data-col="lat" type="number" step="0.000001" value="" placeholder="52.821…"></td>
                            <td><input class="obs-field obs-lon" data-col="lon" type="number" step="0.000001" value="" placeholder="13.577…"></td>
                            <td><input class="obs-field" data-col="rotation" type="number" step="1"       value="0" placeholder="0"></td>
                            <td><input class="obs-field" data-col="icon_url" type="text"   value="" placeholder="https://…/icon.png" style="width:100%;min-width:140px;"></td>
                            <td style="text-align:center;">
                                <input class="obs-field obs-active" data-col="active" type="checkbox" checked>
                            </td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
                </div>

                <div style="display:flex;gap:10px;margin-top:14px;align-items:center;">
                    <button id="btn-obs-save" class="button button-primary">💾 Obstacles speichern</button>
                    <button id="btn-obs-reload" class="button">🔄 Neu laden</button>
                    <div id="obs-msg" style="font-size:13px;min-height:18px;"></div>
                </div>
            </div>
        </div>
    </div>
    </div>

    <!-- Leaflet -->
    <link  rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <style>
    #wcr-obs-admin { max-width: 1500px; }
    #wcr-obs-admin h1 { font-size: 22px; color: #eee; }
    .wcr-obs-tabs { display:flex; gap:8px; margin-bottom:4px; }
    .wcr-obs-tab {
        padding: 8px 20px; border-radius: 8px 8px 0 0;
        background: #1a1a2e; border: 1px solid #333; border-bottom: none;
        color: #888; cursor: pointer; font-size: 13px; font-weight: 600;
        transition: all .15s;
    }
    .wcr-obs-tab.active { background: #222240; color: #4af; border-color: #4af; }
    .wcr-obs-card {
        background: #1a1a2e; border: 1px solid #333;
        border-radius: 0 12px 12px 12px; padding: 20px;
    }
    .wcr-obs-tabs .wcr-obs-tab:first-child.active ~ * { border-radius: 0 12px 12px 12px; }
    .wcr-obs-field { margin-bottom: 18px; }
    .wcr-obs-field label {
        display:block; font-size:13px; font-weight:600;
        color:#ccc; margin-bottom:6px;
    }
    .wcr-obs-field label span {
        font-family:monospace; color:#4af;
        background:rgba(0,180,255,.12); padding:1px 7px;
        border-radius:6px; margin-left:8px;
    }
    .wcr-obs-field input[type=range] {
        width:100%; height:6px;
        -webkit-appearance:none; appearance:none;
        background:linear-gradient(90deg,#4af 0%,#333 0%);
        border-radius:4px; outline:none; cursor:pointer;
    }
    .wcr-obs-field input[type=range]::-webkit-slider-thumb {
        -webkit-appearance:none; width:18px; height:18px;
        border-radius:50%; background:#4af;
        border:2px solid #111; cursor:pointer;
    }
    .wcr-obs-sub { font-size:11px; color:#555; margin-top:4px; }
    #wcr-obs-msg.ok, #obs-msg.ok  { color:#4f4; }
    #wcr-obs-msg.err,#obs-msg.err { color:#f44; }
    .wcr-style-btn {
        padding:5px 10px; font-size:11px; border-radius:6px;
        background:#222; border:1px solid #444; color:#aaa; cursor:pointer;
        transition: all .15s;
    }
    .wcr-style-btn.active { background:#2a3a5a; border-color:#4af; color:#4af; }
    /* Obstacle-Tabelle */
    .wcr-obs-table { width:100%; border-collapse:collapse; font-size:12px; }
    .wcr-obs-table th {
        text-align:left; padding:8px 6px; border-bottom:1px solid #333;
        color:#666; font-weight:600; white-space:nowrap;
    }
    .wcr-obs-table td { padding:4px 6px; border-bottom:1px solid #222; vertical-align:middle; }
    .wcr-obs-table .obs-row:hover td { background:rgba(0,180,255,.04); }
    .wcr-obs-table .obs-row.active-row td { background:rgba(0,180,255,.12); }
    .wcr-obs-table .obs-row.active-row { box-shadow:inset 3px 0 0 #4af; }
    .obs-field {
        width:100%; background:#111; border:1px solid #333;
        color:#ddd; padding:4px 6px; border-radius:4px; font-size:12px;
        box-sizing:border-box;
    }
    .obs-field:focus { border-color:#4af; outline:none; }
    .obs-row.active-row .obs-lat,
    .obs-row.active-row .obs-lon { border-color:#4af; background:#0d1a2a; }
    /* Map-Klick-Modus Indikator */
    #wcr-obs-preview.obs-click-mode { cursor:crosshair !important; }
    #wcr-obs-preview.obs-click-mode::after {
        content: '📍 Klick = Obstacle-Position setzen';
        position:absolute; top:8px; right:8px;
        background:rgba(0,100,255,.8); color:#fff;
        font-size:11px; padding:4px 10px; border-radius:12px;
        pointer-events:none;
    }
    </style>

    <script>
    (function(){
        var CFG         = <?= $cfg_json ?>;
        var OBS_DATA    = <?= $obs_json ?>;
        var NONCE       = '<?= esc_js($nonce) ?>';
        var SAVE_URL    = '<?= esc_js($save_url) ?>';
        var OBS_API     = '<?= esc_js($obs_url) ?>';
        var SAVE_OBS    = '<?= esc_js(rest_url('wakecamp/v1/obstacles/save')) ?>';

        var WP_AJAX     = '<?= esc_js(admin_url('admin-ajax.php')) ?>';

        var currentMode = 'landscape';
        var currentStyle = 'voyager-nolabels';
        var activeObsRow = null;   // aktuell angeklickte Obstacle-Zeile
        var obsTempMarkers = {};   // id → L.marker

        var STYLES = {
            'voyager-nolabels': { url:'https://{s}.basemaps.cartocdn.com/rastertiles/voyager_nolabels/{z}/{x}/{y}{r}.png', attr:'© OpenStreetMap © CARTO' },
            'satellite':        { url:'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', attr:'Tiles © Esri' },
            'dark':             { url:'https://{s}.basemaps.cartocdn.com/dark_nolabels/{z}/{x}/{y}{r}.png', attr:'© OpenStreetMap © CARTO' },
            'light':            { url:'https://{s}.basemaps.cartocdn.com/light_nolabels/{z}/{x}/{y}{r}.png', attr:'© OpenStreetMap © CARTO' },
            'satellite-labels': { url:'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', attr:'Tiles © Esri' }
        };

        var slZoom = document.getElementById('sl-zoom');
        var slLat  = document.getElementById('sl-lat');
        var slLon  = document.getElementById('sl-lon');
        var slRot  = document.getElementById('sl-rot');
        var lblZ   = document.getElementById('lbl-zoom');
        var lblLat = document.getElementById('lbl-lat');
        var lblLon = document.getElementById('lbl-lon');
        var lblRot = document.getElementById('lbl-rot');
        var msg    = document.getElementById('wcr-obs-msg');
        var coords = document.getElementById('wcr-obs-coords');
        var tblBadge = document.getElementById('tbl-mode-badge');

        /* ── Leaflet ── */
        var c = CFG[currentMode];
        var map = L.map('wcr-obs-preview', {
            zoomControl:true, dragging:true,
            scrollWheelZoom:true, zoomSnap:0.1, zoomDelta:0.1
        }).setView([c.lat, c.lon], c.zoom);

        var tileLayer = null;
        function setStyle(style) {
            if (tileLayer) map.removeLayer(tileLayer);
            var s = STYLES[style] || STYLES['voyager-nolabels'];
            tileLayer = L.tileLayer(s.url, {attribution:s.attr, maxZoom:21, detectRetina:true}).addTo(map);
            currentStyle = style;
            document.querySelectorAll('.wcr-style-btn').forEach(function(b){
                b.classList.toggle('active', b.dataset.style === style);
            });
        }
        setStyle(c.style || 'voyager-nolabels');

        /* Fadenkreuz-Marker */
        var crossIcon = L.divIcon({
            html: '<div style="width:24px;height:24px;transform:translate(-50%,-50%)">'
                + '<svg viewBox="0 0 24 24" width="24" height="24">'
                + '<line x1="12" y1="0" x2="12" y2="24" stroke="#0ff" stroke-width="2"/>'
                + '<line x1="0" y1="12" x2="24" y2="12" stroke="#0ff" stroke-width="2"/>'
                + '<circle cx="12" cy="12" r="3" fill="#0ff"/></svg></div>',
            className:'', iconAnchor:[12,12]
        });
        var crossMarker = L.marker([c.lat, c.lon], {icon:crossIcon, interactive:false, zIndexOffset:2000}).addTo(map);

        /* ── Obstacle-Marker auf Karte ── */
        function obsIcon(obs) {
            var TYPE_ICONS = {kicker:'🚀',rail:'🟧',box:'🟦',fun:'⭐',slider:'🟩','default':'🟣'};
            var emoji = TYPE_ICONS[(obs.type||'').toLowerCase()] || TYPE_ICONS['default'];
            var html = '<div style="display:flex;flex-direction:column;align-items:center;transform:rotate('+(obs.rotation||0)+'deg)">'
                + '<div style="font-size:22px;filter:drop-shadow(0 2px 4px rgba(0,0,0,.7))">' + emoji + '</div>'
                + '<span style="font-size:10px;font-weight:700;color:#fff;text-shadow:0 1px 3px rgba(0,0,0,.9);white-space:nowrap;background:rgba(0,0,0,.5);padding:1px 4px;border-radius:3px">' + (obs.name||'') + '</span>'
                + '</div>';
            return L.divIcon({html:html, className:'', iconAnchor:[16,16]});
        }

        function renderObsMarkers() {
            Object.values(obsTempMarkers).forEach(function(m){map.removeLayer(m);});
            obsTempMarkers = {};
            document.querySelectorAll('.obs-row').forEach(function(row){
                var lat = parseFloat(row.querySelector('.obs-lat').value);
                var lon = parseFloat(row.querySelector('.obs-lon').value);
                var id  = row.dataset.id || 'new_' + Math.random();
                var name = row.querySelector('[data-col=name]').value;
                var type = row.querySelector('[data-col=type]').value;
                var rot  = parseFloat(row.querySelector('[data-col=rotation]').value)||0;
                if (!isFinite(lat)||!isFinite(lon)||lat===0||lon===0) return;
                var mk = L.marker([lat,lon], {
                    icon: obsIcon({name:name, type:type, rotation:rot}),
                    draggable: true
                }).addTo(map);
                mk.on('dragend', function(e){
                    var ll = e.target.getLatLng();
                    row.querySelector('.obs-lat').value = ll.lat.toFixed(6);
                    row.querySelector('.obs-lon').value = ll.lng.toFixed(6);
                });
                mk.on('click', function(){
                    setActiveRow(row);
                });
                obsTempMarkers[id] = mk;
            });
        }

        /* ── Aktive Obstacle-Zeile ── */
        function setActiveRow(row) {
            document.querySelectorAll('.obs-row').forEach(function(r){r.classList.remove('active-row');});
            if (row) {
                row.classList.add('active-row');
                activeObsRow = row;
                var lat = row.querySelector('.obs-lat').value;
                var lon = row.querySelector('.obs-lon').value;
                coords.textContent = '📍 Aktiv: ' + (row.querySelector('[data-col=name]').value||'(neu)') + ' – Klick auf Karte setzt Position';
                if (lat && lon && parseFloat(lat) !== 0) {
                    map.panTo([parseFloat(lat), parseFloat(lon)]);
                }
            } else {
                activeObsRow = null;
                coords.textContent = 'Klick auf Karte = neues Karten-Zentrum';
            }
        }

        document.querySelectorAll('.obs-row').forEach(function(row){
            row.addEventListener('click', function(e){
                if (e.target.tagName === 'INPUT') {
                    setActiveRow(row);
                    return;
                }
                setActiveRow(activeObsRow === row ? null : row);
            });
            row.querySelector('.obs-lat').addEventListener('change', function(){ renderObsMarkers(); });
            row.querySelector('.obs-lon').addEventListener('change', function(){ renderObsMarkers(); });
        });

        /* ── Gradient helper ── */
        function updateGradient(input) {
            var pct = (input.value - input.min) / (input.max - input.min) * 100;
            input.style.background = 'linear-gradient(90deg,#4af '+pct+'%,#333 '+pct+'%)';
        }

        /* ── Slider → Karte ── */
        function applySliders() {
            var z   = parseFloat(slZoom.value);
            var lat = parseFloat(slLat.value);
            var lon = parseFloat(slLon.value);
            var rot = parseFloat(slRot.value);
            lblZ.textContent   = z.toFixed(1);
            lblLat.textContent = lat.toFixed(6);
            lblLon.textContent = lon.toFixed(6);
            lblRot.textContent = rot.toFixed(1) + '°';
            map.setView([lat,lon],z);
            crossMarker.setLatLng([lat,lon]);
            [slZoom,slLat,slLon,slRot].forEach(updateGradient);
        }

        slZoom.addEventListener('input', applySliders);
        slLat.addEventListener('input',  applySliders);
        slLon.addEventListener('input',  applySliders);
        slRot.addEventListener('input',  applySliders);

        /* Karte verschieben → Slider */
        map.on('moveend zoomend', function(){
            var cc = map.getCenter();
            var z  = map.getZoom();
            slLat.value  = cc.lat.toFixed(6);
            slLon.value  = cc.lng.toFixed(6);
            slZoom.value = z.toFixed(1);
            applySliders();
        });

        /* ── Klick auf Karte ── */
        map.on('click', function(e){
            if (activeObsRow) {
                // → setzt lat/lon der aktiven Obstacle-Zeile
                var lat = e.latlng.lat.toFixed(6);
                var lon = e.latlng.lng.toFixed(6);
                activeObsRow.querySelector('.obs-lat').value = lat;
                activeObsRow.querySelector('.obs-lon').value = lon;
                coords.textContent = '✅ ' + (activeObsRow.querySelector('[data-col=name]').value||'Obstacle') + ' → ' + lat + ', ' + lon;
                renderObsMarkers();
            } else {
                // → verschiebt Karten-Zentrum
                slLat.value = e.latlng.lat.toFixed(6);
                slLon.value = e.latlng.lng.toFixed(6);
                applySliders();
                map.panTo([parseFloat(slLat.value), parseFloat(slLon.value)]);
            }
        });

        /* ── Mode-Tabs ── */
        document.querySelectorAll('.wcr-obs-tab').forEach(function(btn){
            btn.addEventListener('click', function(){
                document.querySelectorAll('.wcr-obs-tab').forEach(function(b){b.classList.remove('active');});
                btn.classList.add('active');
                currentMode = btn.dataset.mode;
                var mc = CFG[currentMode];
                slLat.value  = mc.lat;
                slLon.value  = mc.lon;
                slZoom.value = mc.zoom;
                slRot.value  = mc.rot || 0;
                setStyle(mc.style || 'voyager-nolabels');
                applySliders();
                document.getElementById('wcr-obs-mode-label').textContent =
                    currentMode === 'portrait' ? '📱 Portrait-Modus (1080×1920)' : '🖥 Landscape-Modus (1920×1080)';
                tblBadge.textContent = currentMode === 'portrait' ? 'Portrait' : 'Landscape';
            });
        });

        /* ── Style-Buttons ── */
        document.querySelectorAll('.wcr-style-btn').forEach(function(btn){
            btn.addEventListener('click', function(){
                setStyle(btn.dataset.style);
            });
        });

        /* ── Reset ── */
        document.getElementById('btn-reset').addEventListener('click', function(){
            slZoom.value = 17.9;
            slLat.value  = 52.821428251670844;
            slLon.value  = 13.5770999960116;
            slRot.value  = 0;
            applySliders();
        });

        /* ── Karten-Config speichern ── */
        document.getElementById('btn-save').addEventListener('click', function(){
            var btn = this;
            btn.disabled = true;
            msg.className = '';
            msg.textContent = 'Speichern …';
            fetch(SAVE_URL, {
                method:'POST',
                headers:{'Content-Type':'application/json','X-WP-Nonce':NONCE},
                body: JSON.stringify({
                    mode:  currentMode,
                    lat:   parseFloat(slLat.value),
                    lon:   parseFloat(slLon.value),
                    zoom:  parseFloat(slZoom.value),
                    rot:   parseFloat(slRot.value),
                    style: currentStyle
                })
            })
            .then(function(r){return r.json();})
            .then(function(d){
                if (d && d.ok) {
                    CFG[currentMode] = {lat:parseFloat(slLat.value),lon:parseFloat(slLon.value),zoom:parseFloat(slZoom.value),rot:parseFloat(slRot.value),style:currentStyle};
                    msg.textContent = '✅ Gespeichert (' + currentMode + ')';
                    msg.className = 'ok';
                } else {
                    msg.textContent = '❌ ' + (d.message||JSON.stringify(d));
                    msg.className = 'err';
                }
            })
            .catch(function(e){ msg.textContent = '❌ ' + e.message; msg.className = 'err'; })
            .finally(function(){ btn.disabled = false; });
        });

        /* ── Obstacles speichern (AJAX) ── */
        document.getElementById('btn-obs-save').addEventListener('click', function(){
            var btn = this;
            var omsg = document.getElementById('obs-msg');
            btn.disabled = true;
            omsg.textContent = 'Speichern …';
            omsg.className = '';

            var rows = [];
            document.querySelectorAll('.obs-row').forEach(function(row){
                var name = row.querySelector('[data-col=name]').value.trim();
                if (!name) return; // leere Zeilen überspringen
                rows.push({
                    id:       row.dataset.id || '',
                    name:     name,
                    type:     row.querySelector('[data-col=type]').value.trim(),
                    lat:      parseFloat(row.querySelector('.obs-lat').value) || 0,
                    lon:      parseFloat(row.querySelector('.obs-lon').value) || 0,
                    rotation: parseFloat(row.querySelector('[data-col=rotation]').value) || 0,
                    icon_url: row.querySelector('[data-col=icon_url]').value.trim(),
                    active:   row.querySelector('.obs-active').checked ? 1 : 0
                });
            });

            fetch(WP_AJAX + '?action=wcr_save_obstacles&_wpnonce=' + encodeURIComponent(NONCE), {
                method:'POST',
                headers:{'Content-Type':'application/json'},
                body: JSON.stringify({obstacles: rows})
            })
            .then(function(r){return r.json();})
            .then(function(d){
                if (d && d.success) {
                    omsg.textContent = '✅ ' + (d.data||'Gespeichert');
                    omsg.className = 'ok';
                    renderObsMarkers();
                } else {
                    omsg.textContent = '❌ ' + (d.data||'Fehler');
                    omsg.className = 'err';
                }
            })
            .catch(function(e){ omsg.textContent = '❌ '+e.message; omsg.className='err'; })
            .finally(function(){ btn.disabled=false; });
        });

        /* Neu laden */
        document.getElementById('btn-obs-reload').addEventListener('click', function(){ location.reload(); });

        /* ── Init ── */
        document.getElementById('wcr-obs-mode-label').textContent = '🖥 Landscape-Modus (1920×1080)';
        tblBadge.textContent = 'Landscape';
        [slZoom,slLat,slLon,slRot].forEach(updateGradient);
        applySliders();
        setTimeout(function(){
            renderObsMarkers();
            map.invalidateSize();
        }, 300);
    })();
    </script>
    <?php
}
