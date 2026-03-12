<?php
/**
 * inc/menu.php v12.2 — Helle Touch-Leiste + Burger-Menü
 * Burger-Button wird ausgeblendet wenn User keine sichtbaren Einträge hat
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

$_burgerItems = [
    ['🎬',  'Kino',        'ctrl/kino.php',           'edit_content' ],
    ['📸',  'Media',       'ctrl/media.php',          'view_media'   ],
    ['🗺',  'Obstacles',   'ctrl/obstacles.php',      'edit_content' ],
    ['💻',  'DS-Seiten',   'ctrl/ds-seiten.php',      'view_ds'      ],
    ['⚙️',  'DS Control',  'ctrl/ds-settings.php',    'view_ds'      ],
    ['📺',  'piSignage',   'ctrl/pisignage.php',      'view_ds'      ],
    ['🎵',  'Spotify',     'ctrl/spotify.php',        'manage_users' ],  // admin + cernal
    ['👥',  'Benutzer',    'ctrl/users.php',          'manage_users' ],
    ['🔐',  'Rechte',      'ctrl/permissions.php',    null           ],
];

// Vorab prüfen welche Burger-Items der User sieht
$_visibleBurger = array_filter($_burgerItems, function($item) {
    $perm = $item[3];
    if ($perm === null) return wcr_is_cernal();
    return wcr_can($perm);
});
$_showBurger = count($_visibleBurger) > 0;

// Ist die aktive Seite im Burger-Menü?
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
    <?php endif; ?>
  </div>

</div>

<?php if ($_showBurger): ?>
<div class="burger-overlay" id="burger-overlay">
  <div class="burger-panel">
    <div class="burger-head">
      <span class="burger-title">Menü</span>
      <button class="burger-close" id="burger-close" type="button">✕</button>
    </div>
    <div class="burger-items">
      <?php foreach ($_visibleBurger as [$icon,$label,$href,$perm]):
        $active = _wcr_menu_active($href, $_currentScript, $_currentQuery);
      ?>
      <a href="/be/<?= $href ?>" class="burger-item <?= $active?'active':'' ?>">
        <span class="bi-icon"><?= $icon ?></span>
        <span class="bi-label"><?= htmlspecialchars($label) ?></span>
      </a>
      <?php endforeach; ?>
      <div class="burger-divider"></div>
      <a href="/be/logout.php" class="burger-item burger-logout">
        <span class="bi-icon">🚪</span>
        <span class="bi-label">Logout</span>
      </a>
    </div>
  </div>
</div>
<?php endif; ?>

<style>
.nav-wrap{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:10px 16px;background:var(--bg-card);border-radius:var(--radius);box-shadow:var(--shadow);margin-bottom:24px;flex-wrap:wrap;}
.nav-main{display:flex;flex-wrap:wrap;gap:6px;align-items:center;flex:1;}
.nav-btn{display:flex;align-items:center;gap:7px;padding:10px 16px;min-height:44px;border-radius:10px;background:var(--bg-body);text-decoration:none;color:var(--text-main);font-size:14px;font-weight:600;white-space:nowrap;border:1.5px solid var(--border-light);transition:background .15s,border-color .15s,color .15s,transform .1s;-webkit-tap-highlight-color:transparent;user-select:none;cursor:pointer;}
.nav-btn:hover{background:var(--border-light);border-color:var(--border);}
.nav-btn:active{transform:scale(.96);background:var(--border);}
.nav-btn.active{background:rgba(var(--primary-rgb),.10);border-color:rgba(var(--primary-rgb),.35);color:var(--primary);}
.nav-home-btn{font-weight:700;}
.nb-icon{font-size:17px;line-height:1;flex-shrink:0;}
.nb-label{line-height:1.2;}
.nav-right{display:flex;align-items:center;gap:10px;flex-shrink:0;}
.nav-user-badge{font-size:12px;}
.nav-burger{display:flex;flex-direction:column;justify-content:center;gap:5px;width:44px;height:44px;padding:10px;border-radius:10px;background:var(--bg-body);border:1.5px solid var(--border-light);cursor:pointer;box-sizing:border-box;-webkit-tap-highlight-color:transparent;transition:background .15s,border-color .15s;}
.nav-burger span{display:block;height:2px;border-radius:2px;background:var(--text-main);transition:background .15s;}
.nav-burger:hover{background:var(--border-light);border-color:var(--border);}
.nav-burger:active{background:var(--border);}
.nav-burger.burger-active{background:rgba(var(--primary-rgb),.10);border-color:rgba(var(--primary-rgb),.35);}
.nav-burger.burger-active span{background:var(--primary);}
.burger-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.25);backdrop-filter:blur(3px);z-index:1000;animation:overlayIn .2s ease;}
.burger-overlay.open{display:block;}
@keyframes overlayIn{from{opacity:0}to{opacity:1}}
.burger-panel{position:absolute;top:0;right:0;width:280px;max-width:90vw;height:100%;background:var(--bg-card);box-shadow:-4px 0 24px rgba(0,0,0,.15);display:flex;flex-direction:column;animation:panelIn .22s cubic-bezier(.4,0,.2,1);overflow-y:auto;}
@keyframes panelIn{from{transform:translateX(100%)}to{transform:translateX(0)}}
.burger-head{display:flex;align-items:center;justify-content:space-between;padding:18px 20px 14px;border-bottom:1px solid var(--border-light);flex-shrink:0;}
.burger-title{font-size:16px;font-weight:700;color:var(--text-main);}
.burger-close{width:32px;height:32px;border:none;background:var(--bg-body);border-radius:8px;font-size:16px;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--text-muted);transition:background .15s;}
.burger-close:hover{background:var(--border-light);color:var(--text-main);}
.burger-items{padding:12px;display:flex;flex-direction:column;gap:4px;flex:1;}
.burger-item{display:flex;align-items:center;gap:12px;padding:13px 14px;min-height:48px;border-radius:10px;text-decoration:none;color:var(--text-main);font-size:15px;font-weight:500;border:1.5px solid transparent;transition:background .13s,border-color .13s,color .13s;-webkit-tap-highlight-color:transparent;}
.burger-item:hover{background:var(--bg-body);}
.burger-item:active{background:var(--border-light);transform:scale(.98);}
.burger-item.active{background:rgba(var(--primary-rgb),.08);border-color:rgba(var(--primary-rgb),.25);color:var(--primary);font-weight:600;}
.bi-icon{font-size:20px;flex-shrink:0;}
.bi-label{flex:1;}
.burger-divider{height:1px;background:var(--border-light);margin:8px 0;}
.burger-logout{color:var(--danger);margin-top:auto;}
.burger-logout:hover{background:rgba(var(--danger-rgb),.06);}
@media(max-width:600px){.nav-wrap{padding:8px 12px;}.nav-btn{padding:9px 13px;font-size:13px;}.nb-icon{font-size:15px;}}
</style>

<?php if ($_showBurger): ?>
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
<?php endif; ?>
