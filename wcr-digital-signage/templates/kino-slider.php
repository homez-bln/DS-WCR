<?php
/**
 * Template: Kino Cinema Slider (Frontend)
 * Shortcode: [wcr_kino_slider]
 * 
 * Design: Unified CI with Glass-Morphism
 * Farben: #679467 (Grün), #000806 (BG), #ececec (Text)
 */
if (!defined('ABSPATH')) exit;

// Fetch films from REST API
$api_url = home_url('/wp-json/wakecamp/v1/kino');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🎬 Open-Air-Kino</title>
    <link rel="stylesheet" href="<?= plugin_dir_url(dirname(__FILE__)) ?>assets/css/wcr-ds-unified.css">
    <style>
        /* ── Kino-spezifische Styles ── */
        .kino-wrapper {
            width: 100vw;
            height: 100vh;
            display: flex;
            flex-direction: column;
            background: var(--clr-bg);
            overflow: hidden;
        }
        
        /* Header */
        .kino-header {
            padding: var(--gap-xl) var(--gap-xl) var(--gap-lg);
            background: linear-gradient(180deg, rgba(0,8,6,1) 0%, rgba(0,8,6,0) 100%);
            position: relative;
            z-index: 10;
        }
        
        .kino-header__content {
            max-width: 1800px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .kino-logo {
            font-size: var(--fs-4xl);
            font-weight: var(--fw-bold);
            color: var(--clr-green);
            text-shadow: 0 0 30px rgba(103, 148, 103, 0.5);
            display: flex;
            align-items: center;
            gap: var(--gap-md);
        }
        
        .kino-meta {
            display: flex;
            gap: var(--gap-lg);
            align-items: center;
        }
        
        .kino-date {
            font-size: var(--fs-2xl);
            color: var(--clr-text);
            font-weight: var(--fw-medium);
        }
        
        /* Slider Container */
        .kino-slider {
            flex: 1;
            position: relative;
            overflow: hidden;
            padding: 0 var(--gap-xl) var(--gap-xl);
        }
        
        .kino-track {
            display: flex;
            gap: var(--gap-xl);
            animation: slide-infinite 60s linear infinite;
            will-change: transform;
        }
        
        .kino-track:hover {
            animation-play-state: paused;
        }
        
        @keyframes slide-infinite {
            0% {
                transform: translateX(0);
            }
            100% {
                transform: translateX(-50%);
            }
        }
        
        /* Film Card */
        .film-card {
            flex: 0 0 450px;
            height: 650px;
            background: var(--clr-bg-glass);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            border: 1px solid rgba(103, 148, 103, 0.2);
            border-radius: var(--radius-lg);
            overflow: hidden;
            position: relative;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--shadow-lg);
        }
        
        .film-card:hover {
            transform: translateY(-12px) scale(1.02);
            border-color: var(--clr-green);
            box-shadow: var(--shadow-lg), 0 0 40px rgba(103, 148, 103, 0.4);
        }
        
        .film-poster {
            width: 100%;
            height: 500px;
            object-fit: cover;
            border-bottom: 2px solid var(--clr-green);
        }
        
        .film-info {
            padding: var(--gap-lg);
            background: linear-gradient(180deg, 
                rgba(0,8,6,0.95) 0%, 
                rgba(11,11,11,0.98) 100%);
        }
        
        .film-title {
            font-size: var(--fs-2xl);
            font-weight: var(--fw-bold);
            color: var(--clr-white);
            margin-bottom: var(--gap-sm);
            line-height: 1.2;
            text-shadow: 0 2px 10px rgba(0,0,0,0.5);
        }
        
        .film-date {
            display: flex;
            align-items: center;
            gap: var(--gap-sm);
            font-size: var(--fs-lg);
            color: var(--clr-green);
            font-weight: var(--fw-semibold);
        }
        
        .film-badge {
            position: absolute;
            top: var(--gap-lg);
            right: var(--gap-lg);
            padding: 8px 16px;
            background: rgba(103, 148, 103, 0.95);
            color: var(--clr-bg);
            font-size: var(--fs-sm);
            font-weight: var(--fw-bold);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            border-radius: var(--radius-sm);
            box-shadow: var(--shadow-md);
            backdrop-filter: blur(10px);
        }
        
        /* Loading State */
        .kino-loading {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            height: 100%;
            gap: var(--gap-lg);
        }
        
        .kino-loading__icon {
            font-size: 64px;
            animation: pulse 2s ease-in-out infinite;
        }
        
        .kino-loading__text {
            font-size: var(--fs-xl);
            color: var(--clr-muted);
        }
        
        /* Empty State */
        .kino-empty {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            height: 100%;
            gap: var(--gap-md);
            text-align: center;
            padding: var(--gap-xl);
        }
        
        .kino-empty__icon {
            font-size: 96px;
            opacity: 0.5;
        }
        
        .kino-empty__title {
            font-size: var(--fs-3xl);
            color: var(--clr-text);
            margin: 0;
        }
        
        .kino-empty__text {
            font-size: var(--fs-lg);
            color: var(--clr-muted);
            max-width: 600px;
        }
    </style>
