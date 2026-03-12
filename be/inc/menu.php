<?php
/**
 * inc/menu.php v11 — Touch-optimierte Kachelleiste
 * Fix oben, mehrere Zeilen, große Tap-Flächen
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

$_menuItems = [
    ['⏱',  'Open',        'ctrl/times.php',          'view_times'   ],
    ['🎬',  'Kino',        'ctrl/kino.php',           'edit_content' ],
    ['📸',  'Media',       'ctrl/media.php',          'view_media'   ],
    ['🥤',  'Getränke',    'ctrl/drinks.php',         'edit_products'],
    ['🍔',  'Essen',       'ctrl/food.php',           'edit_products'],
    ['🍦',  'Eis',         'ctrl/list.php?t=ice',     'edit_products'],
    ['🪝',  'Cable',       'ctrl/list.php?t=cable',   'edit_products'],
    ['⛺',  'Camping',     'ctrl/list.php?t=camping', 'edit_products'],
    ['➕',  'Extra',       'ctrl/list.php?t=extra',   'edit_products'],
    ['🗺',  'Obstacles',   'ctrl/obstacles.php',      'edit_content' ],
    ['💻',  'DS-Seiten',   'ctrl/ds-seiten.php',      'view_ds'      ],
    ['⚙️',  'DS Control',  'ctrl/ds-settings.php',    'view_ds'      ],
    ['📺',  'piSignage',   'ctrl/pisignage.php',      'view_ds'      ],
    ['👥',  'Benutzer',    'ctrl/users.php',          'manage_users' ],
    ['🔐',  'Rechte',      'ctrl/permissions.php',    null           ],
];

require_once __DIR__ . '/design-tokens.php';
?>
<link rel="stylesheet" href="/be/inc/style.css">

<div class="nav-wrap">
  <div class="nav-tiles">

    <a href="/be/index.php" class="nav-tile <?= $_currentScript === 'index.php' ? 'active' : '' ?>">
      <span class="nt-icon">🏠</span>
      <span class="nt-label">Start</span>
    </a>

    <?php foreach ($_menuItems as [$icon, $label, $href, $perm]):
      if ($perm === null) {
        if (!wcr_is_cernal()) continue;
      } elseif (!wcr_can($perm)) {
        continue;
      }
      $active = _wcr_menu_active($href, $_currentScript, $_currentQuery);
    ?>
    <a href="/be/<?= $href ?>" class="nav-tile <?= $active ? 'active' : '' ?>">
      <span class="nt-icon"><?= $icon ?></span>
      <span class="nt-label"><?= htmlspecialchars($label) ?></span>
    </a>
    <?php endforeach; ?>

    <div class="nav-tile-spacer"></div>

    <div class="nav-tile nav-user-tile">
      <span class="nt-icon">👤</span>
      <span class="nt-label"><?= wcr_role_badge() ?></span>
    </div>

    <a href="/be/logout.php" class="nav-tile nav-logout-tile">
      <span class="nt-icon">🚪</span>
      <span class="nt-label">Logout</span>
    </a>

  </div>
</div>

<style>
.nav-wrap {
  position: sticky;
  top: 0;
  z-index: 999;
  background: var(--bg-nav, #111827);
  border-bottom: 2px solid rgba(255,255,255,.08);
  box-shadow: 0 3px 14px rgba(0,0,0,.28);
  padding: 10px 14px;
}
.nav-tiles {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  align-items: center;
}
.nav-tile {
  display: flex;
  align-items: center;
  gap: 7px;
  padding: 9px 16px;
  min-height: 44px;
  border-radius: 10px;
  background: rgba(255,255,255,.06);
  text-decoration: none;
  color: rgba(255,255,255,.75);
  font-size: 13px;
  font-weight: 600;
  white-space: nowrap;
  cursor: pointer;
  border: 1.5px solid transparent;
  transition: background .15s, border-color .15s, color .15s, transform .1s;
  -webkit-tap-highlight-color: transparent;
  user-select: none;
}
.nav-tile:hover  { background: rgba(255,255,255,.12); color: #fff; }
.nav-tile:active { transform: scale(.96); background: rgba(255,255,255,.18); }
.nav-tile.active {
  background: rgba(1,158,227,.18);
  border-color: rgba(1,158,227,.5);
  color: #4fc3f7;
}
.nt-icon  { font-size: 18px; line-height: 1; flex-shrink: 0; }
.nt-label { line-height: 1.2; }
.nav-tile-spacer { flex: 1; min-width: 10px; }
.nav-user-tile {
  background: transparent;
  border-color: transparent;
  color: rgba(255,255,255,.4);
  font-size: 12px;
  cursor: default;
}
.nav-user-tile:hover { background: transparent; color: rgba(255,255,255,.4); }
.nav-logout-tile {
  background: rgba(231,76,60,.12);
  border-color: rgba(231,76,60,.25);
  color: rgba(255,130,120,.85);
}
.nav-logout-tile:hover { background: rgba(231,76,60,.22); color: #ff6b6b; border-color: rgba(231,76,60,.5); }
@media (max-width: 600px) {
  .nav-wrap { padding: 8px 10px; }
  .nav-tile  { padding: 8px 12px; font-size: 12px; }
  .nt-icon   { font-size: 16px; }
}
</style>
