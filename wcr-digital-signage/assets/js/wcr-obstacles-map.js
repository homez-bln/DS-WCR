/* ═══════════════════════════════════════════════════════
   WCR Obstacles Map – wcr-obstacles-map.js
   Label zentriert unter PNG mit Counter-Rotation
═══════════════════════════════════════════════════════ */
(function () {

    const DEFAULT_CFG = {
        lat:   52.821428251670844,
        lon:   13.5770999960116,
        zoom:  17.9,
        rot:   0,
        style: 'voyager-nolabels'
    };

    const STYLES = {
        'voyager-nolabels': { url:'https://{s}.basemaps.cartocdn.com/rastertiles/voyager_nolabels/{z}/{x}/{y}{r}.png', attr:'© OpenStreetMap © CARTO' },
        'satellite':        { url:'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', attr:'Tiles © Esri' },
        'dark':             { url:'https://{s}.basemaps.cartocdn.com/dark_nolabels/{z}/{x}/{y}{r}.png', attr:'© OpenStreetMap © CARTO' },
        'light':            { url:'https://{s}.basemaps.cartocdn.com/light_nolabels/{z}/{x}/{y}{r}.png', attr:'© OpenStreetMap © CARTO' },
        'satellite-labels': { url:'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', attr:'Tiles © Esri' }
    };

    const TYPE_ICONS = { kicker:'🚀', rail:'🟧', box:'🟦', fun:'⭐', slider:'🟩', default:'🟣' };

    function getMode(wrap) {
        var m = (window.wcrObstaclesMap && window.wcrObstaclesMap.mode) || '';
        m = (m||'').toString().toLowerCase().trim();
        if (m==='portrait'||m==='landscape') return m;
        if (wrap && wrap.classList.contains('portrait')) return 'portrait';
        return 'landscape';
    }

    function getMapConfigUrl() {
        return (window.wcrObstaclesMap && window.wcrObstaclesMap.mapConfigUrl) || '';
    }

    function setStageRotation(stage, deg) {
        if (!stage) return;
        if (!isFinite(deg)) deg = 0;
        stage.style.transform = 'rotate(' + deg + 'deg)';
    }

    function initObstaclesMap() {
        var wrap  = document.getElementById('wcr-obstacles-map-wrap');
        var stage = document.getElementById('wcr-obstacles-stage');
        var el    = document.getElementById('wcr-obstacles-map');
        if (!el || !window.L) return;

        var mode        = getMode(wrap);
        var IS_PORTRAIT = (mode === 'portrait');
        var W = IS_PORTRAIT ? 1080 : 1920;
        var H = IS_PORTRAIT ? 1920 : 1080;

        var ICON_SIZE = IS_PORTRAIT ? 60   : 44;
        var FONT_SIZE = IS_PORTRAIT ? '36px' : '28px';
        var LBL_SIZE  = IS_PORTRAIT ? '15px' : '11px';

        var apiUrl = (window.wcrObstaclesMap && window.wcrObstaclesMap.apiUrl)
                     || el.getAttribute('data-api');

        // ── Overlay-Div über Leaflet (z-index:1000) ──
        var mapParent = el.parentNode;
        if (mapParent && mapParent.style.position !== 'absolute' && mapParent.style.position !== 'relative') {
            mapParent.style.position = 'relative';
        }
        var overlay = document.createElement('div');
        overlay.id = 'wcr-obstacles-overlay';
        overlay.style.cssText = [
            'position:absolute','top:0','left:0',
            'width:'+W+'px','height:'+H+'px',
            'z-index:1000','pointer-events:none','overflow:visible'
        ].join(';');
        el.insertAdjacentElement('afterend', overlay);

        // ── Leaflet Map ──
        var map = L.map(el, {
            zoomControl:false, attributionControl:true,
            dragging:false, scrollWheelZoom:false,
            doubleClickZoom:false, touchZoom:false,
            zoomSnap:0, preferCanvas:true
        }).setView([DEFAULT_CFG.lat, DEFAULT_CFG.lon], DEFAULT_CFG.zoom);

        setStageRotation(stage, DEFAULT_CFG.rot);

        // ── Map-Rotation tracken ──
        var mapRotation = 0;

        // ── Map-Config + Style laden ──
        var cfgUrl = getMapConfigUrl();
        if (cfgUrl) {
            var u = cfgUrl + (cfgUrl.indexOf('?')>=0?'&':'?') + 'mode=' + encodeURIComponent(mode);
            fetch(u, {credentials:'same-origin'})
                .then(function(r){return r.ok?r.json():null;})
                .then(function(cfg){
                    if (!cfg) return;
                    var lat=parseFloat(cfg.lat),lon=parseFloat(cfg.lon),zoom=parseFloat(cfg.zoom),rot=parseFloat(cfg.rot),style=cfg.style||DEFAULT_CFG.style;
                    if (isFinite(lat)&&isFinite(lon)&&isFinite(zoom)) map.setView([lat,lon],zoom);
                    if (isFinite(rot)){
                        mapRotation = rot;
                        setStageRotation(stage,rot);
                        map.invalidateSize({animate:false});
                    }
                    var sd=STYLES[style]||STYLES[DEFAULT_CFG.style];
                    L.tileLayer(sd.url,{attribution:sd.attr,maxZoom:21,detectRetina:true}).addTo(map);
                })
                .catch(function(){
                    var def=STYLES[DEFAULT_CFG.style];
                    L.tileLayer(def.url,{attribution:def.attr,maxZoom:21,detectRetina:true}).addTo(map);
                });
        } else {
            var def=STYLES[DEFAULT_CFG.style];
            L.tileLayer(def.url,{attribution:def.attr,maxZoom:21,detectRetina:true}).addTo(map);
        }
        setTimeout(function(){map.invalidateSize({animate:false});},150);

        // ── Obstacles rendern ──
        function renderObstacles(list) {
            list.forEach(function(o) {
                var lat = parseFloat(o.lat||0);
                var lon = parseFloat(o.lon||0);
                var rot = parseFloat(o.rotation||0);
                var type  = (o.type||'default').toLowerCase();
                var emoji = TYPE_ICONS[type]||TYPE_ICONS.default;
                var label = o.name||'';
                var ico   = o.icon_url||'';

                // ── Positions-Auswahl je nach Mode ──
                var px, py;
                if (IS_PORTRAIT) {
                    px = parseFloat(o.pos_x_p != null && o.pos_x_p !== '' ? o.pos_x_p : -1);
                    py = parseFloat(o.pos_y_p != null && o.pos_y_p !== '' ? o.pos_y_p : -1);
                } else {
                    px = parseFloat(o.pos_x_l != null && o.pos_x_l !== '' ? o.pos_x_l : -1);
                    py = parseFloat(o.pos_y_l != null && o.pos_y_l !== '' ? o.pos_y_l : -1);
                }
                // Fallback auf altes pos_x/pos_y wenn neue Felder leer
                if (px < 0) px = parseFloat(o.pos_x||0);
                if (py < 0) py = parseFloat(o.pos_y||0);

                if (lat !== 0 && lon !== 0) {
                    // ── Geo-Marker: PNG rotiert, Label zentriert unter PNG ──
                    var iconHtml = '<div style="display:flex;flex-direction:column;align-items:center;gap:4px;">';
                    
                    // Icon-Container (nur dieser rotiert)
                    iconHtml += '<div style="transform:rotate('+rot+'deg);">';
                    if (ico) {
                        // PNG mit Fallback
                        iconHtml += '<div style="position:relative;width:'+ICON_SIZE+'px;height:'+ICON_SIZE+'px;">';
                        iconHtml += '<img src="'+ico+'" '
                                 + 'style="width:100%;height:100%;object-fit:contain;filter:drop-shadow(0 2px 6px rgba(0,0,0,.7));position:absolute;top:0;left:0" '
                                 + 'onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'block\'" />';
                        iconHtml += '<div style="display:none;font-size:'+FONT_SIZE+';line-height:1;filter:drop-shadow(0 2px 5px rgba(0,0,0,.8));text-align:center">'+emoji+'</div>';
                        iconHtml += '</div>';
                    } else {
                        iconHtml += '<div style="font-size:'+FONT_SIZE+';line-height:1;filter:drop-shadow(0 2px 5px rgba(0,0,0,.8))">'+emoji+'</div>';
                    }
                    iconHtml += '</div>';
                    
                    // Label-Wrapper mit Counter-Rotation (zentriert)
                    if (label) {
                        var counterRot = -mapRotation;
                        iconHtml += '<div style="display:inline-block;transform:rotate('+counterRot+'deg);transform-origin:center;">';
                        iconHtml += '<span style="font-size:'+LBL_SIZE+';font-weight:700;color:#fff;text-shadow:0 1px 4px rgba(0,0,0,.9);white-space:nowrap;letter-spacing:.03em;">'+label+'</span>';
                        iconHtml += '</div>';
                    }
                    iconHtml += '</div>';
                    
                    L.marker([lat,lon],{
                        icon:L.divIcon({
                            html:iconHtml,
                            className:'wcr-leaflet-obstacle',
                            iconAnchor:[ICON_SIZE/2,ICON_SIZE/2]
                        }),
                        zIndexOffset:1000
                    }).addTo(map);

                } else if (px >= 0 && px <= 100 && py >= 0 && py <= 100) {
                    // ── Pixel-Position: PNG rotiert, Label horizontal ──
                    var d = document.createElement('div');
                    d.className = 'wcr-obstacle';
                    d.style.cssText = [
                        'position:absolute',
                        'left:'+(px/100*W)+'px',
                        'top:' +(py/100*H)+'px',
                        'transform:translate(-50%,-50%)',
                        'display:flex','flex-direction:column','align-items:center','gap:4px','pointer-events:none'
                    ].join(';');
                    
                    // Icon-Container (rotiert)
                    var iconWrap = document.createElement('div');
                    iconWrap.style.transform = 'rotate('+rot+'deg)';
                    
                    if (ico) {
                        // PNG mit Fallback
                        var imgEl = document.createElement('img');
                        imgEl.src = ico;
                        imgEl.style.cssText = 'width:'+ICON_SIZE+'px;height:'+ICON_SIZE+'px;object-fit:contain;filter:drop-shadow(0 2px 6px rgba(0,0,0,.5))';
                        imgEl.onerror = function() {
                            this.style.display = 'none';
                            var fallback = document.createElement('div');
                            fallback.textContent = emoji;
                            fallback.style.cssText = 'font-size:'+FONT_SIZE+';line-height:1;filter:drop-shadow(0 2px 6px rgba(0,0,0,.5))';
                            this.parentNode.insertBefore(fallback, this);
                        };
                        iconWrap.appendChild(imgEl);
                    } else {
                        var iconDiv = document.createElement('div');
                        iconDiv.textContent = emoji;
                        iconDiv.style.cssText = 'font-size:'+FONT_SIZE+';line-height:1;filter:drop-shadow(0 2px 6px rgba(0,0,0,.5))';
                        iconWrap.appendChild(iconDiv);
                    }
                    d.appendChild(iconWrap);
                    
                    // Label-Wrapper mit Counter-Rotation
                    if (label) {
                        var lblWrap = document.createElement('div');
                        lblWrap.style.cssText = 'display:inline-block;transform:rotate('+ (-mapRotation) +'deg);transform-origin:center';
                        var lblEl = document.createElement('span');
                        lblEl.textContent = label;
                        lblEl.style.cssText = 'font-size:'+LBL_SIZE+';font-weight:700;color:#fff;text-shadow:0 1px 4px rgba(0,0,0,.8);white-space:nowrap';
                        lblWrap.appendChild(lblEl);
                        d.appendChild(lblWrap);
                    }
                    overlay.appendChild(d);
                }
            });
        }

        if (apiUrl) {
            fetch(apiUrl)
                .then(function(r){return r.ok?r.json():[];})
                .then(function(data){
                    if (!Array.isArray(data)||data.length===0) return;
                    overlay.querySelectorAll('.wcr-obstacle').forEach(function(n){n.remove();});
                    var hint=(wrap||el).querySelector('.wcr-obstacles-placeholder-hint');
                    if (hint) hint.remove();
                    renderObstacles(data);
                })
                .catch(function(){});
        }
    }

    if (document.readyState==='loading') {
        document.addEventListener('DOMContentLoaded', initObstaclesMap);
    } else {
        initObstaclesMap();
    }

})();
