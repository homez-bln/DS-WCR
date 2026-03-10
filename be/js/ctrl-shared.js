/* ctrl-shared.js — gemeinsames JS für alle Produkt-Seiten v10 + CSRF-Token-Update
 * Eingebunden per PHP include (wegen TABLE-Variable)
 * FIX v6: War in jeder ctrl-Datei 1:1 kopiert (5× identischer Code)
 * SECURITY v9: CSRF-Token wird aus data-csrf-Attribut gelesen
 * SECURITY v10: Token wird nach API-Response aktualisiert (Token-Rotation)
 */

// ── CSRF-Token aus <body data-csrf="..."> lesen ──
function getCsrfToken() {
    return document.body.getAttribute('data-csrf') || '';
}

function toggleGroup(h) {
    const key  = h.dataset.group;
    const body = document.querySelector('[data-group-body="' + key + '"]');
    if (!body) return;
    const collapsed = body.classList.toggle('collapsed');
    h.classList.toggle('collapsed', collapsed);
    // Sync für food.php (doppelte Header list/gallery)
    document.querySelectorAll('.group-header[data-group="' + key + '"]').forEach(el => {
        if (el !== h) el.classList.toggle('collapsed', collapsed);
    });
    localStorage.setItem(key, collapsed ? '1' : '0');
}

function setView(view) {
    const c = document.getElementById('items-container');
    if (!c) return;
    if (view === 'gallery') {
        c.classList.replace('view-list', 'view-gallery');
        document.getElementById('btn-list').classList.remove('active');
        document.getElementById('btn-gallery').classList.add('active');
    } else {
        c.classList.replace('view-gallery', 'view-list');
        document.getElementById('btn-gallery').classList.remove('active');
        document.getElementById('btn-list').classList.add('active');
    }
    localStorage.setItem('viewPref_' + TABLE, view);
}

function handleCardClick(e, nr) {
    const c = document.getElementById('items-container');
    if (!c || !c.classList.contains('view-gallery')) return;
    if (e.target.tagName === 'INPUT') return;
    const cb = document.getElementById('cb-' + nr);
    if (cb) { cb.checked = !cb.checked; upd(cb, 'toggle'); }
}

function upd(el, mode) {
    const nr  = el.getAttribute('data-nr');
    const val = mode === 'toggle' ? (el.checked ? '1' : '0') : el.value;
    if (mode === 'toggle') {
        const card = document.getElementById('card-' + nr);
        if (card) card.classList.toggle('card-off', !el.checked);
    }
    const s = document.getElementById('s-' + nr);
    if (!s) return;
    s.textContent = '…'; s.className = 'status-msg visible';
    
    // ── CSRF-Token mitschicken ──
    const params = new URLSearchParams();
    params.append('table', TABLE);
    params.append('nummer', nr);
    params.append('mode', mode);
    params.append('value', val);
    params.append('csrf_token', getCsrfToken());
    
    fetch('/be/api/update_ticket.php', {
        method : 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body   : params.toString()
    })
    .then(r => r.json())
    .then(d => {
        if (d.ok) {
            // ── CSRF-Token nach Rotation aktualisieren ──
            // API hat neues Token zurückgegeben (wcr_verify_csrf_silent rotiert automatisch).
            // Frontend MUSS es speichern, sonst schlägt nächster Request fehl.
            if (d.csrf_token) {
                document.body.dataset.csrf = d.csrf_token;
            }
            
            s.textContent = 'OK'; s.className = 'status-msg visible success';
            setTimeout(() => { s.textContent = ''; s.className = 'status-msg'; }, 1500);
        } else {
            s.textContent = 'Fehler'; s.className = 'status-msg visible error';
            console.error(d);
        }
    })
    .catch(() => { s.textContent = 'Err'; s.className = 'status-msg visible error'; });
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.group-header').forEach(h => {
        const key = h.dataset.group;
        if (localStorage.getItem(key) === '1') {
            h.classList.add('collapsed');
            const b = document.querySelector('[data-group-body="' + key + '"]');
            if (b) b.classList.add('collapsed');
        }
    });
    const pref = localStorage.getItem('viewPref_' + TABLE);
    if (pref === 'gallery') setView('gallery');
});
