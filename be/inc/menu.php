<?php
/**
 * inc/menu.php v14 — Apple-Style Pastell-Kacheln, helles 2/3-Panel
 */
$_currentScript = basename($_SERVER['PHP_SELF']);
$_currentQuery  = $_SERVER['QUERY_STRING'] ?? '';

if (!function_exists('_wcr_menu_active')) {
    function _wcr_menu_active(string $href, string $cur, string $curQ): bool {
        $hrefFile = basename(strtok($href, '?'));
        if ($cur !== $hrefFile) return false;
        $hrefQ = parse_url($href, PHP_URL_QUERY) ?? '';
        if ($hrefQ === '') return true;
        parse_str($hrefQ, $hP);
        parse_str($curQ, $cP);
        return ($hP['t'] ?? '') === ($cP['t'] ?? '');
    }
}

$_mainItems = [
    ['⏱',  'Open',      'ctrl/times.php',          'view_times'   ],
    ['🥤',  'Getränke',  'ctrl/drinks.php',         'edit_products'],
    ['🍔',  'Essen',     'ctrl/food.php',           'edit_products'],
    ['🍦',  'Eis',       'ctrl/list.php?t=ice',     'edit_products'],
    ['🪝',  'Cable',     'ctrl/list.php?t=cable',   'edit_products'],
    ['⛺',  'Camping',   'ctrl/list.php?t=camping', 'edit_products'],
    ['➕',  'Extra',     'ctrl/list.php?t=extra',   'edit_products'],
];

// Apple Pastell — hell, gesättigt genug für Wiedererkennbarkeit
$_tileColors = [
    ['#d0e8ff','#1a6fb5'],  // Kino       — Hellblau
    ['#ffecd0','#b56a10'],  // Media      — Pfirsich
    ['#d4f5d4','#1a7a1a'],  // Obstacles  — Mintgrün
    ['#ede0ff','#6b2fb5'],  // DS-Seiten  — Lavendel
    ['#ffd6f5','#a0238a'],  // DS Control — Rosa
    ['#ffd6d6','#c0241c'],  // Instagram  — Lachsrot
    ['#cce8ff','#0a5899'],  // piSignage  — Eisblau
    ['#d4f7e0','#0f7a3a'],  // Spotify    — Mintgrün
    ['#d6f4ff','#0a7a99'],  // Benutzer   — Türkis
    ['#e8e8e8','#4a4a4a'],  // Rechte     — Silber
];

$_burgerItems = [
    ['🎬',  'Kino',        'ctrl/kino.php',           'edit_content', $_tileColors[0]],
    ['📸',  'Media',       'ctrl/media.php',          'view_media',   $_tileColors[1]],
    ['🗺',  'Obstacles',   'ctrl/obstacles.php',      'edit_content', $_tileColors[2]],
    ['💻',  'DS-Seiten',   'ctrl/ds-seiten.php',      'view_ds',      $_tileColors[3]],
    ['⚙️',  'DS Control',  'ctrl/ds-settings.php',    'view_ds',      $_tileColors[4]],
    ['📸',  'Instagram',   'ctrl/instagram.php',      'view_ds',      $_tileColors[5]],
    ['📺',  'piSignage',   'ctrl/pisignage.php',      'view_ds',      $_tileColors[6]],
    ['🎵',  'Spotify',     'ctrl/spotify.php',        'manage_users', $_tileColors[7]],
    ['👥',  'Benutzer',    'ctrl/users.php',          'manage_users', $_tileColors[8]],
    ['🔐',  'Rechte',      'ctrl/permissions.php',    null,           $_tileColors[9]],
];

$_visibleBurger = array_values(array_filter($_burgerItems, function($item) {
    $perm = $item[3];
    if ($perm === null) return wcr_is_cernal();
    return wcr_can($perm);
}));
$_showBurger  = count($_visibleBurger) > 0;
$_burgerActive = false;
foreach ($_visibleBurger as [$i,$l,$href]) {
    if (_wcr_menu_active($href, $_currentScript, $_currentQuery)) { $_burgerActive = true; break; }
}

require_once __DIR__ . '/design-tokens.php';
?>
<link rel="stylesheet" href="/be/inc/style.css">

