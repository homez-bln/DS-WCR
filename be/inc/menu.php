<?php
/**
 * inc/menu.php v13 — Nav-Leiste + Windows-8-Style Kachel-Panel
 * Wenn User keine Burger-Items hat: direkter Logout-Button statt Burger
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

// Kachel-Farben im Win8-Stil (flat, satt, kein Rund)
$_tileColors = [
    '#0078d7', // Kino       — Win8-Blau
    '#e8a200', // Media      — Amber
    '#107c10', // Obstacles  — Xbox-Grün
    '#5c2d91', // DS-Seiten  — Lila
    '#b4009e', // DS Control — Magenta
    '#e81123', // Instagram  — Rot
    '#004e8c', // piSignage  — Dunkelblau
    '#1db954', // Spotify    — Spotify-Grün
    '#0099bc', // Benutzer   — Türkis
    '#4d4d4d', // Rechte     — Grau
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

$_visibleBurger = array_filter($_burgerItems, function($item) {
    $perm = $item[3];
    if ($perm === null) return wcr_is_cernal();
    return wcr_can($perm);
});
$_visibleBurger = array_values($_visibleBurger); // Re-index
$_showBurger = count($_visibleBurger) > 0;

$_burgerActive = false;
foreach ($_visibleBurger as [$i,$l,$href,$p]) {
    if (_wcr_menu_active($href, $_currentScript, $_currentQuery)) { $_burgerActive = true; break; }
}

require_once __DIR__ . '/design-tokens.php';
?>
<link rel="stylesheet" href="/be/inc/style.css">

<div class="nav-wrap">
  <div class="nav-main">
    <a href="/be/index.php" class="nav-btn nav-home-btn <?= $_currentScript==='index.php'?'active':'' ?>">
      <span class="nb-icon">🏠</span>
      <span class="nb-label">Start</span>
    </a>
    <?php foreach ($_mainItems as [$icon,$label,$href,$perm]):
      if ($perm && !wcr_can($perm)) continue;
      $active = _wcr_menu_active($href, $_currentScript, $_currentQuery);
    ?>
    <a href="/be/<?= $href ?>" class="nav-btn <?= $active?'active':'' ?>">
      <span class="nb-icon"><?= $icon ?></span>
      <span class="nb-label"><?= htmlspecialchars($label) ?></span>
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
      <span class="nb-icon">🚪</span>
      <span class="nb-label">Logout</span>
    </a>
    <?php endif; ?>
  </div>
</div>

<?php if ($_showBurger): ?>
<div class="tile-overlay" id="burger-overlay">
  <div class="tile-panel">

    <!-- Header -->
    <div class="tile-header">
      <div class="tile-header-left">
        <div class="tile-win-logo">⊞</div>
        <span class="tile-header-title">Menü</span>
      </div>
      <button class="tile-close" id="burger-close" type="button">✕</button>
    </div>

    <!-- Kachel-Grid -->
    <div class="tile-grid">
      <?php foreach ($_visibleBurger as $idx => [$icon, $label, $href, $perm, $color]):
        $active = _wcr_menu_active($href, $_currentScript, $_currentQuery);
        // Jede 5. Kachel (0-indiziert) wird breit (colspan 2) wenn ungerade Anzahl am Ende
        $wide = false; // Nur für Logout genutzt
      ?>
      <a href="/be/<?= $href ?>" class="tile <?= $active ? 'tile--active' : '' ?>"
         style="--tc:<?= $color ?>">
        <span class="tile-icon"><?= $icon ?></span>
        <span class="tile-label"><?= htmlspecialchars($label) ?></span>
        <?php if ($active): ?><span class="tile-dot"></span><?php endif; ?>
      </a>
      <?php endforeach; ?>

      <!-- Logout-Kachel — immer volle Breite -->
      <a href="/be/logout.php" class="tile tile--wide tile--logout">
        <span class="tile-icon">🚪</span>
        <span class="tile-label">Logout</span>
      </a>
    </div>

  </div>
</div>
<?php endif; ?>

<style>
/* ── Nav ──────────────────────────────────────────────── */
.nav-wrap{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:10px 16px;background:var(--bg-card);border-radius:var(--radius);box-shadow:var(--shadow);margin-bottom:24px;flex-wrap:wrap;}
.nav-main{display:flex;flex-wrap:wrap;gap:6px;align-items:center;flex:1;}
.nav-btn{display:flex;align-items:center;gap:7px;padding:10px 16px;min-height:44px;border-radius:10px;background:var(--bg-body);text-decoration:none;color:var(--text-main);font-size:14px;font-weight:600;white-space:nowrap;border:1.5px solid var(--border-light);transition:background .15s,border-color .15s,color .15s,transform .1s;-webkit-tap-highlight-color:transparent;user-select:none;cursor:pointer;}
.nav-btn:hover{background:var(--border-light);border-color:var(--border);}
.nav-btn:active{transform:scale(.96);background:var(--border);}
.nav-btn.active{background:rgba(var(--primary-rgb),.10);border-color:rgba(var(--primary-rgb),.35);color:var(--primary);}
.nav-home-btn{font-weight:700;}
.nav-logout-btn{color:var(--danger);border-color:rgba(var(--danger-rgb),.25);}
.nav-logout-btn:hover{background:rgba(var(--danger-rgb),.06);border-color:rgba(var(--danger-rgb),.4);}
.nb-icon{font-size:17px;line-height:1;flex-shrink:0;}
.nb-label{line-height:1.2;}
.nav-right{display:flex;align-items:center;gap:10px;flex-shrink:0;}
.nav-user-badge{font-size:12px;}
.nav-burger{display:flex;flex-direction:column;justify-content:center;gap:5px;width:44px;height:44px;padding:10px;border-radius:10px;background:var(--bg-body);border:1.5px solid var(--border-light);cursor:pointer;box-sizing:border-box;-webkit-tap-highlight-color:transparent;transition:background .15s,border-color .15s;}
.nav-burger span{display:block;height:2px;border-radius:2px;background:var(--text-main);transition:background .15s;}
.nav-burger:hover{background:var(--border-light);border-color:var(--border);}
.nav-burger:active{background:var(--border);}
.nav-burger.burger-active span{background:var(--primary);}

