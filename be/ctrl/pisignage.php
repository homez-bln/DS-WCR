<?php
/**
 * ctrl/pisignage.php — piSignage v3
 * 4 Tabs: Szenen | Assets | Playlists | Verbindung
 */
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/pisignage-config.php';
require_login();
if (!wcr_is_cernal()) { http_response_code(403); include __DIR__.'/../inc/403.php'; exit; }
$config = wcr_pisignage_load_config();
$csrf   = wcr_csrf_token();
?>
<!DOCTYPE html>
<html lang="de">
<head><meta charset="UTF-8"><title>piSignage</title></head>
<body class="bo" data-csrf="<?= htmlspecialchars($csrf) ?>">
<?php include __DIR__.'/../inc/menu.php'; ?>

<div class="header-controls">
  <h1>📺 piSignage</h1>
</div>

<!-- Tab-Navigation -->
<div class="pi-tabs">
  <button class="pi-tab active" data-tab="scenes">⚡ Szenen</button>
  <button class="pi-tab" data-tab="assets">🗂 Assets</button>
  <button class="pi-tab" data-tab="playlists">🎬 Playlists</button>
  <button class="pi-tab" data-tab="connection">🔌 Verbindung</button>
</div>

<!-- ══ TAB: SZENEN ═════════════════════════════════════════════════ -->
<div class="pi-tab-content active" id="tab-scenes">

  <!-- Szenen-Buttons -->
  <div class="pi-section">
    <div class="pi-section-head">⚡ Modus aktivieren</div>
    <div class="pi-section-sub">Ein Klick schaltet alle Screens gleichzeitig um</div>
    <div id="scene-tiles" class="scene-grid"><div class="pi-loading">⏳ Lade Szenen …</div></div>
    <div class="upload-msg" id="scene-trigger-status" style="display:none"></div>
    <div class="pi-log-box" id="scene-trigger-log" style="display:none">
      <div class="pi-log-title">Trigger-Protokoll</div><pre></pre>
    </div>
  </div>

  <!-- Szenen-Editor -->
  <div class="pi-section" id="scene-editor-wrap">
    <div class="pi-section-head">✏️ Szenen verwalten</div>
    <div class="pi-section-sub">Jede Szene definiert für jeden Screen eine Playlist — alles wird aus piSignage geladen</div>
    <div id="scene-editor-list"></div>
    <div class="pi-row" style="margin-top:12px;gap:12px;display:flex">
      <button type="button" class="btn-secondary" id="btn-add-scene">＋ Neue Szene</button>
      <button type="button" class="btn-upload" id="btn-save-scenes">💾 Alle Szenen speichern</button>
    </div>
    <div class="upload-msg" id="scene-save-status" style="display:none"></div>
  </div>
</div>

<!-- ══ TAB: ASSETS ══════════════════════════════════════════════════ -->
<div class="pi-tab-content" id="tab-assets">
  <div class="pi-section">
    <div class="pi-section-head">📂 Asset hochladen</div>
    <div class="pi-section-sub">Bilder (JPG/PNG/GIF/WebP) und Videos (MP4/WebM) direkt zu piSignage übertragen</div>
    <div class="asset-upload-zone" id="asset-drop-zone">
      <div class="auz-icon">📁</div>
      <div class="auz-text">Dateien hier ablegen oder klicken zum Auswählen</div>
      <div class="auz-hint">JPG · PNG · GIF · WebP · MP4 · WebM · PDF · max 500 MB</div>
      <input type="file" id="asset-file-input" multiple accept="image/*,video/mp4,video/webm,video/quicktime,application/pdf" style="display:none">
    </div>
    <div id="asset-upload-queue"></div>
    <div class="upload-msg" id="asset-upload-status" style="display:none"></div>
  </div>
  <div class="pi-section">
    <div class="pi-section-head-row">
      <div class="pi-section-head">🖼 Vorhandene Assets</div>
      <button type="button" class="btn-secondary btn-sm" id="btn-reload-assets">🔄 Neu laden</button>
    </div>
    <div id="asset-grid" class="asset-grid"><div class="pi-loading">⏳ Lade Assets …</div></div>
  </div>
</div>

