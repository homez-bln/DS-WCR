<?php
/**
 * ctrl/pisignage.php — piSignage Playlist-Steuerung + Preset-System
 * NUR für cernal zugänglich
 */
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/pisignage-config.php';

require_login();

if (!wcr_is_cernal()) {
    http_response_code(403);
    $pageTitle = 'Kein Zugriff';
    include __DIR__ . '/../inc/403.php';
    exit;
}

$config = wcr_pisignage_load_config();
$csrf   = wcr_csrf_token();
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>Verwaltung: piSignage</title>
</head>
<body class="bo" data-csrf="<?= htmlspecialchars($csrf) ?>">
<?php include __DIR__ . '/../inc/menu.php'; ?>

<div class="header-controls">
  <h1>📺 piSignage</h1>
</div>

<!-- ══════════════════════════════════════════════════════
     BLOCK 1 — MODUS-PRESETS
══════════════════════════════════════════════════════ -->
<div class="pi-section">
  <div class="pi-section-head">⚡ Modus aktivieren</div>
  <div class="pi-section-sub">Ein Klick triggert alle zugewiesenen Screens gleichzeitig</div>

  <div id="preset-tiles" class="preset-grid">
    <div class="preset-loading">⏳ Presets werden geladen …</div>
  </div>

  <div class="upload-msg" id="trigger-global-status" style="display:none"></div>

  <!-- Trigger-Log -->
  <div class="pi-log-box" id="trigger-log" style="display:none">
    <div class="pi-log-title">Trigger-Protokoll</div>
    <pre id="trigger-log-content"></pre>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     BLOCK 2 — PRESET EDITOR
══════════════════════════════════════════════════════ -->
<div class="pi-section" id="preset-editor-section">
  <div class="pi-section-head">✏️ Presets verwalten</div>
  <div class="pi-section-sub">Presets anlegen, bearbeiten und löschen — Änderungen sofort aktiv</div>

  <div id="preset-editor-list">
    <!-- wird per JS gefüllt -->
  </div>

  <div class="pi-editor-actions">
    <button type="button" class="btn-secondary" id="btn-add-preset">＋ Neues Preset</button>
    <button type="button" class="btn-upload" id="btn-save-presets">💾 Alle Presets speichern</button>
  </div>
  <div class="upload-msg" id="editor-status" style="display:none"></div>
</div>

<!-- ══════════════════════════════════════════════════════
     BLOCK 3 — VERBINDUNG & MANUELLER TRIGGER
══════════════════════════════════════════════════════ -->
<div class="pisignage-layout">

  <div class="pi-panel">
    <h3>🔌 Verbindung & Token</h3>
    <form id="pisignage-settings-form">
      <input type="hidden" name="action" value="save_settings">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <div class="pi-field">
        <label>Base URL</label>
        <input type="text" name="base_url" value="<?= htmlspecialchars($config['base_url']) ?>" placeholder="https://homez_wcr.pisignage.com">
      </div>
      <div class="pi-field">
        <label>API Token <span class="pi-hint">manuell eintragen oder automatisch abrufen</span></label>
        <input type="text" name="api_token" value="<?= htmlspecialchars($config['api_token']) ?>" placeholder="x-access-token hier einfügen">
      </div>
      <div class="pi-divider">Automatischer Token-Abruf (optional)</div>
      <div class="pi-field">
        <label>Login E-Mail</label>
        <input type="email" name="email" value="<?= htmlspecialchars($config['email']) ?>" placeholder="dein@pisignage-account.com">
      </div>
      <div class="pi-field">
        <label>Passwort</label>
        <input type="password" name="password" value="<?= htmlspecialchars($config['password']) ?>" placeholder="Passwort">
      </div>
      <div class="pi-field">
        <label>OTP Code <span class="pi-hint">nur falls piSignage MFA verlangt</span></label>
        <input type="text" id="otp" name="otp" placeholder="6-stelliger Code">
      </div>
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
      <div class="pi-field">
        <label>Gruppe / Monitor</label>
        <select id="group_id" name="group_id"><option value="">Bitte laden …</option></select>
      </div>
      <div class="pi-field">
        <label>Playlist</label>
        <select id="playlist_id" name="playlist_id"><option value="">Bitte laden …</option></select>
      </div>
      <div class="pi-actions">
        <button type="submit" class="btn-upload">▶️ Playlist triggern</button>
        <button type="button" class="btn-secondary" id="btn-reload-data">🔄 Neu laden</button>
      </div>
    </form>
    <div class="upload-msg" id="trigger-status" style="display:none"></div>
    <div class="pi-log-box" id="pi-log" style="display:none">
      <div class="pi-log-title">API Response</div>
      <pre id="pi-log-content"></pre>
    </div>
  </div>