/* ── Win8 Tile Overlay ────────────────────────────────── */
.tile-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);backdrop-filter:blur(6px);z-index:1000;}
.tile-overlay.open{display:flex;justify-content:flex-end;animation:tOverlayIn .22s ease;}
@keyframes tOverlayIn{from{opacity:0}to{opacity:1}}

.tile-panel{width:360px;max-width:95vw;height:100%;background:#1a1a2e;display:flex;flex-direction:column;animation:tPanelIn .25s cubic-bezier(.4,0,.2,1);overflow-y:auto;}
@keyframes tPanelIn{from{transform:translateX(100%)}to{transform:translateX(0)}}

/* Header */
.tile-header{display:flex;align-items:center;justify-content:space-between;padding:18px 20px 16px;background:#111122;border-bottom:2px solid #0078d7;flex-shrink:0;}
.tile-header-left{display:flex;align-items:center;gap:10px;}
.tile-win-logo{font-size:22px;color:#0078d7;line-height:1;}
.tile-header-title{font-size:15px;font-weight:700;color:#fff;letter-spacing:.5px;text-transform:uppercase;}
.tile-close{width:34px;height:34px;border:none;background:rgba(255,255,255,.08);border-radius:3px;font-size:16px;cursor:pointer;display:flex;align-items:center;justify-content:center;color:rgba(255,255,255,.7);transition:background .15s;}
.tile-close:hover{background:#e81123;color:#fff;}

/* Grid */
.tile-grid{display:grid;grid-template-columns:1fr 1fr;gap:4px;padding:16px;align-content:start;}

/* Einzelne Kachel */
.tile{position:relative;display:flex;flex-direction:column;justify-content:flex-end;padding:12px;min-height:100px;text-decoration:none;color:#fff;background:var(--tc,#0078d7);border-radius:2px;overflow:hidden;transition:filter .15s,transform .12s;-webkit-tap-highlight-color:transparent;}
.tile::before{content:'';position:absolute;inset:0;background:linear-gradient(135deg,rgba(255,255,255,.12) 0%,transparent 60%);pointer-events:none;}
.tile:hover{filter:brightness(1.15);}
.tile:active{transform:scale(.96);filter:brightness(.9);}
.tile-icon{position:absolute;top:10px;left:12px;font-size:28px;line-height:1;}
.tile-label{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;line-height:1.2;position:relative;z-index:1;}
.tile-dot{position:absolute;bottom:8px;right:8px;width:8px;height:8px;border-radius:50%;background:rgba(255,255,255,.9);box-shadow:0 0 6px rgba(255,255,255,.6);}
.tile--active{outline:3px solid rgba(255,255,255,.8);outline-offset:-3px;}

/* Breite Kachel (volle Breite, 2 Spalten) */
.tile--wide{grid-column:1/-1;min-height:56px;flex-direction:row;align-items:center;justify-content:flex-start;gap:14px;padding:14px 18px;}
.tile--wide .tile-icon{position:static;font-size:22px;}
.tile--wide .tile-label{font-size:13px;}

/* Logout */
.tile--logout{background:#c0392b;margin-top:4px;}
.tile--logout:hover{background:#e74c3c;filter:none;}

@media(max-width:400px){.tile-panel{width:100%;}.tile{min-height:86px;}.tile-icon{font-size:22px;}}
</style>

<script>
(function(){
  const overlay=document.getElementById('burger-overlay');
  const btn=document.getElementById('nav-burger-btn');
  const close=document.getElementById('burger-close');
  function open(){overlay.classList.add('open');btn.setAttribute('aria-expanded','true');document.body.style.overflow='hidden';}
  function shut(){overlay.classList.remove('open');btn.setAttribute('aria-expanded','false');document.body.style.overflow='';}
  btn.addEventListener('click',function(){overlay.classList.contains('open')?shut():open();});
  close.addEventListener('click',shut);
  overlay.addEventListener('click',function(e){if(e.target===overlay)shut();});
  document.addEventListener('keydown',function(e){if(e.key==='Escape')shut();});
})();
</script>
