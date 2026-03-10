<?php
/**
 * inc/img-upload-modal.php
 * Bild-Upload-Modal für Produkt-Seiten (admin + cernal)
 * v2: + CSRF-Token-Update nach Upload/Delete
 *
 * Voraussetzung: $TABLE muss im aufrufenden Scope gesetzt sein.
 * Einbinden vor </body>, nach ctrl-shared.js
 */
if (!wcr_is_admin()) return; // Nur für admin + cernal
?>

<!-- ── Bild-Upload Modal ─────────────────────────────────────────────────────────── -->
<div id="img-modal" class="img-modal-overlay" style="display:none" onclick="if(event.target===this)closeImgModal()">
  <div class="img-modal-box">

    <div class="img-modal-header">
      <div>
        <div class="img-modal-title">🖼️ Produktbild hochladen</div>
        <div class="img-modal-sub" id="img-modal-product-name"></div>
      </div>
      <button class="img-modal-close" onclick="closeImgModal()">✕</button>
    </div>

    <!-- Drop-Zone -->
    <div class="img-dropzone" id="img-dropzone"
         ondragover="event.preventDefault(); this.classList.add('drag-over')"
         ondragleave="this.classList.remove('drag-over')"
         ondrop="handleImgDrop(event)">
      <div class="img-drop-inner" id="img-drop-inner">
        <div class="img-drop-icon">📸</div>
        <div class="img-drop-text">Bild hier ablegen</div>
        <div class="img-drop-sub">oder</div>
        <label class="btn-upload" style="cursor:pointer; display:inline-block;">
          Datei auswählen
          <input type="file" id="img-file-input" accept="image/*" style="display:none"
                 onchange="handleImgSelect(this)">
        </label>
        <div class="img-drop-hint">JPG · PNG · WebP · GIF · max. 10 MB</div>
      </div>
    </div>

    <!-- Vorschau -->
    <div id="img-preview-wrap" style="display:none">
      <div class="img-preview-inner">
        <img id="img-preview" src="" alt="Vorschau">
        <div class="img-preview-info">
          <div id="img-preview-name" class="img-pname"></div>
          <div id="img-preview-size" class="img-psize"></div>
        </div>
      </div>
      <div class="img-preview-actions">
        <button class="btn-upload" id="img-upload-btn" onclick="doImgUpload()">⬆ Hochladen</button>
        <button class="btn-secondary" onclick="resetImgModal()">↩ Anderes Bild</button>
      </div>
    </div>

    <!-- Aktuelles Bild -->
    <div id="img-current-wrap" style="display:none" class="img-current">
      <div class="img-current-label">Aktuelles Bild</div>
      <div class="img-current-inner">
        <img id="img-current" src="" alt="">
        <button class="img-delete-btn" onclick="deleteImg()" title="Bild entfernen">🗑 Entfernen</button>
      </div>
    </div>

    <!-- Status -->
    <div id="img-status" class="img-status" style="display:none"></div>

  </div>
</div>

<style>
.img-modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.55); display:flex; align-items:center; justify-content:center; z-index:9500; backdrop-filter:blur(4px); }
.img-modal-box     { background:var(--bg-card); border-radius:14px; padding:28px; width:500px; max-width:95vw; box-shadow:0 12px 50px rgba(0,0,0,.25); display:flex; flex-direction:column; gap:16px; }
.img-modal-header  { display:flex; justify-content:space-between; align-items:flex-start; }
.img-modal-title   { font-size:17px; font-weight:700; }
.img-modal-sub     { font-size:13px; color:var(--text-muted); margin-top:3px; }
.img-modal-close   { width:32px; height:32px; border-radius:50%; border:1px solid var(--border); background:var(--bg-subtle); cursor:pointer; font-size:14px; display:flex; align-items:center; justify-content:center; flex-shrink:0; transition:background .15s; }
.img-modal-close:hover { background:var(--border-light); }

/* Drop-Zone */
.img-dropzone      { border:2px dashed var(--border); border-radius:10px; transition:border-color .2s, background .2s; }
.img-dropzone.drag-over { border-color:var(--primary); background:rgba(0,113,227,.04); }
.img-drop-inner    { padding:32px 20px; text-align:center; display:flex; flex-direction:column; align-items:center; gap:8px; }
.img-drop-icon     { font-size:40px; }
.img-drop-text     { font-size:15px; font-weight:600; color:var(--text-main); }
.img-drop-sub      { font-size:13px; color:var(--text-muted); }
.img-drop-hint     { font-size:11px; color:var(--text-muted); margin-top:4px; }

