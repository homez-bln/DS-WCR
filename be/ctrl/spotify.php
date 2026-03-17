<?php
/**
 * ctrl/spotify.php — Spotify Controller
 * v1.2: App-Einstellungen in Settings-Popup (wie instagram.php)
 */
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/pisignage-config.php';
require_login();
if (!wcr_can('manage_users') && !wcr_is_cernal()) {
    http_response_code(403); include __DIR__.'/../inc/403.php'; exit;
}

function sp_cfg_load_ctrl(): array {
    if (!defined('DSC_WP_API_BASE')) return [];
    $ch=curl_init(DSC_WP_API_BASE.'/options/wcr_spotify_config?wcr_secret='.urlencode(DSC_WP_SECRET));
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>5,CURLOPT_SSL_VERIFYPEER=>false]);
    $b=curl_exec($ch);$c=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    if($c===200&&$b){$j=json_decode($b,true);if(isset($j['value'])&&is_array($j['value']))return $j['value'];}
    return [];
}
$cfg  = sp_cfg_load_ctrl();
$csrf = wcr_csrf_token();
$connected   = !empty($cfg['refresh_token']);
$redirectUri = (!empty($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST'].'/be/ctrl/spotify-callback.php';
?>
<!DOCTYPE html>
<html lang="de">
<head><meta charset="UTF-8"><title>Spotify</title></head>
<body class="bo" data-csrf="<?= htmlspecialchars($csrf) ?>">
<?php include __DIR__.'/../inc/menu.php'; ?>

<!-- ── Settings-Popup ──────────────────────────────────────────────────── -->
<div id="sp-settings-overlay" onclick="if(event.target===this)closeSpSettings()">
  <div id="sp-settings-popup">
    <div id="sp-settings-header">
      <h2>⚙️ App-Einstellungen</h2>
      <button id="sp-settings-close" onclick="closeSpSettings()" title="Schließen">✕</button>
    </div>
    <div id="sp-settings-body">

      <div class="sp-popup-layout">

        <div class="pi-panel">
          <h3>🔧 Spotify App Einstellungen</h3>
          <div class="sp-setup-steps">
            <div class="sp-step"><span class="sp-step-nr">1</span>
              Gehe zu <a href="https://developer.spotify.com/dashboard" target="_blank" rel="noopener">developer.spotify.com/dashboard</a>
            </div>
            <div class="sp-step"><span class="sp-step-nr">2</span>
              Erstelle eine neue App → <em>Web API</em> auswählen
            </div>
            <div class="sp-step"><span class="sp-step-nr">3</span>
              Füge als Redirect URI genau diese URL ein:<br>
              <code class="sp-redirect-uri"><?= htmlspecialchars($redirectUri) ?></code>
              <button type="button" class="sp-copy-btn" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($redirectUri) ?>')">📋 Kopieren</button>
            </div>
            <div class="sp-step"><span class="sp-step-nr">4</span>
              Client ID und Client Secret unten eintragen → Speichern → Verbinden
            </div>
          </div>
          <form id="sp-settings-form">
            <input type="hidden" name="action" value="save_settings">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <div class="pi-field">
              <label>Client ID</label>
              <input type="text" name="client_id" value="<?= htmlspecialchars($cfg['client_id']??'') ?>" placeholder="32-stelliger Hex-String">
            </div>
            <div class="pi-field">
              <label>Client Secret</label>
              <input type="password" name="client_secret" value="<?= htmlspecialchars($cfg['client_secret']??'') ?>" placeholder="Leer lassen um beizubehalten">
            </div>
            <div class="pi-field">
              <label>Redirect URI <span class="pi-hint">(automatisch befüllt)</span></label>
              <input type="text" name="redirect_uri" value="<?= htmlspecialchars($cfg['redirect_uri']??$redirectUri) ?>">
            </div>
            <div class="pi-actions">
              <button type="submit" class="btn-upload">💾 Speichern</button>
              <button type="button" class="btn-secondary" id="btn-start-auth">
                <?= $connected ? '🔄 Neu verbinden' : '🔗 Mit Spotify verbinden' ?>
              </button>
            </div>
          </form>
          <div class="upload-msg" id="settings-status" style="display:none"></div>
          <?php if ($connected): ?>
          <div class="sp-connected-badge">✅ Spotify verbunden</div>
          <?php endif; ?>
        </div>

        <div class="pi-panel">
          <h3>ℹ️ Berechtigungen</h3>
          <p style="font-size:13px;color:var(--text-muted);line-height:1.6">Folgende Spotify-Scopes werden angefragt:</p>
          <ul class="sp-scope-list">
            <li><code>user-read-playback-state</code> — Gerätestatus lesen</li>
            <li><code>user-modify-playback-state</code> — Wiedergabe steuern</li>
            <li><code>user-read-currently-playing</code> — Aktueller Song</li>
            <li><code>playlist-read-private</code> — Private Playlists</li>
            <li><code>playlist-read-collaborative</code> — Kollaborative Playlists</li>
          </ul>
          <div style="margin-top:16px;padding:12px 14px;background:rgba(29,185,84,.08);border-radius:8px;border:1px solid rgba(29,185,84,.25);font-size:13px;color:#1a6e35;">
            <strong>Hinweis:</strong> Für die Wiedergabe muss Spotify auf einem Gerät aktiv sein (App offen). Der Refresh Token wird sicher in wp_options gespeichert.
          </div>
        </div>

      </div>
    </div><!-- /sp-settings-body -->
  </div>
</div>

<!-- ── Header ──────────────────────────────────────────────────────────── -->
<div class="header-controls">
  <h1>🎵 Spotify</h1>
  <div style="display:flex;align-items:center;gap:12px;">
    <div id="sp-now-playing" class="sp-now-playing" style="display:none">
      <img id="sp-art" src="" alt="" class="sp-art">
      <div class="sp-np-info">
        <div id="sp-track-name" class="sp-track-name"></div>
        <div id="sp-artist-name" class="sp-artist-name"></div>
      </div>
      <div class="sp-controls">
        <button class="sp-ctrl-btn" id="sp-pause" title="Pause/Play">⏸</button>
        <button class="sp-ctrl-btn" id="sp-next"  title="Nächster">⏭</button>
      </div>
    </div>
    <button class="settings-btn" onclick="openSpSettings()">⚙️ App-Einstellungen</button>
  </div>
</div>

<?php if (!$connected): ?>
<div class="sp-notice">
  <div class="sp-notice-icon">🔗</div>
  <div class="sp-notice-text">
    <strong>Spotify noch nicht verbunden.</strong><br>
    Klicke auf <em>⚙️ App-Einstellungen</em> um die Verbindung einzurichten.
  </div>
</div>
<?php endif; ?>

<!-- ── Tabs (nur noch 2) ──────────────────────────────────────────────── -->
<div class="pi-tabs">
  <button class="pi-tab active" data-tab="playlists">🎵 Playlists</button>
  <button class="pi-tab" data-tab="queue">➕ Song hinzufügen</button>
</div>

<!-- ── TAB: PLAYLISTS ──────────────────────────────────────────────── -->
<div class="pi-tab-content active" id="tab-playlists">
  <div class="pi-section">
    <div class="pi-section-head">▶️ Playlist abspielen</div>
    <div class="pi-section-sub">Auf aktivem Gerät sofort starten — Gerät unten wählbar</div>
    <div id="playlist-grid" class="sp-playlist-grid"><div class="pi-loading">⏳ Lade Playlists …</div></div>
    <div class="upload-msg" id="play-status" style="display:none"></div>
  </div>
  <div class="pi-section">
    <div class="pi-section-head">📱 Aktives Gerät</div>
    <div id="device-list" class="sp-device-list"><div class="pi-loading">⏳ Lade Geräte …</div></div>
    <button type="button" class="btn-secondary" id="btn-reload-devices" style="margin-top:10px">🔄 Neu laden</button>
  </div>
</div>

<!-- ── TAB: QUEUE ───────────────────────────────────────────────────── -->
<div class="pi-tab-content" id="tab-queue">
  <div class="pi-section">
    <div class="pi-section-head">🔍 Song suchen &amp; zur Warteschlange hinzufügen</div>
    <div class="sp-search-row">
      <input type="text" id="search-input" placeholder="Künstler, Song oder Album …" class="sp-search-input">
      <button type="button" class="btn-upload" id="btn-search">🔍 Suchen</button>
    </div>
    <div id="search-results" class="sp-search-results"></div>
    <div class="upload-msg" id="queue-status" style="display:none"></div>
  </div>
</div>

<style>
/* ── Settings-Popup ────────────────────────────────────────────────── */
#sp-settings-overlay{
  display:none;position:fixed;inset:0;z-index:9999;
  background:rgba(0,0,0,.55);backdrop-filter:blur(3px);
  align-items:center;justify-content:center;
}
#sp-settings-overlay.open{display:flex;}
#sp-settings-popup{
  background:#fff;border-radius:16px;overflow:hidden;
  box-shadow:0 24px 80px rgba(0,0,0,.28);
  width:min(900px,95vw);height:min(84vh,780px);
  display:flex;flex-direction:column;
}
#sp-settings-header{
  display:flex;align-items:center;justify-content:space-between;
  padding:12px 18px;border-bottom:1px solid #e5e7eb;background:#f9fafb;flex-shrink:0;
}
#sp-settings-header h2{margin:0;font-size:.92rem;font-weight:700;display:flex;align-items:center;gap:7px;}
#sp-settings-close{background:none;border:none;font-size:1.25rem;cursor:pointer;color:#6b7280;padding:4px 8px;border-radius:6px;line-height:1;}
#sp-settings-close:hover{background:#f3f4f6;color:#111;}
#sp-settings-body{flex:1;overflow-y:auto;padding:20px 24px 28px;}
.sp-popup-layout{display:grid;grid-template-columns:1fr 1fr;gap:20px;}
@media(max-width:700px){.sp-popup-layout{grid-template-columns:1fr;}}
/* ── Settings-Button (gleicher Style) ────────────────────────────── */
.settings-btn{display:inline-flex;align-items:center;gap:6px;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:8px;color:#374151;font-size:.85rem;font-weight:600;padding:6px 14px;cursor:pointer;transition:background .15s;}
.settings-btn:hover{background:#e5e7eb;}
/* ── Now Playing Bar ──────────────────────────────────────────────── */
.sp-now-playing{display:flex;align-items:center;gap:12px;background:var(--bg-card);border:1px solid var(--border-light);border-radius:12px;padding:8px 14px;box-shadow:var(--shadow);}
.sp-art{width:44px;height:44px;border-radius:6px;object-fit:cover;flex-shrink:0;}
.sp-np-info{flex:1;min-width:0;}
.sp-track-name{font-size:13px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.sp-artist-name{font-size:12px;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.sp-controls{display:flex;gap:6px;}
.sp-ctrl-btn{background:none;border:1px solid var(--border-light);border-radius:8px;width:36px;height:36px;font-size:16px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .15s;}
.sp-ctrl-btn:hover{background:var(--border-light);}
/* ── Notice ───────────────────────────────────────────────────────── */
.sp-notice{display:flex;align-items:center;gap:14px;background:#fff8e1;border:1px solid #ffe082;border-radius:12px;padding:16px 20px;margin-bottom:20px;}
.sp-notice-icon{font-size:28px;}
.sp-notice-text{font-size:13px;color:#5d4037;line-height:1.5;}
/* ── Playlist Grid ──────────────────────────────────────────────── */
.sp-playlist-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;margin-bottom:8px;}
.sp-pl-card{background:var(--bg-subtle);border:1.5px solid var(--border-light);border-radius:12px;overflow:hidden;cursor:pointer;transition:transform .15s,box-shadow .15s,border-color .15s;}
.sp-pl-card:hover{transform:translateY(-2px);box-shadow:var(--shadow-hover);border-color:var(--border);}
.sp-pl-card:active{transform:scale(.97);}
.sp-pl-img{width:100%;aspect-ratio:1;object-fit:cover;display:block;background:var(--border-light);}
.sp-pl-img-placeholder{width:100%;aspect-ratio:1;background:linear-gradient(135deg,#1DB954,#158a3e);display:flex;align-items:center;justify-content:center;font-size:32px;}
.sp-pl-info{padding:10px 12px;}
.sp-pl-name{font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.sp-pl-count{font-size:11px;color:var(--text-muted);margin-top:2px;}
.sp-pl-card.playing{border-color:#1DB954;background:rgba(29,185,84,.06);}
/* ── Device List ────────────────────────────────────────────────── */
.sp-device-list{display:flex;flex-wrap:wrap;gap:8px;}
.sp-device-card{display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:10px;border:1.5px solid var(--border-light);background:var(--bg-subtle);cursor:pointer;font-size:13px;font-weight:500;transition:border-color .15s,background .15s;min-height:44px;}
.sp-device-card:hover{border-color:var(--border);background:var(--border-light);}
.sp-device-card.selected{border-color:#1DB954;background:rgba(29,185,84,.08);color:#158a3e;font-weight:600;}
.sp-device-icon{font-size:20px;}
/* ── Search ────────────────────────────────────────────────────────── */
.sp-search-row{display:flex;gap:10px;margin-bottom:16px;}
.sp-search-input{flex:1;padding:10px 14px;border:1.5px solid var(--border);border-radius:10px;font-size:14px;font-family:var(--font);color:var(--text-main);background:var(--bg-card);transition:border-color .15s;}
.sp-search-input:focus{outline:none;border-color:var(--primary);}
.sp-search-results{display:flex;flex-direction:column;gap:6px;}
.sp-track-row{display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:10px;border:1px solid var(--border-light);background:var(--bg-subtle);cursor:pointer;transition:background .13s;}
.sp-track-row:hover{background:var(--border-light);}
.sp-track-art{width:44px;height:44px;border-radius:6px;object-fit:cover;flex-shrink:0;}
.sp-track-info{flex:1;min-width:0;}
.sp-track-title{font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.sp-track-meta{font-size:12px;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.sp-track-dur{font-size:12px;color:var(--text-muted);flex-shrink:0;}
.sp-add-btn{background:none;border:1.5px solid var(--border);border-radius:8px;padding:6px 12px;font-size:12px;font-weight:600;cursor:pointer;white-space:nowrap;transition:background .13s,border-color .13s,color .13s;}
.sp-add-btn:hover{background:#1DB954;border-color:#1DB954;color:#fff;}
/* ── Setup Steps (im Popup) ─────────────────────────────────────── */
.sp-setup-steps{margin-bottom:20px;}
.sp-step{display:flex;align-items:flex-start;gap:12px;padding:10px 0;border-bottom:1px solid var(--border-xlight);font-size:13px;color:var(--text-main);line-height:1.5;}
.sp-step:last-child{border-bottom:none;}
.sp-step-nr{width:24px;height:24px;border-radius:50%;background:var(--primary);color:#fff;font-size:12px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px;}
.sp-redirect-uri{display:inline-block;background:var(--bg-body);border:1px solid var(--border-light);border-radius:6px;padding:4px 8px;font-size:12px;word-break:break-all;margin-top:4px;}
.sp-copy-btn{background:none;border:1px solid var(--border);border-radius:6px;padding:3px 8px;font-size:11px;cursor:pointer;margin-left:6px;transition:background .13s;}
.sp-copy-btn:hover{background:var(--border-light);}
.sp-scope-list{padding-left:18px;font-size:13px;color:var(--text-muted);line-height:2;}
.sp-scope-list code{background:var(--bg-body);padding:1px 5px;border-radius:4px;font-size:12px;}
.sp-connected-badge{margin-top:16px;padding:10px 14px;background:rgba(29,185,84,.1);border:1px solid rgba(29,185,84,.3);border-radius:8px;font-size:13px;font-weight:600;color:#158a3e;}
</style>

<script>
(function(){
'use strict';

const API='/be/api/spotify.php';
let DEVICES=[], SELECTED_DEVICE='';

function csrf(){return document.body.getAttribute('data-csrf')||'';}
function updateCsrf(t){if(!t)return;document.body.setAttribute('data-csrf',t);document.querySelectorAll('input[name="csrf_token"]').forEach(function(e){e.value=t;});}

async function api(obj){
    const fd=new FormData();
    fd.set('csrf_token',csrf());
    Object.entries(obj).forEach(function([k,v]){fd.set(k,v);});
    try{
        const r=await fetch(API,{method:'POST',body:fd,credentials:'same-origin'});
        const d=await r.json();
        if(d.csrf_token) updateCsrf(d.csrf_token);
        return d;
    }catch(e){return{success:false,error:e.message};}
}

// ── Settings-Popup ──────────────────────────────────────────────
// im globalen Scope damit onclick="..." funktioniert
window.openSpSettings = function(){
    var ov=document.getElementById('sp-settings-overlay');
    if(ov){ov.classList.add('open');document.body.style.overflow='hidden';}
};
window.closeSpSettings = function(){
    var ov=document.getElementById('sp-settings-overlay');
    if(ov){ov.classList.remove('open');document.body.style.overflow='';}
};
document.addEventListener('keydown',function(e){if(e.key==='Escape')window.closeSpSettings();});

// Tabs
document.querySelectorAll('.pi-tab').forEach(function(btn){
    btn.addEventListener('click',function(){
        document.querySelectorAll('.pi-tab,.pi-tab-content').forEach(function(el){el.classList.remove('active');});
        btn.classList.add('active');
        document.getElementById('tab-'+btn.dataset.tab).classList.add('active');
    });
});

function st(id,msg,type){const e=document.getElementById(id);if(!e)return;e.className='upload-msg '+type;e.textContent=msg;e.style.display='block';}
function msToTime(ms){const s=Math.floor(ms/1000);return Math.floor(s/60)+':'+(s%60).toString().padStart(2,'0');}
function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

// ── Geräte laden ────────────────────────────────────────────────────
async function loadDevices(){
    const r=await api({action:'get_devices'});
    const list=document.getElementById('device-list');
    const devices=(r.data?.devices)||[];
    DEVICES=devices;
    if(!devices.length){list.innerHTML='<div class="pi-loading">Kein aktives Gerät — Spotify App öffnen.</div>';return;}
    list.innerHTML='';
    devices.forEach(function(d){
        const isActive=d.is_active;
        if(!SELECTED_DEVICE&&isActive) SELECTED_DEVICE=d.id;
        const card=document.createElement('div');
        card.className='sp-device-card'+(isActive||d.id===SELECTED_DEVICE?' selected':'');
        card.dataset.id=d.id;
        const icons={'Computer':'💻','Smartphone':'📱','Speaker':'🔊','TV':'📺','CastAudio':'📡','CastVideo':'📺'};
        card.innerHTML='<span class="sp-device-icon">'+(icons[d.type]||'🎵')+'</span>'
            +'<span>'+esc(d.name)+'</span>'+(isActive?'<span style="font-size:11px;color:#1DB954;margin-left:auto">Aktiv</span>':'');
        card.addEventListener('click',function(){
            SELECTED_DEVICE=d.id;
            document.querySelectorAll('.sp-device-card').forEach(function(c){c.classList.remove('selected');});
            card.classList.add('selected');
        });
        list.appendChild(card);
    });
}

// ── Playlists laden ───────────────────────────────────────────────
async function loadPlaylists(){
    const r=await api({action:'get_playlists'});
    const grid=document.getElementById('playlist-grid');
    const items=(r.data?.items)||[];
    if(!items.length){grid.innerHTML='<div class="pi-loading">Keine Playlists gefunden.</div>';return;}
    grid.innerHTML='';
    items.forEach(function(pl){
        const card=document.createElement('div'); card.className='sp-pl-card';
        const imgUrl=pl.images?.[0]?.url||'';
        card.innerHTML=(imgUrl
            ?'<img class="sp-pl-img" src="'+esc(imgUrl)+'" loading="lazy" alt="">'
            :'<div class="sp-pl-img-placeholder">🎵</div>')
            +'<div class="sp-pl-info"><div class="sp-pl-name">'+esc(pl.name)+'</div>'
            +'<div class="sp-pl-count">'+(pl.tracks?.total||0)+' Songs</div></div>';
        card.addEventListener('click',function(){ playPlaylist(pl.uri,card); });
        grid.appendChild(card);
    });
}

async function playPlaylist(uri, card){
    card.style.opacity='.6';
    const r=await api({action:'play_playlist',uri:uri,device_id:SELECTED_DEVICE});
    card.style.opacity='';
    document.querySelectorAll('.sp-pl-card').forEach(function(c){c.classList.remove('playing');});
    if(r.success) card.classList.add('playing');
    st('play-status',r.message||r.error||JSON.stringify(r),r.success?'ok':'err');
    if(r.success) setTimeout(loadCurrentTrack,1500);
}

// ── Song suchen ───────────────────────────────────────────────────
document.getElementById('btn-search').addEventListener('click',searchTracks);
document.getElementById('search-input').addEventListener('keydown',function(e){if(e.key==='Enter')searchTracks();});

async function searchTracks(){
    const q=document.getElementById('search-input').value.trim();
    if(!q) return;
    document.getElementById('btn-search').textContent='⏳';
    const r=await api({action:'search_tracks',q:q});
    document.getElementById('btn-search').textContent='🔍 Suchen';
    const results=document.getElementById('search-results');
    const tracks=(r.data?.tracks?.items)||[];
    if(!tracks.length){results.innerHTML='<div class="pi-loading">Keine Ergebnisse.</div>';return;}
    results.innerHTML='';
    tracks.forEach(function(t){
        const row=document.createElement('div'); row.className='sp-track-row';
        const img=t.album?.images?.[2]?.url||t.album?.images?.[0]?.url||'';
        const artists=t.artists?.map(function(a){return a.name;}).join(', ')||'';
        row.innerHTML='<img class="sp-track-art" src="'+esc(img)+'" alt="">'
            +'<div class="sp-track-info"><div class="sp-track-title">'+esc(t.name)+'</div>'
            +'<div class="sp-track-meta">'+esc(artists)+' · '+esc(t.album?.name||'')+'</div></div>'
            +'<span class="sp-track-dur">'+msToTime(t.duration_ms||0)+'</span>'
            +'<button class="sp-add-btn" data-uri="'+esc(t.uri)+'">➕ Queue</button>';
        row.querySelector('.sp-add-btn').addEventListener('click',async function(e){
            e.stopPropagation();
            const btn=e.target; btn.textContent='⏳';
            const res=await api({action:'add_to_queue',uri:t.uri,device_id:SELECTED_DEVICE});
            btn.textContent=res.success?'✅ Added':'❌ Fehler';
            st('queue-status',res.message||res.error||'',res.success?'ok':'err');
            setTimeout(function(){btn.textContent='➕ Queue';},3000);
        });
        results.appendChild(row);
    });
}

// ── App-Einstellungen (Popup) ────────────────────────────────────────
document.getElementById('sp-settings-form').addEventListener('submit',async function(e){
    e.preventDefault();
    const fd=new FormData(this);
    const obj={}; fd.forEach(function(v,k){obj[k]=v;});
    const r=await api(obj);
    st('settings-status',r.message||r.error||JSON.stringify(r),r.success?'ok':'err');
});
document.getElementById('btn-start-auth').addEventListener('click',async function(){
    const r=await api({action:'start_auth'});
    if(r.success&&r.auth_url) window.open(r.auth_url,'_blank','width=500,height=700');
    else st('settings-status',r.error||'Fehler','err');
});

// ── Now Playing ──────────────────────────────────────────────────────
async function loadCurrentTrack(){
    const r=await api({action:'get_current'});
    const bar=document.getElementById('sp-now-playing');
    if(!r.success||!r.data?.item){bar.style.display='none';return;}
    const t=r.data.item;
    document.getElementById('sp-art').src=t.album?.images?.[2]?.url||t.album?.images?.[0]?.url||'';
    document.getElementById('sp-track-name').textContent=t.name||'';
    document.getElementById('sp-artist-name').textContent=t.artists?.map(function(a){return a.name;}).join(', ')||'';
    bar.style.display='flex';
}

document.getElementById('sp-pause').addEventListener('click',async function(){
    const isPlaying=this.textContent==='⏸';
    const r=await api({action:isPlaying?'pause':'resume'});
    if(r.success) this.textContent=isPlaying?'▶️':'⏸';
});
document.getElementById('sp-next').addEventListener('click',async function(){
    await api({action:'next_track'}); setTimeout(loadCurrentTrack,1000);
});
document.getElementById('btn-reload-devices').addEventListener('click',loadDevices);

// ── Init ────────────────────────────────────────────────────────────────
(async function init(){
    await Promise.all([loadDevices(),loadPlaylists(),loadCurrentTrack()]);
    setInterval(loadCurrentTrack,30000);
})();

})();
</script>

<?php include __DIR__.'/../inc/debug.php'; ?>
</body></html>