</div>

<style>
/* ── Layout ──────────────────────────────────────────── */
.pisignage-layout{display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start;}
.pi-section{background:var(--bg-card);border-radius:var(--radius);box-shadow:var(--shadow);padding:22px 24px;margin-bottom:20px;}
.pi-section-head{font-size:14px;font-weight:700;margin:0 0 3px;}
.pi-section-sub{font-size:12px;color:var(--text-muted);margin:0 0 18px;}
.pi-panel{background:var(--bg-card);border-radius:var(--radius);box-shadow:var(--shadow);padding:var(--sp-5);}
.pi-panel h3{font-size:15px;font-weight:600;margin:0 0 var(--sp-4);color:var(--text-main);}
/* ── Preset Tiles ────────────────────────────────────── */
.preset-grid{display:flex;flex-wrap:wrap;gap:12px;margin-bottom:14px;}
.preset-tile{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;
  min-width:130px;padding:18px 16px;border-radius:12px;cursor:pointer;border:2px solid transparent;
  background:var(--bg-subtle);transition:transform .15s,box-shadow .15s,border-color .15s;position:relative;}
.preset-tile:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(0,0,0,.18);border-color:var(--border);}
.preset-tile.active{box-shadow:0 0 0 3px rgba(1,158,227,.25);}
.preset-tile-icon{font-size:28px;line-height:1;}
.preset-tile-label{font-size:13px;font-weight:700;color:var(--text-main);text-align:center;}
.preset-tile-bar{position:absolute;bottom:0;left:0;right:0;height:3px;border-radius:0 0 10px 10px;}
.preset-tile-spin{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
  background:rgba(0,0,0,.35);border-radius:10px;font-size:20px;display:none;}
.preset-loading{color:var(--text-muted);font-size:13px;padding:12px 0;}
/* ── Preset Editor ───────────────────────────────────── */
.preset-card{background:var(--bg-subtle);border:1px solid var(--border-light);border-radius:10px;
  padding:16px 18px;margin-bottom:12px;}
.preset-card-head{display:flex;align-items:center;gap:10px;margin-bottom:14px;}
.preset-card-head input{padding:7px 10px;border:1px solid var(--border);border-radius:7px;font-size:13px;
  background:var(--bg-card);color:var(--text-main);}
.pc-icon{width:52px;}
.pc-label{flex:1;}
.pc-color{width:44px;height:36px;padding:2px;border-radius:7px;cursor:pointer;border:1px solid var(--border);}
.pc-del{background:transparent;border:1px solid var(--border);border-radius:7px;padding:6px 10px;
  color:var(--text-muted);cursor:pointer;font-size:13px;margin-left:auto;}
.pc-del:hover{background:#fff0f0;color:#c0392b;border-color:#ffd0cc;}
.preset-actions-head{display:grid;grid-template-columns:1fr 1fr 36px;gap:8px;font-size:11px;
  font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);margin-bottom:6px;padding:0 4px;}
.preset-action-row{display:grid;grid-template-columns:1fr 1fr 36px;gap:8px;margin-bottom:6px;}
.preset-action-row select{padding:7px 10px;border:1px solid var(--border);border-radius:7px;
  font-size:12px;background:var(--bg-card);color:var(--text-main);}
.pa-del{background:transparent;border:1px solid var(--border);border-radius:7px;cursor:pointer;
  color:var(--text-muted);font-size:14px;}
.pa-del:hover{background:#fff0f0;color:#c0392b;border-color:#ffd0cc;}
.btn-add-action{background:transparent;border:1px dashed var(--border);border-radius:7px;
  padding:6px 12px;font-size:12px;color:var(--text-muted);cursor:pointer;width:100%;margin-top:4px;}
.btn-add-action:hover{background:var(--bg-subtle);color:var(--text-main);}
.pi-editor-actions{display:flex;gap:12px;margin-top:4px;}
/* ── Shared ──────────────────────────────────────────── */
.pi-field{margin-bottom:var(--sp-4);}
.pi-field label{display:block;font-size:12px;font-weight:600;color:var(--text-muted);margin-bottom:6px;
  text-transform:uppercase;letter-spacing:.4px;}
.pi-field input,.pi-field select{width:100%;padding:10px 12px;border:1px solid var(--border);
  border-radius:var(--radius-sm);font-size:14px;font-family:var(--font);color:var(--text-main);
  background:var(--bg-card);box-sizing:border-box;}
.pi-hint{font-size:11px;font-weight:400;color:var(--text-light);text-transform:none;letter-spacing:0;margin-left:6px;}
.pi-divider{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;
  color:var(--text-light);border-top:1px solid var(--border-light);padding-top:var(--sp-3);margin-bottom:var(--sp-4);}
.pi-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:var(--sp-5);}
.pi-log-box{margin-top:var(--sp-4);background:var(--bg-subtle);border:1px solid var(--border-light);border-radius:var(--radius-sm);overflow:hidden;}
.pi-log-title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;
  color:var(--text-muted);padding:8px 12px;border-bottom:1px solid var(--border-light);background:var(--bg-body);}
