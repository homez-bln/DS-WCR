'use strict';

/* ================================================================
   PREVIEW RELOAD
   Nach jeder gespeicherten Zeit wird der iFrame nach 2s neu geladen.
   Debounced: mehrere schnelle Änderungen = nur ein Reload.
   ================================================================ */
var _previewTimer = null;
function schedulePreviewReload() {
    clearTimeout(_previewTimer);
    _previewTimer = setTimeout(function () {
        var iframe = document.getElementById('preview-iframe');
        if (!iframe) return;
        iframe.style.opacity    = '0.4';
        iframe.style.transition = 'opacity .3s';
        iframe.onload = function () {
            iframe.style.opacity = '1';
            iframe.onload = null;
        };
        iframe.src = iframe.src.split('?')[0] + '?t=' + Date.now();
    }, 2000);
}

/* ================================================================
   1. ZEIT-INPUT SPEICHERN
   ================================================================ */
function updateTime(input, date, col) {
    input.classList.remove('is-fallback');
    input.classList.add('saving');
    sendData(date, col, input.value,
        () => {
            input.classList.remove('saving');
            input.classList.add('saved');
            setTimeout(() => input.classList.remove('saved'), 1200);
            schedulePreviewReload();
        },
        () => {
            input.classList.remove('saving');
            input.classList.add('error');
        }
    );
}

function clearTimes(date) {
    if (!confirm('Start- und Endzeit für ' + date + ' wirklich löschen?')) return;
    const startInput = document.getElementById('start-' + date);
    const endInput   = document.getElementById('end-'   + date);
    const btn        = document.querySelector('[onclick="clearTimes(\\'' + date + '\\')"]');
    if (startInput) { startInput.value = ''; startInput.classList.add('saving'); }
    if (endInput)   { endInput.value   = ''; endInput.classList.add('saving'); endInput.classList.add('is-fallback'); }
    sendData(date, 'start_time', '', () => {
        if (startInput) { startInput.classList.remove('saving'); startInput.classList.add('saved'); setTimeout(() => startInput.classList.remove('saved'), 1200); }
        sendData(date, 'end_time', '', () => {
            if (endInput) { endInput.classList.remove('saving'); endInput.classList.add('saved'); setTimeout(() => endInput.classList.remove('saved'), 1200); }
            if (btn) btn.remove();
            schedulePreviewReload();
        }, err => { if (endInput) endInput.classList.add('error'); alert('Fehler: ' + err); });
    }, err => { if (startInput) startInput.classList.add('error'); alert('Fehler: ' + err); });
}

function toggleCourse(date, col, toggleBtn) {
    const pill      = toggleBtn.closest('.course-pill');
    const wasActive = pill.classList.contains('active');
    const newVal    = wasActive ? 0 : 1;
    const textInput = pill.querySelector('.course-input');
    const textVal   = textInput ? textInput.value : '';
    const textCol   = col + '_text';
    const startVal  = document.getElementById('start-' + date)?.value ?? '';
    const endVal    = document.getElementById('end-'   + date)?.value ?? '';
    pill.classList.toggle('active');
    toggleBtn.style.opacity = '0.5';
    sendData(date, col, newVal, () => {
        toggleBtn.style.opacity = '1';
        const fallback = textInput ? (textInput.dataset.fallback || '') : '';
        const saveText = newVal ? textVal : '';
        sendData(date, textCol, saveText, () => {
            if (!newVal && textInput) textInput.value = fallback;
            schedulePreviewReload();
        }, err => console.warn('Text-Fehler: ' + err));
    }, err => { toggleBtn.style.opacity = '1'; pill.classList.toggle('active'); alert('Fehler: ' + err); },
    { preserve_start_time: startVal, preserve_end_time: endVal });
}

function updateCourseText(input, date, col) {
    input.style.color = '#ccc';
    sendData(date, col, input.value,
        () => {
            input.style.color = input.closest('.course-pill').classList.contains('active') ? '#333' : '#aaa';
            schedulePreviewReload();
        },
        () => { input.style.color = 'red'; alert('Speicherfehler beim Kurstext'); }
    );
}

function sendData(date, col, val, onSuccess, onError, extra = {}) {
    fetch('../api/save_opening_hours.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            datum: date, 
            field: col, 
            value: val, 
            // ── CSRF-Token anhängen ──
            csrf_token: document.body.dataset.csrf || '',
            ...extra 
        })
    })
    .then(r => r.json())
    .then(d => { 
        if (d.success) {
            // ── CSRF-Token nach Rotation aktualisieren ──
            // API hat neues Token zurückgegeben, Frontend muss es speichern
            // damit nächster Request auf derselben Seite funktioniert
            if (d.csrf_token) {
                document.body.dataset.csrf = d.csrf_token;
            }
            onSuccess(); 
        } else {
            onError(d.error || 'Unbekannter Fehler'); 
        }
    })
    .catch(err => onError(err.message || err));
}

/* ================================================================
   SCREENSHOT
   ================================================================ */
function downloadAsJPG() {
    const btn = event.currentTarget || event.target;
    const origText = btn.textContent;
    btn.textContent = 'Lädt…';
    btn.disabled    = true;

    const pw   = 1080, ph = 600;
    const left = Math.round((screen.width  - pw) / 2);
    const top  = Math.round((screen.height - ph) / 2);
    const popup = window.open(
        'https://wcr-webpage.de/oeffnungszeiten-story/?wcr_screenshot=1',
        'wcr_shot',
        'width=' + pw + ',height=' + ph + ',left=' + left + ',top=' + top
    );

    if (!popup) {
        alert('Popup blockiert — bitte Popup-Blocker für diese Seite erlauben.');
        btn.textContent = origText;
        btn.disabled    = false;
        return;
    }

    function onMsg(e) {
        if (!e.data || !e.data.wcr_screenshot_done) return;
        window.removeEventListener('message', onMsg);
        btn.textContent = origText;
        btn.disabled    = false;
    }
    window.addEventListener('message', onMsg);
    setTimeout(function () {
        window.removeEventListener('message', onMsg);
        btn.textContent = origText;
        btn.disabled    = false;
    }, 30000);
}

function toggleClosed(date, btn) {
    const wasClosed = btn.classList.contains('active');
    const newVal    = wasClosed ? 0 : 1;
    btn.style.opacity = '0.5';
    sendData(date, 'is_closed', newVal, () => {
        btn.style.opacity = '1';
        btn.classList.toggle('active');
        btn.textContent = newVal ? '🔓' : '🔒';
        btn.title       = newVal ? 'Wieder öffnen' : 'Als geschlossen markieren';
        const cell       = btn.closest('.cell');
        const existBadge = cell.querySelector('.badge-closed');
        if (newVal) {
            cell.classList.add('cell-closed');
            if (!existBadge) { const b2 = document.createElement('span'); b2.className = 'badge-closed'; b2.textContent = 'Geschlossen'; cell.querySelector('div').appendChild(b2); }
            ['start','end'].forEach(t => { const i = document.getElementById(t+'-'+date); if(i) i.disabled=true; });
        } else {
            cell.classList.remove('cell-closed');
            if (existBadge) existBadge.remove();
            ['start','end'].forEach(t => { const i = document.getElementById(t+'-'+date); if(i) i.disabled=false; });
        }
        schedulePreviewReload();
    }, err => { btn.style.opacity = '1'; alert('Fehler: ' + err); });
}
