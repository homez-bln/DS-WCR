<?php
/**
 * ctrl/pisignage.php — piSignage Playlist-Steuerung (nur für cernal)
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
$csrf   = $_SESSION['csrf_token'] ?? '';
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>Verwaltung: piSignage</title>
</head>
<body class="bo">
<?php include __DIR__ . '/../inc/menu.php'; ?>

<div class="header-controls">
  <h1>📺 piSignage</h1>
</div>

<div class="pisignage-layout">

  <!-- ── LINKE SPALTE: Verbindung ── -->
  <div class="pi-panel">
    <h3>🔌 Verbindung & Token</h3>

    <form id="pisignage-settings-form">
      <input type="hidden" name="action"     value="save_settings">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

      <div class="pi-field">
        <label>Base URL</label>
        <input type="text" name="base_url"
               value="<?= htmlspecialchars($config['base_url']) ?>"
               placeholder="https://homez_wcr.pisignage.com">
      </div>

      <div class="pi-field">
        <label>API Token <span class="pi-hint">manuell eintragen oder automatisch abrufen</span></label>
        <input type="text" name="api_token"
               value="<?= htmlspecialchars($config['api_token']) ?>"
               placeholder="x-access-token hier einfügen">
      </div>

      <div class="pi-divider">Automatischer Token-Abruf (optional)</div>

      <div class="pi-field">
        <label>Login E-Mail</label>
        <input type="email" name="email"
               value="<?= htmlspecialchars($config['email']) ?>"
               placeholder="dein@pisignage-account.com">
      </div>

      <div class="pi-field">
        <label>Passwort</label>
        <input type="password" name="password"
               value="<?= htmlspecialchars($config['password']) ?>"
               placeholder="Passwort">
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

  <!-- ── RECHTE SPALTE: Playlist triggern ── -->
  <div class="pi-panel">
    <h3>▶️ Playlist triggern</h3>

    <form id="pisignage-trigger-form">
      <input type="hidden" name="action"     value="set_playlist">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

      <div class="pi-field">
        <label>Gruppe / Monitor</label>
        <select id="group_id" name="group_id">
          <option value="">Bitte laden …</option>
        </select>
      </div>

      <div class="pi-field">
        <label>Playlist</label>
        <select id="playlist_id" name="playlist_id">
          <option value="">Bitte laden …</option>
        </select>
      </div>

      <div class="pi-actions">
        <button type="submit" class="btn-upload">▶️ Playlist triggern</button>
        <button type="button" class="btn-secondary" id="btn-reload-data">🔄 Neu laden</button>
      </div>
    </form>

    <div class="upload-msg" id="trigger-status" style="display:none"></div>

    <!-- Response-Log -->
    <div class="pi-log-box" id="pi-log" style="display:none">
      <div class="pi-log-title">API Response</div>
      <pre id="pi-log-content"></pre>
    </div>
  </div>

</div>

<style>
.pisignage-layout {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    align-items: start;
}
.pi-panel {
    background: var(--bg-card);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: var(--sp-5);
}
.pi-panel h3 {
    font-size: 15px;
    font-weight: 600;
    margin: 0 0 var(--sp-4);
    color: var(--text-main);
}
.pi-field {
    margin-bottom: var(--sp-4);
}
.pi-field label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: var(--text-muted);
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}
.pi-field input,
.pi-field select {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    font-size: 14px;
    font-family: var(--font);
    color: var(--text-main);
    background: var(--bg-card);
    box-sizing: border-box;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.pi-field input:focus,
.pi-field select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), var(--alpha-10));
}
.pi-hint {
    font-size: 11px;
    font-weight: 400;
    color: var(--text-light);
    text-transform: none;
    letter-spacing: 0;
    margin-left: 6px;
}
.pi-divider {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-light);
    border-top: 1px solid var(--border-light);
    padding-top: var(--sp-3);
    margin-bottom: var(--sp-4);
}
.pi-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: var(--sp-5);
}
.pi-log-box {
    margin-top: var(--sp-4);
    background: var(--bg-subtle);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-sm);
    overflow: hidden;
}
.pi-log-title {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-muted);
    padding: 8px 12px;
    border-bottom: 1px solid var(--border-light);
    background: var(--bg-body);
}
.pi-log-box pre {
    margin: 0;
    padding: 12px;
    font-size: 12px;
    color: var(--text-main);
    white-space: pre-wrap;
    word-break: break-all;
    max-height: 260px;
    overflow-y: auto;
    font-family: ui-monospace, 'SF Mono', Menlo, monospace;
}
@media (max-width: 900px) {
    .pisignage-layout { grid-template-columns: 1fr; }
}
</style>

<script>
(async function () {
    const apiUrl         = '/be/api/pisignage.php';
    const settingsForm   = document.getElementById('pisignage-settings-form');
    const triggerForm    = document.getElementById('pisignage-trigger-form');
    const settingsStatus = document.getElementById('settings-status');
    const triggerStatus  = document.getElementById('trigger-status');
    const piLog          = document.getElementById('pi-log');
    const piLogContent   = document.getElementById('pi-log-content');
    const groupSelect    = document.getElementById('group_id');
    const playlistSelect = document.getElementById('playlist_id');

    function showStatus(el, msg, type) {
        el.className = 'upload-msg ' + type;
        el.textContent = msg;
        el.style.display = 'block';
    }

    function showLog(obj) {
        piLog.style.display = 'block';
        piLogContent.textContent = JSON.stringify(obj, null, 2);
    }

    function csrf() {
        return settingsForm.querySelector('[name="csrf_token"]').value;
    }

    async function postForm(formData) {
        try {
            const res = await fetch(apiUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });
            return await res.json();
        } catch (e) {
            return { success: false, error: e.message };
        }
    }

    function normalizeItems(payload) {
        if (Array.isArray(payload))             return payload;
        if (Array.isArray(payload?.data))       return payload.data;
        if (Array.isArray(payload?.data?.data)) return payload.data.data;
        if (Array.isArray(payload?.data?.items))return payload.data.items;
        return [];
    }

    function optionValue(item) { return item._id || item.id || item.name || ''; }
    function optionLabel(item) { return item.name || item.title || item._id || item.id || 'Unbenannt'; }

    async function loadGroups() {
        const fd = new FormData();
        fd.append('action', 'get_groups');
        fd.append('csrf_token', csrf());
        const res   = await postForm(fd);
        const items = normalizeItems(res);
        groupSelect.innerHTML = '<option value="">Bitte wählen …</option>';
        items.forEach(item => {
            const opt = document.createElement('option');
            opt.value = optionValue(item);
            opt.textContent = optionLabel(item);
            groupSelect.appendChild(opt);
        });
        return res;
    }

    async function loadPlaylists() {
        const fd = new FormData();
        fd.append('action', 'get_playlists');
        fd.append('csrf_token', csrf());
        const res   = await postForm(fd);
        const items = normalizeItems(res);
        playlistSelect.innerHTML = '<option value="">Bitte wählen …</option>';
        items.forEach(item => {
            const opt = document.createElement('option');
            opt.value = optionValue(item);
            opt.textContent = optionLabel(item);
            playlistSelect.appendChild(opt);
        });
        return res;
    }

    // Einstellungen speichern
    settingsForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        const res = await postForm(new FormData(settingsForm));
        showStatus(settingsStatus, res.message || res.error || JSON.stringify(res), res.success ? 'ok' : 'err');
    });

    // Token automatisch abrufen
    document.getElementById('btn-request-token').addEventListener('click', async function () {
        const fd = new FormData();
        fd.append('action',     'request_token');
        fd.append('csrf_token', csrf());
        fd.append('otp',        document.getElementById('otp').value);
        const res = await postForm(fd);
        showStatus(settingsStatus, res.message || res.error || JSON.stringify(res), res.success ? 'ok' : 'err');
        if (res.token_masked) {
            showLog(res);
        }
    });

    // Verbindung testen
    document.getElementById('btn-test-connection').addEventListener('click', async function () {
        const fd = new FormData();
        fd.append('action',     'test_connection');
        fd.append('csrf_token', csrf());
        const res = await postForm(fd);
        showStatus(settingsStatus, res.success ? '✅ Verbindung erfolgreich!' : ('❌ Fehler: ' + (res.error || 'Unbekannt')), res.success ? 'ok' : 'err');
        showLog(res);
    });

    // Gruppen & Playlists neu laden
    document.getElementById('btn-reload-data').addEventListener('click', async function () {
        showStatus(triggerStatus, 'Lade Daten …', 'ok');
        await loadGroups();
        await loadPlaylists();
        showStatus(triggerStatus, '✅ Gruppen & Playlists geladen', 'ok');
    });

    // Playlist triggern
    triggerForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        const fd  = new FormData(triggerForm);
        const res = await postForm(fd);
        showStatus(triggerStatus, res.success ? '✅ Playlist erfolgreich getriggert!' : ('❌ Fehler: ' + (res.error || 'Unbekannt')), res.success ? 'ok' : 'err');
        showLog(res);
    });

    // Initial laden
    await loadGroups();
    await loadPlaylists();
})();
</script>

<?php include __DIR__ . '/../inc/debug.php'; ?>
</body>
</html>