<!-- ══ TAB: PLAYLISTS ═══════════════════════════════════════════════ -->
<div class="pi-tab-content" id="tab-playlists">
  <div class="pi-section">
    <div class="pi-section-head">➕ Neue Playlist erstellen</div>
    <div class="pi-field">
      <label>Playlist-Name</label>
      <input type="text" id="pl-new-name" placeholder="z.B. Wakeboard Sommer 2026" style="max-width:380px">
    </div>
    <div class="pi-section-sub" style="margin-bottom:8px">Assets auswählen und Reihenfolge festlegen:</div>
    <div id="pl-asset-picker" class="pl-asset-picker"><div class="pi-loading">⏳ Lade Assets …</div></div>
    <div id="pl-selected-list" class="pl-selected-list"></div>
    <div class="pi-row" style="margin-top:12px;display:flex;gap:10px">
      <button type="button" class="btn-upload" id="btn-create-playlist">➕ Playlist erstellen</button>
    </div>
    <div class="upload-msg" id="pl-create-status" style="display:none"></div>
  </div>
  <div class="pi-section">
    <div class="pi-section-head-row">
      <div class="pi-section-head">🎬 Vorhandene Playlists</div>
      <button type="button" class="btn-secondary btn-sm" id="btn-reload-playlists">🔄 Neu laden</button>
    </div>
    <div id="playlist-list" class="playlist-list"><div class="pi-loading">⏳ Lade Playlists …</div></div>
  </div>
</div>

<!-- ══ TAB: VERBINDUNG ══════════════════════════════════════════════ -->
<div class="pi-tab-content" id="tab-connection">
  <div class="pisignage-layout">
    <div class="pi-panel">
      <h3>🔌 Verbindung & Token</h3>
      <form id="pisignage-settings-form">
        <input type="hidden" name="action" value="save_settings">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <div class="pi-field"><label>Base URL</label><input type="text" name="base_url" value="<?= htmlspecialchars($config['base_url']) ?>" placeholder="https://homez_wcr.pisignage.com"></div>
        <div class="pi-field"><label>API Token</label><input type="text" name="api_token" value="<?= htmlspecialchars($config['api_token']) ?>" placeholder="x-access-token"></div>
        <div class="pi-divider">Automatischer Token-Abruf</div>
        <div class="pi-field"><label>E-Mail</label><input type="email" name="email" value="<?= htmlspecialchars($config['email']) ?>"></div>
        <div class="pi-field"><label>Passwort</label><input type="password" name="password" value="<?= htmlspecialchars($config['password']) ?>"></div>
        <div class="pi-field"><label>OTP <span class="pi-hint">nur bei MFA</span></label><input type="text" id="otp" name="otp" placeholder="6-stellig"></div>
        <div class="pi-actions">
          <button type="submit" class="btn-upload">💾 Speichern</button>
          <button type="button" class="btn-secondary" id="btn-request-token">🔑 Token abrufen</button>
          <button type="button" class="btn-secondary" id="btn-test-connection">🔗 Verbindung testen</button>
        </div>
      </form>
      <div class="upload-msg" id="settings-status" style="display:none"></div>
    </div>
    <div class="pi-panel">
      <h3>▶️ Manueller Trigger</h3>
      <form id="pisignage-trigger-form">
        <input type="hidden" name="action" value="set_playlist">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <div class="pi-field"><label>Gruppe</label><select id="group_id" name="group_id"><option value="">Laden …</option></select></div>
        <div class="pi-field"><label>Playlist</label><select id="playlist_id" name="playlist_id"><option value="">Laden …</option></select></div>
        <div class="pi-actions">
          <button type="submit" class="btn-upload">▶️ Triggern</button>
          <button type="button" class="btn-secondary" id="btn-reload-manual">🔄 Neu laden</button>
        </div>
      </form>
      <div class="upload-msg" id="trigger-status" style="display:none"></div>
      <div class="pi-log-box" id="pi-log" style="display:none"><div class="pi-log-title">API Response</div><pre></pre></div>
    </div>
  </div>
</div>

