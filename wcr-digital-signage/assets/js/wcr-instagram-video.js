(function () {
    const API     = wcrInstagramVideo.restUrl + 'wakecamp/v1/instagram/videos';
    const HASHTAG = wcrInstagramVideo.hashtag;

    let videos  = [];
    let current = 0;

    const player   = document.getElementById('wcr-iv-player');
    const fadeEl   = document.querySelector('.wcr-iv-fade');
    const username = document.getElementById('wcr-iv-username');
    const timeEl   = document.getElementById('wcr-iv-time');
    const counter  = document.getElementById('wcr-iv-counter');
    const muteBtn  = document.getElementById('wcr-iv-mute');
    const dots     = () => document.querySelectorAll('.wcr-iv-dot');

    function timeAgo(iso) {
        const s = Math.floor((Date.now() - new Date(iso)) / 1000);
        if (s < 60)    return 'Gerade eben';
        if (s < 3600)  return Math.floor(s / 60) + ' Min';
        if (s < 86400) return Math.floor(s / 3600) + ' Std';
        return Math.floor(s / 86400) + ' Tag' + (Math.floor(s / 86400) > 1 ? 'e' : '');
    }

    function updateDots() {
        dots().forEach((d, i) => {
            d.classList.remove('active', 'done');
            const fill = d.querySelector('.wcr-iv-dot-fill');
            fill.style.transition = 'none';
            fill.style.width = '0%';
            if (i < current) { d.classList.add('done'); fill.style.width = '100%'; }
            if (i === current) d.classList.add('active');
        });
    }

    function startProgress() {
        if (!player.duration) return;
        const fill = dots()[current]?.querySelector('.wcr-iv-dot-fill');
        if (!fill) return;
        const dur = player.duration * 1000;
        fill.style.transition = `width ${dur}ms linear`;
        fill.style.width = '100%';
    }

    function playVideo(index) {
        if (!videos[index]) return;
        const v = videos[index];
        fadeEl.classList.add('active');
        setTimeout(() => {
            player.src = v.media_url || v.thumbnail_url || '';
            player.poster = v.thumbnail_url || '';
            if (username) username.textContent = v.username ? `@${v.username}` : `#${HASHTAG}`;
            if (timeEl)   timeEl.textContent   = timeAgo(v.timestamp);
            if (counter)  counter.textContent  = `${index + 1} / ${videos.length}`;
            updateDots();
            player.load();
            player.play().catch(() => {});
            fadeEl.classList.remove('active');
        }, 600);
    }

    function load() {
        fetch(API)
            .then(r => r.json())
            .then(data => {
                if (!data.length) return;
                videos  = data;
                current = 0;
                updateDots();
                playVideo(0);
            })
            .catch(console.error);
    }

    if (player) {
        player.addEventListener('loadedmetadata', startProgress);
        player.addEventListener('ended', () => {
            current = (current + 1) % videos.length;
            playVideo(current);
        });
    }
    if (muteBtn) {
        muteBtn.addEventListener('click', () => {
            player.muted = !player.muted;
            muteBtn.textContent = player.muted ? '\uD83D\uDD07' : '\uD83D\uDD0A';
        });
    }

    setInterval(load, 30 * 60 * 1000);
    document.addEventListener('DOMContentLoaded', load);
})();