<!-- NAV -->
<div class="nav-wrap">
  <div class="nav-main">
    <a href="/be/index.php" class="nav-btn nav-home-btn <?= $_currentScript==='index.php'?'active':'' ?>">
      <span class="nb-icon">🏠</span><span class="nb-label">Start</span>
    </a>
    <?php foreach ($_mainItems as [$icon,$label,$href,$perm]):
      if ($perm && !wcr_can($perm)) continue;
      $active = _wcr_menu_active($href, $_currentScript, $_currentQuery);
    ?>
    <a href="/be/<?= $href ?>" class="nav-btn <?= $active?'active':'' ?>">
      <span class="nb-icon"><?= $icon ?></span><span class="nb-label"><?= htmlspecialchars($label) ?></span>
    </a>
    <?php endforeach; ?>
  </div>
  <div class="nav-right">
    <span class="nav-user-badge"><?= wcr_role_badge() ?></span>
    <?php if ($_showBurger): ?>
    <button class="nav-burger <?= $_burgerActive?'burger-active':'' ?>" id="nav-burger-btn" type="button" aria-label="Menü" aria-expanded="false">
      <span></span><span></span><span></span>
    </button>
    <?php else: ?>
    <a href="/be/logout.php" class="nav-btn nav-logout-btn">
      <span class="nb-icon">🚪</span><span class="nb-label">Logout</span>
    </a>
    <?php endif; ?>
  </div>
</div>

<?php if ($_showBurger): ?>
<!-- TILE OVERLAY -->
<div class="tile-overlay" id="burger-overlay">
  <div class="tile-panel" id="tile-panel">

    <!-- Panel-Header -->
    <div class="tile-header">
      <div class="tile-header-left">
        <div class="tile-sf-icon"></div>
        <span class="tile-header-title">Menü</span>
      </div>
      <button class="tile-close" id="burger-close" type="button">
        <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
          <path d="M1 1l10 10M11 1L1 11" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
      </button>
    </div>

    <!-- Kachel-Grid -->
    <div class="tile-scroll">
      <div class="tile-grid">
        <?php foreach ($_visibleBurger as $idx => [$icon, $label, $href, $perm, $colors]):
          [$bg, $fg] = $colors;
          $active = _wcr_menu_active($href, $_currentScript, $_currentQuery);
        ?>
        <a href="/be/<?= $href ?>" class="tile <?= $active?'tile--active':'' ?>"
           style="--tbg:<?= $bg ?>;--tfg:<?= $fg ?>">
          <span class="tile-icon"><?= $icon ?></span>
          <span class="tile-label"><?= htmlspecialchars($label) ?></span>
          <?php if ($active): ?><span class="tile-check">✓</span><?php endif; ?>
        </a>
        <?php endforeach; ?>
      </div>

      <!-- Logout — separat unten -->
      <div class="tile-logout-wrap">
        <a href="/be/logout.php" class="tile-logout-btn">
          <span>🚪</span> Abmelden
        </a>
      </div>
    </div>

  </div>
</div>
<?php endif; ?>

<style>
/* ── NAV ──────────────────────────────────────────────────── */
.nav-wrap{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:10px 16px;background:var(--bg-card);border-radius:var(--radius);box-shadow:var(--shadow);margin-bottom:24px;flex-wrap:wrap;}
.nav-main{display:flex;flex-wrap:wrap;gap:6px;align-items:center;flex:1;}
.nav-btn{display:flex;align-items:center;gap:7px;padding:10px 16px;min-height:44px;border-radius:10px;background:var(--bg-body);text-decoration:none;color:var(--text-main);font-size:14px;font-weight:600;white-space:nowrap;border:1.5px solid var(--border-light);transition:background .15s,border-color .15s,color .15s,transform .1s;-webkit-tap-highlight-color:transparent;user-select:none;cursor:pointer;}
.nav-btn:hover{background:var(--border-light);border-color:var(--border);}
.nav-btn:active{transform:scale(.96);}
.nav-btn.active{background:rgba(var(--primary-rgb),.10);border-color:rgba(var(--primary-rgb),.35);color:var(--primary);}
.nav-home-btn{font-weight:700;}
.nav-logout-btn{color:var(--danger);border-color:rgba(var(--danger-rgb),.25);}
.nav-logout-btn:hover{background:rgba(var(--danger-rgb),.06);}
.nb-icon{font-size:17px;line-height:1;flex-shrink:0;}
.nb-label{line-height:1.2;}
.nav-right{display:flex;align-items:center;gap:10px;flex-shrink:0;}
.nav-user-badge{font-size:12px;}
.nav-burger{display:flex;flex-direction:column;justify-content:center;gap:5px;width:44px;height:44px;padding:10px;border-radius:10px;background:var(--bg-body);border:1.5px solid var(--border-light);cursor:pointer;box-sizing:border-box;-webkit-tap-highlight-color:transparent;transition:background .15s,border-color .15s;}
.nav-burger span{display:block;height:2px;border-radius:2px;background:var(--text-main);transition:background .15s;}
.nav-burger:hover{background:var(--border-light);}
.nav-burger.burger-active span{background:var(--primary);}

