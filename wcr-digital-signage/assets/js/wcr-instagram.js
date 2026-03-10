(function () {
    const cfg    = window.wcrInstagram || {};
    const API    = (cfg.restUrl || '/wp-json/') + 'wakecamp/v1/instagram';
    const RELOAD = (parseInt(cfg.refresh, 10) || 10) * 60 * 1000;
    const NEW_MS = (parseInt(cfg.newHours, 10) || 2) * 3600 * 1000;
    const SHOW_U = cfg.showUser !== '0';
    const HASHTAG = cfg.hashtag || 'wakecampruhlsdorf';
    const HAS_TOKEN = cfg.hasToken === true || cfg.hasToken === 'true' || cfg.hasToken === 1;

    function timeAgo(iso) {
        const s = Math.floor((Date.now() - new Date(iso)) / 1000);
        if (s < 60)    return 'Gerade';
        if (s < 3600)  return Math.floor(s / 60) + ' Min';
        if (s < 86400) return Math.floor(s / 3600) + ' Std';
        return Math.floor(s / 86400) + ' T';
    }

    function isNew(iso) { return Date.now() - new Date(iso) < NEW_MS; }

    function buildPost(p) {
        const el = document.createElement('div');
        el.className = 'wcr-insta-post';
        if (p.media_type === 'VIDEO') {
            el.innerHTML = `<video src="${p.media_url}" autoplay muted loop playsinline poster="${p.thumbnail_url || ''}"></video>`;
        } else {
            el.innerHTML = `<img src="${p.media_url || p.thumbnail_url || ''}" alt="" loading="lazy">`;
        }
        const typeIcon = p.media_type === 'VIDEO' ? '▶' : p.media_type === 'CAROUSEL_ALBUM' ? '⊞' : '';
        if (typeIcon) el.insertAdjacentHTML('beforeend', `<span class="wcr-insta-badge-type">${typeIcon}</span>`);
        if (isNew(p.timestamp)) el.insertAdjacentHTML('beforeend', `<span class="wcr-insta-badge-new">Neu</span>`);
        if (SHOW_U) {
            const user = p.username ? `@${p.username}` : `#${HASHTAG}`;
            el.insertAdjacentHTML('beforeend', `
                <div class="wcr-insta-overlay">
                    <span class="wcr-insta-username">${user}</span>
                    <span class="wcr-insta-time">${timeAgo(p.timestamp)}</span>
                </div>`);
        }
        el.addEventListener('click', () => window.open(p.permalink, '_blank'));
        return el;
    }

    // Platzhalter wenn kein Token oder keine Posts
    function renderPlaceholder(grid, reason) {
        grid.innerHTML = '';
        const count = 8;
        for (let i = 0; i < count; i++) {
            const el = document.createElement('div');
            el.className = 'wcr-insta-post wcr-insta-placeholder';
            el.innerHTML = `
                <div class="wcr-insta-ph-inner">
                    <span class="wcr-insta-ph-icon">📸</span>
                    <span class="wcr-insta-ph-text">${reason}</span>
                </div>`;
            grid.appendChild(el);
        }
    }

    function render(posts) {
        const grid = document.getElementById('wcr-insta-grid');
        if (!grid) return;

        if (!posts || !posts.length) {
            const msg = HAS_TOKEN ? 'Keine Beiträge gefunden' : 'Instagram nicht verbunden 🔑';
            renderPlaceholder(grid, msg);
            return;
        }

        grid.innerHTML = '';
        posts.slice(0, 8).forEach(p => grid.appendChild(buildPost(p)));
    }

    function load() {
        if (!HAS_TOKEN) {
            const grid = document.getElementById('wcr-insta-grid');
            if (grid) renderPlaceholder(grid, 'Instagram nicht verbunden 🔑');
            return;
        }
        fetch(API)
            .then(r => r.json())
            .then(data => render(Array.isArray(data) ? data : []))
            .catch(() => {
                const grid = document.getElementById('wcr-insta-grid');
                if (grid) renderPlaceholder(grid, 'Verbindung fehlgeschlagen');
            });
    }

    document.addEventListener('DOMContentLoaded', load);
    setInterval(load, RELOAD);
})();