<!-- ══ CSS ════════════════════════════════════════════════════════ -->
<style>
/* Tabs */
.pi-tabs{display:flex;gap:4px;margin-bottom:20px;border-bottom:2px solid var(--border-light);padding-bottom:0;}
.pi-tab{background:none;border:none;padding:10px 18px;font-size:13px;font-weight:600;color:var(--text-muted);cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-2px;border-radius:6px 6px 0 0;transition:color .15s,border-color .15s;}
.pi-tab:hover{color:var(--text-main);}
.pi-tab.active{color:var(--primary);border-bottom-color:var(--primary);background:var(--bg-subtle);}
.pi-tab-content{display:none;}
.pi-tab-content.active{display:block;}
/* Sections */
.pi-section{background:var(--bg-card);border-radius:var(--radius);box-shadow:var(--shadow);padding:22px 24px;margin-bottom:20px;}
.pi-section-head{font-size:14px;font-weight:700;margin:0 0 3px;}
.pi-section-sub{font-size:12px;color:var(--text-muted);margin:0 0 16px;}
.pi-section-head-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;}
.pi-loading{color:var(--text-muted);font-size:13px;padding:8px 0;}
/* Scene tiles */
.scene-grid{display:flex;flex-wrap:wrap;gap:12px;margin-bottom:14px;}
.scene-tile{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;min-width:130px;padding:18px 14px;border-radius:12px;cursor:pointer;border:2px solid transparent;background:var(--bg-subtle);transition:transform .15s,box-shadow .15s;position:relative;}
.scene-tile:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(0,0,0,.18);}
.scene-tile.firing{opacity:.7;pointer-events:none;}
.scene-tile-icon{font-size:28px;line-height:1;}
.scene-tile-label{font-size:13px;font-weight:700;color:var(--text-main);text-align:center;}
.scene-tile-count{font-size:10px;color:var(--text-muted);}
.scene-tile-bar{position:absolute;bottom:0;left:0;right:0;height:3px;border-radius:0 0 10px 10px;}
/* Scene editor */
.scene-card{background:var(--bg-subtle);border:1px solid var(--border-light);border-radius:10px;padding:16px 18px;margin-bottom:12px;}
.scene-card-head{display:flex;align-items:center;gap:8px;margin-bottom:14px;flex-wrap:wrap;}
.scene-card-head input[type=text]{padding:7px 10px;border:1px solid var(--border);border-radius:7px;font-size:13px;background:var(--bg-card);color:var(--text-main);}
.sc-icon{width:52px;}
.sc-label{flex:1;min-width:140px;}
.sc-color{width:40px;height:34px;padding:2px;border-radius:7px;cursor:pointer;border:1px solid var(--border);}
.sc-del{background:transparent;border:1px solid var(--border);border-radius:7px;padding:6px 10px;color:var(--text-muted);cursor:pointer;font-size:13px;margin-left:auto;}
.sc-del:hover{background:#fff0f0;color:#c0392b;border-color:#ffd0cc;}
/* Scene matrix */
.scene-matrix{width:100%;border-collapse:collapse;font-size:13px;}
.scene-matrix th{text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);padding:6px 8px;border-bottom:1px solid var(--border-light);}
.scene-matrix td{padding:6px 8px;border-bottom:1px solid var(--border-light);vertical-align:middle;}
.scene-matrix td:first-child{font-weight:600;color:var(--text-main);white-space:nowrap;font-size:12px;}
.scene-matrix select{width:100%;padding:6px 8px;border:1px solid var(--border);border-radius:6px;font-size:12px;background:var(--bg-card);color:var(--text-main);}
.scene-matrix tr:last-child td{border-bottom:none;}
/* Asset upload */
.asset-upload-zone{border:2px dashed var(--border);border-radius:12px;padding:32px 24px;text-align:center;cursor:pointer;transition:border-color .2s,background .2s;margin-bottom:16px;}
.asset-upload-zone:hover,.asset-upload-zone.drag-over{border-color:var(--primary);background:rgba(1,158,227,.05);}
.auz-icon{font-size:36px;margin-bottom:8px;}
.auz-text{font-size:14px;font-weight:600;color:var(--text-main);margin-bottom:4px;}
.auz-hint{font-size:12px;color:var(--text-muted);}
.upload-queue-item{display:flex;align-items:center;gap:10px;padding:8px 10px;background:var(--bg-subtle);border-radius:8px;margin-bottom:6px;font-size:13px;}
.uqi-name{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.uqi-bar-wrap{width:120px;height:6px;background:var(--border-light);border-radius:3px;overflow:hidden;}
.uqi-bar{height:100%;background:var(--primary);border-radius:3px;transition:width .3s;}
.uqi-status{font-size:12px;min-width:60px;text-align:right;}
/* Asset grid */
.asset-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:10px;}
.asset-card{background:var(--bg-subtle);border:1px solid var(--border-light);border-radius:8px;overflow:hidden;position:relative;}
.asset-card img,.asset-card video{width:100%;height:80px;object-fit:cover;display:block;}
.asset-card-name{font-size:11px;padding:4px 6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--text-main);}
.asset-card-del{position:absolute;top:4px;right:4px;background:rgba(0,0,0,.6);color:#fff;border:none;border-radius:4px;width:22px;height:22px;cursor:pointer;font-size:12px;display:none;align-items:center;justify-content:center;}
.asset-card:hover .asset-card-del{display:flex;}
.asset-card-type{position:absolute;top:4px;left:4px;background:rgba(0,0,0,.55);color:#fff;border-radius:4px;font-size:10px;padding:2px 5px;}
/* Playlist */
.pl-asset-picker{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px;max-height:260px;overflow-y:auto;padding:4px;}
.pl-asset-thumb{width:90px;text-align:center;cursor:pointer;border:2px solid transparent;border-radius:8px;padding:4px;transition:border-color .15s;}
.pl-asset-thumb:hover{border-color:var(--primary);}
.pl-asset-thumb.selected{border-color:var(--primary);background:rgba(1,158,227,.08);}
.pl-asset-thumb img,.pl-asset-thumb video{width:80px;height:55px;object-fit:cover;border-radius:5px;display:block;margin:0 auto;}
.pl-asset-thumb-name{font-size:10px;color:var(--text-muted);margin-top:3px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.pl-selected-list{margin-bottom:10px;}
.pl-sel-row{display:flex;align-items:center;gap:8px;padding:6px 8px;background:var(--bg-subtle);border-radius:7px;margin-bottom:4px;font-size:12px;}
.pl-sel-dur{width:60px;}
.pl-sel-dur input{width:100%;padding:4px;border:1px solid var(--border);border-radius:5px;font-size:12px;text-align:center;}
.pl-sel-del{background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:14px;}
.pl-sel-del:hover{color:#c0392b;}
.playlist-list{display:flex;flex-direction:column;gap:8px;}
.playlist-row{display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--bg-subtle);border-radius:8px;font-size:13px;}
.playlist-row-name{flex:1;font-weight:600;}
.playlist-row-count{font-size:11px;color:var(--text-muted);}
.playlist-row-del{background:transparent;border:1px solid var(--border);border-radius:6px;padding:4px 8px;color:var(--text-muted);cursor:pointer;font-size:12px;}
.playlist-row-del:hover{background:#fff0f0;color:#c0392b;border-color:#ffd0cc;}
/* Shared */
.pisignage-layout{display:grid;grid-template-columns:1fr 1fr;gap:24px;}
.pi-panel{background:var(--bg-card);border-radius:var(--radius);box-shadow:var(--shadow);padding:var(--sp-5);}
.pi-panel h3{font-size:15px;font-weight:600;margin:0 0 var(--sp-4);}
.pi-field{margin-bottom:var(--sp-4);}
.pi-field label{display:block;font-size:12px;font-weight:600;color:var(--text-muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.4px;}
.pi-field input,.pi-field select{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:14px;font-family:var(--font);color:var(--text-main);background:var(--bg-card);box-sizing:border-box;}
.pi-hint{font-size:11px;font-weight:400;color:var(--text-light);text-transform:none;letter-spacing:0;margin-left:6px;}
.pi-divider{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-light);border-top:1px solid var(--border-light);padding-top:var(--sp-3);margin-bottom:var(--sp-4);}
.pi-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:var(--sp-5);}
.pi-log-box{margin-top:var(--sp-4);background:var(--bg-subtle);border:1px solid var(--border-light);border-radius:var(--radius-sm);overflow:hidden;}
.pi-log-title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);padding:8px 12px;border-bottom:1px solid var(--border-light);background:var(--bg-body);}
.pi-log-box pre{margin:0;padding:12px;font-size:12px;color:var(--text-main);white-space:pre-wrap;word-break:break-all;max-height:260px;overflow-y:auto;font-family:ui-monospace,'SF Mono',Menlo,monospace;}
.btn-sm{font-size:12px;padding:5px 10px;}
@media(max-width:900px){.pisignage-layout{grid-template-columns:1fr;}.scene-grid{gap:8px;}.scene-tile{min-width:100px;}}
</style>

<script>
(function(){
'use strict';

const API = '/be/api/pisignage.php';
let GROUPS=[], PLAYLISTS=[], ASSETS=[], SCENES=[];
let PL_SELECTED=[]; // für Playlist-Editor

// ─ CSRF
function csrf(){ return document.body.getAttribute('data-csrf')||''; }
function updateCsrf(t){ if(!t)return; document.body.setAttribute('data-csrf',t); document.querySelectorAll('input[name="csrf_token"]').forEach(function(e){e.value=t;}); }

// ─ API
async function api(obj, file){
    const fd = new FormData();
    fd.set('csrf_token', csrf());
    Object.entries(obj).forEach(function([k,v]){ fd.set(k,v); });
    if(file) fd.set('file', file);
    try{
        const r = await fetch(API,{method:'POST',body:fd,credentials:'same-origin'});
        const d = await r.json();
        if(d.csrf_token) updateCsrf(d.csrf_token);
        return d;
    }catch(e){ return {success:false,error:e.message}; }
}

// ─ Tabs
document.querySelectorAll('.pi-tab').forEach(function(btn){
    btn.addEventListener('click',function(){
        document.querySelectorAll('.pi-tab,.pi-tab-content').forEach(function(el){el.classList.remove('active');});
        btn.classList.add('active');
        document.getElementById('tab-'+btn.dataset.tab).classList.add('active');
    });
});

// ─ Normalize
function norm(r){
    const p=r?.data;
    if(Array.isArray(p))             return p;
    if(Array.isArray(p?.data))       return p.data;
    if(Array.isArray(p?.data?.data)) return p.data.data;
    if(Array.isArray(p?.data?.items))return p.data.items;
    return [];
}
function gVal(i){return i._id||i.id||i.name||'';}
function gLbl(i){return i.name||i.title||i._id||i.id||'?';}

// ─ Status / Log
function st(id,msg,type){ const e=document.getElementById(id); if(!e)return; e.className='upload-msg '+type; e.textContent=msg; e.style.display='block'; }
function logEl(el,obj){ el.style.display='block'; el.querySelector('pre').textContent=JSON.stringify(obj,null,2); }

// ════ LOAD DATA ═════════════════════════════════════════════════════

async function loadGroups(){
    const r=await api({action:'get_groups'});
    GROUPS=norm(r);
    // manueller Trigger
    const gs=document.getElementById('group_id');
    gs.innerHTML='<option value="">Bitte wählen</option>';
    GROUPS.forEach(function(g){gs.add(new Option(gLbl(g),gVal(g)));});
    return GROUPS;
}
async function loadPlaylists(){
    const r=await api({action:'get_playlists'});
    PLAYLISTS=norm(r);
    const ps=document.getElementById('playlist_id');
    ps.innerHTML='<option value="">Bitte wählen</option>';
    PLAYLISTS.forEach(function(p){ps.add(new Option(gLbl(p),gVal(p)));});
    renderPlaylistList();
    renderPlAssetPicker();
    return PLAYLISTS;
}
async function loadAssets(){
    const r=await api({action:'get_assets'});
    ASSETS=norm(r);
    renderAssetGrid();
    renderPlAssetPicker();
    return ASSETS;
}
async function loadScenes(){
    const r=await api({action:'load_scenes'});
    SCENES=r.scenes||[];
    renderSceneTiles();
    renderSceneEditor();
}

// ════ TAB: SZENEN ══════════════════════════════════════════════════

function renderSceneTiles(){
    const grid=document.getElementById('scene-tiles');
    if(!SCENES.length){grid.innerHTML='<div class="pi-loading">Keine Szenen — unten anlegen.</div>';return;}
    grid.innerHTML='';
    SCENES.forEach(function(scene){
        const tile=document.createElement('button');
        tile.type='button'; tile.className='scene-tile'; tile.dataset.id=scene.id;
        tile.innerHTML='<span class="scene-tile-icon">'+scene.icon+'</span>'+
            '<span class="scene-tile-label">'+esc(scene.label)+'</span>'+
            '<span class="scene-tile-count">'+(scene.slots||[]).length+' Screens</span>'+
            '<span class="scene-tile-bar" style="background:'+scene.color+'"></span>';
        tile.style.borderColor=scene.color+'55';
        tile.addEventListener('click',function(){triggerScene(scene,tile);});
        grid.appendChild(tile);
    });
}

async function triggerScene(scene,tile){
    const statEl=document.getElementById('scene-trigger-status');
    const logBox=document.getElementById('scene-trigger-log');
    tile.classList.add('firing');
    st('scene-trigger-status','⏳ "'+scene.label+'" wird aktiviert …','ok');
    const r=await api({action:'trigger_scene',scene_id:scene.id});
    tile.classList.remove('firing');
    st('scene-trigger-status', r.success?'✅ '+scene.label+': '+r.message:'❌ '+(r.message||r.error||'Fehler'), r.success?'ok':'err');
    if(r.results) logEl(logBox,r);
}

function renderSceneEditor(){
    const list=document.getElementById('scene-editor-list');
    list.innerHTML='';
    SCENES.forEach(function(scene,si){
        const card=document.createElement('div'); card.className='scene-card'; card.dataset.si=si;
        // Header
        const head=document.createElement('div'); head.className='scene-card-head';
        head.innerHTML=
            '<input class="sc-icon" type="text" placeholder="🎬" value="'+esc(scene.icon)+'" data-f="icon">'+
            '<input class="sc-label" type="text" placeholder="Szenen-Name" value="'+esc(scene.label)+'" data-f="label">'+
            '<input class="sc-color" type="color" value="'+esc(scene.color)+'" data-f="color">'+
            '<button type="button" class="sc-del" title="Szene löschen">🗑</button>';
        head.querySelector('.sc-del').addEventListener('click',function(){SCENES.splice(si,1);renderSceneEditor();renderSceneTiles();});
        ['icon','label','color'].forEach(function(f){
            head.querySelector('[data-f="'+f+'"]').addEventListener('input',function(e){SCENES[si][f]=e.target.value;renderSceneTiles();});
        });
        card.appendChild(head);
        // Matrix-Tabelle: alle Gruppen mit Playlist-Dropdown
        const tbl=document.createElement('table'); tbl.className='scene-matrix';
        tbl.innerHTML='<thead><tr><th>Screen / Gruppe</th><th>Playlist</th></tr></thead>';
        const tbody=document.createElement('tbody');
        GROUPS.forEach(function(group){
            const gid=gVal(group); const glbl=gLbl(group);
            // aktuellen Slot für diese Gruppe suchen
            const existingSlot=(scene.slots||[]).find(function(s){return s.group_id===gid;});
            const tr=document.createElement('tr');
            const tdLabel=document.createElement('td'); tdLabel.textContent=glbl;
            const tdSelect=document.createElement('td');
            const sel=document.createElement('select');
            sel.innerHTML='<option value="">— keine —</option>';
            PLAYLISTS.forEach(function(pl){
                const o=new Option(gLbl(pl),gVal(pl));
                if(existingSlot && gVal(pl)===existingSlot.playlist_id) o.selected=true;
                sel.appendChild(o);
            });
            sel.addEventListener('change',function(){
                // Slot updaten oder hinzufügen
                const slots=SCENES[si].slots=SCENES[si].slots||[];
                const idx=slots.findIndex(function(s){return s.group_id===gid;});
                if(sel.value===''){
                    if(idx>-1) slots.splice(idx,1);
                } else {
                    const slotData={group_id:gid,group_label:glbl,playlist_id:sel.value,playlist_label:gLbl(PLAYLISTS.find(function(p){return gVal(p)===sel.value;})||{})};
                    if(idx>-1) slots[idx]=slotData; else slots.push(slotData);
                }
            });
            tdSelect.appendChild(sel);
            tr.appendChild(tdLabel); tr.appendChild(tdSelect);
            tbody.appendChild(tr);
        });
        tbl.appendChild(tbody); card.appendChild(tbl);
        list.appendChild(card);
    });
}

document.getElementById('btn-add-scene').addEventListener('click',function(){
    SCENES.push({id:'',label:'Neue Szene',icon:'🎬',color:'#019ee3',slots:[]});
    renderSceneEditor(); renderSceneTiles();
    document.getElementById('scene-editor-wrap').scrollIntoView({behavior:'smooth'});
});

document.getElementById('btn-save-scenes').addEventListener('click',async function(){
    SCENES.forEach(function(s){
        if(!s.id) s.id=s.label.toLowerCase().replace(/[^a-z0-9]+/g,'_').replace(/^_|_$/g,'');
    });
    const r=await api({action:'save_scenes',scenes:JSON.stringify(SCENES)});
    st('scene-save-status',r.message||r.error||JSON.stringify(r),r.success?'ok':'err');
    if(r.success) renderSceneTiles();
});

// ════ TAB: ASSETS ══════════════════════════════════════════════════

const dropZone=document.getElementById('asset-drop-zone');
const fileInput=document.getElementById('asset-file-input');

dropZone.addEventListener('click',function(){fileInput.click();});
dropZone.addEventListener('dragover',function(e){e.preventDefault();dropZone.classList.add('drag-over');});
dropZone.addEventListener('dragleave',function(){dropZone.classList.remove('drag-over');});
dropZone.addEventListener('drop',function(e){e.preventDefault();dropZone.classList.remove('drag-over');uploadFiles(e.dataTransfer.files);});
fileInput.addEventListener('change',function(){uploadFiles(fileInput.files);fileInput.value='';});
document.getElementById('btn-reload-assets').addEventListener('click',function(){loadAssets();});

async function uploadFiles(files){
    const queue=document.getElementById('asset-upload-queue');
    for(let i=0;i<files.length;i++){
        const file=files[i];
        const item=document.createElement('div'); item.className='upload-queue-item';
        item.innerHTML='<span class="uqi-name">'+esc(file.name)+'</span><div class="uqi-bar-wrap"><div class="uqi-bar" style="width:30%"></div></div><span class="uqi-status">Upload …</span>';
        queue.appendChild(item);
        const bar=item.querySelector('.uqi-bar');
        const statusSpan=item.querySelector('.uqi-status');
        bar.style.width='60%';
        const r=await api({action:'upload_asset'},file);
        bar.style.width=r.success?'100%':'0%';
        statusSpan.textContent=r.success?'✅ OK':'❌ Fehler';
        statusSpan.style.color=r.success?'var(--success,#27ae60)':'#e74c3c';
        if(r.success){await loadAssets();}
    }
    st('asset-upload-status','Upload abgeschlossen','ok');
}

function renderAssetGrid(){
    const grid=document.getElementById('asset-grid');
    if(!ASSETS.length){grid.innerHTML='<div class="pi-loading">Keine Assets vorhanden.</div>';return;}
    grid.innerHTML='';
    ASSETS.forEach(function(a){
        const fn=a.filename||a.name||a._id||'';
        const isVideo=fn.match(/\.(mp4|webm|mov)$/i);
        const isImg=fn.match(/\.(jpg|jpeg|png|gif|webp)$/i);
        const card=document.createElement('div'); card.className='asset-card';
        let preview='';
        if(isImg) preview='<img src="'+esc(a.url||a.path||'')+'" loading="lazy" alt="'+esc(fn)+'">';
        else if(isVideo) preview='<video src="'+esc(a.url||a.path||'')+'" muted preload="none"></video>';
        else preview='<div style="height:80px;display:flex;align-items:center;justify-content:center;font-size:28px;background:var(--bg-subtle)">📄</div>';
        card.innerHTML=preview+
            '<div class="asset-card-type">'+esc(fn.split('.').pop().toUpperCase())+'</div>'+
            '<div class="asset-card-name" title="'+esc(fn)+'">'+esc(fn)+'</div>'+
            '<button class="asset-card-del" title="Löschen" data-fn="'+esc(fn)+'">✕</button>';
        card.querySelector('.asset-card-del').addEventListener('click',async function(){
            if(!confirm('Asset "'+fn+'" wirklich löschen?'))return;
            const r=await api({action:'delete_asset',filename:fn});
            if(r.success){await loadAssets();} else{alert(r.error||'Fehler');}
        });
        grid.appendChild(card);
    });
}

// ════ TAB: PLAYLISTS ════════════════════════════════════════════════

function renderPlAssetPicker(){
    const picker=document.getElementById('pl-asset-picker');
    if(!ASSETS.length){picker.innerHTML='<div class="pi-loading">Keine Assets — erst hochladen.</div>';return;}
    picker.innerHTML='';
    ASSETS.forEach(function(a){
        const fn=a.filename||a.name||a._id||'';
        const isVideo=fn.match(/\.(mp4|webm|mov)$/i);
        const thumb=document.createElement('div'); thumb.className='pl-asset-thumb'; thumb.dataset.fn=fn;
        if(PL_SELECTED.find(function(s){return s.filename===fn;})) thumb.classList.add('selected');
        let img='';
        if(isVideo) img='<video src="'+esc(a.url||a.path||'')+'" muted preload="none"></video>';
        else img='<img src="'+esc(a.url||a.path||'')+'" loading="lazy" alt="'+esc(fn)+'">';
        thumb.innerHTML=img+'<div class="pl-asset-thumb-name">'+esc(fn)+'</div>';
        thumb.addEventListener('click',function(){
            const idx=PL_SELECTED.findIndex(function(s){return s.filename===fn;});
            if(idx>-1){ PL_SELECTED.splice(idx,1); thumb.classList.remove('selected'); }
            else{ PL_SELECTED.push({filename:fn,duration:10}); thumb.classList.add('selected'); }
            renderPlSelectedList();
        });
        picker.appendChild(thumb);
    });
}

function renderPlSelectedList(){
    const list=document.getElementById('pl-selected-list');
    if(!PL_SELECTED.length){list.innerHTML='';return;}
    list.innerHTML='<div style="font-size:12px;font-weight:700;color:var(--text-muted);margin-bottom:6px;">Ausgewählte Assets (Reihenfolge = Playlist-Reihenfolge):</div>';
    PL_SELECTED.forEach(function(sel,i){
        const row=document.createElement('div'); row.className='pl-sel-row';
        row.innerHTML='<span style="font-weight:600;flex:1">'+esc(sel.filename)+'</span>'+
            '<span class="pl-sel-dur" title="Anzeigedauer in Sekunden"><input type="number" min="1" max="600" value="'+sel.duration+'" title="Sekunden"> s</span>'+
            '<button class="pl-sel-del" data-i="'+i+'">✕</button>';
        row.querySelector('input').addEventListener('input',function(e){PL_SELECTED[i].duration=parseInt(e.target.value)||10;});
        row.querySelector('.pl-sel-del').addEventListener('click',function(){
            const fn=PL_SELECTED[i].filename;
            PL_SELECTED.splice(i,1);
            renderPlSelectedList();
            document.querySelectorAll('.pl-asset-thumb[data-fn="'+fn+'"]').forEach(function(t){t.classList.remove('selected');});
        });
        list.appendChild(row);
    });
}

function renderPlaylistList(){
    const list=document.getElementById('playlist-list');
    if(!PLAYLISTS.length){list.innerHTML='<div class="pi-loading">Keine Playlists.</div>';return;}
    list.innerHTML='';
    PLAYLISTS.forEach(function(pl){
        const pid=gVal(pl);
        const row=document.createElement('div'); row.className='playlist-row';
        row.innerHTML='<span class="playlist-row-name">'+esc(gLbl(pl))+'</span>'+
            '<span class="playlist-row-count">'+(pl.assets||pl.items||[]).length+' Assets</span>'+
            '<button class="playlist-row-del" data-pid="'+esc(pid)+'">🗑 Löschen</button>';
        row.querySelector('.playlist-row-del').addEventListener('click',async function(){
            if(!confirm('Playlist "'+gLbl(pl)+'" wirklich löschen?'))return;
            const r=await api({action:'delete_playlist',playlist_id:pid});
            if(r.success){await loadPlaylists();} else{alert(r.error||'Fehler');}
        });
        list.appendChild(row);
    });
}

document.getElementById('btn-create-playlist').addEventListener('click',async function(){
    const name=document.getElementById('pl-new-name').value.trim();
    if(!name){alert('Bitte einen Playlist-Namen eingeben.');return;}
    if(!PL_SELECTED.length){alert('Bitte mindestens ein Asset auswählen.');return;}
    const r=await api({action:'create_playlist',name:name,assets:JSON.stringify(PL_SELECTED)});
    st('pl-create-status',r.message||r.error||JSON.stringify(r),r.success?'ok':'err');
    if(r.success){
        PL_SELECTED=[];
        document.getElementById('pl-new-name').value='';
        await loadPlaylists();
        renderPlAssetPicker();
        renderPlSelectedList();
    }
});
document.getElementById('btn-reload-playlists').addEventListener('click',function(){loadPlaylists();});

// ════ TAB: VERBINDUNG ═══════════════════════════════════════════════

document.getElementById('pisignage-settings-form').addEventListener('submit',async function(e){
    e.preventDefault();
    const r=await api({action:'save_settings', base_url:this.base_url.value, api_token:this.api_token.value, email:this.email.value, password:this.password.value});
    st('settings-status',r.message||r.error||JSON.stringify(r),r.success?'ok':'err');
});
document.getElementById('btn-request-token').addEventListener('click',async function(){
    const r=await api({action:'request_token',otp:document.getElementById('otp').value});
    st('settings-status',r.message||r.error||JSON.stringify(r),r.success?'ok':'err');
    if(r.token_masked) logEl(document.getElementById('pi-log'),r);
});
document.getElementById('btn-test-connection').addEventListener('click',async function(){
    const r=await api({action:'test_connection'});
    st('settings-status',r.success?'✅ Verbindung erfolgreich!':'❌ '+(r.error||'Fehler'),r.success?'ok':'err');
    logEl(document.getElementById('pi-log'),r);
});
document.getElementById('btn-reload-manual').addEventListener('click',async function(){
    await loadGroups(); await loadPlaylists();
    st('trigger-status','✅ Geladen','ok');
});
document.getElementById('pisignage-trigger-form').addEventListener('submit',async function(e){
    e.preventDefault();
    const r=await api({action:'set_playlist',group_id:document.getElementById('group_id').value,playlist_id:document.getElementById('playlist_id').value});
    st('trigger-status',r.success?'✅ Playlist getriggert!':'❌ '+(r.error||'Fehler'),r.success?'ok':'err');
    logEl(document.getElementById('pi-log'),r);
});

// ─ Utils
function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

// ════ INIT ═══════════════════════════════════════════════════════════
(async function init(){
    await loadGroups();
    await loadPlaylists();
    await loadAssets();
    await loadScenes(); // nach Groups+Playlists damit Matrix-Dropdowns gefüllt sind
})();

})();
</script>

<?php include __DIR__.'/../inc/debug.php'; ?>
</body>
</html>