/* ── OVERLAY ─────────────────────────────────────────────── */
.tile-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.30);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);z-index:1000;justify-content:flex-end;}
.tile-overlay.open{display:flex;animation:tOvIn .2s ease;}
@keyframes tOvIn{from{opacity:0}to{opacity:1}}

/* Panel — 2/3 der Bildschirmbreite */
.tile-panel{width:67vw;max-width:960px;min-width:320px;height:100%;background:rgba(248,248,250,.92);backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);display:flex;flex-direction:column;animation:tPanIn .28s cubic-bezier(.32,0,.18,1);border-left:1px solid rgba(0,0,0,.08);box-shadow:-12px 0 48px rgba(0,0,0,.18);}
@keyframes tPanIn{from{transform:translateX(100%)}to{transform:translateX(0)}}

/* Header */
.tile-header{display:flex;align-items:center;justify-content:space-between;padding:20px 28px 18px;border-bottom:1px solid rgba(0,0,0,.08);flex-shrink:0;background:rgba(255,255,255,.7);}
.tile-header-left{display:flex;align-items:center;gap:10px;}
.tile-sf-icon{font-size:22px;color:#007aff;line-height:1;font-family:-apple-system,system-ui;}
.tile-header-title{font-size:18px;font-weight:700;color:#1c1c1e;letter-spacing:-.3px;font-family:-apple-system,system-ui,sans-serif;}
.tile-close{width:30px;height:30px;border:none;background:rgba(0,0,0,.07);border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#636366;transition:background .15s,color .15s;flex-shrink:0;}
.tile-close:hover{background:rgba(0,0,0,.13);color:#1c1c1e;}

/* Scroll-Container */
.tile-scroll{flex:1;overflow-y:auto;padding:24px 28px 28px;}
.tile-scroll::-webkit-scrollbar{width:4px;}
.tile-scroll::-webkit-scrollbar-thumb{background:rgba(0,0,0,.15);border-radius:4px;}

/* Grid — 3 Spalten auf breitem Panel */
.tile-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:20px;}
@media(max-width:600px){.tile-grid{grid-template-columns:repeat(2,1fr);}}

/* Kachel */
.tile{position:relative;display:flex;flex-direction:column;justify-content:space-between;padding:16px;min-height:110px;text-decoration:none;color:var(--tfg,#1c1c1e);background:var(--tbg,#e8e8ee);border-radius:16px;overflow:hidden;transition:transform .15s,box-shadow .15s,filter .15s;box-shadow:0 2px 8px rgba(0,0,0,.07);-webkit-tap-highlight-color:transparent;border:1.5px solid rgba(0,0,0,.06);}
.tile:hover{transform:translateY(-3px) scale(1.02);box-shadow:0 8px 24px rgba(0,0,0,.13);}
.tile:active{transform:scale(.96);box-shadow:0 1px 4px rgba(0,0,0,.08);filter:brightness(.95);}
.tile-icon{font-size:32px;line-height:1;display:block;}
.tile-label{font-size:12px;font-weight:700;letter-spacing:.1px;line-height:1.2;font-family:-apple-system,system-ui,sans-serif;margin-top:8px;}
.tile-check{position:absolute;top:10px;right:10px;width:20px;height:20px;border-radius:50%;background:rgba(0,0,0,.18);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:var(--tfg);}
.tile--active{box-shadow:0 0 0 2.5px var(--tfg),0 4px 16px rgba(0,0,0,.12);}

/* Logout */
.tile-logout-wrap{padding-top:4px;}
.tile-logout-btn{display:flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:15px;border-radius:14px;background:rgba(255,59,48,.10);color:#c0392b;font-size:15px;font-weight:600;text-decoration:none;font-family:-apple-system,system-ui,sans-serif;border:1.5px solid rgba(255,59,48,.18);transition:background .15s,transform .12s;}
.tile-logout-btn:hover{background:rgba(255,59,48,.17);}
.tile-logout-btn:active{transform:scale(.98);}

@media(max-width:768px){.tile-panel{width:100%;min-width:0;}}
</style>

<script>
(function(){
  const overlay=document.getElementById('burger-overlay');
  const btn=document.getElementById('nav-burger-btn');
  const close=document.getElementById('burger-close');
  const panel=document.getElementById('tile-panel');
  function open(){overlay.classList.add('open');btn.setAttribute('aria-expanded','true');document.body.style.overflow='hidden';}
  function shut(){overlay.classList.remove('open');btn.setAttribute('aria-expanded','false');document.body.style.overflow='';}
  btn.addEventListener('click',function(){overlay.classList.contains('open')?shut():open();});
  close.addEventListener('click',shut);
  overlay.addEventListener('click',function(e){if(!panel.contains(e.target))shut();});
  document.addEventListener('keydown',function(e){if(e.key==='Escape')shut();});
})();
</script>