/* Vorschau */
.img-preview-inner { display:flex; gap:14px; align-items:center; background:var(--bg-subtle); border-radius:10px; padding:12px; }
.img-preview-inner img { width:80px; height:80px; object-fit:cover; border-radius:8px; border:1px solid var(--border); }
.img-pname         { font-size:14px; font-weight:600; word-break:break-all; }
.img-psize         { font-size:12px; color:var(--text-muted); margin-top:3px; }
.img-preview-actions { display:flex; gap:10px; }
.img-preview-actions .btn-upload { flex:1; }
.img-preview-actions .btn-secondary { flex:1; }

/* Aktuelles Bild */
.img-current       { border-top:1px solid var(--border-light); padding-top:12px; }
.img-current-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--text-muted); margin-bottom:8px; }
.img-current-inner { display:flex; align-items:center; gap:14px; }
.img-current-inner img { width:64px; height:64px; object-fit:cover; border-radius:8px; border:1px solid var(--border); }
.img-delete-btn    { padding:6px 14px; font-size:12px; background:#fff0f0; color:#c0392b; border:1px solid #ffd0cc; border-radius:7px; cursor:pointer; transition:opacity .15s; }
.img-delete-btn:hover { opacity:.75; }

/* Status */
.img-status        { padding:10px 14px; border-radius:8px; font-size:13px; font-weight:500; }
.img-status.ok     { background:rgba(52,199,89,.12); color:#1c7c34; border:1px solid rgba(52,199,89,.3); }
.img-status.error  { background:rgba(255,59,48,.10); color:#c0392b; border:1px solid rgba(255,59,48,.3); }
.img-status.loading { background:rgba(0,113,227,.08); color:#0071e3; border:1px solid rgba(0,113,227,.2); }

/* Upload-Button an der Karte (nur admin/cernal) */
.card-img-upload-btn {
    position:absolute; bottom:4px; right:4px;
    width:28px; height:28px; border-radius:50%;
    background:rgba(0,0,0,.55); backdrop-filter:blur(4px);
    border:1px solid rgba(255,255,255,.2);
    color:#fff; font-size:13px;
    cursor:pointer; display:flex; align-items:center; justify-content:center;
    opacity:0; transition:opacity .2s;
    z-index:2;
}
.item-card:hover .card-img-upload-btn { opacity:1; }
.card-image-container { position:relative; }
</style>

<script>
var _imgModal = {
    table:   '',
    nummer:  0,
    product: '',
    file:    null,
};

// ── Modal öffnen ────────────────────────────────────────────────────────────────
function openImgModal(table, nummer, productName, currentUrl) {
    _imgModal.table   = table;
    _imgModal.nummer  = nummer;
    _imgModal.product = productName;
    _imgModal.file    = null;

    document.getElementById('img-modal-product-name').textContent = '# ' + nummer + ' · ' + productName;
    document.getElementById('img-modal').style.display = 'flex';

    // Vorschau zurücksetzen
    resetImgModal();

    // Aktuelles Bild anzeigen
    var cw = document.getElementById('img-current-wrap');
    var ci = document.getElementById('img-current');
    if (currentUrl) {
        ci.src = currentUrl;
        cw.style.display = 'block';
    } else {
        cw.style.display = 'none';
    }

    hideImgStatus();
}

function closeImgModal() {
    document.getElementById('img-modal').style.display = 'none';
    _imgModal.file = null;
}

function resetImgModal() {
    document.getElementById('img-preview-wrap').style.display = 'none';
    document.getElementById('img-dropzone').style.display     = 'block';
    document.getElementById('img-file-input').value           = '';
    _imgModal.file = null;
    hideImgStatus();
}

// ── Datei auswählen / Drop ────────────────────────────────────────────────────────────────
function handleImgSelect(input) {
    if (input.files && input.files[0]) showImgPreview(input.files[0]);
}

function handleImgDrop(e) {
    e.preventDefault();
    document.getElementById('img-dropzone').classList.remove('drag-over');
    var files = e.dataTransfer.files;
    if (files && files[0]) showImgPreview(files[0]);
}

function showImgPreview(file) {
    _imgModal.file = file;
    var reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('img-preview').src = e.target.result;
        document.getElementById('img-preview-name').textContent = file.name;
        document.getElementById('img-preview-size').textContent = (file.size / 1024).toFixed(0) + ' KB';
        document.getElementById('img-dropzone').style.display    = 'none';
        document.getElementById('img-preview-wrap').style.display = 'block';
        hideImgStatus();
    };
    reader.readAsDataURL(file);
}

// ── Upload senden ────────────────────────────────────────────────────────────────
function doImgUpload() {
    if (!_imgModal.file) return;
    var btn = document.getElementById('img-upload-btn');
    btn.disabled    = true;
    btn.textContent = '⏳ Lädt...';
    showImgStatus('Bild wird hochgeladen…', 'loading');

    var fd = new FormData();
    fd.append('file',   _imgModal.file);
    fd.append('table',  _imgModal.table);
    fd.append('nummer', _imgModal.nummer);
    // ── CSRF-Token anhängen ──
    fd.append('csrf_token', document.body.dataset.csrf || '');

    fetch('/be/api/upload_image.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.disabled    = false;
            btn.textContent = '⬆ Hochladen';
            if (data.ok) {
                // ── CSRF-Token nach Rotation aktualisieren ──
                // API hat neues Token zurückgegeben, Frontend muss es speichern
                // damit nächster Upload auf derselben Seite funktioniert
                if (data.csrf_token) {
                    document.body.dataset.csrf = data.csrf_token;
                }

                showImgStatus('✓ Bild gespeichert!', 'ok');
                // Karte in der Produktliste aktualisieren
                updateCardImage(_imgModal.nummer, data.url);
                // Aktuelles Bild im Modal updaten
                document.getElementById('img-current').src = data.url + '?t=' + Date.now();
                document.getElementById('img-current-wrap').style.display = 'block';
                // Nach 1.5s Modal schließen
                setTimeout(closeImgModal, 1500);
            } else {
                showImgStatus('✗ ' + (data.error || 'Fehler'), 'error');
            }
        })
        .catch(function(e) {
            btn.disabled    = false;
            btn.textContent = '⬆ Hochladen';
            showImgStatus('✗ Netzwerkfehler: ' + e.message, 'error');
        });
}

// ── Bild löschen ────────────────────────────────────────────────────────────────
function deleteImg() {
    if (!confirm('Bild von "' + _imgModal.product + '" entfernen?')) return;
    var fd = new FormData();
    fd.append('table',  _imgModal.table);
    fd.append('nummer', _imgModal.nummer);
    fd.append('delete', '1');
    // ── CSRF-Token anhängen ──
    fd.append('csrf_token', document.body.dataset.csrf || '');

    fetch('/be/api/upload_image.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.ok) {
                // ── CSRF-Token nach Rotation aktualisieren ──
                // API hat neues Token zurückgegeben, Frontend muss es speichern
                // damit nächste Aktion auf derselben Seite funktioniert
                if (data.csrf_token) {
                    document.body.dataset.csrf = data.csrf_token;
                }

                showImgStatus('✓ Bild entfernt', 'ok');
                updateCardImage(_imgModal.nummer, '');
                document.getElementById('img-current-wrap').style.display = 'none';
                setTimeout(closeImgModal, 1000);
            } else {
                showImgStatus('✗ ' + (data.error || 'Fehler'), 'error');
            }
        });
}