.pi-log-box pre{margin:0;padding:12px;font-size:12px;color:var(--text-main);white-space:pre-wrap;
  word-break:break-all;max-height:260px;overflow-y:auto;font-family:ui-monospace,'SF Mono',Menlo,monospace;}
@media(max-width:900px){.pisignage-layout{grid-template-columns:1fr;}.preset-grid{gap:8px;}.preset-tile{min-width:100px;padding:14px 10px;}}
</style>

<script>
(function () {
'use strict';

const API      = '/be/api/pisignage.php';
const sfForm   = document.getElementById('pisignage-settings-form');
const tfForm   = document.getElementById('pisignage-trigger-form');
const gSel     = document.getElementById('group_id');
const pSel     = document.getElementById('playlist_id');

// ── Alle verfügbaren Gruppen & Playlists (gecacht für Editor-Dropdowns)
let ALL_GROUPS    = [];
let ALL_PLAYLISTS = [];
// Alle Presets (editierbar)
let PRESETS = [];

// ── CSRF ──────────────────────────────────────────────────────────────
function csrf() { return document.body.getAttribute('data-csrf') || ''; }
function updateCsrf(t) {
    if (!t) return;
    document.body.setAttribute('data-csrf', t);
    document.querySelectorAll('input[name="csrf_token"]').forEach(function(el){ el.value = t; });
}

// ── API-Wrapper ──────────────────────────────────────────────────────
async function api(formData) {
    formData.set('csrf_token', csrf());
    try {
        const res  = await fetch(API, { method:'POST', body: formData, credentials:'same-origin' });
        const data = await res.json();
        if (data.csrf_token) updateCsrf(data.csrf_token);
        return data;
    } catch(e) { return { success:false, error: e.message }; }
}
function fd(obj) {
    const f = new FormData();
    Object.entries(obj).forEach(function([k,v]){ f.append(k, v); });
    return f;
}

// ── Status / Log ─────────────────────────────────────────────────────
function status(el, msg, type) {
    el.className = 'upload-msg ' + type;
    el.textContent = msg;
    el.style.display = 'block';
}
function log(el, obj) {
    el.style.display = 'block';
    el.querySelector('pre').textContent = JSON.stringify(obj, null, 2);
}

// ── Normalize piSignage list responses ───────────────────────────────
function normalize(payload) {
    if (Array.isArray(payload))             return payload;
    if (Array.isArray(payload?.data))       return payload.data;
    if (Array.isArray(payload?.data?.data)) return payload.data.data;
    if (Array.isArray(payload?.data?.items))return payload.data.items;
    return [];
}
function val(item)   { return item._id  || item.id   || item.name || ''; }
function label(item) { return item.name || item.title || item._id  || item.id || 'Unbenannt'; }

// ── Gruppen & Playlists laden ─────────────────────────────────────────
async function loadGroups() {
    const res = await api(fd({ action:'get_groups' }));
    ALL_GROUPS = normalize(res);
    gSel.innerHTML = '<option value="">Bitte wählen …</option>';
    ALL_GROUPS.forEach(function(g){ gSel.add(new Option(label(g), val(g))); });
}
async function loadPlaylists() {
    const res = await api(fd({ action:'get_playlists' }));
    ALL_PLAYLISTS = normalize(res);
    pSel.innerHTML = '<option value="">Bitte wählen …</option>';
    ALL_PLAYLISTS.forEach(function(p){ pSel.add(new Option(label(p), val(p))); });
}

// ── Preset-Tiles rendern ──────────────────────────────────────────────
function renderTiles() {
    const grid = document.getElementById('preset-tiles');
    if (!PRESETS.length) {
        grid.innerHTML = '<div class="preset-loading">Keine Presets vorhanden — unten anlegen.</div>';
        return;
    }
    grid.innerHTML = '';
    PRESETS.forEach(function(preset) {
        const tile = document.createElement('button');
        tile.type = 'button';
        tile.className = 'preset-tile';
        tile.dataset.id = preset.id;
        tile.innerHTML = 
            '<span class="preset-tile-icon">' + preset.icon + '</span>' +
            '<span class="preset-tile-label">' + preset.label + '</span>' +
            '<span class="preset-tile-bar" style="background:' + preset.color + '"></span>' +
            '<span class="preset-tile-spin">⏳</span>';
        tile.style.borderColor = preset.color + '44';
        tile.addEventListener('click', function(){ triggerPreset(preset, tile); });
        grid.appendChild(tile);
    });
}

// ── Preset triggern ───────────────────────────────────────────────────
async function triggerPreset(preset, tile) {
    const spin   = tile.querySelector('.preset-tile-spin');
    const statEl = document.getElementById('trigger-global-status');
    const logEl  = document.getElementById('trigger-log');
    spin.style.display = 'flex';
    tile.disabled = true;
    status(statEl, '⏳ "' + preset.label + '" wird aktiviert …', 'ok');
    const res = await api(fd({ action:'trigger_preset', preset_id: preset.id }));
    spin.style.display = 'none';
    tile.disabled = false;
    if (res.success) {
        tile.classList.add('active');
        setTimeout(function(){ tile.classList.remove('active'); }, 2000);
        status(statEl, '✅ ' + preset.label + ' — ' + (res.message || 'aktiviert'), 'ok');
    } else {
        status(statEl, '❌ ' + (res.message || res.error || 'Fehler'), 'err');
    }
    if (res.results) log(logEl, res);
}

// ── Preset-Editor rendern ─────────────────────────────────────────────
function renderEditor() {
    const list = document.getElementById('preset-editor-list');
    list.innerHTML = '';
    PRESETS.forEach(function(preset, pi) {
        const card = document.createElement('div');
        card.className = 'preset-card';
        card.dataset.pi = pi;
        // Header
        const head = document.createElement('div');
        head.className = 'preset-card-head';
        head.innerHTML =
            '<input class="pc-icon" type="text" placeholder="🎬" value="' + esc(preset.icon) + '" data-f="icon">' +
            '<input class="pc-label" type="text" placeholder="Preset-Name" value="' + esc(preset.label) + '" data-f="label">' +
            '<input class="pc-color" type="color" value="' + esc(preset.color) + '" data-f="color">' +
            '<button type="button" class="pc-del" title="Preset löschen">🗑</button>';
        head.querySelector('.pc-del').addEventListener('click', function(){ PRESETS.splice(pi, 1); renderEditor(); renderTiles(); });
        ['icon','label','color'].forEach(function(f) {
            head.querySelector('[data-f="'+f+'"]').addEventListener('input', function(e){
                PRESETS[pi][f] = e.target.value;
                renderTiles();
            });
        });
        card.appendChild(head);
        // Aktionen
        const aHead = document.createElement('div');
        aHead.className = 'preset-actions-head';
        aHead.innerHTML = '<span>Gruppe / Monitor</span><span>Playlist</span><span></span>';
        card.appendChild(aHead);
        const aList = document.createElement('div');
        aList.className = 'preset-action-list';
        (preset.actions || []).forEach(function(action, ai) {
            aList.appendChild(makeActionRow(pi, ai, action));
        });
        card.appendChild(aList);
        // + Aktion hinzufügen
        const addBtn = document.createElement('button');
        addBtn.type = 'button';
        addBtn.className = 'btn-add-action';
        addBtn.textContent = '＋ Aktion hinzufügen';
        addBtn.addEventListener('click', function() {
            PRESETS[pi].actions.push({ group_id:'', playlist_id:'', label:'' });
            renderEditor();
        });
        card.appendChild(addBtn);
        list.appendChild(card);
    });
}

function makeActionRow(pi, ai, action) {
    const row = document.createElement('div');
    row.className = 'preset-action-row';
    // Gruppe-Select
    const gSel = document.createElement('select');
    gSel.innerHTML = '<option value="">Gruppe wählen …</option>';
    ALL_GROUPS.forEach(function(g){
        const o = new Option(label(g), val(g));
        if (val(g) === action.group_id) o.selected = true;
        gSel.appendChild(o);
    });
    gSel.addEventListener('change', function(){ PRESETS[pi].actions[ai].group_id = gSel.value; });
    // Playlist-Select
    const plSel = document.createElement('select');
    plSel.innerHTML = '<option value="">Playlist wählen …</option>';
    ALL_PLAYLISTS.forEach(function(p){
        const o = new Option(label(p), val(p));
        if (val(p) === action.playlist_id) o.selected = true;
        plSel.appendChild(o);
    });
    plSel.addEventListener('change', function(){ PRESETS[pi].actions[ai].playlist_id = plSel.value; });
    // Löschen
    const del = document.createElement('button');
    del.type = 'button';
    del.className = 'pa-del';
    del.textContent = '✕';
    del.addEventListener('click', function(){ PRESETS[pi].actions.splice(ai,1); renderEditor(); });
    row.appendChild(gSel);
    row.appendChild(plSel);
    row.appendChild(del);
    return row;
}

function esc(str) {
    return String(str||'').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;');
}

// ── Presets laden ─────────────────────────────────────────────────────
async function loadPresets() {
    const res = await api(fd({ action:'load_presets' }));
    PRESETS = res.presets || [];
    renderTiles();
    renderEditor();
}

// ── Presets speichern ─────────────────────────────────────────────────
document.getElementById('btn-save-presets').addEventListener('click', async function() {
    const statEl = document.getElementById('editor-status');
    // IDs aus Labels generieren falls leer
    PRESETS.forEach(function(p) {
        if (!p.id) p.id = p.label.toLowerCase().replace(/[^a-z0-9]+/g,'_').replace(/^_|_$/g,'');
    });
    const f = fd({ action:'save_presets', presets: JSON.stringify(PRESETS) });
    const res = await api(f);
    status(statEl, res.message || res.error || JSON.stringify(res), res.success ? 'ok' : 'err');
    if (res.success) renderTiles();
});

// ── Neues leeres Preset ───────────────────────────────────────────────
document.getElementById('btn-add-preset').addEventListener('click', function() {
    PRESETS.push({ id:'', label:'Neues Preset', icon:'🎬', color:'#019ee3', actions:[] });
    renderEditor();
    renderTiles();
    document.getElementById('preset-editor-section').scrollIntoView({ behavior:'smooth' });
});

// ── Einstellungen speichern ───────────────────────────────────────────
sfForm.addEventListener('submit', async function(e) {
    e.preventDefault();
    const res = await api(new FormData(sfForm));
    status(document.getElementById('settings-status'), res.message || res.error || JSON.stringify(res), res.success ? 'ok' : 'err');
});

// ── Token abrufen ─────────────────────────────────────────────────────
document.getElementById('btn-request-token').addEventListener('click', async function() {
    const res = await api(fd({ action:'request_token', otp: document.getElementById('otp').value }));
    status(document.getElementById('settings-status'), res.message || res.error || JSON.stringify(res), res.success ? 'ok' : 'err');
    if (res.token_masked) log(document.getElementById('pi-log'), res);
});

// ── Verbindung testen ────────────────────────────────────────────────
document.getElementById('btn-test-connection').addEventListener('click', async function() {
    const res = await api(fd({ action:'test_connection' }));
    status(document.getElementById('settings-status'), res.success ? '✅ Verbindung erfolgreich!' : '❌ ' + (res.error||'Fehler'), res.success ? 'ok' : 'err');
    log(document.getElementById('pi-log'), res);
});

// ── Neu laden ────────────────────────────────────────────────────────
document.getElementById('btn-reload-data').addEventListener('click', async function() {
    status(document.getElementById('trigger-status'), 'Lade Daten …', 'ok');
    await loadGroups();
    await loadPlaylists();
    renderEditor(); // Dropdowns in Editor neu befüllen
    status(document.getElementById('trigger-status'), '✅ Gruppen & Playlists geladen', 'ok');
});

// ── Manueller Trigger ────────────────────────────────────────────────
tfForm.addEventListener('submit', async function(e) {
    e.preventDefault();
    const res = await api(new FormData(tfForm));
    status(document.getElementById('trigger-status'), res.success ? '✅ Playlist erfolgreich getriggert!' : '❌ ' + (res.error||'Fehler'), res.success ? 'ok' : 'err');
    log(document.getElementById('pi-log'), res);
});

// ── Init ─────────────────────────────────────────────────────────────
(async function init() {
    await loadGroups();
    await loadPlaylists();
    await loadPresets(); // Editor nach Gruppen/Playlists laden damit Dropdowns gefüllt sind
})();

})();
</script>

<?php include __DIR__ . '/../inc/debug.php'; ?>
</body>
</html>