</head>
<body>

<div class="kino-wrapper">
    <!-- Header -->
    <header class="kino-header">
        <div class="kino-header__content">
            <div class="kino-logo">
                🎬<span>Open-Air-Kino</span>
            </div>
            <div class="kino-meta">
                <div class="kino-date" id="current-date"></div>
                <span class="ds-badge ds-badge--green" id="live-badge">
                    <span style="width:6px;height:6px;background:currentColor;border-radius:50%;display:inline-block;animation:pulse 2s infinite;"></span>
                    Live
                </span>
            </div>
        </div>
    </header>
    
    <!-- Slider -->
    <div class="kino-slider" id="kino-slider">
        <!-- Loading State -->
        <div class="kino-loading" id="loading-state">
            <div class="kino-loading__icon">🎬</div>
            <div class="kino-loading__text">Filme werden geladen...</div>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';
    
    const API_URL = '<?= $api_url ?>';
    const slider = document.getElementById('kino-slider');
    const loadingState = document.getElementById('loading-state');
    const dateEl = document.getElementById('current-date');
    
    // Update Date & Time
    function updateDateTime() {
        const now = new Date();
        const options = { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric'
        };
        dateEl.textContent = now.toLocaleDateString('de-DE', options);
    }
    updateDateTime();
    setInterval(updateDateTime, 60000); // Update every minute
    
    // Format Date
    function formatDate(dateStr) {
        const date = new Date(dateStr);
        const options = { 
            weekday: 'short', 
            day: '2-digit', 
            month: 'short' 
        };
        return date.toLocaleDateString('de-DE', options);
    }
    
    // Check if film is today
    function isToday(dateStr) {
        const filmDate = new Date(dateStr).toDateString();
        const today = new Date().toDateString();
        return filmDate === today;
    }
    
    // Render Films
    function renderFilms(films) {
        if (!films || films.length === 0) {
            slider.innerHTML = `
                <div class="kino-empty">
                    <div class="kino-empty__icon">🎬</div>
                    <h2 class="kino-empty__title">Keine Filme geplant</h2>
                    <p class="kino-empty__text">
                        Aktuell sind keine Filme im Programm.<br>
                        Schau später nochmal vorbei!
                    </p>
                </div>
            `;
            return;
        }
        
        // Duplicate films for infinite scroll
        const duplicatedFilms = [...films, ...films];
        
        const track = document.createElement('div');
        track.className = 'kino-track';
        
        duplicatedFilms.forEach(film => {
            const card = document.createElement('div');
            card.className = 'film-card';
            
            const badge = isToday(film.date) 
                ? '<div class="film-badge">⚡ Heute</div>' 
                : '';
            
            card.innerHTML = `
                ${badge}
                <img 
                    src="${film.cover_url}" 
                    alt="${film.title}"
                    class="film-poster"
                    onerror="this.src='https://via.placeholder.com/450x500/011e45/679467?text=${encodeURIComponent(film.title)}'"
                >
                <div class="film-info">
                    <h3 class="film-title">${film.title}</h3>
                    <div class="film-date">
                        📅 ${formatDate(film.date)}
                    </div>
                </div>
            `;
            
            track.appendChild(card);
        });
        
        slider.innerHTML = '';
        slider.appendChild(track);
    }
    
    // Fetch Films
    async function fetchFilms() {
        try {
            const response = await fetch(API_URL);
            if (!response.ok) throw new Error('API Error');
            
            const films = await response.json();
            renderFilms(films);
        } catch (error) {
            console.error('Kino API Error:', error);
            slider.innerHTML = `
                <div class="kino-empty">
                    <div class="kino-empty__icon">⚠️</div>
                    <h2 class="kino-empty__title">Verbindungsfehler</h2>
                    <p class="kino-empty__text">
                        Die Filmdaten konnten nicht geladen werden.<br>
                        Seite wird automatisch aktualisiert...
                    </p>
                </div>
            `;
            // Retry after 10 seconds
            setTimeout(fetchFilms, 10000);
        }
    }
    
    // Initialize
    fetchFilms();
    
    // Refresh every 5 minutes
    setInterval(fetchFilms, 300000);
    
})();
</script>

</body>
</html>