// ── Produktkarte in der Liste aktualisieren ──────────────────────────────────────────────────────────
function updateCardImage(nummer, url) {
    var card = document.getElementById('card-' + nummer);
    if (!card) return;
    var cic = card.querySelector('.card-image-container');
    if (!cic) return;
    var img = cic.querySelector('.product-img');
    var ph  = cic.querySelector('.card-image-placeholder');
    if (url) {
        if (img) {
            img.src = url + '?t=' + Date.now();
        } else {
            var ni = document.createElement('img');
            ni.src = url + '?t=' + Date.now();
            ni.className = 'product-img';
            ni.style.cssText = 'width:100%;height:100%;object-fit:cover;border-radius:6px;';
            if (ph) ph.replaceWith(ni);
            else cic.appendChild(ni);
        }
        // Upload-Button Upload-Icon updaten
        var ub = cic.querySelector('.card-img-upload-btn');
        if (ub) ub.title = 'Bild ändern';
    } else {
        if (img) img.remove();
        if (!cic.querySelector('.card-image-placeholder')) {
            var s = document.createElement('span');
            s.className = 'card-image-placeholder';
            s.textContent = '📷';
            cic.appendChild(s);
        }
    }
}

function showImgStatus(msg, type) {
    var el = document.getElementById('img-status');
    el.textContent  = msg;
    el.className    = 'img-status ' + type;
    el.style.display = 'block';
}
function hideImgStatus() {
    var el = document.getElementById('img-status');
    if (el) el.style.display = 'none';
}
</script>
